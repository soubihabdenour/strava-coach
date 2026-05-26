<?php

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/StravaClient.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/Coach.php';
require_once __DIR__ . '/PaceCalculator.php';
require_once __DIR__ . '/PlanGenerator.php';
require_once __DIR__ . '/GeminiClient.php';
require_once __DIR__ . '/AiPlanGenerator.php';
require_once __DIR__ . '/CompletionTracker.php';
require_once __DIR__ . '/CoachAgent.php';

Config::load(__DIR__ . '/../.env');

session_start();
I18n::init();

function strava_client(): StravaClient
{
    $id = Config::get('STRAVA_CLIENT_ID');
    $secret = Config::get('STRAVA_CLIENT_SECRET');
    $redirect = Config::get('STRAVA_REDIRECT_URI', 'http://localhost:8000/callback.php');
    if (!$id || !$secret) {
        http_response_code(500);
        exit('Missing STRAVA_CLIENT_ID / STRAVA_CLIENT_SECRET. Copy .env.example to .env and fill them in.');
    }
    return new StravaClient($id, $secret, $redirect);
}

function gemini_client(): GeminiClient
{
    $apiKey = Config::get('GEMINI_API_KEY');
    if (!$apiKey) {
        throw new RuntimeException('GEMINI_API_KEY not configured. Add it to .env.');
    }
    $model = Config::get('GEMINI_MODEL') ?: 'gemini-2.5-flash';
    return new GeminiClient($apiKey, $model);
}

function current_access_token(): ?string
{
    if (empty($_SESSION['token'])) return null;
    $token = $_SESSION['token'];
    if (($token['expires_at'] ?? 0) < time() + 60) {
        try {
            $refreshed = strava_client()->refreshToken($token['refresh_token']);
            $_SESSION['token'] = array_merge($token, $refreshed);
            return $_SESSION['token']['access_token'];
        } catch (Throwable $e) {
            unset($_SESSION['token'], $_SESSION['athlete']);
            return null;
        }
    }
    return $token['access_token'];
}

function render(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require __DIR__ . '/../views/layout_open.php';
    require __DIR__ . '/../views/' . $view . '.php';
    require __DIR__ . '/../views/layout_close.php';
}

function e(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/**
 * Inline an SVG from public/icons/{name}.svg so it inherits currentColor.
 * Pass extra CSS classes to apply (e.g. icon('run', 'sport-icon active')).
 */
function icon(string $name, string $class = 'icon'): string
{
    static $cache = [];
    if (!isset($cache[$name])) {
        $path = __DIR__ . '/../public/icons/' . basename($name) . '.svg';
        $cache[$name] = is_file($path) ? file_get_contents($path) : '';
    }
    if (!$cache[$name]) return '';
    return preg_replace('/<svg\b/', '<svg class="' . htmlspecialchars($class, ENT_QUOTES) . '"', $cache[$name], 1);
}
