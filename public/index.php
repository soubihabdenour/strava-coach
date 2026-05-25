<?php
require_once __DIR__ . '/../src/bootstrap.php';

if (current_access_token()) {
    header('Location: dashboard.php');
    exit;
}

render('landing');
