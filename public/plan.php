<?php
require_once __DIR__ . '/../src/bootstrap.php';

if (!current_access_token()) {
    header('Location: index.php');
    exit;
}
$athleteId = (int)$_SESSION['athlete_id'];
$store = activity_store();
$plans = plan_store();
try { $store->syncIfStale($athleteId); } catch (Throwable $e) { error_log('Strava sync failed: ' . $e->getMessage()); }

if (isset($_GET['reset'])) {
    $plans->archiveActive($athleteId);
    header('Location: plan.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'rotate_calendar_token') {
    token_store()->rotateCalendarToken($athleteId);
    header('Location: plan.php');
    exit;
}

// Per-day actions: mark done / skipped / clear / swap.
// Validate against the *active* plan to prevent writes to archived plans via stale forms.
$dayAction = $_POST['day_action'] ?? null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && in_array($dayAction, ['mark_done','mark_skipped','clear_status','swap','clear_swap','set_override','clear_override'], true)) {
    $active = $plans->getActive($athleteId);
    if ($active) {
        $planId = (int)$active['_id'];
        $weekIdx = (int)($_POST['week_index'] ?? 0);
        $day = (string)($_POST['day'] ?? '');
        $actions = plan_actions();

        switch ($dayAction) {
            case 'mark_done':
                $actions->setStatus($planId, $weekIdx, $day, 'done');
                break;
            case 'mark_skipped':
                $actions->setStatus($planId, $weekIdx, $day, 'skipped');
                break;
            case 'clear_status':
                $actions->clearStatus($planId, $weekIdx, $day);
                break;
            case 'swap':
                $other = (string)($_POST['swap_with'] ?? '');
                $actions->setSwap($planId, $weekIdx, $day, $other);
                break;
            case 'clear_swap':
                $actions->clearSwap($planId, $weekIdx, $day);
                break;
            case 'set_override':
                $actions->setOverride($planId, $weekIdx, $day, [
                    'title'        => $_POST['override_title'] ?? null,
                    'distance_km'  => $_POST['override_distance_km'] ?? null,
                    'duration_min' => $_POST['override_duration_min'] ?? null,
                    'desc'         => $_POST['override_desc'] ?? null,
                ]);
                break;
            case 'clear_override':
                $actions->clearOverride($planId, $weekIdx, $day);
                break;
        }
    }
    $redirect = $_POST['return_to'] ?? 'plan.php';
    if (!in_array($redirect, ['plan.php', 'dashboard.php'], true)) $redirect = 'plan.php';
    header('Location: ' . $redirect);
    exit;
}

$apiKey = Config::get('GEMINI_API_KEY');
$aiAvailable = !empty($apiKey);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    $goal = $_POST['goal'] ?? '10k';
    $goalDate = $_POST['goal_date'] ?? '';
    $weeklyHours = (int)($_POST['weekly_hours'] ?? 8);
    $longRunDay = $_POST['long_run_day'] ?? 'Sun';
    $injuries = trim($_POST['injuries'] ?? '');
    $useAi = !empty($_POST['use_ai']) && $aiAvailable;

    $cantTrainDays = array_values(array_intersect(
        ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
        (array)($_POST['cant_train_days'] ?? [])
    ));
    $sessionsOverride = (int)($_POST['sessions_override'] ?? 0) ?: null;
    $targetTime = trim((string)($_POST['target_time'] ?? ''));
    $intensityPref = in_array($_POST['intensity_preference'] ?? '', ['polarized','pyramidal','threshold'], true)
        ? $_POST['intensity_preference'] : 'polarized';
    $surface = in_array($_POST['surface'] ?? '', ['road','trail','track','treadmill','mixed'], true)
        ? $_POST['surface'] : 'mixed';
    $poolLength = in_array($_POST['pool_length'] ?? '', ['25m','50m','25y','ow'], true)
        ? $_POST['pool_length'] : '25m';
    $bikeLocation = in_array($_POST['bike_location'] ?? '', ['outdoor','indoor','mixed'], true)
        ? $_POST['bike_location'] : 'mixed';
    $swimDays = array_values(array_intersect(
        ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
        (array)($_POST['swim_days'] ?? [])
    ));

    if (!isset(PlanGenerator::GOALS[$goal]) || !$goalDate) {
        http_response_code(400);
        exit('Invalid input.');
    }
    if (PlanGenerator::GOALS[$goal]['ai_only'] && !$useAi) {
        http_response_code(400);
        exit('This goal requires AI generation. Add GEMINI_API_KEY to .env or pick a run goal.');
    }

    try {
        $start = new DateTimeImmutable('today');
        $end = new DateTimeImmutable($goalDate);
        if ($end <= $start) {
            http_response_code(400);
            exit('Goal date must be in the future.');
        }

        $activities = $store->getRecent($athleteId, 28);
        $coach = new Coach($activities);
        $baseline = $coach->summary()['current_block']['distance_km'] / 4;
        $paces = PaceCalculator::compute($activities);

        if ($useAi) {
            $plan = (new AiPlanGenerator(gemini_client()))->generate($goal, $start, $end, [
                'weekly_hours' => $weeklyHours,
                'long_run_day' => $longRunDay,
                'injuries' => $injuries ?: 'none reported',
                'baseline_km' => $baseline,
                'paces' => $paces,
                'cant_train_days' => $cantTrainDays,
                'sessions_override' => $sessionsOverride,
                'target_time' => $targetTime,
                'intensity_preference' => $intensityPref,
                'surface' => $surface,
                'pool_length' => $poolLength,
                'bike_location' => $bikeLocation,
                'swim_days' => $swimDays,
            ], I18n::locale());
        } else {
            $plan = (new PlanGenerator())->generate($goal, $start, $end, $baseline);
            $plan['engine'] = 'rule';
            $plan['sport'] = PlanGenerator::GOALS[$goal]['sport'] ?? 'run';
            $plan['paces'] = $paces;
            $plan['weekly_hours'] = $weeklyHours;
        }
        $plans->save($athleteId, $plan);
    } catch (Throwable $e) {
        http_response_code(500);
        exit('Could not generate plan: ' . e($e->getMessage()));
    }

    header('Location: plan.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'regenerate' && ($plan = $plans->getActive($athleteId))) {
    try {
        $client = gemini_client();
        $activities = $store->getRecent($athleteId, 56);
        $perWeek = CompletionTracker::perWeek($plan, $activities);
        $completion = CompletionTracker::forPrompt($perWeek);

        $baseline = (new Coach($activities))->summary()['current_block']['distance_km'] / 4;
        $paces = PaceCalculator::compute($activities);

        $newPlan = (new AiPlanGenerator($client))->generate(
            $plan['goal'],
            new DateTimeImmutable('today'),
            new DateTimeImmutable($plan['goal_date']),
            [
                'weekly_hours' => $plan['weekly_hours'] ?? 8,
                'long_run_day' => $plan['long_run_day'] ?? 'Sun',
                'injuries' => $plan['injuries'] ?: 'none reported',
                'baseline_km' => $baseline,
                'paces' => $paces,
                'completion' => $completion,
                'cant_train_days' => $plan['cant_train_days'] ?? [],
                'sessions_override' => $plan['sessions_override'] ?? null,
                'target_time' => $plan['target_time'] ?? '',
                'intensity_preference' => $plan['intensity_preference'] ?? 'polarized',
                'surface' => $plan['surface'] ?? 'mixed',
                'pool_length' => $plan['pool_length'] ?? '25m',
                'bike_location' => $plan['bike_location'] ?? 'mixed',
                'swim_days' => $plan['swim_days'] ?? [],
            ],
            I18n::locale()
        );
        $plans->save($athleteId, $newPlan);
    } catch (Throwable $e) {
        http_response_code(500);
        exit('Could not regenerate plan: ' . e($e->getMessage()));
    }
    header('Location: plan.php');
    exit;
}

if ($plan = $plans->getActive($athleteId)) {
    $completion = null;
    $activities = [];
    try {
        $activities = $store->getRecent($athleteId, 56);
        $completion = CompletionTracker::perWeek($plan, $activities);
    } catch (Throwable) {
    }
    $calendarToken = token_store()->ensureCalendarToken($athleteId);
    $feedUrl = sprintf(
        '%s://%s/plan-feed.php?t=%s',
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http',
        $_SERVER['HTTP_HOST'] ?? 'localhost',
        $calendarToken
    );
    $actionsMap = plan_actions()->forPlan((int)$plan['_id']);
    render('plan', [
        'plan' => $plan,
        'completion' => $completion,
        'aiAvailable' => $aiAvailable,
        'feedUrl' => $feedUrl,
        'activities' => $activities,
        'actionsMap' => $actionsMap,
    ]);
    exit;
}

try {
    $activities = $store->getRecent($athleteId, 28);
    $coach = new Coach($activities);
    $baseline = $coach->summary()['current_block']['distance_km'] / 4;
    $paces = PaceCalculator::compute($activities);
} catch (Throwable $e) {
    $baseline = 0;
    $paces = ['run' => ['has_data' => false, 'zones' => []], 'bike' => ['has_data' => false], 'swim' => ['has_data' => false]];
}

render('plan_form', [
    'goals' => PlanGenerator::GOALS,
    'baselineKm' => $baseline,
    'defaultGoalDate' => (new DateTimeImmutable('+10 weeks'))->format('Y-m-d'),
    'aiAvailable' => $aiAvailable,
    'paces' => $paces,
]);
