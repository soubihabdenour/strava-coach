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

$plan = plan_store()->getActive($athleteId);
$actionsMap = $plan ? plan_actions()->forPlan((int)$plan['_id']) : [];
$todayCtx = $plan ? PlanProgress::todayContext($plan, $actionsMap) : null;
$todayStatus = null;
if ($todayCtx && $todayCtx['state'] === 'active') {
    $todayKey = $todayCtx['week_index'] . ':' . $todayCtx['today_dow'];
    $todayAction = $actionsMap[$todayKey] ?? null;
    $todayStatus = PlanProgress::matchActivityStatus(
        $todayCtx['day'], $todayCtx['today'], $activities, $todayAction
    );
}
$weekCtx = $plan ? PlanProgress::weekContext($plan, $activities, $actionsMap) : null;

render('dashboard', [
    'athlete' => $athlete,
    'summary' => $summary,
    'tips' => $tips,
    'plan' => $plan,
    'todayCtx' => $todayCtx,
    'todayStatus' => $todayStatus,
    'weekCtx' => $weekCtx,
]);
