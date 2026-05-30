<?php
require_once __DIR__ . '/../src/bootstrap.php';

if (!current_access_token()) {
    header('Location: index.php');
    exit;
}

$athleteId = (int)$_SESSION['athlete_id'];
$plan = plan_store()->getActive($athleteId);

if (!$plan) {
    header('Location: plan.php');
    exit;
}

$athleteName = $_SESSION['athlete']['firstname'] ?? 'Athlete';
$ics = IcsExporter::fromPlan($plan, $athleteName, $athleteId);

$slug = preg_replace('/[^a-zA-Z0-9._-]/', '-', ($plan['goal'] ?? 'plan') . '-' . ($plan['start_date'] ?? date('Y-m-d')));
$filename = 'coach-plan-' . $slug . '.ics';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');
echo $ics;
