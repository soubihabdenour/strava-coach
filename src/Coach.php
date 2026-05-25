<?php

class Coach
{
    /** @var array<int,array<string,mixed>> */
    private array $activities;

    public function __construct(array $activities)
    {
        usort($activities, fn($a, $b) => strcmp($a['start_date_local'] ?? '', $b['start_date_local'] ?? ''));
        $this->activities = $activities;
    }

    public function summary(): array
    {
        $weeks = $this->weeklyBuckets(12);
        $current = $this->aggregateWeeks(array_slice($weeks, -4, 4, true));
        $prior = $this->aggregateWeeks(array_slice($weeks, -8, 4, true));

        return [
            'total_activities' => count($this->activities),
            'weeks' => $weeks,
            'current_block' => $current,
            'prior_block' => $prior,
            'volume_change_pct' => $this->percentChange($prior['distance_km'], $current['distance_km']),
            'time_change_pct' => $this->percentChange($prior['moving_hours'], $current['moving_hours']),
            'sport_breakdown' => $this->sportBreakdown(),
            'last_activity' => end($this->activities) ?: null,
            'rest_days_last_14' => $this->restDaysInLastN(14),
        ];
    }

    public function recommendations(): array
    {
        $tips = [];
        $s = $this->summary();

        if ($s['total_activities'] === 0) {
            return [[
                'level' => 'info',
                'title' => 'No recent activity',
                'body' => 'Sync a workout in Strava to unlock coaching. Start with a 20–30 minute easy effort to baseline your fitness.',
            ]];
        }

        $delta = $s['volume_change_pct'];
        if ($delta !== null) {
            if ($delta > 30) {
                $tips[] = [
                    'level' => 'warn',
                    'title' => 'Volume spike — back off this week',
                    'body' => sprintf(
                        'Your last 4 weeks of distance are %+d%% vs the prior block. The 10%% rule is a guideline; sustained jumps above 25–30%% raise injury risk. Hold or trim volume 10–15%% this week and keep intensity easy.',
                        (int)round($delta)
                    ),
                ];
            } elseif ($delta < -25) {
                $tips[] = [
                    'level' => 'info',
                    'title' => 'Volume has dropped — rebuild gradually',
                    'body' => sprintf(
                        'You logged %+d%% less distance in the past 4 weeks. Aim to add ~10%% per week back to your previous baseline before pushing intensity.',
                        (int)round($delta)
                    ),
                ];
            } else {
                $tips[] = [
                    'level' => 'good',
                    'title' => 'Volume is in a healthy range',
                    'body' => sprintf('4-week distance change vs the prior block: %+d%%. Stay near this load while you build consistency.', (int)round($delta)),
                ];
            }
        }

        $rest = $s['rest_days_last_14'];
        if ($rest < 2) {
            $tips[] = [
                'level' => 'warn',
                'title' => 'Too few rest days',
                'body' => sprintf('Only %d full rest day(s) in the past 14. Schedule at least 1–2 complete rest days per week — adaptation happens during recovery, not during training.', $rest),
            ];
        } elseif ($rest > 8) {
            $tips[] = [
                'level' => 'info',
                'title' => 'Long stretches of inactivity',
                'body' => sprintf('%d rest days in the last 14. If unintentional, add a short 20-min activity on two of those days to keep the engine warm.', $rest),
            ];
        }

        $longRunShare = $this->longestEffortShare();
        if ($longRunShare !== null && $longRunShare > 0.5) {
            $tips[] = [
                'level' => 'warn',
                'title' => 'One workout dominates your week',
                'body' => sprintf('Your longest single session is %d%% of weekly volume. Spread mileage across 3–5 sessions to reduce per-workout strain.', (int)round($longRunShare * 100)),
            ];
        }

        $intensity = $this->intensityMix();
        if ($intensity !== null) {
            $easyPct = (int)round($intensity['easy_pct']);
            if ($intensity['easy_pct'] < 70) {
                $tips[] = [
                    'level' => 'warn',
                    'title' => 'Too much hard running',
                    'body' => sprintf('Only %d%% of your time is in easy HR zones. Polarized / 80-20 training recommends ~80%% easy, 20%% hard. Slow down most days to absorb harder sessions.', $easyPct),
                ];
            } else {
                $tips[] = [
                    'level' => 'good',
                    'title' => 'Healthy easy/hard split',
                    'body' => sprintf('About %d%% of your time is easy. That gives the body room to adapt while keeping hard sessions effective.', $easyPct),
                ];
            }
        }

        $sports = $s['sport_breakdown'];
        if (!empty($sports)) {
            $top = array_key_first($sports);
            $topShare = $sports[$top] / array_sum($sports);
            if ($topShare > 0.9 && count($this->activities) > 6) {
                $tips[] = [
                    'level' => 'info',
                    'title' => 'Add cross-training',
                    'body' => sprintf('%d%% of activities are %s. One weekly low-impact session (bike, swim, strength) reduces overuse risk and builds aerobic base.', (int)round($topShare * 100), $top),
                ];
            }
        }

        $tips[] = $this->nextWorkoutSuggestion($s);

        return $tips;
    }

    private function weeklyBuckets(int $weeks): array
    {
        $buckets = [];
        $start = (new DateTimeImmutable('monday this week'))->modify('-' . ($weeks - 1) . ' weeks');
        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $start->modify('+' . $i . ' weeks');
            $key = $weekStart->format('Y-m-d');
            $buckets[$key] = [
                'start' => $key,
                'distance_km' => 0.0,
                'moving_hours' => 0.0,
                'elevation_m' => 0.0,
                'activities' => 0,
            ];
        }

        foreach ($this->activities as $a) {
            $date = substr($a['start_date_local'] ?? '', 0, 10);
            if (!$date) continue;
            $d = new DateTimeImmutable($date);
            $monday = $d->modify('monday this week')->format('Y-m-d');
            if (!isset($buckets[$monday])) continue;
            $buckets[$monday]['distance_km'] += ($a['distance'] ?? 0) / 1000;
            $buckets[$monday]['moving_hours'] += ($a['moving_time'] ?? 0) / 3600;
            $buckets[$monday]['elevation_m'] += $a['total_elevation_gain'] ?? 0;
            $buckets[$monday]['activities']++;
        }
        return $buckets;
    }

    private function aggregateWeeks(array $weeks): array
    {
        $sum = ['distance_km' => 0.0, 'moving_hours' => 0.0, 'elevation_m' => 0.0, 'activities' => 0];
        foreach ($weeks as $w) {
            $sum['distance_km'] += $w['distance_km'];
            $sum['moving_hours'] += $w['moving_hours'];
            $sum['elevation_m'] += $w['elevation_m'];
            $sum['activities'] += $w['activities'];
        }
        return $sum;
    }

    private function percentChange(float $from, float $to): ?float
    {
        if ($from <= 0.01) {
            return $to > 0 ? 100.0 : null;
        }
        return (($to - $from) / $from) * 100;
    }

    private function sportBreakdown(): array
    {
        $counts = [];
        foreach ($this->activities as $a) {
            $sport = $a['sport_type'] ?? $a['type'] ?? 'Other';
            $counts[$sport] = ($counts[$sport] ?? 0) + 1;
        }
        arsort($counts);
        return $counts;
    }

    private function restDaysInLastN(int $n): int
    {
        $active = [];
        $cutoff = (new DateTimeImmutable())->modify('-' . $n . ' days')->format('Y-m-d');
        foreach ($this->activities as $a) {
            $date = substr($a['start_date_local'] ?? '', 0, 10);
            if ($date && $date >= $cutoff) {
                $active[$date] = true;
            }
        }
        return $n - count($active);
    }

    private function longestEffortShare(): ?float
    {
        $cutoff = (new DateTimeImmutable())->modify('-7 days')->format('Y-m-d');
        $weekly = 0.0;
        $longest = 0.0;
        foreach ($this->activities as $a) {
            $date = substr($a['start_date_local'] ?? '', 0, 10);
            if ($date && $date >= $cutoff) {
                $d = ($a['distance'] ?? 0) / 1000;
                $weekly += $d;
                if ($d > $longest) $longest = $d;
            }
        }
        return $weekly > 0 ? $longest / $weekly : null;
    }

    private function intensityMix(): ?array
    {
        $easy = 0.0;
        $hard = 0.0;
        $hasHr = false;
        foreach ($this->activities as $a) {
            if (empty($a['average_heartrate']) || empty($a['max_heartrate'])) continue;
            $hasHr = true;
            $ratio = $a['average_heartrate'] / max(1, $a['max_heartrate']);
            $minutes = ($a['moving_time'] ?? 0) / 60;
            if ($ratio < 0.78) {
                $easy += $minutes;
            } else {
                $hard += $minutes;
            }
        }
        if (!$hasHr) return null;
        $total = $easy + $hard;
        return $total > 0 ? ['easy_pct' => 100 * $easy / $total, 'hard_pct' => 100 * $hard / $total] : null;
    }

    private function nextWorkoutSuggestion(array $s): array
    {
        $last = $s['last_activity'];
        $todayLoad = 0;
        if ($last) {
            $lastDate = substr($last['start_date_local'] ?? '', 0, 10);
            if ($lastDate === (new DateTimeImmutable())->format('Y-m-d')) {
                $todayLoad = ($last['moving_time'] ?? 0) / 60;
            }
        }

        if ($todayLoad > 60) {
            return [
                'level' => 'info',
                'title' => "Tomorrow's plan: recovery",
                'body' => 'Long session today. Tomorrow: easy 30–40 min in zone 1–2, or full rest. Hydrate, eat carbs + protein within 60 min.',
            ];
        }

        $weekKm = end($s['weeks'])['distance_km'] ?? 0;
        if ($weekKm < 10) {
            return [
                'level' => 'info',
                'title' => "Next session: easy build",
                'body' => '30–40 min at conversational pace (zone 2). Focus on cadence and breathing — no watch chasing.',
            ];
        }
        if ($weekKm < 30) {
            return [
                'level' => 'info',
                'title' => "Next session: tempo intervals",
                'body' => 'Warm up 15 min easy. 4 × 5 min at comfortably-hard effort (zone 3–4), 2 min jog between. Cool down 10 min.',
            ];
        }
        return [
            'level' => 'info',
            'title' => "Next session: long aerobic",
            'body' => 'Build to 70–90 min steady at low zone 2. Optional: last 10 min at marathon-pace effort to teach the body to finish strong.',
        ];
    }
}
