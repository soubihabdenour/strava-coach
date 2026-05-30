<?php

class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dir = __DIR__ . '/../storage';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (!is_dir($dir) || !is_writable($dir)) {
                $resolved = realpath($dir) ?: $dir;
                throw new RuntimeException(
                    "storage/ is not writable (path: {$resolved}). " .
                    "Create it and give the web user write access — e.g. `mkdir -p storage && chmod 775 storage && chown :www-data storage` " .
                    "(replace www-data with the correct group for your web server)."
                );
            }
            $path = $dir . '/coach.db';
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

        // Idempotent column add for the calendar subscription token.
        $cols = $pdo->query("PRAGMA table_info(athletes)")->fetchAll();
        $hasCalToken = false;
        foreach ($cols as $c) if (($c['name'] ?? '') === 'calendar_token') { $hasCalToken = true; break; }
        if (!$hasCalToken) {
            $pdo->exec("ALTER TABLE athletes ADD COLUMN calendar_token TEXT");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_athletes_calendar_token ON athletes(calendar_token)");
        }

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

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS plans (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                athlete_id INTEGER NOT NULL REFERENCES athletes(id) ON DELETE CASCADE,
                goal TEXT NOT NULL,
                sport TEXT NOT NULL,
                locale TEXT,
                engine TEXT,
                start_date TEXT NOT NULL,
                goal_date TEXT NOT NULL,
                weeks_total INTEGER NOT NULL,
                plan_json TEXT NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 0,
                created_at INTEGER NOT NULL,
                archived_at INTEGER
            )
        ");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_plans_athlete_active ON plans(athlete_id, is_active)");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS plan_day_actions (
                plan_id INTEGER NOT NULL REFERENCES plans(id) ON DELETE CASCADE,
                week_index INTEGER NOT NULL,
                day TEXT NOT NULL,
                status TEXT,
                swap_with TEXT,
                note TEXT,
                updated_at INTEGER NOT NULL,
                PRIMARY KEY (plan_id, week_index, day)
            )
        ");
    }
}
