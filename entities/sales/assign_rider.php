<?php
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$order_ids = $_POST['order_ids'] ?? [];
$rider_id = intval($_POST['rider_id'] ?? 0);

if (!is_array($order_ids) || !$rider_id) {
    http_response_code(400);
    exit('Invalid input');
}

foreach ($order_ids as $order_id) {
    $order_id = intval($order_id);
    // Use INSERT ... ON DUPLICATE KEY UPDATE to assign or reassign
    $stmt = $pdo->prepare("
        INSERT INTO delivery_riders (order_id, rider_id, assigned_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE rider_id = VALUES(rider_id), assigned_at = NOW()
    ");
    $stmt->execute([$order_id, $rider_id]);
}

echo 'ok';