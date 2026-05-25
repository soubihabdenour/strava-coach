<?php

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/StravaClient.php';
require_once __DIR__ . '/Coach.php';
require_once __DIR__ . '/PlanGenerator.php';

Config::load(__DIR__ . '/../.env');

session_start();

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
