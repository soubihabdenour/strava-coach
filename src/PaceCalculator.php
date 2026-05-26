<?php

class PaceCalculator
{
    /**
     * @param array<int,array<string,mixed>> $activities
     */
    public static function compute(array $activities): array
    {
        $runs = array_values(array_filter($activities, fn($a) => in_array(($a['sport_type'] ?? $a['type'] ?? ''), ['Run', 'TrailRun', 'VirtualRun'], true)));
        $rides = array_values(array_filter($activities, fn($a) => in_array(($a['sport_type'] ?? $a['type'] ?? ''), ['Ride', 'VirtualRide', 'GravelRide'], true)));
        $swims = array_values(array_filter($activities, fn($a) => in_array(($a['sport_type'] ?? $a['type'] ?? ''), ['Swim'], true)));

        $maxHr = self::maxObservedHr($activities);

        return [
            'max_hr' => $maxHr,
            'run' => self::runPaces($runs, $maxHr),
            'bike' => self::bikePower($rides, $maxHr),
            'swim' => self::swimPaces($swims),
        ];
    }

    private static function maxObservedHr(array $activities): ?int
    {
        $maxes = array_filter(array_map(fn($a) => $a['max_heartrate'] ?? null, $activities));
        return $maxes ? (int)max($maxes) : null;
    }

    /**
     * @return array{has_data:bool,sample_n:int,easy_pace_s_per_km:?float,threshold_pace_s_per_km:?float,zones:array<string,string>}
     */
    private static function runPaces(array $runs, ?int $maxHr): array
    {
        if (empty($runs)) {
            return ['has_data' => false, 'sample_n' => 0, 'easy_pace_s_per_km' => null, 'threshold_pace_s_per_km' => null, 'zones' => []];
        }

        $easySamples = [];
        $thresholdSamples = [];
        $allSamples = [];

        foreach ($runs as $r) {
            $dist = ($r['distance'] ?? 0);
            $time = ($r['moving_time'] ?? 0);
            if ($dist < 500 || $time < 300) continue;
            $paceSec = $time / ($dist / 1000);
            if ($paceSec < 180 || $paceSec > 900) continue;
            $allSamples[] = $paceSec;

            $avgHr = $r['average_heartrate'] ?? null;
            if ($maxHr && $avgHr) {
                $hrFrac = $avgHr / $maxHr;
                if ($hrFrac < 0.78) {
                    $easySamples[] = $paceSec;
                } elseif ($hrFrac >= 0.85 && $hrFrac <= 0.92) {
                    $thresholdSamples[] = $paceSec;
                }
            }
        }

        if (empty($easySamples) && !empty($allSamples)) {
            $sorted = $allSamples;
            sort($sorted);
            $easySamples = array_slice($sorted, (int)floor(count($sorted) * 0.6));
        }

        $easyPace = $easySamples ? self::median($easySamples) : null;
        $thresholdPace = $thresholdSamples ? self::median($thresholdSamples) : ($easyPace ? $easyPace * 0.82 : null);

        return [
            'has_data' => count($allSamples) >= 3,
            'sample_n' => count($allSamples),
            'easy_pace_s_per_km' => $easyPace,
            'threshold_pace_s_per_km' => $thresholdPace,
            'zones' => self::runZones($easyPace, $thresholdPace),
        ];
    }

    private static function runZones(?float $easy, ?float $threshold): array
    {
        if (!$easy && !$threshold) return [];
        $threshold ??= $easy * 0.82;
        $easy ??= $threshold / 0.82;

        return [
            'easy' => self::paceRange($easy * 1.0, $easy * 1.1),
            'marathon' => self::paceRange($threshold * 1.06, $threshold * 1.10),
            'tempo' => self::paceRange($threshold * 1.00, $threshold * 1.04),
            'threshold' => self::paceRange($threshold * 0.97, $threshold * 1.00),
            'vo2' => self::paceRange($threshold * 0.91, $threshold * 0.95),
        ];
    }

    private static function bikePower(array $rides, ?int $maxHr): array
    {
        if (empty($rides)) {
            return ['has_data' => false, 'ftp_estimate_w' => null, 'avg_speed_kmh' => null, 'zones_w' => []];
        }

        $powers = [];
        $speeds = [];
        foreach ($rides as $r) {
            $dist = $r['distance'] ?? 0;
            $time = $r['moving_time'] ?? 0;
            if ($dist > 1000 && $time > 600) {
                $speeds[] = ($dist / 1000) / ($time / 3600);
            }
            if (!empty($r['average_watts']) && ($r['moving_time'] ?? 0) > 1800) {
                $powers[] = (float)$r['average_watts'];
            }
        }

        $avgSpeed = $speeds ? self::median($speeds) : null;
        $ftp = null;
        if (!empty($powers)) {
            sort($powers);
            $top = array_slice($powers, (int)floor(count($powers) * 0.8));
            $ftp = $top ? array_sum($top) / count($top) * 0.95 : null;
        }

        return [
            'has_data' => !empty($speeds),
            'ftp_estimate_w' => $ftp ? (int)round($ftp) : null,
            'avg_speed_kmh' => $avgSpeed ? round($avgSpeed, 1) : null,
            'zones_w' => $ftp ? self::bikeZones($ftp) : [],
        ];
    }

    private static function bikeZones(float $ftp): array
    {
        return [
            'recovery' => '< ' . (int)round($ftp * 0.55) . ' W',
            'endurance' => (int)round($ftp * 0.56) . '–' . (int)round($ftp * 0.75) . ' W',
            'tempo' => (int)round($ftp * 0.76) . '–' . (int)round($ftp * 0.90) . ' W',
            'threshold' => (int)round($ftp * 0.91) . '–' . (int)round($ftp * 1.05) . ' W',
            'vo2' => (int)round($ftp * 1.06) . '–' . (int)round($ftp * 1.20) . ' W',
        ];
    }

    private static function swimPaces(array $swims): array
    {
        if (empty($swims)) {
            return ['has_data' => false, 'css_pace_s_per_100m' => null, 'zones' => []];
        }

        $samples = [];
        foreach ($swims as $s) {
            $dist = $s['distance'] ?? 0;
            $time = $s['moving_time'] ?? 0;
            if ($dist >= 400 && $time >= 300) {
                $pace = $time / ($dist / 100);
                if ($pace >= 60 && $pace <= 360) {
                    $samples[] = $pace;
                }
            }
        }

        if (empty($samples)) {
            return ['has_data' => false, 'css_pace_s_per_100m' => null, 'zones' => []];
        }

        sort($samples);
        $fast = array_slice($samples, 0, max(1, (int)ceil(count($samples) * 0.3)));
        $css = array_sum($fast) / count($fast);

        return [
            'has_data' => true,
            'css_pace_s_per_100m' => $css,
            'zones' => [
                'easy' => self::swimRange($css * 1.15, $css * 1.25),
                'aerobic' => self::swimRange($css * 1.05, $css * 1.15),
                'css' => self::swimRange($css * 0.98, $css * 1.02),
                'sprint' => self::swimRange($css * 0.88, $css * 0.95),
            ],
        ];
    }

    private static function median(array $values): float
    {
        sort($values);
        $n = count($values);
        $mid = (int)floor($n / 2);
        return $n % 2 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }

    private static function paceRange(float $a, float $b): string
    {
        $lo = min($a, $b);
        $hi = max($a, $b);
        return self::formatPace($lo) . '–' . self::formatPace($hi) . '/km';
    }

    private static function formatPace(float $secPerKm): string
    {
        $m = (int)floor($secPerKm / 60);
        $s = (int)round($secPerKm - $m * 60);
        if ($s === 60) { $m++; $s = 0; }
        return sprintf('%d:%02d', $m, $s);
    }

    private static function swimRange(float $a, float $b): string
    {
        $lo = min($a, $b);
        $hi = max($a, $b);
        return self::formatPace($lo) . '–' . self::formatPace($hi) . '/100m';
    }

    /**
     * Plain-text summary suitable for LLM prompts.
     */
    public static function toPromptText(array $paces): string
    {
        $lines = [];
        $lines[] = '- Max HR observed: ' . ($paces['max_hr'] ?? 'unknown');

        $r = $paces['run'];
        if ($r['has_data']) {
            $lines[] = sprintf('- Run easy pace: %s/km, threshold pace: %s/km (sample n=%d)',
                $r['easy_pace_s_per_km'] ? self::formatPace($r['easy_pace_s_per_km']) : 'n/a',
                $r['threshold_pace_s_per_km'] ? self::formatPace($r['threshold_pace_s_per_km']) : 'n/a',
                $r['sample_n']
            );
            foreach ($r['zones'] as $name => $range) {
                $lines[] = "  - run $name: $range";
            }
        } else {
            $lines[] = '- No run pace data available.';
        }

        $b = $paces['bike'];
        if ($b['has_data']) {
            $lines[] = sprintf('- Bike avg speed: %s km/h, FTP est: %s W',
                $b['avg_speed_kmh'] ?? 'n/a',
                $b['ftp_estimate_w'] ?? 'unknown (no power data)'
            );
            foreach ($b['zones_w'] as $name => $range) {
                $lines[] = "  - bike $name: $range";
            }
        } else {
            $lines[] = '- No bike data available.';
        }

        $s = $paces['swim'];
        if ($s['has_data']) {
            $lines[] = '- Swim CSS pace est: ' . self::formatPace($s['css_pace_s_per_100m']) . '/100m';
            foreach ($s['zones'] as $name => $range) {
                $lines[] = "  - swim $name: $range";
            }
        } else {
            $lines[] = '- No swim data available.';
        }

        return implode("\n", $lines);
    }
}
