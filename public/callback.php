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

$_SESSION['token'] = $token;
$_SESSION['athlete'] = $token['athlete'] ?? null;

header('Location: dashboard.php');
