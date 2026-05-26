<?php

class PlanGenerator
{
    public const GOALS = [
        'fitness'    => ['min_weeks' => 4,  'max_weeks' => 16, 'default_weeks' => 8,  'peak_km' => 40,  'sessions_per_week' => 4, 'sport' => 'run', 'ai_only' => false],
        '5k'         => ['min_weeks' => 6,  'max_weeks' => 12, 'default_weeks' => 8,  'peak_km' => 45,  'sessions_per_week' => 5, 'sport' => 'run', 'ai_only' => false],
        '10k'        => ['min_weeks' => 8,  'max_weeks' => 14, 'default_weeks' => 10, 'peak_km' => 55,  'sessions_per_week' => 5, 'sport' => 'run', 'ai_only' => false],
        'half'       => ['min_weeks' => 10, 'max_weeks' => 16, 'default_weeks' => 12, 'peak_km' => 70,  'sessions_per_week' => 5, 'sport' => 'run', 'ai_only' => false],
        'marathon'   => ['min_weeks' => 14, 'max_weeks' => 20, 'default_weeks' => 16, 'peak_km' => 90,  'sessions_per_week' => 6, 'sport' => 'run', 'ai_only' => false],
        'gran_fondo' => ['min_weeks' => 10, 'max_weeks' => 20, 'default_weeks' => 14, 'peak_km' => 350, 'sessions_per_week' => 5, 'sport' => 'bike', 'ai_only' => true],
        'swim_5k'    => ['min_weeks' => 8,  'max_weeks' => 16, 'default_weeks' => 12, 'peak_km' => 18,  'sessions_per_week' => 4, 'sport' => 'swim', 'ai_only' => true],
        'sprint_tri' => ['min_weeks' => 8,  'max_weeks' => 14, 'default_weeks' => 10, 'peak_km' => 0,   'sessions_per_week' => 6, 'sport' => 'tri',  'ai_only' => true],
        'oly_tri'    => ['min_weeks' => 12, 'max_weeks' => 18, 'default_weeks' => 14, 'peak_km' => 0,   'sessions_per_week' => 7, 'sport' => 'tri',  'ai_only' => true],
        'half_iron'  => ['min_weeks' => 16, 'max_weeks' => 24, 'default_weeks' => 20, 'peak_km' => 0,   'sessions_per_week' => 8, 'sport' => 'tri',  'ai_only' => true],
        'full_iron'  => ['min_weeks' => 20, 'max_weeks' => 32, 'default_weeks' => 24, 'peak_km' => 0,   'sessions_per_week' => 9, 'sport' => 'tri',  'ai_only' => true],
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
            'locale' => I18n::locale(),
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
        if ($isDeload) return t('theme.deload');
        return match ($phase) {
            'base'  => t('theme.base'),
            'build' => t('theme.build'),
            'peak'  => t('theme.peak'),
            'taper' => $weekIdx === $totalWeeks - 1 ? t('theme.race_week') : t('theme.taper'),
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
            'Mon' => $this->rest(t('sched.mon_rest')),
            'Tue' => $this->wrapQuality($quality),
            'Wed' => $this->easy($easyKm, t('sched.wed_easy')),
            'Thu' => $phase === 'base'
                ? $this->easy($easyKm * 1.1, t('sched.thu_base'))
                : $this->tempo($goal, $phase, $weekKm),
            'Fri' => $this->rest(t('sched.fri_rest')),
            'Sat' => $sessionsPerWeek >= 5
                ? $this->easy($easyKm * 0.8, t('sched.sat_shakeout'))
                : $this->rest(t('sched.sat_rest')),
            'Sun' => $this->long($longKm, $phase, $goal),
        ];

        if ($sessionsPerWeek >= 6) {
            $schedule['Fri'] = $this->easy($easyKm * 0.8, t('sched.fri_easy_6day'));
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
                ['title' => t('quality.base.hill_strides.title'),  'distance_km' => 7,  'desc' => t('quality.base.hill_strides.desc')],
                ['title' => t('quality.base.easy_strides.title'),  'distance_km' => 8,  'desc' => t('quality.base.easy_strides.desc')],
                ['title' => t('quality.base.fartlek.title'),       'distance_km' => 8,  'desc' => t('quality.base.fartlek.desc')],
            ],
            'build' => [
                ['title' => t('quality.build.vo2.title'),          'distance_km' => 9,  'desc' => t('quality.build.vo2.desc')],
                ['title' => t('quality.build.cruise.title'),       'distance_km' => 10, 'desc' => t('quality.build.cruise.desc')],
                ['title' => t('quality.build.hills.title'),        'distance_km' => 8,  'desc' => t('quality.build.hills.desc')],
            ],
            'peak' => [
                ['title' => t('quality.peak.goal_pace.title'),     'distance_km' => 10, 'desc' => t('quality.peak.goal_pace.desc')],
                ['title' => t('quality.peak.race_sim.title'),      'distance_km' => 11, 'desc' => t('quality.peak.race_sim.desc')],
                ['title' => t('quality.peak.sharp_200.title'),     'distance_km' => 8,  'desc' => t('quality.peak.sharp_200.desc')],
            ],
            'taper' => [
                ['title' => t('quality.taper.primer.title'),       'distance_km' => 7,  'desc' => t('quality.taper.primer.desc')],
            ],
            default => [['title' => t('quality.default.title'), 'distance_km' => 6, 'desc' => t('quality.default.desc')]],
        };
        return $pool[$weekIdx % count($pool)];
    }

    private function tempo(string $goal, string $phase, float $weekKm): array
    {
        $minutes = $phase === 'peak' ? 30 : ($phase === 'build' ? 25 : 20);
        return [
            'type' => 'tempo',
            'title' => t('pday.tempo_title'),
            'distance_km' => round(min(12, max(6, $weekKm * 0.18)), 1),
            'desc' => t('pday.tempo_desc', $minutes),
        ];
    }

    private function long(float $km, string $phase, string $goal): array
    {
        $desc = t('pday.long_desc_default');
        if ($phase === 'peak' && in_array($goal, ['half', 'marathon'], true)) {
            $desc = t('pday.long_desc_peak');
        } elseif ($phase === 'build') {
            $desc = t('pday.long_desc_build');
        }
        return [
            'type' => 'long',
            'title' => t('pday.long_run', $km),
            'distance_km' => $km,
            'desc' => $desc,
        ];
    }

    private function easy(float $km, string $desc): array
    {
        $km = round(max(3, $km), 1);
        return [
            'type' => 'easy',
            'title' => t('pday.easy_km', $km),
            'distance_km' => $km,
            'desc' => $desc,
        ];
    }

    private function rest(string $desc): array
    {
        return ['type' => 'rest', 'title' => t('pday.rest'), 'distance_km' => 0, 'desc' => $desc];
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
            '5k'       => t('race.5k'),
            '10k'      => t('race.10k'),
            'half'     => t('race.half'),
            'marathon' => t('race.marathon'),
            default    => t('race.default'),
        };
        return [
            ['day' => 'Mon', 'type' => 'rest',    'title' => t('pday.rest'),      'distance_km' => 0, 'desc' => t('race.mon.desc')],
            ['day' => 'Tue', 'type' => 'easy',    'title' => t('race.tue.title'), 'distance_km' => 5, 'desc' => t('race.tue.desc')],
            ['day' => 'Wed', 'type' => 'quality', 'title' => t('race.wed.title'), 'distance_km' => 6, 'desc' => t('race.wed.desc')],
            ['day' => 'Thu', 'type' => 'rest',    'title' => t('pday.rest'),      'distance_km' => 0, 'desc' => t('race.thu.desc')],
            ['day' => 'Fri', 'type' => 'easy',    'title' => t('race.fri.title'), 'distance_km' => 3, 'desc' => t('race.fri.desc')],
            ['day' => 'Sat', 'type' => 'rest',    'title' => t('pday.rest'),      'distance_km' => 0, 'desc' => t('race.sat.desc')],
            ['day' => 'Sun', 'type' => 'race',    'title' => t('race.sun.title'), 'distance_km' => 0, 'desc' => $raceDesc],
        ];
    }
}
