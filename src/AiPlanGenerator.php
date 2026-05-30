<?php

class AiPlanGenerator
{
    private GeminiClient $client;

    public function __construct(GeminiClient $client)
    {
        $this->client = $client;
    }

    /**
     * @param array $constraints Keys: weekly_hours, injuries, long_run_day, baseline_km, paces, completion,
     *                                  cant_train_days, sessions_override, target_time, intensity_preference,
     *                                  surface, pool_length, bike_location
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
            'long_run_day' => $constraints['long_run_day'] ?? 'Sun',
            'injuries' => $constraints['injuries'] ?? '',
            'cant_train_days' => $constraints['cant_train_days'] ?? [],
            'sessions_override' => $constraints['sessions_override'] ?? null,
            'target_time' => $constraints['target_time'] ?? '',
            'intensity_preference' => $constraints['intensity_preference'] ?? 'polarized',
            'surface' => $constraints['surface'] ?? 'mixed',
            'pool_length' => $constraints['pool_length'] ?? '25m',
            'bike_location' => $constraints['bike_location'] ?? 'mixed',
            'weeks' => $weeksOut,
        ];
    }

    private function systemPrompt(string $locale): string
    {
        $lang = $locale === 'fr'
            ? 'Write every "theme", "title", "desc", "purpose", "fueling", "target", "recovery", and "notes" field in French. Use the informal "tu" form. Keep step "kind" values as the English enum literals.'
            : 'Write every "theme", "title", "desc", "purpose", "fueling", "target", "recovery", and "notes" field in English.';

        return <<<PROMPT
You are an expert endurance coach with deep experience programming run, bike, swim, and triathlon training plans.

You will produce a complete week-by-week training plan as structured JSON matching the provided schema.

Coaching principles:
- Progressive overload: ~10%/week volume bump, deload every 4th week to ~80% of prior week.
- Polarized intensity (or whatever the athlete's intensity_preference specifies). No more than 2 hard sessions/week (3 for advanced).
- Phase structure: base (40%) → build (35%) → peak (15%) → taper (10%). Last week is race week.
- Quality + long run separated by ≥48 h.
- Respect weekly hours, injuries, and any days the athlete cannot train EXACTLY (no session on a blocked day).
- For triathlon: balance swim/bike/run, include 1–2 bricks/week in build/peak.
- Use the provided pace/power/swim CSS targets in workout content (e.g. "5×1 km @ 4:35/km").
- If a target finish time is given, calibrate paces toward it (e.g. for a sub-45 10K: 4:30/km goal pace, 4:25/km threshold).

Per-week fields:
- index, phase (base/build/peak/taper), theme (short string), target_km (number), target_hours (number), days[].

Per-day fields:
- day: one of "Mon","Tue","Wed","Thu","Fri","Sat","Sun" (English, fixed).
- type: one of rest, easy, long, tempo, quality, race, brick.
- sport: one of rest, run, bike, swim, strength, multi.
- title: short workout name (e.g. "5×1 km threshold").
- distance_km, duration_min: planned totals.
- desc: 1–2 sentences of CONTEXT — the why and the focus cue. Not a step list.
- purpose: one short phrase, the adaptation target (e.g. "Aerobic base", "Lactate threshold tolerance", "Neuromuscular sharpening", "Recovery").
- rpe: integer 1–10, the average effort (1=very easy walk, 5=conversational, 7=comfortably hard, 9=hard, 10=max).
- fueling: ONLY for sessions > 75 min — one short note on carbs / hydration / sodium. Otherwise omit.
- structured_steps: the executable breakdown. Always include for any session of meaningful intensity (warm-up + main/interval + cool-down). For pure rest, leave empty. For a continuous easy run, a single "main" step is fine.

Step fields:
- kind: one of "warmup", "main", "interval", "cooldown" (English enum, fixed).
- reps: integer, required for kind="interval" (e.g. 5 for 5×1km).
- duration_min: number (optional).
- distance_km: number (optional; use for track-style sets like 0.4 for 400 m).
- target: short intensity descriptor (e.g. "easy Z2", "4:35/km threshold", "5K effort", "Z3 80% FTP", "CSS pace").
- recovery: only for kind="interval" — short note on between-rep recovery (e.g. "90 s jog", "200 m easy").
- notes: optional extra cue (form, cadence, surface).

Domain-specific inputs to use ONLY when relevant to the sport:
- run surface → only for run / triathlon plans.
- pool length → only for swim / triathlon plans.
- bike location (outdoor / indoor) → only for bike / triathlon plans.

- {$lang}
- Be specific and practical. No filler. Each desc stays under 2 sentences; the structured_steps carry the detail.
PROMPT;
    }

    private function userPrompt(string $goal, array $cfg, DateTimeImmutable $monday, DateTimeImmutable $goalDate, int $weeks, array $constraints, string $locale): string
    {
        $goalLabel = $this->goalLabel($goal, $locale);
        $sport = $cfg['sport'];
        $sessionsPerWeek = (int)($constraints['sessions_override'] ?? 0) ?: $cfg['sessions_per_week'];

        $baseline = $constraints['baseline_km'] ?? 0;
        $hours = $constraints['weekly_hours'] ?? null;
        $injuries = trim($constraints['injuries'] ?? '');
        $longDay = $constraints['long_run_day'] ?? 'Sun';

        $cantTrain = $constraints['cant_train_days'] ?? [];
        $cantTrainLine = empty($cantTrain)
            ? 'none — all 7 days available'
            : 'BLOCKED (do not schedule anything): ' . implode(', ', $cantTrain);

        $intensity = $constraints['intensity_preference'] ?? 'polarized';
        $intensityLine = match ($intensity) {
            'pyramidal' => 'pyramidal — more time at threshold, fewer extremes (60% easy / 30% threshold / 10% VO2)',
            'threshold' => 'threshold-heavy — frequent sustained efforts at lactate threshold',
            default     => 'polarized — 80% easy, 20% hard, minimal threshold',
        };

        $targetTime = trim($constraints['target_time'] ?? '');
        $targetLine = $targetTime !== '' ? "- Target finish: {$targetTime}\n" : '';

        $envLines = [];
        if (in_array($sport, ['run', 'tri'], true)) {
            $envLines[] = '- Run surface preference: ' . ($constraints['surface'] ?? 'mixed');
        }
        if (in_array($sport, ['swim', 'tri'], true)) {
            $envLines[] = '- Pool: ' . ($constraints['pool_length'] ?? '25m');
        }
        if (in_array($sport, ['bike', 'tri'], true)) {
            $envLines[] = '- Bike location: ' . ($constraints['bike_location'] ?? 'mixed');
        }
        $envBlock = $envLines ? "\nEquipment / environment:\n" . implode("\n", $envLines) . "\n" : '';

        $pacesText = isset($constraints['paces'])
            ? PaceCalculator::toPromptText($constraints['paces'])
            : '(no pace data available)';

        $completionText = '';
        if (!empty($constraints['completion'])) {
            $completionText = "\n\nThe athlete previously had a plan in progress. Compliance per week (1.0 = on target, <1.0 = under, >1.0 = over):\n";
            foreach ($constraints['completion'] as $w) {
                $completionText .= sprintf("- Week starting %s: %.0f%% of prescribed volume completed.\n", $w['start'], $w['ratio'] * 100);
            }
            $completionText .= "\nFactor this into the new plan: if recent weeks were under-completed, do not ramp aggressively.";
        }

        return <<<PROMPT
Generate a {$weeks}-week training plan.

Athlete:
- Goal: {$goalLabel} ({$sport})
{$targetLine}- Plan starts: {$monday->format('Y-m-d')} (Monday)
- Goal date: {$goalDate->format('Y-m-d')} (final week is race week)
- Target sessions per week: {$sessionsPerWeek}
- Weekly hours available: {$hours} h
- Recent weekly volume baseline: {$baseline} km
- Preferred long-session day: {$longDay}
- Off days: {$cantTrainLine}
- Injuries / constraints: {$injuries}
- Intensity preference: {$intensityLine}
{$envBlock}
Pace / power targets (use these in workout content):
{$pacesText}
{$completionText}

Produce exactly {$weeks} weeks. Phase distribution: base/build/peak/taper ≈ 40/35/15/10. Deload every 4th week (~80% volume). Last week is race week.
For each day include: type, sport, title, distance_km, duration_min, desc (short why), purpose, rpe, structured_steps. Add fueling only when duration_min > 75.
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
                                        'purpose' => ['type' => 'string'],
                                        'rpe' => ['type' => 'integer'],
                                        'fueling' => ['type' => 'string'],
                                        'structured_steps' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'kind' => ['type' => 'string', 'enum' => ['warmup', 'main', 'interval', 'cooldown']],
                                                    'reps' => ['type' => 'integer'],
                                                    'duration_min' => ['type' => 'number'],
                                                    'distance_km' => ['type' => 'number'],
                                                    'target' => ['type' => 'string'],
                                                    'recovery' => ['type' => 'string'],
                                                    'notes' => ['type' => 'string'],
                                                ],
                                                'required' => ['kind'],
                                            ],
                                        ],
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
                    'purpose' => $d['purpose'] ?? null,
                    'rpe' => isset($d['rpe']) ? max(1, min(10, (int)$d['rpe'])) : null,
                    'fueling' => isset($d['fueling']) && trim((string)$d['fueling']) !== '' ? $d['fueling'] : null,
                    'structured_steps' => $this->normaliseSteps($d['structured_steps'] ?? []),
                ], $w['days'] ?? []),
            ];
        }
        return $out;
    }

    private function normaliseSteps(array $steps): array
    {
        $allowed = ['warmup', 'main', 'interval', 'cooldown'];
        $out = [];
        foreach ($steps as $s) {
            $kind = $s['kind'] ?? 'main';
            if (!in_array($kind, $allowed, true)) $kind = 'main';
            $step = ['kind' => $kind];
            if (isset($s['reps']) && (int)$s['reps'] > 1) $step['reps'] = (int)$s['reps'];
            if (isset($s['duration_min']) && (float)$s['duration_min'] > 0) $step['duration_min'] = round((float)$s['duration_min'], 1);
            if (isset($s['distance_km']) && (float)$s['distance_km'] > 0) $step['distance_km'] = round((float)$s['distance_km'], 2);
            if (isset($s['target']) && trim((string)$s['target']) !== '') $step['target'] = trim((string)$s['target']);
            if ($kind === 'interval' && isset($s['recovery']) && trim((string)$s['recovery']) !== '') $step['recovery'] = trim((string)$s['recovery']);
            if (isset($s['notes']) && trim((string)$s['notes']) !== '') $step['notes'] = trim((string)$s['notes']);
            $out[] = $step;
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
