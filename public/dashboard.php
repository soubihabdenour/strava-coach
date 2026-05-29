<?php
require_once __DIR__ . '/../src/bootstrap.php';

if (!current_access_token()) {
    header('Location: index.php');
    exit;
}

$athleteId = (int)$_SESSION['athlete_id'];
$athlete = $_SESSION['athlete'] ?? token_store()->getAthlete($athleteId);

$store = activity_store();
try {
    $store->syncIfStale($athleteId);
} catch (Throwable $e) {
    error_log('Strava sync failed: ' . $e->getMessage());
}
$activities = $store->getRecent($athleteId, 84);

$coach = new Coach($activities);
$summary = $coach->summary();
$tips = $coach->recommendations();

render('dashboard', [
    'athlete' => $athlete,
    'summary' => $summary,
    'tips' => $tips,
]);
