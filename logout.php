<?php
require_once __DIR__ . '/settings/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Unset all session variables
$_SESSION = [];

// Destroy session data on the server
session_unset();
session_destroy();

// Delete the session cookie (if set)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Optionally, redirect to login page
header('Location: login.php');
exit;