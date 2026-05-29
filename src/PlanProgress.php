<?php

class PlanProgress
{
    public const DOW_ORDER = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    private const SPORT_MAP = [
        'run'  => ['Run', 'TrailRun', 'VirtualRun'],
        'bike' => ['Ride', 'VirtualRide', 'GravelRide', 'EBikeRide', 'MountainBikeRide'],
        'swim' => ['Swim'],
        'multi' => null, // brick day: any endurance type counts
    ];

    /**
     * Locate today within $plan and return week + day context.
     * Possible shapes:
     *   ['state' => 'active', 'today' => 'YYYY-MM-DD', 'today_dow' => 'Wed',
     *    'week' => {...}, 'week_index' => 7, 'weeks_total' => 12,
     *    'day' => {...}, 'days_to_goal' => 63]
     *   ['state' => 'pre_start', 'starts' => 'YYYY-MM-DD']
     *   ['state' => 'ended',     'ended'  => 'YYYY-MM-DD']
     *   ['state' => 'gap',       'today'  => 'YYYY-MM-DD']   // plan active but no entry for today
     */
    public static function todayContext(array $plan, ?DateTimeImmutable $today = null): array
    {
        $today ??= new DateTimeImmutable('today');
        $todayStr = $today->format('Y-m-d');

        if ($todayStr < ($plan['start_date'] ?? '')) {
            return ['state' => 'pre_start', 'starts' => $plan['start_date']];
        }
        if ($todayStr > ($plan['goal_date'] ?? '')) {
            return ['state' => 'ended', 'ended' => $plan['goal_date']];
        }

        $todayMonday = $today->modify('monday this week')->format('Y-m-d');
        $todayDow = self::DOW_ORDER[(int)$today->format('N') - 1];

        $weekData = null;
        $weekIndex = null;
        $dayData = null;
        foreach ($plan['weeks'] ?? [] as $i => $w) {
            if (($w['start'] ?? '') !== $todayMonday) continue;
            $weekData = $w;
            $weekIndex = (int)($w['index'] ?? ($i + 1));
            foreach ($w['days'] ?? [] as $d) {
                if (($d['day'] ?? '') === $todayDow) {
                    $dayData = $d;
                    break;
                }
            }
            break;
        }

        if (!$weekData || !$dayData) {
            return ['state' => 'gap', 'today' => $todayStr];
        }

        $goalDate = new DateTimeImmutable($plan['goal_date']);
        $daysToGoal = max(0, (int)$today->diff($goalDate)->format('%r%a'));

        return [
            'state'        => 'active',
            'today'        => $todayStr,
            'today_dow'    => $todayDow,
            'week'         => $weekData,
            'week_index'   => $weekIndex,
            'weeks_total'  => (int)($plan['weeks_total'] ?? count($plan['weeks'] ?? [])),
            'day'          => $dayData,
            'days_to_goal' => $daysToGoal,
        ];
    }

    /**
     * Find a Strava activity from $date that plausibly fulfills $day's sport.
     * Returns null for rest / strength days (nothing expected) or when no match found.
     */
    public static function matchActivity(array $day, string $date, array $activities): ?array
    {
        $sport = $day['sport'] ?? 'rest';
        if (in_array($sport, ['rest', 'strength'], true)) return null;

        $allowed = array_key_exists($sport, self::SPORT_MAP) ? self::SPORT_MAP[$sport] : null;

        foreach ($activities as $a) {
            if (substr($a['start_date_local'] ?? '', 0, 10) !== $date) continue;
            if ($allowed !== null) {
                $st = $a['sport_type'] ?? $a['type'] ?? '';
                if (!in_array($st, $allowed, true)) continue;
            }
            return $a;
        }
        return null;
    }
}
