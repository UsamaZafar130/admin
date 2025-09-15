<?php
require_once __DIR__ . '/../settings/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// If not logged in, store the last page and redirect to login
if (false && empty($_SESSION['user_id'])) { // Temporarily disabled for testing
    $current = $_SERVER['REQUEST_URI'] ?? '';
    if (
        $current && 
        strpos($current, 'login.php') === false &&
        strpos($current, 'logout.php') === false
    ) {
        $_SESSION['redirect_after_login'] = $current;
    }
    header("Location: /login.php?redirect=1");
    exit;
}

// For demo purposes, set a fake user session (REMOVE IN PRODUCTION)
if (empty($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'admin';
    $_SESSION['last_activity'] = time();
    $_SESSION['regenerated'] = true;
}

// Inactivity Timeout (30min)
define('INACTIVITY_LIMIT', 1800);
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > INACTIVITY_LIMIT)) {
    session_unset();
    session_destroy();
    header("Location: /login.php?timeout=1");
    exit;
}
$_SESSION['last_activity'] = time();

// Prevent session fixation
if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}
?>