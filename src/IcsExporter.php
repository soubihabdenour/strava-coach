<?php

class IcsExporter
{
    private const DOW_OFFSET = ['Mon' => 0, 'Tue' => 1, 'Wed' => 2, 'Thu' => 3, 'Fri' => 4, 'Sat' => 5, 'Sun' => 6];

    /**
     * Render a plan as an RFC 5545 iCalendar file (all-day events, one per scheduled workout).
     * Rest days are skipped so calendars stay scannable.
     */
    /**
     * Minimal valid VCALENDAR with no events — used by the subscription feed
     * when there's no active plan, so calendar apps cleanly remove prior events.
     */
    public static function emptyCalendar(string $label = 'Training plan'): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Strava Personal Coach//Plan Export//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::escapeText($label),
            'END:VCALENDAR',
        ];
        return implode("\r\n", $lines) . "\r\n";
    }

    public static function fromPlan(array $plan, string $athleteName = 'Athlete'): string
    {
        $now = gmdate('Ymd\THis\Z');
        $planId = (int)($plan['_id'] ?? 0);
        $goal = $plan['goal'] ?? 'plan';
        $domain = self::domain();

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Strava Personal Coach//Plan Export//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::escapeText('Training plan — ' . $goal),
            'X-WR-CALDESC:' . self::escapeText("{$athleteName}'s training plan, generated from Strava data."),
        ];

        foreach ($plan['weeks'] ?? [] as $week) {
            $weekStart = $week['start'] ?? null;
            if (!$weekStart) continue;
            $weekIdx = (int)($week['index'] ?? 0);

            foreach ($week['days'] ?? [] as $day) {
                if (($day['sport'] ?? 'rest') === 'rest') continue;

                $dayCode = $day['day'] ?? 'Mon';
                $offset = self::DOW_OFFSET[$dayCode] ?? 0;
                $eventDate = (new DateTimeImmutable($weekStart))->modify("+{$offset} days");
                $dateStr = $eventDate->format('Ymd');
                $dateEnd = $eventDate->modify('+1 day')->format('Ymd');

                $summary = self::buildSummary($day);
                $body = self::buildDescription($day, $week);
                $uid = sprintf('coach-plan-%d-w%d-%s-%s@%s', $planId, $weekIdx, $dayCode, $dateStr, $domain);

                $lines[] = 'BEGIN:VEVENT';
                $lines[] = 'UID:' . $uid;
                $lines[] = 'DTSTAMP:' . $now;
                $lines[] = 'DTSTART;VALUE=DATE:' . $dateStr;
                $lines[] = 'DTEND;VALUE=DATE:' . $dateEnd;
                $lines[] = 'SUMMARY:' . self::escapeText($summary);
                $lines[] = 'DESCRIPTION:' . self::escapeText($body);
                $lines[] = 'CATEGORIES:Training,' . self::categoryToken($day['sport'] ?? '') . ',' . self::categoryToken($week['phase'] ?? '');
                $lines[] = 'TRANSP:TRANSPARENT';
                $lines[] = 'END:VEVENT';
            }
        }

        $lines[] = 'END:VCALENDAR';

        $folded = array_map([self::class, 'foldLine'], $lines);
        return implode("\r\n", $folded) . "\r\n";
    }

    private static function buildSummary(array $day): string
    {
        $emoji = self::sportEmoji($day['sport'] ?? '');
        $title = trim((string)($day['title'] ?? 'Workout'));
        $distance = (float)($day['distance_km'] ?? 0);
        $summary = ($emoji ? $emoji . ' ' : '') . $title;
        if ($distance > 0) {
            $summary .= ' · ' . rtrim(rtrim(number_format($distance, 1), '0'), '.') . ' km';
        } elseif (!empty($day['duration_min'])) {
            $summary .= ' · ' . (int)$day['duration_min'] . ' min';
        }
        return $summary;
    }

    private static function buildDescription(array $day, array $week): string
    {
        $lines = [];

        if (!empty($day['desc'])) {
            $lines[] = trim((string)$day['desc']);
            $lines[] = '';
        }

        $meta = [];
        if (!empty($day['purpose'])) $meta[] = 'Purpose: ' . $day['purpose'];
        if (!empty($day['rpe']))     $meta[] = 'Effort: ' . (int)$day['rpe'] . '/10';
        if (!empty($day['duration_min'])) $meta[] = 'Planned: ' . (int)$day['duration_min'] . ' min';
        if ($meta) {
            $lines[] = implode(' · ', $meta);
            $lines[] = '';
        }

        if (!empty($day['structured_steps'])) {
            $lines[] = 'Workout:';
            $i = 1;
            foreach ($day['structured_steps'] as $step) {
                $lines[] = '  ' . $i . '. ' . self::stepLine($step);
                $i++;
            }
            $lines[] = '';
        }

        if (!empty($day['fueling'])) {
            $lines[] = 'Fueling: ' . $day['fueling'];
            $lines[] = '';
        }

        $weekBits = ['Week ' . ($week['index'] ?? '?')];
        if (!empty($week['phase'])) $weekBits[] = ucfirst((string)$week['phase']) . ' phase';
        if (!empty($week['theme'])) $weekBits[] = (string)$week['theme'];
        $lines[] = '— ' . implode(' · ', $weekBits);

        return rtrim(implode("\n", $lines));
    }

    private static function stepLine(array $step): string
    {
        $kind = ucfirst($step['kind'] ?? 'main');
        $parts = [$kind];

        if (!empty($step['reps']) && (int)$step['reps'] > 1) {
            $parts[] = (int)$step['reps'] . '×';
        }

        $vol = [];
        if (!empty($step['distance_km'])) {
            $km = (float)$step['distance_km'];
            $vol[] = $km >= 1
                ? rtrim(rtrim(number_format($km, 2), '0'), '.') . ' km'
                : (int)round($km * 1000) . ' m';
        }
        if (!empty($step['duration_min'])) {
            $min = (float)$step['duration_min'];
            $vol[] = $min >= 1
                ? rtrim(rtrim(number_format($min, 1), '0'), '.') . ' min'
                : (int)round($min * 60) . ' s';
        }
        if ($vol) $parts[] = implode(' / ', $vol);
        if (!empty($step['target'])) $parts[] = '@ ' . $step['target'];
        if (!empty($step['recovery'])) $parts[] = '(recovery: ' . $step['recovery'] . ')';

        $line = implode(' ', $parts);
        if (!empty($step['notes'])) $line .= ' — ' . $step['notes'];
        return $line;
    }

    private static function sportEmoji(string $sport): string
    {
        return match ($sport) {
            'run'      => '🏃',
            'bike'     => '🚴',
            'swim'     => '🏊',
            'strength' => '🏋️',
            'multi'    => '🔁',
            default    => '',
        };
    }

    private static function categoryToken(string $s): string
    {
        $s = preg_replace('/[^a-zA-Z0-9]/', '', $s);
        return $s !== '' ? ucfirst(strtolower($s)) : 'General';
    }

    /**
     * Per RFC 5545 §3.3.11: escape \, comma, semicolon, newline in TEXT values.
     */
    private static function escapeText(string $s): string
    {
        return str_replace(
            ['\\',  "\r\n", "\n", "\r",  ',',   ';'],
            ['\\\\', '\\n', '\\n', '\\n', '\\,', '\\;'],
            $s
        );
    }

    /**
     * Per RFC 5545 §3.1: fold lines longer than 75 octets at byte boundaries
     * with CRLF + single space continuation.
     */
    private static function foldLine(string $line): string
    {
        if (strlen($line) <= 75) return $line;
        $chunks = str_split($line, 73);
        $first = array_shift($chunks);
        return $first . "\r\n " . implode("\r\n ", $chunks);
    }

    private static function domain(): string
    {
        $host = strtok($_SERVER['HTTP_HOST'] ?? 'coach.local', ':');
        $host = preg_replace('/[^a-zA-Z0-9.\-]/', '', (string)$host);
        return $host !== '' ? $host : 'coach.local';
    }
}
