<?php
require_once __DIR__.'/../../includes/auth_check.php';
require_once __DIR__.'/../../includes/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $batch_name = trim($_POST['batch_name'] ?? '');
    $batch_date = $_POST['batch_date'] ?? '';
    $created_by = $_SESSION['user_id'] ?? null;

    // If batch_name is empty, auto-generate
    if (!$batch_name) {
        // Step 1: Insert with temporary name
        $tmp_name = 'Batch Creating...'; // placeholder
        $stmt = $pdo->prepare("INSERT INTO shipping_batches (batch_name, batch_date, created_by) VALUES (?, ?, ?)");
        $stmt->execute([$tmp_name, $batch_date, $created_by]);
        $batch_id = $pdo->lastInsertId();

        // Step 2: Generate new batch name
        // Format: Batch YYYY-MM-DD #100XXX
        $today = $batch_date ?: date('Y-m-d');
        $batch_no = "100" . str_pad($batch_id, 3, "0", STR_PAD_LEFT);
        $auto_name = "Batch {$today} #{$batch_no}";

        // Step 3: Update row with the generated name
        $stmt = $pdo->prepare("UPDATE shipping_batches SET batch_name=? WHERE id=?");
        $stmt->execute([$auto_name, $batch_id]);
    } else {
        // User provided a batch name
        $stmt = $pdo->prepare("INSERT INTO shipping_batches (batch_name, batch_date, created_by) VALUES (?, ?, ?)");
        $stmt->execute([$batch_name, $batch_date, $created_by]);
    }
    header("Location: list.php");
    exit;
}
header("Location: list.php?error=1");
exit;