<?php

class AiPlanGenerator
{
    private GeminiClient $client;

    public function __construct(GeminiClient $client)
    {
        $this->client = $client;
    }

    /**
     * @param array $constraints Keys: weekly_hours, injuries, long_run_day, baseline_km, paces, completion
     */
    public function generate(
        string $goal,
        DateTimeImmutable $startDate,
        DateTimeImmutable $goalDate,
        array $constraints,
        string $locale
    ): array {
        if (!isset(PlanGenerator::GOALS[$goal])) {
            throw new InvalidArgumentException("Unknown goal: {$goal}");
        }
        $cfg = PlanGenerator::GOALS[$goal];

        $diffDays = (int)$startDate->diff($goalDate)->format('%r%a');
        $weeks = max($cfg['min_weeks'], min($cfg['max_weeks'], (int)floor($diffDays / 7)));

        $monday = $startDate->modify('monday this week');
        if ($monday > $startDate) {
            $monday = $monday->modify('-1 week');
        }

        $systemPrompt = $this->systemPrompt($locale);
        $userPrompt = $this->userPrompt($goal, $cfg, $monday, $goalDate, $weeks, $constraints, $locale);
        $schema = $this->responseSchema($weeks);

        $response = $this->client->generateJson($systemPrompt, $userPrompt, $schema);

        $weeksOut = $this->normaliseWeeks($response['weeks'] ?? [], $monday);

        return [
            'goal' => $goal,
            'locale' => $locale,
            'engine' => 'ai',
            'sport' => $cfg['sport'],
            'start_date' => $startDate->format('Y-m-d'),
            'goal_date' => $goalDate->format('Y-m-d'),
            'weeks_total' => count($weeksOut),
            'baseline_km' => round($constraints['baseline_km'] ?? 0, 1),
            'peak_km' => $this->peakVolume($weeksOut),
            'weekly_hours' => $constraints['weekly_hours'] ?? null,
            'paces' => $constraints['paces'] ?? null,
            'weeks' => $weeksOut,
        ];
    }

    private function systemPrompt(string $locale): string
    {
        $lang = $locale === 'fr'
            ? 'Write every "theme", "title", and "desc" field in French. Use the informal "tu" form.'
            : 'Write every "theme", "title", and "desc" field in English.';

        return <<<PROMPT
You are an expert endurance coach with deep experience programming run, bike, swim, and triathlon training plans.

You will produce a complete week-by-week training plan as structured JSON matching the provided schema.

Coaching principles to follow:
- Progressive overload: increase weekly volume ~10% then deload every 4th week to ~80% of the prior week.
- Polarized intensity: ~80% easy, ~20% moderate/hard. No more than 2 hard sessions per week (3 for advanced).
- Phase structure: base (40%) → build (35%) → peak (15%) → taper (10%). Last week is race week.
- Quality + long run separated by at least 48h.
- Respect the athlete's stated weekly hours and injury constraints exactly.
- For triathlon: balance swim/bike/run, include 1–2 bricks per week in build/peak.
- Use the provided pace/power/swim CSS targets in workout descriptions when relevant (e.g. "5×1km @ 4:35/km").
- Day field uses "Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun" (English, fixed).
- Type field is one of: rest, easy, long, tempo, quality, race, brick.
- Sport field per day is one of: rest, run, bike, swim, strength, multi.
- Phase field per week is one of: base, build, peak, taper.
- {$lang}
- Be specific and practical. No filler. Each desc should be actionable in under 4 sentences.
PROMPT;
    }

    private function userPrompt(string $goal, array $cfg, DateTimeImmutable $monday, DateTimeImmutable $goalDate, int $weeks, array $constraints, string $locale): string
    {
        $goalLabel = $this->goalLabel($goal, $locale);
        $sport = $cfg['sport'];
        $sessionsPerWeek = $cfg['sessions_per_week'];

        $baseline = $constraints['baseline_km'] ?? 0;
        $hours = $constraints['weekly_hours'] ?? null;
        $injuries = trim($constraints['injuries'] ?? '');
        $longDay = $constraints['long_run_day'] ?? 'Sun';

        $pacesText = isset($constraints['paces'])
            ? PaceCalculator::toPromptText($constraints['paces'])
            : '(no pace data available)';

        $completionText = '';
        if (!empty($constraints['completion'])) {
            $completionText = "\n\nThe athlete previously had a plan in progress. Here is their compliance per week (1.0 = on target, <1.0 = under, >1.0 = over):\n";
            foreach ($constraints['completion'] as $w) {
                $completionText .= sprintf("- Week starting %s: %.0f%% of prescribed volume completed.\n", $w['start'], $w['ratio'] * 100);
            }
            $completionText .= "\nFactor this into the new plan: if recent weeks were under-completed, do not ramp aggressively.";
        }

        return <<<PROMPT
Generate a {$weeks}-week training plan.

Athlete:
- Goal: {$goalLabel} ({$sport})
- Plan starts: {$monday->format('Y-m-d')} (Monday)
- Goal date: {$goalDate->format('Y-m-d')} (final week is race week)
- Target sessions per week: {$sessionsPerWeek}
- Weekly hours available: {$hours} h
- Recent weekly volume baseline: {$baseline} km
- Preferred long-session day: {$longDay}
- Injuries / constraints: {$injuries}

Pace / power targets (use these in workout descs):
{$pacesText}
{$completionText}

Produce exactly {$weeks} weeks. Phase distribution: base/build/peak/taper roughly 40/35/15/10. Insert a deload week every 4th week (volume ~80%). The very last week is race week.
PROMPT;
    }

    private function responseSchema(int $weeks): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'weeks' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'index' => ['type' => 'integer'],
                            'phase' => ['type' => 'string', 'enum' => ['base', 'build', 'peak', 'taper']],
                            'theme' => ['type' => 'string'],
                            'target_km' => ['type' => 'number'],
                            'target_hours' => ['type' => 'number'],
                            'days' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'day' => ['type' => 'string', 'enum' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']],
                                        'type' => ['type' => 'string', 'enum' => ['rest', 'easy', 'long', 'tempo', 'quality', 'race', 'brick']],
                                        'sport' => ['type' => 'string', 'enum' => ['rest', 'run', 'bike', 'swim', 'strength', 'multi']],
                                        'title' => ['type' => 'string'],
                                        'distance_km' => ['type' => 'number'],
                                        'duration_min' => ['type' => 'integer'],
                                        'desc' => ['type' => 'string'],
                                    ],
                                    'required' => ['day', 'type', 'sport', 'title', 'desc'],
                                ],
                            ],
                        ],
                        'required' => ['index', 'phase', 'theme', 'target_km', 'days'],
                    ],
                ],
            ],
            'required' => ['weeks'],
        ];
    }

    private function normaliseWeeks(array $weeks, DateTimeImmutable $monday): array
    {
        $out = [];
        foreach ($weeks as $i => $w) {
            $out[] = [
                'index' => $w['index'] ?? ($i + 1),
                'start' => $monday->modify('+' . $i . ' weeks')->format('Y-m-d'),
                'phase' => $w['phase'] ?? 'base',
                'theme' => $w['theme'] ?? '',
                'target_km' => round((float)($w['target_km'] ?? 0), 1),
                'target_hours' => isset($w['target_hours']) ? round((float)$w['target_hours'], 1) : null,
                'days' => array_map(fn($d) => [
                    'day' => $d['day'] ?? 'Mon',
                    'type' => $d['type'] ?? 'easy',
                    'sport' => $d['sport'] ?? 'run',
                    'title' => $d['title'] ?? '',
                    'distance_km' => isset($d['distance_km']) ? round((float)$d['distance_km'], 1) : 0,
                    'duration_min' => $d['duration_min'] ?? null,
                    'desc' => $d['desc'] ?? '',
                ], $w['days'] ?? []),
            ];
        }
        return $out;
    }

    private function peakVolume(array $weeks): float
    {
        $max = 0.0;
        foreach ($weeks as $w) {
            if ($w['target_km'] > $max) $max = $w['target_km'];
        }
        return round($max, 1);
    }

    private function goalLabel(string $goal, string $locale): string
    {
        static $cache = [];
        if (!isset($cache[$locale])) {
            $file = __DIR__ . '/lang/' . $locale . '.php';
            $cache[$locale] = is_file($file) ? require $file : [];
        }
        return $cache[$locale]['goal.' . $goal] ?? $goal;
    }
}
