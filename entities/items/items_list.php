<?php
require_once '../../includes/auth_check.php';
require_once '../../includes/db_connection.php';
header('Content-Type: application/json');

$stmt = $pdo->query("SELECT id, name FROM items WHERE deleted_at IS NULL ORDER BY name");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));