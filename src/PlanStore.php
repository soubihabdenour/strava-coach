<?php

class PlanStore
{
    public function __construct(private PDO $pdo) {}

    /**
     * Save a new plan as active. Any existing active plan for the athlete is archived.
     * Returns the new plan's row id.
     */
    public function save(int $athleteId, array $plan): int
    {
        $now = time();
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                'UPDATE plans
                    SET is_active = 0,
                        archived_at = COALESCE(archived_at, :now)
                  WHERE athlete_id = :ath AND is_active = 1'
            )->execute([':ath' => $athleteId, ':now' => $now]);

            $stmt = $this->pdo->prepare(
                'INSERT INTO plans
                    (athlete_id, goal, sport, locale, engine,
                     start_date, goal_date, weeks_total, plan_json,
                     is_active, created_at)
                 VALUES
                    (:ath, :goal, :sport, :loc, :eng,
                     :start, :end, :weeks, :json, 1, :now)'
            );
            $stmt->execute([
                ':ath'   => $athleteId,
                ':goal'  => $plan['goal'],
                ':sport' => $plan['sport'] ?? 'run',
                ':loc'   => $plan['locale'] ?? 'en',
                ':eng'   => $plan['engine'] ?? 'ai',
                ':start' => $plan['start_date'],
                ':end'   => $plan['goal_date'],
                ':weeks' => (int)$plan['weeks_total'],
                ':json'  => json_encode($plan, JSON_UNESCAPED_UNICODE),
                ':now'   => $now,
            ]);
            $id = (int)$this->pdo->lastInsertId();
            $this->pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Return the athlete's currently active plan (decoded, with `_id` set), or null.
     */
    public function getActive(int $athleteId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, plan_json FROM plans
              WHERE athlete_id = :ath AND is_active = 1
              ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([':ath' => $athleteId]);
        $row = $stmt->fetch();
        if (!$row) return null;

        $plan = json_decode($row['plan_json'], true);
        if (!is_array($plan)) return null;
        $plan['_id'] = (int)$row['id'];
        return $plan;
    }

    public function archiveActive(int $athleteId): void
    {
        $this->pdo->prepare(
            'UPDATE plans
                SET is_active = 0,
                    archived_at = COALESCE(archived_at, :now)
              WHERE athlete_id = :ath AND is_active = 1'
        )->execute([':ath' => $athleteId, ':now' => time()]);
    }
}
