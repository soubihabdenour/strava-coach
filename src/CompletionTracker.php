<?php

class CompletionTracker
{
    /**
     * For each plan week, compute the ratio of actual km / prescribed km from the athlete's activities.
     * Only counts weeks whose start date is on or before today.
     *
     * @param array $plan Plan structure from PlanGenerator or AiPlanGenerator
     * @param array<int,array<string,mixed>> $activities Strava activities
     * @return array<int,array{index:int,start:string,prescribed_km:float,actual_km:float,ratio:float,past:bool}>
     */
    public static function perWeek(array $plan, array $activities): array
    {
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $planSport = $plan['sport'] ?? 'run';
        $byWeek = [];

        foreach ($activities as $a) {
            $type = $a['sport_type'] ?? $a['type'] ?? '';
            if (!self::matchesSport($type, $planSport)) continue;
            $date = substr($a['start_date_local'] ?? '', 0, 10);
            if (!$date) continue;
            try {
                $monday = (new DateTimeImmutable($date))->modify('monday this week')->format('Y-m-d');
            } catch (Throwable) {
                continue;
            }
            $byWeek[$monday] = ($byWeek[$monday] ?? 0) + ($a['distance'] ?? 0) / 1000;
        }

        $out = [];
        foreach ($plan['weeks'] as $w) {
            $prescribed = (float)($w['target_km'] ?? 0);
            $actual = $byWeek[$w['start']] ?? 0.0;
            $past = $w['start'] <= $today;
            $ratio = $prescribed > 0.1 ? $actual / $prescribed : ($actual > 0 ? 1.0 : 0.0);
            $out[] = [
                'index' => $w['index'],
                'start' => $w['start'],
                'prescribed_km' => round($prescribed, 1),
                'actual_km' => round($actual, 1),
                'ratio' => round($ratio, 2),
                'past' => $past,
            ];
        }
        return $out;
    }

    /**
     * Compact summary suitable for inclusion in a re-generation prompt.
     * Only past weeks with non-trivial prescribed volume are included.
     *
     * @return array<int,array{start:string,ratio:float}>
     */
    public static function forPrompt(array $perWeek): array
    {
        return array_values(array_map(
            fn($w) => ['start' => $w['start'], 'ratio' => $w['ratio']],
            array_filter($perWeek, fn($w) => $w['past'] && $w['prescribed_km'] > 1)
        ));
    }

    private static function matchesSport(string $stravaType, string $planSport): bool
    {
        if ($planSport === 'tri' || $planSport === 'multi') return true;
        $map = [
            'run' => ['Run', 'TrailRun', 'VirtualRun'],
            'bike' => ['Ride', 'VirtualRide', 'GravelRide', 'EBikeRide', 'MountainBikeRide'],
            'swim' => ['Swim'],
        ];
        return isset($map[$planSport]) && in_array($stravaType, $map[$planSport], true);
    }
}
