<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/helpers.php';

Auth::start();

if (!empty($_SESSION['id_user'])) {
    log_activity('Logout', 'User ' . ($_SESSION['username'] ?? '-') . ' logout');
}

Auth::logout();

header('Location: login.php');
exit;