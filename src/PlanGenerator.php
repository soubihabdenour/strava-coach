<?php

class PlanGenerator
{
    public const GOALS = [
        'fitness'  => ['label' => 'General fitness',  'min_weeks' => 4,  'max_weeks' => 16, 'default_weeks' => 8,  'peak_km' => 40,  'sessions_per_week' => 4],
        '5k'       => ['label' => '5K race',          'min_weeks' => 6,  'max_weeks' => 12, 'default_weeks' => 8,  'peak_km' => 45,  'sessions_per_week' => 5],
        '10k'      => ['label' => '10K race',         'min_weeks' => 8,  'max_weeks' => 14, 'default_weeks' => 10, 'peak_km' => 55,  'sessions_per_week' => 5],
        'half'     => ['label' => 'Half marathon',    'min_weeks' => 10, 'max_weeks' => 16, 'default_weeks' => 12, 'peak_km' => 70,  'sessions_per_week' => 5],
        'marathon' => ['label' => 'Marathon',         'min_weeks' => 14, 'max_weeks' => 20, 'default_weeks' => 16, 'peak_km' => 90,  'sessions_per_week' => 6],
    ];

    private const PHASE_SPLIT = ['base' => 0.40, 'build' => 0.35, 'peak' => 0.15, 'taper' => 0.10];

    public function generate(
        string $goal,
        DateTimeImmutable $startDate,
        DateTimeImmutable $goalDate,
        float $baselineWeeklyKm,
    ): array {
        if (!isset(self::GOALS[$goal])) {
            throw new InvalidArgumentException("Unknown goal: {$goal}");
        }
        $cfg = self::GOALS[$goal];

        $diffDays = (int)$startDate->diff($goalDate)->format('%r%a');
        $weeks = max($cfg['min_weeks'], min($cfg['max_weeks'], (int)floor($diffDays / 7)));
        if ($weeks < $cfg['min_weeks']) {
            $weeks = $cfg['min_weeks'];
        }

        $startKm = max(15.0, $baselineWeeklyKm > 0 ? $baselineWeeklyKm : 20.0);
        $peakKm = max($startKm * 1.3, (float)$cfg['peak_km']);
        $volumes = $this->volumeCurve($weeks, $startKm, $peakKm);

        $phases = $this->phaseAssignment($weeks);

        $monday = $startDate->modify('monday this week');
        if ($monday > $startDate) {
            $monday = $monday->modify('-1 week');
        }

        $weeksOut = [];
        for ($i = 0; $i < $weeks; $i++) {
            $phase = $phases[$i];
            $isRaceWeek = ($i === $weeks - 1);
            $week = [
                'index' => $i + 1,
                'start' => $monday->modify('+' . $i . ' weeks')->format('Y-m-d'),
                'phase' => $phase,
                'theme' => $this->weekTheme($phase, $i, $weeks, $goal),
                'target_km' => round($volumes[$i], 1),
                'days' => $this->buildWeek($goal, $phase, $volumes[$i], $i, $weeks, $isRaceWeek),
            ];
            $weeksOut[] = $week;
        }

        return [
            'goal' => $goal,
            'goal_label' => $cfg['label'],
            'start_date' => $startDate->format('Y-m-d'),
            'goal_date' => $goalDate->format('Y-m-d'),
            'weeks_total' => $weeks,
            'baseline_km' => round($startKm, 1),
            'peak_km' => round($peakKm, 1),
            'weeks' => $weeksOut,
        ];
    }

    private function phaseAssignment(int $weeks): array
    {
        $counts = [
            'base'  => max(2, (int)round($weeks * self::PHASE_SPLIT['base'])),
            'build' => max(2, (int)round($weeks * self::PHASE_SPLIT['build'])),
            'peak'  => max(1, (int)round($weeks * self::PHASE_SPLIT['peak'])),
            'taper' => max(1, (int)round($weeks * self::PHASE_SPLIT['taper'])),
        ];
        $total = array_sum($counts);
        while ($total > $weeks) {
            $counts['base']--; $total--;
        }
        while ($total < $weeks) {
            $counts['base']++; $total++;
        }
        $out = [];
        foreach (['base', 'build', 'peak', 'taper'] as $p) {
            for ($i = 0; $i < $counts[$p]; $i++) $out[] = $p;
        }
        return $out;
    }

    private function volumeCurve(int $weeks, float $startKm, float $peakKm): array
    {
        $phases = $this->phaseAssignment($weeks);
        $peakIdx = 0;
        foreach ($phases as $i => $p) {
            if ($p === 'peak') $peakIdx = $i;
        }
        if ($peakIdx === 0) $peakIdx = $weeks - 2;

        $out = [];
        for ($i = 0; $i < $weeks; $i++) {
            $phase = $phases[$i];
            if ($phase === 'taper') {
                $taperWeeks = count(array_filter($phases, fn($p) => $p === 'taper'));
                $taperIdx = $i - ($weeks - $taperWeeks);
                $factor = 1.0 - 0.3 * ($taperIdx + 1) / $taperWeeks;
                $vol = $peakKm * $factor;
            } else {
                $progress = $peakIdx > 0 ? min(1.0, $i / $peakIdx) : 1.0;
                $vol = $startKm + ($peakKm - $startKm) * $progress;
            }
            if (($i + 1) % 4 === 0 && $phase !== 'taper') {
                $vol *= 0.8;
            }
            $out[] = $vol;
        }
        return $out;
    }

    private function weekTheme(string $phase, int $weekIdx, int $totalWeeks, string $goal): string
    {
        $isDeload = ($weekIdx + 1) % 4 === 0 && $phase !== 'taper';
        if ($isDeload) return 'Deload — drop volume, keep rhythm';
        return match ($phase) {
            'base'  => 'Aerobic base — easy mileage and frequency',
            'build' => 'Build phase — introduce threshold and VO2 work',
            'peak'  => 'Race-specific — sharpen at goal pace',
            'taper' => $weekIdx === $totalWeeks - 1 ? 'Race week — rest, fuel, execute' : 'Taper — reduce volume, hold intensity',
            default => '',
        };
    }

    private function buildWeek(string $goal, string $phase, float $weekKm, int $weekIdx, int $totalWeeks, bool $isRaceWeek): array
    {
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $sessionsPerWeek = self::GOALS[$goal]['sessions_per_week'];

        if ($isRaceWeek && $goal !== 'fitness') {
            return $this->raceWeek($goal);
        }

        $longShare = $phase === 'base' ? 0.30 : ($phase === 'taper' ? 0.20 : 0.28);
        $longKm = round($weekKm * $longShare, 1);
        $longKm = min($longKm, $this->maxLongRun($goal));

        $quality = $this->qualityWorkout($goal, $phase, $weekIdx);
        $qualityKm = $quality['distance_km'];

        $remaining = max(0, $weekKm - $longKm - $qualityKm);
        $easySlots = $sessionsPerWeek - 2;
        if ($phase === 'base') $easySlots = max($easySlots, 3);
        $easyKm = $remaining / max(1, $easySlots);

        $schedule = [
            'Mon' => $this->rest('Recovery — full rest or 20 min walk/mobility.'),
            'Tue' => $this->wrapQuality($quality),
            'Wed' => $this->easy($easyKm, 'Easy aerobic — conversational pace (zone 2). Nose-breathing if possible.'),
            'Thu' => $phase === 'base'
                ? $this->easy($easyKm * 1.1, 'Mid-week steady — relaxed but committed. Add 4×20s strides at the end.')
                : $this->tempo($goal, $phase, $weekKm),
            'Fri' => $this->rest('Rest or 30–40 min cross-train (bike, swim, easy strength).'),
            'Sat' => $sessionsPerWeek >= 5
                ? $this->easy($easyKm * 0.8, 'Pre-long-run shakeout — easy 30–45 min, include 4×20s strides.')
                : $this->rest('Optional cross-train or rest.'),
            'Sun' => $this->long($longKm, $phase, $goal),
        ];

        if ($sessionsPerWeek >= 6) {
            $schedule['Fri'] = $this->easy($easyKm * 0.8, 'Easy recovery run — slow and short, primer for the weekend.');
        }

        $ordered = [];
        foreach ($days as $d) {
            $ordered[] = ['day' => $d] + $schedule[$d];
        }
        return $ordered;
    }

    private function maxLongRun(string $goal): float
    {
        return match ($goal) {
            'fitness'  => 14,
            '5k'       => 14,
            '10k'      => 18,
            'half'     => 22,
            'marathon' => 34,
            default    => 16,
        };
    }

    private function qualityWorkout(string $goal, string $phase, int $weekIdx): array
    {
        $pool = match ($phase) {
            'base' => [
                ['title' => 'Hill strides',           'distance_km' => 7,  'desc' => '15 min easy warm-up. 6×20s uphill strides at hard effort, walk down. 15 min easy cool-down.'],
                ['title' => 'Easy + strides',         'distance_km' => 8,  'desc' => '40–50 min easy aerobic. Finish with 6×20s strides on flat ground, full recovery between.'],
                ['title' => 'Fartlek (play)',         'distance_km' => 8,  'desc' => '15 min warm-up. 8×1 min steady (zone 3) / 1 min jog. 10 min cool-down.'],
            ],
            'build' => [
                ['title' => 'VO2 intervals 5×800m',   'distance_km' => 9,  'desc' => '15 min warm-up. 5×800m at 5K effort, 2:30 jog recovery. 10 min cool-down.'],
                ['title' => 'Cruise intervals',       'distance_km' => 10, 'desc' => '15 min warm-up. 4×1 km at threshold (comfortably hard), 90s jog. 10 min cool-down.'],
                ['title' => 'Hill repeats 6×90s',     'distance_km' => 8,  'desc' => '15 min warm-up. 6×90s uphill at hard effort, jog down recovery. 10 min cool-down.'],
            ],
            'peak' => [
                ['title' => 'Goal-pace intervals',    'distance_km' => 10, 'desc' => '15 min warm-up. 5×1 km at goal race pace, 2 min jog. 10 min cool-down.'],
                ['title' => 'Race-pace simulation',   'distance_km' => 11, 'desc' => '15 min warm-up. 3×2 km at goal race pace, 3 min jog. 10 min cool-down.'],
                ['title' => 'Sharpening 200s',        'distance_km' => 8,  'desc' => '15 min warm-up. 10×200m fast (slightly under 5K pace), 200m jog recovery. 10 min cool-down.'],
            ],
            'taper' => [
                ['title' => 'Pace primer',            'distance_km' => 7,  'desc' => '15 min warm-up. 4×400m at goal race pace, 2 min jog. 10 min easy.'],
            ],
            default => [['title' => 'Easy', 'distance_km' => 6, 'desc' => 'Easy 40 min.']],
        };
        return $pool[$weekIdx % count($pool)];
    }

    private function tempo(string $goal, string $phase, float $weekKm): array
    {
        $minutes = $phase === 'peak' ? 30 : ($phase === 'build' ? 25 : 20);
        return [
            'type' => 'tempo',
            'title' => 'Tempo run',
            'distance_km' => round(min(12, max(6, $weekKm * 0.18)), 1),
            'desc' => "15 min easy. {$minutes} min at comfortably-hard threshold pace (you can speak 3–4 words at a time). 10 min easy cool-down.",
        ];
    }

    private function long(float $km, string $phase, string $goal): array
    {
        $desc = "Steady aerobic long run at conversational pace. Practice race-day fueling if going over 75 min.";
        if ($phase === 'peak' && in_array($goal, ['half', 'marathon'], true)) {
            $desc = "Long run with finishing quality: last 15–20 min at goal race pace. Practice fueling.";
        } elseif ($phase === 'build') {
            $desc = "Long aerobic run — final 10 min progressively faster (still controlled).";
        }
        return [
            'type' => 'long',
            'title' => "Long run {$km} km",
            'distance_km' => $km,
            'desc' => $desc,
        ];
    }

    private function easy(float $km, string $desc): array
    {
        $km = round(max(3, $km), 1);
        return [
            'type' => 'easy',
            'title' => "Easy {$km} km",
            'distance_km' => $km,
            'desc' => $desc,
        ];
    }

    private function rest(string $desc): array
    {
        return ['type' => 'rest', 'title' => 'Rest', 'distance_km' => 0, 'desc' => $desc];
    }

    private function wrapQuality(array $q): array
    {
        return [
            'type' => 'quality',
            'title' => $q['title'],
            'distance_km' => $q['distance_km'],
            'desc' => $q['desc'],
        ];
    }

    private function raceWeek(string $goal): array
    {
        $raceDesc = match ($goal) {
            '5k'       => 'RACE DAY — 5K. 15 min warm-up with strides. Go out controlled, build into it.',
            '10k'      => 'RACE DAY — 10K. 15 min warm-up with 4×100m strides. First 2 km steady, then settle to goal pace.',
            'half'     => 'RACE DAY — Half marathon. 10 min easy warm-up. Hold goal pace from the start, fuel every 30–40 min.',
            'marathon' => 'RACE DAY — Marathon. No warm-up beyond walking. Start 5–10 sec/km slower than goal pace. Fuel every 30 min, drink every aid station.',
            default    => 'Goal day — execute your event.',
        };
        return [
            ['day' => 'Mon', 'type' => 'rest',   'title' => 'Rest',              'distance_km' => 0, 'desc' => 'Full rest. Hydrate, sleep, light stretching.'],
            ['day' => 'Tue', 'type' => 'easy',   'title' => 'Easy 30\'',          'distance_km' => 5, 'desc' => '30 min very easy. Just opening the legs.'],
            ['day' => 'Wed', 'type' => 'quality','title' => 'Sharpener 4×400m',   'distance_km' => 6, 'desc' => '15 min warm-up. 4×400m at goal race pace, 2 min jog. 10 min cool-down.'],
            ['day' => 'Thu', 'type' => 'rest',   'title' => 'Rest',              'distance_km' => 0, 'desc' => 'Rest or 20 min walk. Start carb loading if event > 90 min.'],
            ['day' => 'Fri', 'type' => 'easy',   'title' => 'Shakeout 20\'',      'distance_km' => 3, 'desc' => '20 min very easy + 4×20s strides. Lay out race kit tonight.'],
            ['day' => 'Sat', 'type' => 'rest',   'title' => 'Rest',              'distance_km' => 0, 'desc' => 'Rest. Light meal, hydrate, early to bed.'],
            ['day' => 'Sun', 'type' => 'race',   'title' => 'RACE',              'distance_km' => 0, 'desc' => $raceDesc],
        ];
    }
}
