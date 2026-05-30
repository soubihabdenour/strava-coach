<?php

class TokenStore
{
    public function __construct(private PDO $pdo) {}

    /**
     * Upsert the athlete row and their token in one call.
     * Strava's exchangeCode() response includes both; refresh() does not.
     * @return int athlete_id
     */
    public function saveAthleteAndToken(array $athlete, array $token): int
    {
        $athleteId = (int)$athlete['id'];
        $now = time();

        $stmt = $this->pdo->prepare(
            'INSERT INTO athletes (id, firstname, lastname, sex, profile, created_at, updated_at)
             VALUES (:id, :firstname, :lastname, :sex, :profile, :created, :updated)
             ON CONFLICT(id) DO UPDATE SET
               firstname = excluded.firstname,
               lastname  = excluded.lastname,
               sex       = excluded.sex,
               profile   = excluded.profile,
               updated_at = excluded.updated_at'
        );
        $stmt->execute([
            ':id' => $athleteId,
            ':firstname' => $athlete['firstname'] ?? null,
            ':lastname'  => $athlete['lastname'] ?? null,
            ':sex'       => $athlete['sex'] ?? null,
            ':profile'   => $athlete['profile'] ?? null,
            ':created'   => $now,
            ':updated'   => $now,
        ]);

        $this->save($athleteId, $token);
        return $athleteId;
    }

    /**
     * Upsert just the token. Used both after initial code exchange and after refresh.
     * Strava refresh responses omit "scope" — preserve the previous value when null.
     */
    public function save(int $athleteId, array $token): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tokens (athlete_id, access_token, refresh_token, expires_at, scope, updated_at)
             VALUES (:id, :access, :refresh, :exp, :scope, :upd)
             ON CONFLICT(athlete_id) DO UPDATE SET
               access_token  = excluded.access_token,
               refresh_token = excluded.refresh_token,
               expires_at    = excluded.expires_at,
               scope         = COALESCE(excluded.scope, tokens.scope),
               updated_at    = excluded.updated_at'
        );
        $stmt->execute([
            ':id'      => $athleteId,
            ':access'  => $token['access_token'],
            ':refresh' => $token['refresh_token'],
            ':exp'     => (int)$token['expires_at'],
            ':scope'   => $token['scope'] ?? null,
            ':upd'     => time(),
        ]);
    }

    public function get(int $athleteId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT access_token, refresh_token, expires_at, scope FROM tokens WHERE athlete_id = :id'
        );
        $stmt->execute([':id' => $athleteId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function delete(int $athleteId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM tokens WHERE athlete_id = :id');
        $stmt->execute([':id' => $athleteId]);
    }

    public function getAthlete(int $athleteId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, firstname, lastname, sex, profile FROM athletes WHERE id = :id'
        );
        $stmt->execute([':id' => $athleteId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Return the athlete's calendar subscription token, minting one on first call.
     */
    public function ensureCalendarToken(int $athleteId): string
    {
        $stmt = $this->pdo->prepare('SELECT calendar_token FROM athletes WHERE id = :id');
        $stmt->execute([':id' => $athleteId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException("No athlete {$athleteId}");
        }
        $existing = $row['calendar_token'] ?? null;
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }
        return $this->rotateCalendarToken($athleteId);
    }

    /**
     * Replace the athlete's calendar token, invalidating any previously-shared URL.
     */
    public function rotateCalendarToken(int $athleteId): string
    {
        $token = bin2hex(random_bytes(24));
        $stmt = $this->pdo->prepare(
            'UPDATE athletes SET calendar_token = :t, updated_at = :u WHERE id = :id'
        );
        $stmt->execute([':t' => $token, ':u' => time(), ':id' => $athleteId]);
        return $token;
    }

    public function athleteIdByCalendarToken(string $token): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM athletes WHERE calendar_token = :t LIMIT 1');
        $stmt->execute([':t' => $token]);
        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : null;
    }
}
