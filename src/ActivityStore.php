<?php

class ActivityStore
{
    public function __construct(private PDO $pdo) {}

    /**
     * Activities in the last $days, returned as decoded raw Strava JSON
     * so existing Coach / CompletionTracker / PaceCalculator code keeps working.
     */
    public function getRecent(int $athleteId, int $days): array
    {
        $cutoff = (new DateTimeImmutable("-{$days} days"))->format('Y-m-d');
        $stmt = $this->pdo->prepare(
            'SELECT raw_json FROM activities
             WHERE athlete_id = :ath AND start_date_local >= :cut
             ORDER BY start_date_local ASC'
        );
        $stmt->execute([':ath' => $athleteId, ':cut' => $cutoff]);
        $out = [];
        while ($row = $stmt->fetch()) {
            $a = json_decode($row['raw_json'], true);
            if (is_array($a)) $out[] = $a;
        }
        return $out;
    }

    /**
     * Pull from Strava only if our cache hasn't been refreshed in $maxAgeMin minutes.
     * Safe to call on every page load.
     */
    public function syncIfStale(int $athleteId, int $maxAgeMin = 15): void
    {
        $state = $this->getSyncState($athleteId);
        if ($state && (time() - (int)$state['last_synced_at']) < $maxAgeMin * 60) {
            return;
        }
        $this->sync($athleteId);
    }

    /**
     * Incremental sync. Fetches from (max stored start_date - 1 day),
     * or last 12 weeks on first run. Upserts by activity id.
     */
    public function sync(int $athleteId): int
    {
        $maxDate = $this->maxStartDate($athleteId);
        $after = $maxDate
            ? (new DateTimeImmutable($maxDate))->modify('-1 day')->getTimestamp()
            : time() - 84 * 86400;

        $client = strava_client();
        $store = $this;
        $written = strava_with_refresh($athleteId, function (string $token) use ($client, $store, $athleteId, $after) {
            $n = 0;
            $page = 1;
            while (true) {
                $batch = $client->getActivities($token, 100, $page, $after);
                if (empty($batch)) break;
                foreach ($batch as $a) {
                    $store->upsert($athleteId, $a);
                    $n++;
                }
                if (count($batch) < 100) break;
                $page++;
                if ($page > 5) break;
            }
            return $n;
        });

        $this->saveSyncState($athleteId, time(), $after);
        return $written;
    }

    public function upsert(int $athleteId, array $a): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO activities (
                id, athlete_id, sport_type, start_date_local,
                distance_m, moving_time_s, elapsed_time_s, elevation_gain_m,
                avg_hr, max_hr, avg_watts, weighted_avg_watts,
                kilojoules, avg_cadence, suffer_score, has_heartrate,
                raw_json, fetched_at
            ) VALUES (
                :id, :ath, :sport, :date,
                :dist, :mov, :elap, :elev,
                :ahr, :mhr, :aw, :waw,
                :kj, :cad, :suff, :hhr,
                :raw, :fetched
            )
            ON CONFLICT(id) DO UPDATE SET
                sport_type         = excluded.sport_type,
                start_date_local   = excluded.start_date_local,
                distance_m         = excluded.distance_m,
                moving_time_s      = excluded.moving_time_s,
                elapsed_time_s     = excluded.elapsed_time_s,
                elevation_gain_m   = excluded.elevation_gain_m,
                avg_hr             = excluded.avg_hr,
                max_hr             = excluded.max_hr,
                avg_watts          = excluded.avg_watts,
                weighted_avg_watts = excluded.weighted_avg_watts,
                kilojoules         = excluded.kilojoules,
                avg_cadence        = excluded.avg_cadence,
                suffer_score       = excluded.suffer_score,
                has_heartrate      = excluded.has_heartrate,
                raw_json           = excluded.raw_json,
                fetched_at         = excluded.fetched_at'
        );
        $stmt->execute([
            ':id'      => (int)$a['id'],
            ':ath'     => $athleteId,
            ':sport'   => $a['sport_type'] ?? $a['type'] ?? null,
            ':date'    => $a['start_date_local'] ?? null,
            ':dist'    => $a['distance'] ?? null,
            ':mov'     => $a['moving_time'] ?? null,
            ':elap'    => $a['elapsed_time'] ?? null,
            ':elev'    => $a['total_elevation_gain'] ?? null,
            ':ahr'     => $a['average_heartrate'] ?? null,
            ':mhr'     => $a['max_heartrate'] ?? null,
            ':aw'      => $a['average_watts'] ?? null,
            ':waw'     => $a['weighted_average_watts'] ?? null,
            ':kj'      => $a['kilojoules'] ?? null,
            ':cad'     => $a['average_cadence'] ?? null,
            ':suff'    => $a['suffer_score'] ?? null,
            ':hhr'     => isset($a['has_heartrate']) ? (int)$a['has_heartrate'] : null,
            ':raw'     => json_encode($a, JSON_UNESCAPED_UNICODE),
            ':fetched' => time(),
        ]);
    }

    public function maxStartDate(int $athleteId): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT MAX(start_date_local) AS m FROM activities WHERE athlete_id = :ath'
        );
        $stmt->execute([':ath' => $athleteId]);
        $row = $stmt->fetch();
        return $row && $row['m'] ? $row['m'] : null;
    }

    private function getSyncState(int $athleteId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT last_synced_at, last_after_cursor FROM sync_state WHERE athlete_id = :ath'
        );
        $stmt->execute([':ath' => $athleteId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function saveSyncState(int $athleteId, int $syncedAt, int $afterCursor): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sync_state (athlete_id, last_synced_at, last_after_cursor)
             VALUES (:ath, :ts, :cur)
             ON CONFLICT(athlete_id) DO UPDATE SET
               last_synced_at    = excluded.last_synced_at,
               last_after_cursor = excluded.last_after_cursor'
        );
        $stmt->execute([':ath' => $athleteId, ':ts' => $syncedAt, ':cur' => $afterCursor]);
    }
}
