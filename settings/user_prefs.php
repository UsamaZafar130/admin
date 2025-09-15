<?php
/**
 * User Preferences Logic for FrozoFun Admin
 * Handles loading and saving per-user preferences (theme, timezone, etc.)
 * Assumes user is logged in and $_SESSION['user_id'] is set
 */

require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/functions.php';

function load_user_preferences($user_id) {
    // TODO: Load from DB, fallback to defaults
    // Example stub:
    return [
        'theme' => 'default',
        'timezone' => 'UTC'
    ];
}

function save_user_preferences($user_id, $prefs) {
    // TODO: Save to DB
    return true;
}

// Handle AJAX updates to user preferences
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_prefs') {
    $user_id = current_user_id();
    $theme = $_POST['theme'] ?? 'default';
    $timezone = $_POST['timezone'] ?? 'UTC';
    $prefs = [
        'theme' => $theme,
        'timezone' => $timezone
    ];
    $success = save_user_preferences($user_id, $prefs);
    ajax_response(['success' => $success]);
}