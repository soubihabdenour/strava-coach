<?php
require_once __DIR__ . '/../src/bootstrap.php';

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

header('Location: ' . strava_client()->authorizationUrl($state));
