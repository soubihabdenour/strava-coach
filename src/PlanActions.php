<?php

class PlanActions
{
    public const VALID_DAYS = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    public const VALID_STATUS = ['done', 'skipped'];
    public const OVERRIDE_FIELDS = ['title', 'distance_km', 'duration_min', 'desc'];

    public function __construct(private PDO $pdo) {}

    /**
     * Return all actions for a plan keyed by "<week_index>:<day>" for fast lookup.
     * @return array<string, array{status: ?string, swap_with: ?string, note: ?string, override: ?array}>
     */
    public function forPlan(int $planId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT week_index, day, status, swap_with, note, override_json
               FROM plan_day_actions WHERE plan_id = :p'
        );
        $stmt->execute([':p' => $planId]);
        $out = [];
        while ($row = $stmt->fetch()) {
            $key = $row['week_index'] . ':' . $row['day'];
            $override = null;
            if (!empty($row['override_json'])) {
                $decoded = json_decode($row['override_json'], true);
                if (is_array($decoded) && $decoded !== []) $override = $decoded;
            }
            $out[$key] = [
                'status' => $row['status'],
                'swap_with' => $row['swap_with'],
                'note' => $row['note'],
                'override' => $override,
            ];
        }
        return $out;
    }

    /**
     * Override per-day fields (title, distance_km, duration_min, desc). Pass an empty
     * array to clear. Unknown keys are dropped; values are normalised to safe types.
     */
    public function setOverride(int $planId, int $weekIdx, string $day, array $override): void
    {
        if (!in_array($day, self::VALID_DAYS, true)) return;

        $clean = [];
        foreach (self::OVERRIDE_FIELDS as $field) {
            if (!array_key_exists($field, $override)) continue;
            $v = $override[$field];
            switch ($field) {
                case 'title':
                case 'desc':
                    $v = trim((string)$v);
                    if ($v !== '') $clean[$field] = $v;
                    break;
                case 'distance_km':
                    if ($v !== '' && $v !== null) {
                        $clean[$field] = round(min(500, max(0, (float)$v)), 2);
                    }
                    break;
                case 'duration_min':
                    if ($v !== '' && $v !== null) {
                        $clean[$field] = min(1440, max(0, (int)$v));
                    }
                    break;
            }
        }

        $json = $clean ? json_encode($clean, JSON_UNESCAPED_UNICODE) : null;
        $this->upsert($planId, $weekIdx, $day, ['override_json' => $json]);
        $this->pruneEmptyRow($planId, $weekIdx, $day);
    }

    public function clearOverride(int $planId, int $weekIdx, string $day): void
    {
        if (!in_array($day, self::VALID_DAYS, true)) return;
        $this->upsert($planId, $weekIdx, $day, ['override_json' => null]);
        $this->pruneEmptyRow($planId, $weekIdx, $day);
    }

    public function setStatus(int $planId, int $weekIdx, string $day, ?string $status): void
    {
        if (!in_array($day, self::VALID_DAYS, true)) return;
        if ($status !== null && !in_array($status, self::VALID_STATUS, true)) return;

        $this->upsert($planId, $weekIdx, $day, ['status' => $status]);
    }

    public function clearStatus(int $planId, int $weekIdx, string $day): void
    {
        if (!in_array($day, self::VALID_DAYS, true)) return;
        $this->upsert($planId, $weekIdx, $day, ['status' => null]);
        $this->pruneEmptyRow($planId, $weekIdx, $day);
    }

    /**
     * Swap two days within a week. Idempotent: setting the same pair twice toggles it off.
     * Clears any prior swap involving either day first.
     */
    public function setSwap(int $planId, int $weekIdx, string $dayA, string $dayB): void
    {
        if ($dayA === $dayB) return;
        if (!in_array($dayA, self::VALID_DAYS, true)) return;
        if (!in_array($dayB, self::VALID_DAYS, true)) return;

        // Toggle off if the exact swap already exists
        $stmt = $this->pdo->prepare(
            'SELECT swap_with FROM plan_day_actions
              WHERE plan_id = :p AND week_index = :w AND day = :d'
        );
        $stmt->execute([':p' => $planId, ':w' => $weekIdx, ':d' => $dayA]);
        if ($stmt->fetchColumn() === $dayB) {
            $this->clearSwap($planId, $weekIdx, $dayA);
            return;
        }

        $this->pdo->beginTransaction();
        try {
            // Wipe any prior swap that touched either day, on either side
            $clear = $this->pdo->prepare(
                'UPDATE plan_day_actions
                    SET swap_with = NULL, updated_at = :t
                  WHERE plan_id = :p AND week_index = :w
                    AND (day IN (:a, :b) OR swap_with IN (:a, :b))'
            );
            $clear->execute([':p' => $planId, ':w' => $weekIdx, ':a' => $dayA, ':b' => $dayB, ':t' => time()]);

            $this->upsert($planId, $weekIdx, $dayA, ['swap_with' => $dayB]);
            $this->upsert($planId, $weekIdx, $dayB, ['swap_with' => $dayA]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function clearSwap(int $planId, int $weekIdx, string $day): void
    {
        if (!in_array($day, self::VALID_DAYS, true)) return;

        $stmt = $this->pdo->prepare(
            'SELECT swap_with FROM plan_day_actions
              WHERE plan_id = :p AND week_index = :w AND day = :d'
        );
        $stmt->execute([':p' => $planId, ':w' => $weekIdx, ':d' => $day]);
        $partner = $stmt->fetchColumn();

        $this->upsert($planId, $weekIdx, $day, ['swap_with' => null]);
        $this->pruneEmptyRow($planId, $weekIdx, $day);
        if (is_string($partner) && $partner !== '') {
            $this->upsert($planId, $weekIdx, $partner, ['swap_with' => null]);
            $this->pruneEmptyRow($planId, $weekIdx, $partner);
        }
    }

    /**
     * Set whichever field is provided; preserve all other columns.
     */
    private function upsert(int $planId, int $weekIdx, string $day, array $changes): void
    {
        $now = time();
        $hasStatus   = array_key_exists('status', $changes);
        $hasSwap     = array_key_exists('swap_with', $changes);
        $hasNote     = array_key_exists('note', $changes);
        $hasOverride = array_key_exists('override_json', $changes);

        $stmt = $this->pdo->prepare(
            'INSERT INTO plan_day_actions
                (plan_id, week_index, day, status, swap_with, note, override_json, updated_at)
             VALUES
                (:p, :w, :d, :s, :sw, :n, :ov, :t)
             ON CONFLICT(plan_id, week_index, day) DO UPDATE SET
                status        = ' . ($hasStatus   ? 'excluded.status'        : 'plan_day_actions.status')        . ',
                swap_with     = ' . ($hasSwap     ? 'excluded.swap_with'     : 'plan_day_actions.swap_with')     . ',
                note          = ' . ($hasNote     ? 'excluded.note'          : 'plan_day_actions.note')          . ',
                override_json = ' . ($hasOverride ? 'excluded.override_json' : 'plan_day_actions.override_json') . ',
                updated_at    = excluded.updated_at'
        );
        $stmt->execute([
            ':p'  => $planId,
            ':w'  => $weekIdx,
            ':d'  => $day,
            ':s'  => $changes['status']        ?? null,
            ':sw' => $changes['swap_with']     ?? null,
            ':n'  => $changes['note']          ?? null,
            ':ov' => $changes['override_json'] ?? null,
            ':t'  => $now,
        ]);
    }

    /**
     * Delete the row if all action fields are now empty (avoid leaving null tombstones).
     */
    private function pruneEmptyRow(int $planId, int $weekIdx, string $day): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM plan_day_actions
              WHERE plan_id = :p AND week_index = :w AND day = :d
                AND status IS NULL
                AND swap_with IS NULL
                AND (note IS NULL OR note = "")
                AND override_json IS NULL'
        );
        $stmt->execute([':p' => $planId, ':w' => $weekIdx, ':d' => $day]);
    }
}
