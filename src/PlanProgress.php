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
     * If $actions contains a swap for today's slot, the returned `day` reflects the
     * swapped workout and `swapped_from` carries the original day code.
     *
     * Possible shapes:
     *   ['state' => 'active', 'today' => 'YYYY-MM-DD', 'today_dow' => 'Wed',
     *    'week' => {...}, 'week_index' => 7, 'weeks_total' => 12,
     *    'day' => {...}, 'swapped_from' => null|'Thu', 'days_to_goal' => 63]
     *   ['state' => 'pre_start', 'starts' => 'YYYY-MM-DD']
     *   ['state' => 'ended',     'ended'  => 'YYYY-MM-DD']
     *   ['state' => 'gap',       'today'  => 'YYYY-MM-DD']
     */
    public static function todayContext(array $plan, array $actions = [], ?DateTimeImmutable $today = null): array
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

        // Apply swap: if today's slot is swapped with another day, render the partner's planned workout here.
        $swappedFrom = null;
        $todayAction = $actions[$weekIndex . ':' . $todayDow] ?? null;
        $swapPartner = $todayAction['swap_with'] ?? null;
        if ($swapPartner) {
            foreach ($weekData['days'] as $d) {
                if (($d['day'] ?? '') === $swapPartner) {
                    $dayData = $d;
                    $dayData['day'] = $todayDow; // re-label for the slot
                    $swappedFrom = $swapPartner;
                    break;
                }
            }
        }

        // Apply per-day override (title/distance/duration/desc edits) on top of swap.
        $override = $todayAction['override'] ?? null;
        if ($override) {
            $dayData = array_merge($dayData, $override);
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
            'swapped_from' => $swappedFrom,
            'edited'       => !empty($override),
            'days_to_goal' => $daysToGoal,
        ];
    }

    /**
     * Return the current week's 7 days enriched with status, swap info, and activity match.
     * Returns null if today is outside the plan's active range.
     */
    public static function weekContext(array $plan, array $activities = [], array $actions = [], ?DateTimeImmutable $today = null): ?array
    {
        $today ??= new DateTimeImmutable('today');
        $todayStr = $today->format('Y-m-d');
        if ($todayStr < ($plan['start_date'] ?? '') || $todayStr > ($plan['goal_date'] ?? '')) {
            return null;
        }

        $todayMonday = $today->modify('monday this week')->format('Y-m-d');
        $todayDow = self::DOW_ORDER[(int)$today->format('N') - 1];

        $weekData = null; $weekIndex = null;
        foreach ($plan['weeks'] ?? [] as $i => $w) {
            if (($w['start'] ?? '') !== $todayMonday) continue;
            $weekData = $w;
            $weekIndex = (int)($w['index'] ?? ($i + 1));
            break;
        }
        if (!$weekData) return null;

        $monday = new DateTimeImmutable($weekData['start']);
        $daysByCode = [];
        foreach ($weekData['days'] ?? [] as $d) {
            $daysByCode[$d['day'] ?? ''] = $d;
        }

        $out = [];
        foreach (self::DOW_ORDER as $offset => $dow) {
            $date = $monday->modify("+{$offset} days")->format('Y-m-d');
            $action = $actions[$weekIndex . ':' . $dow] ?? null;
            $plannedDay = $daysByCode[$dow] ?? null;
            $swappedFrom = null;

            if ($action && !empty($action['swap_with']) && isset($daysByCode[$action['swap_with']])) {
                $plannedDay = $daysByCode[$action['swap_with']];
                $plannedDay['day'] = $dow;
                $swappedFrom = $action['swap_with'];
            }

            if ($plannedDay && !empty($action['override'])) {
                $plannedDay = array_merge($plannedDay, $action['override']);
            }

            $match = $plannedDay
                ? self::matchActivityStatus($plannedDay, $date, $activities, $action)
                : ['status' => 'pending', 'activity' => null];

            $out[] = [
                'date' => $date,
                'day' => $dow,
                'plan' => $plannedDay,
                'swapped_from' => $swappedFrom,
                'edited' => !empty($action['override']),
                'action' => $action,
                'match' => $match,
                'is_today' => $dow === $todayDow,
                'is_past' => $date < $todayStr,
                'is_future' => $date > $todayStr,
            ];
        }

        return [
            'week_index' => $weekIndex,
            'weeks_total' => (int)($plan['weeks_total'] ?? count($plan['weeks'] ?? [])),
            'phase' => $weekData['phase'] ?? null,
            'theme' => $weekData['theme'] ?? '',
            'days' => $out,
        ];
    }

    /**
     * Determine the display status for a scheduled day:
     *   'manual_done'    — user marked it done
     *   'manual_skipped' — user marked it skipped
     *   'auto_matched'   — Strava activity matches sport + date
     *   'pending'        — none of the above
     *
     * Manual actions always win over auto-matching.
     */
    public static function matchActivityStatus(array $day, string $date, array $activities, ?array $action = null): array
    {
        $status = $action['status'] ?? null;
        if ($status === 'done') {
            return ['status' => 'manual_done', 'activity' => null];
        }
        if ($status === 'skipped') {
            return ['status' => 'manual_skipped', 'activity' => null];
        }
        $match = self::matchActivity($day, $date, $activities);
        if ($match) {
            return ['status' => 'auto_matched', 'activity' => $match];
        }
        return ['status' => 'pending', 'activity' => null];
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
