<?php
require_once __DIR__ . '/../src/bootstrap.php';

$token = current_access_token();
if (!$token) {
    header('Location: index.php');
    exit;
}

$client = strava_client();

try {
    $athlete = $_SESSION['athlete'] ?? $client->getAthlete($token);
    $activities = $client->fetchRecentActivities($token, 84);
} catch (Throwable $e) {
    http_response_code(502);
    exit('Strava API error: ' . e($e->getMessage()));
}

$coach = new Coach($activities);
$summary = $coach->summary();
$tips = $coach->recommendations();

render('dashboard', [
    'athlete' => $athlete,
    'summary' => $summary,
    'tips' => $tips,
]);
