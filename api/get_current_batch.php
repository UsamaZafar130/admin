<?php
/**
 * API endpoint to get current batch ID
 * Uses the same logic as index.php for determining current batch
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connection.php';

header('Content-Type: application/json');

try {
    // Get upcoming batch for shortcut (same logic as index.php lines 52-63)
    $batchShortcutId = null;
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT id FROM shipping_batches WHERE batch_date >= CURDATE() ORDER BY batch_date ASC, id ASC LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['id'])) {
            $batchShortcutId = (int)$row['id'];
        }
    }
    
    if ($batchShortcutId) {
        echo json_encode(['success' => true, 'batch_id' => $batchShortcutId]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No current batch found']);
    }
    
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>