<?php
require_once __DIR__ . '/../src/bootstrap.php';

$token = current_access_token();
if (!$token) {
    header('Location: index.php');
    exit;
}

$sport = $_GET['sport'] ?? 'run';
if (!CoachAgent::isValid($sport)) {
    $sport = 'run';
}

if (isset($_GET['clear'])) {
    unset($_SESSION['coach_history'][$sport]);
    header('Location: coach.php?sport=' . urlencode($sport));
    exit;
}

$_SESSION['coach_history'][$sport] ??= [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $message = trim($_POST['message'] ?? '');
    if ($message !== '' && strlen($message) <= 4000) {
        $_SESSION['coach_history'][$sport][] = ['role' => 'user', 'text' => $message];

        try {
            $apiKey = Config::get('GEMINI_API_KEY');
            if (!$apiKey) {
                throw new RuntimeException('GEMINI_API_KEY not configured. Add it to .env.');
            }
            $activities = strava_client()->fetchRecentActivities($token, 28);
            $summary = (new Coach($activities))->summary();
            $systemPrompt = CoachAgent::buildSystemPrompt($sport, $summary, I18n::locale());

            $client = new GeminiClient($apiKey);
            $reply = $client->chat($systemPrompt, $_SESSION['coach_history'][$sport]);

            $_SESSION['coach_history'][$sport][] = ['role' => 'model', 'text' => $reply];

            if (count($_SESSION['coach_history'][$sport]) > 20) {
                $_SESSION['coach_history'][$sport] = array_slice($_SESSION['coach_history'][$sport], -20);
            }
        } catch (Throwable $e) {
            array_pop($_SESSION['coach_history'][$sport]);
            $_SESSION['coach_error'][$sport] = $e->getMessage();
        }
    }
    header('Location: coach.php?sport=' . urlencode($sport));
    exit;
}

$error = $_SESSION['coach_error'][$sport] ?? null;
unset($_SESSION['coach_error'][$sport]);

render('coach', [
    'sport' => $sport,
    'history' => $_SESSION['coach_history'][$sport],
    'error' => $error,
]);
