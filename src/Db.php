<?php

class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $path = __DIR__ . '/../storage/coach.db';
            $pdo = new PDO('sqlite:' . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA foreign_keys = ON');
            self::migrate($pdo);
            self::$pdo = $pdo;
        }
        return self::$pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS athletes (
                id INTEGER PRIMARY KEY,
                firstname TEXT,
                lastname TEXT,
                sex TEXT,
                profile TEXT,
                created_at INTEGER NOT NULL,
                updated_at INTEGER NOT NULL
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tokens (
                athlete_id INTEGER PRIMARY KEY REFERENCES athletes(id) ON DELETE CASCADE,
                access_token TEXT NOT NULL,
                refresh_token TEXT NOT NULL,
                expires_at INTEGER NOT NULL,
                scope TEXT,
                updated_at INTEGER NOT NULL
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS activities (
                id INTEGER PRIMARY KEY,
                athlete_id INTEGER NOT NULL REFERENCES athletes(id) ON DELETE CASCADE,
                sport_type TEXT,
                start_date_local TEXT,
                distance_m REAL,
                moving_time_s INTEGER,
                elapsed_time_s INTEGER,
                elevation_gain_m REAL,
                avg_hr REAL,
                max_hr REAL,
                avg_watts REAL,
                weighted_avg_watts REAL,
                kilojoules REAL,
                avg_cadence REAL,
                suffer_score REAL,
                has_heartrate INTEGER,
                raw_json TEXT,
                fetched_at INTEGER NOT NULL
            )
        ");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_activities_athlete_date ON activities(athlete_id, start_date_local)");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sync_state (
                athlete_id INTEGER PRIMARY KEY REFERENCES athletes(id) ON DELETE CASCADE,
                last_synced_at INTEGER,
                last_after_cursor INTEGER
            )
        ");
    }
}
