<?php
require_once __DIR__.'/../../includes/auth_check.php';
require_once __DIR__.'/../../includes/db_connection.php';

header('Content-Type: application/json');

function fix_batch_status($pdo, $batch_id) {
    // Get total orders in batch
    $total = $pdo->prepare("SELECT COUNT(*) FROM shipping_batch_orders WHERE batch_id = ?");
    $total->execute([$batch_id]);
    $total = (int)$total->fetchColumn();

    // Get delivered orders in batch
    $delivered = $pdo->prepare("
        SELECT COUNT(*) FROM shipping_batch_orders sbo
        JOIN sales_orders so ON sbo.order_id = so.id
        WHERE sbo.batch_id = ? AND so.delivered = 1
    ");
    $delivered->execute([$batch_id]);
    $delivered = (int)$delivered->fetchColumn();

    // Set status according to business rules
    if ($total === 0) {
        $status = 0; // Pending (empty batch)
    } elseif ($delivered === 0) {
        $status = 0; // Pending (none delivered)
    } elseif ($delivered === $total) {
        $status = 2; // Delivered (all delivered)
    } else {
        $status = 1; // In Process (some delivered, some not)
    }
    $update = $pdo->prepare("UPDATE shipping_batches SET status=? WHERE id=?");
    $update->execute([$status, $batch_id]);
}

$action = $_REQUEST['action'] ?? '';
$batch_id = intval($_REQUEST['batch_id'] ?? 0);

if ($action === 'add_orders' && $batch_id && !empty($_REQUEST['order_ids'])) {
    $order_ids = array_map('intval', $_REQUEST['order_ids']);
    $stmt = $pdo->prepare("INSERT IGNORE INTO shipping_batch_orders (batch_id, order_id) VALUES (?, ?)");
    foreach ($order_ids as $oid) {
        $stmt->execute([$batch_id, $oid]);
    }
    fix_batch_status($pdo, $batch_id);
    echo json_encode(['status'=>'success']);
    exit;
}

if ($action === 'remove_order' && $batch_id && !empty($_REQUEST['order_id'])) {
    $order_id = intval($_REQUEST['order_id']);
    $stmt = $pdo->prepare("DELETE FROM shipping_batch_orders WHERE batch_id=? AND order_id=?");
    $stmt->execute([$batch_id, $order_id]);
    fix_batch_status($pdo, $batch_id);
    echo json_encode(['status'=>'success']);
    exit;
}

// Move order to another batch
if ($action === 'move_order') {
    $order_id = intval($_REQUEST['order_id'] ?? 0);
    $new_batch_id = intval($_REQUEST['new_batch_id'] ?? 0);

    if (!$batch_id || !$order_id || !$new_batch_id) {
        echo json_encode(['status'=>'error', 'message'=>'Invalid input']);
        exit;
    }

    // Check target batch exists and is not delivered (status != 2)
    $stmt = $pdo->prepare("SELECT status FROM shipping_batches WHERE id=?");
    $stmt->execute([$new_batch_id]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$batch) {
        echo json_encode(['status'=>'error', 'message'=>'Target batch does not exist']);
        exit;
    }
    if ((string)$batch['status'] === '2') {
        echo json_encode(['status'=>'error', 'message'=>'Cannot move to a delivered batch']);
        exit;
    }

    // Check if order is already in the new batch
    $check = $pdo->prepare("SELECT 1 FROM shipping_batch_orders WHERE batch_id=? AND order_id=?");
    $check->execute([$new_batch_id, $order_id]);
    if ($check->fetch()) {
        echo json_encode(['status'=>'error', 'message'=>'Order already in target batch']);
        exit;
    }

    // Remove from current batch
    $delete = $pdo->prepare("DELETE FROM shipping_batch_orders WHERE batch_id=? AND order_id=?");
    $delete->execute([$batch_id, $order_id]);
    fix_batch_status($pdo, $batch_id);

    // Add to new batch
    $insert = $pdo->prepare("INSERT INTO shipping_batch_orders (batch_id, order_id) VALUES (?, ?)");
    $success = $insert->execute([$new_batch_id, $order_id]);
    fix_batch_status($pdo, $new_batch_id);

    if ($success) {
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error', 'message'=>'Failed to move order']);
    }
    exit;
}

// Get unbatched undelivered orders for linking
if ($action === 'get_unbatched_orders' && $batch_id) {
    require_once __DIR__.'/../../includes/functions.php';
    $orders = $pdo->query("SELECT so.id, so.grand_total, c.name as customer_name
        FROM sales_orders so
        LEFT JOIN customers c ON so.customer_id=c.id
        WHERE so.delivered=0 AND so.cancelled=0
        AND so.id NOT IN (SELECT order_id FROM shipping_batch_orders)
        ORDER BY so.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Add formatted order numbers
    foreach ($orders as &$order) {
        $order['formatted_order_number'] = format_order_number($order['id']);
    }
    
    echo json_encode(['orders' => $orders]);
    exit;
}

// Batch status fix API (for JS on pageload/manual fix)
if ($action === 'fix_batch_status' && $batch_id) {
    fix_batch_status($pdo, $batch_id);
    $stmt = $pdo->prepare("SELECT status FROM shipping_batches WHERE id=?");
    $stmt->execute([$batch_id]);
    $status = (int)($stmt->fetchColumn());
    echo json_encode(['status'=>'success', 'batch_status'=>$status]);
    exit;
}

echo json_encode(['status'=>'error','message'=>'Invalid request']);
exit;