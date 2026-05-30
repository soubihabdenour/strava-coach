<?php
require_once __DIR__ . '/../src/bootstrap.php';

$token = $_GET['t'] ?? '';

// Tight format check before any DB hit — pure hex of the expected length.
if (!is_string($token) || !preg_match('/^[a-f0-9]{48}$/', $token)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Not found.\n");
}

$athleteId = token_store()->athleteIdByCalendarToken($token);
if (!$athleteId) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Not found.\n");
}

$plan = plan_store()->getActive($athleteId);

header('Content-Type: text/calendar; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('X-Robots-Tag: noindex, nofollow');

if (!$plan) {
    echo IcsExporter::emptyCalendar('Training plan — no active plan');
    exit;
}

$athlete = token_store()->getAthlete($athleteId);
$name = $athlete['firstname'] ?? 'Athlete';
echo IcsExporter::fromPlan($plan, $name);
