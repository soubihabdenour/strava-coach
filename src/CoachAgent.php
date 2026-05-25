<?php

class CoachAgent
{
    public const SPORTS = ['run', 'swim', 'cycle', 'tri', 'nutrition'];

    public static function isValid(string $sport): bool
    {
        return in_array($sport, self::SPORTS, true);
    }

    public static function buildSystemPrompt(string $sport, array $summary, string $locale): string
    {
        $persona = self::persona($sport);
        $context = self::stravaContext($summary);
        $lang = $locale === 'fr'
            ? 'Always reply in French. Use the informal "tu" form.'
            : 'Always reply in English.';

        return <<<PROMPT
{$persona}

Athlete context (from Strava, last 4 weeks):
{$context}

Style:
- Be concise and practical. Avoid generic platitudes and pep-talk.
- Cite specific numbers from the athlete context when relevant.
- Use short paragraphs and bullets. No emoji-heavy responses.
- If asked about something outside your specialty, briefly redirect to the right coach (run / swim / cycle / triathlon / nutrition).
- Never invent data the athlete didn't provide. If you need a number that isn't shown, ask for it.
- {$lang}
PROMPT;
    }

    private static function persona(string $sport): string
    {
        return match ($sport) {
            'run' => 'You are an experienced running coach. You specialize in distance running from 5K to marathon, pacing by HR/RPE, periodization, drills, and injury prevention.',
            'swim' => 'You are an experienced swim coach. You specialize in pool and open-water training, stroke technique cues (catch, body roll, breathing), pacing by feel, and aerobic / threshold / sprint sets.',
            'cycle' => 'You are an experienced cycling coach. You specialize in road and indoor training, prescription by power or HR zones, climbing, descending, group-ride tactics, and recovery.',
            'tri' => 'You are an experienced triathlon coach. You program across swim/bike/run, manage weekly load and brick sessions, plan tapers for races (sprint to Ironman), and balance the three disciplines without overtraining.',
            'nutrition' => 'You are a sports nutrition coach for endurance athletes. You advise on fueling around workouts, daily nutrition, race-day strategy, hydration, and recovery. You do not prescribe supplements, diagnose deficiencies, or make medical claims — refer to a registered dietitian or doctor for those.',
            default => '',
        };
    }

    private static function stravaContext(array $summary): string
    {
        $cur = $summary['current_block'] ?? ['distance_km' => 0, 'moving_hours' => 0, 'elevation_m' => 0, 'activities' => 0];
        $delta = $summary['volume_change_pct'] ?? null;
        $rest = $summary['rest_days_last_14'] ?? null;
        $sports = $summary['sport_breakdown'] ?? [];

        $sportLine = empty($sports)
            ? 'no activities yet'
            : implode(', ', array_map(fn($k, $v) => "$k: $v", array_keys($sports), array_values($sports)));

        $deltaStr = $delta === null ? 'n/a' : sprintf('%+d%%', (int)round($delta));

        return sprintf(
            "- Total distance: %.1f km\n- Moving time: %.1f h\n- Elevation: %.0f m\n- Activities: %d\n- Volume change vs prior 4 weeks: %s\n- Rest days in last 14: %s\n- Activity mix: %s",
            $cur['distance_km'], $cur['moving_hours'], $cur['elevation_m'],
            $cur['activities'], $deltaStr, $rest ?? 0, $sportLine
        );
    }
}
