<?php
require_once __DIR__ . '/../src/bootstrap.php';

$token = current_access_token();
if (!$token) {
    header('Location: index.php');
    exit;
}

if (isset($_GET['reset'])) {
    unset($_SESSION['plan']);
    header('Location: plan.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    $goal = $_POST['goal'] ?? '10k';
    $goalDate = $_POST['goal_date'] ?? '';
    if (!isset(PlanGenerator::GOALS[$goal]) || !$goalDate) {
        http_response_code(400);
        exit('Invalid input.');
    }

    try {
        $start = new DateTimeImmutable('today');
        $end = new DateTimeImmutable($goalDate);
        if ($end <= $start) {
            http_response_code(400);
            exit('Goal date must be in the future.');
        }

        $activities = strava_client()->fetchRecentActivities($token, 28);
        $coach = new Coach($activities);
        $baseline = $coach->summary()['current_block']['distance_km'] / 4;

        $plan = (new PlanGenerator())->generate($goal, $start, $end, $baseline);
        $_SESSION['plan'] = $plan;
    } catch (Throwable $e) {
        http_response_code(500);
        exit('Could not generate plan: ' . e($e->getMessage()));
    }

    header('Location: plan.php');
    exit;
}

if (!empty($_SESSION['plan'])) {
    render('plan', ['plan' => $_SESSION['plan']]);
    exit;
}

try {
    $activities = strava_client()->fetchRecentActivities($token, 28);
    $coach = new Coach($activities);
    $baseline = $coach->summary()['current_block']['distance_km'] / 4;
} catch (Throwable $e) {
    $baseline = 0;
}

render('plan_form', [
    'goals' => PlanGenerator::GOALS,
    'baselineKm' => $baseline,
    'defaultGoalDate' => (new DateTimeImmutable('+10 weeks'))->format('Y-m-d'),
]);
