<?php
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $timezone = $_POST['timezone'] ?? '';
    
    // Validate timezone
    $valid_timezones = [
        'UTC',
        'Asia/Karachi', 
        'America/New_York',
        'Europe/London'
    ];
    
    if (in_array($timezone, $valid_timezones)) {
        $_SESSION['user_timezone'] = $timezone;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid timezone']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>