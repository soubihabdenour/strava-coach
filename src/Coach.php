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
                'title' => t('coach.no_activity.title'),
                'body' => t('coach.no_activity.body'),
            ]];
        }

        $delta = $s['volume_change_pct'];
        if ($delta !== null) {
            if ($delta > 30) {
                $tips[] = [
                    'level' => 'warn',
                    'title' => t('coach.volume_spike.title'),
                    'body' => t('coach.volume_spike.body', (int)round($delta)),
                ];
            } elseif ($delta < -25) {
                $tips[] = [
                    'level' => 'info',
                    'title' => t('coach.volume_drop.title'),
                    'body' => t('coach.volume_drop.body', (int)round($delta)),
                ];
            } else {
                $tips[] = [
                    'level' => 'good',
                    'title' => t('coach.volume_healthy.title'),
                    'body' => t('coach.volume_healthy.body', (int)round($delta)),
                ];
            }
        }

        $rest = $s['rest_days_last_14'];
        if ($rest < 2) {
            $tips[] = [
                'level' => 'warn',
                'title' => t('coach.few_rest.title'),
                'body' => t('coach.few_rest.body', $rest),
            ];
        } elseif ($rest > 8) {
            $tips[] = [
                'level' => 'info',
                'title' => t('coach.inactive.title'),
                'body' => t('coach.inactive.body', $rest),
            ];
        }

        $longRunShare = $this->longestEffortShare();
        if ($longRunShare !== null && $longRunShare > 0.5) {
            $tips[] = [
                'level' => 'warn',
                'title' => t('coach.one_workout.title'),
                'body' => t('coach.one_workout.body', (int)round($longRunShare * 100)),
            ];
        }

        $intensity = $this->intensityMix();
        if ($intensity !== null) {
            $easyPct = (int)round($intensity['easy_pct']);
            if ($intensity['easy_pct'] < 70) {
                $tips[] = [
                    'level' => 'warn',
                    'title' => t('coach.too_hard.title'),
                    'body' => t('coach.too_hard.body', $easyPct),
                ];
            } else {
                $tips[] = [
                    'level' => 'good',
                    'title' => t('coach.healthy_split.title'),
                    'body' => t('coach.healthy_split.body', $easyPct),
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
                    'title' => t('coach.cross_train.title'),
                    'body' => t('coach.cross_train.body', (int)round($topShare * 100), $top),
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
                'title' => t('coach.next_recovery.title'),
                'body' => t('coach.next_recovery.body'),
            ];
        }

        $weekKm = end($s['weeks'])['distance_km'] ?? 0;
        if ($weekKm < 10) {
            return [
                'level' => 'info',
                'title' => t('coach.next_easy.title'),
                'body' => t('coach.next_easy.body'),
            ];
        }
        if ($weekKm < 30) {
            return [
                'level' => 'info',
                'title' => t('coach.next_tempo.title'),
                'body' => t('coach.next_tempo.body'),
            ];
        }
        return [
            'level' => 'info',
            'title' => t('coach.next_long.title'),
            'body' => t('coach.next_long.body'),
        ];
    }
}
