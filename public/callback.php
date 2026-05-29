<?php
require_once __DIR__ . '/../src/bootstrap.php';

$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;

if (!$code || !$state || !hash_equals($_SESSION['oauth_state'] ?? '', $state)) {
    http_response_code(400);
    exit('Invalid OAuth response.');
}
unset($_SESSION['oauth_state']);

try {
    $token = strava_client()->exchangeCode($code);
} catch (Throwable $e) {
    http_response_code(502);
    exit('Could not exchange code: ' . e($e->getMessage()));
}

$athlete = $token['athlete'] ?? null;
if (!is_array($athlete) || empty($athlete['id'])) {
    http_response_code(502);
    exit('Strava did not return athlete info with the token.');
}

try {
    $athleteId = token_store()->saveAthleteAndToken($athlete, $token);
} catch (Throwable $e) {
    http_response_code(500);
    exit('Could not persist token: ' . e($e->getMessage()));
}

$_SESSION['athlete_id'] = $athleteId;
$_SESSION['athlete'] = $athlete;

header('Location: dashboard.php');
