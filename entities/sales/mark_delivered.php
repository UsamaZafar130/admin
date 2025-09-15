<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/order_actions.php';
require_once __DIR__ . '/../../includes/db_connection.php';

// --- AJAX or Direct? ---
$isAjax = (
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

// Get order_ids[] from POST or GET
$order_ids = [];
$source = $_POST['source'] ?? $_GET['source'] ?? '';
if (isset($_POST['order_ids']) && is_array($_POST['order_ids'])) {
    $order_ids = $_POST['order_ids'];
} elseif (isset($_GET['order_ids']) && is_array($_GET['order_ids'])) {
    $order_ids = $_GET['order_ids'];
} elseif (isset($_GET['order_id'])) {
    $order_ids = [$_GET['order_id']];
}

if (empty($order_ids)) {
    $response = ['success' => false, 'message' => 'No order selected'];
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        ?>
        <html><body><div style="color:#e05353;font-size:1.2rem;text-align:center;padding:40px;">No order selected.<br><a href="javascript:window.close();window.history.back();" style="color:#0070e0;">Back</a></div></body></html>
        <?php
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = require __DIR__ . '/../../includes/db_connection.php';
$results = [];
foreach ($order_ids as $oid) {
    $oid = intval($oid);
    $result = markOrderAsDelivered($oid, $user_id);
    // Fetch customer name for response
    $customer_name = '';
    $stmt = $pdo->prepare("SELECT c.name FROM sales_orders s LEFT JOIN customers c ON s.customer_id = c.id WHERE s.id = ?");
    $stmt->execute([$oid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && isset($row['name'])) {
        $customer_name = $row['name'];
    }
    $results[] = [
        'order_id' => $oid,
        'success' => $result['success'],
        'message' => $result['message'] ?? '',
        'customer_name' => $customer_name,
    ];
}

// Partition results into successes and failures
$successes = array_filter($results, fn($r) => $r['success']);
$failures = array_filter($results, fn($r) => !$r['success']);

// If single order, handle as before (ask for payment modal)
if (count($order_ids) === 1 && in_array($source, ['list', 'page', 'print'])) {
    if ($successes) {
        $order_id = intval($order_ids[0]);
        $stmt = $pdo->prepare("SELECT s.id, s.grand_total, s.paid, s.customer_id, c.name AS customer_name, c.contact
                               FROM sales_orders s
                               LEFT JOIN customers c ON s.customer_id = c.id
                               WHERE s.id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        $response = [
            'success' => true,
            'ask_payment' => true,
            'order_id' => $order['id'],
            'grand_total' => $order['grand_total'],
            'paid' => $order['paid'],
            'customer_name' => $order['customer_name'],
            'contact' => $order['contact']
        ];
    } else {
        // Single order but failed
        $response = ['success' => false, 'results' => $results];
    }
} else {
    // Bulk or API: partial success if at least one worked
    if (count($successes) > 0) {
        $response = [
            'success' => true,
            'results' => $results,
            'partial' => count($failures) > 0 ? true : false,
            'failures' => array_values($failures)
        ];
    } else {
        // All failed
        $response = ['success' => false, 'results' => $results];
    }
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
} else {
    // Show simple HTML page for direct access
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Mark Delivered</title>

        <style>
            body { font-family: sans-serif; padding: 38px 16px; background: #f9fbfd; text-align: center;}
            .status-box { background: #fff; border-radius: 15px; margin: 0 auto; max-width: 420px; padding: 32px 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.07);}
            .success { color: #219150; font-size: 1.6rem; font-weight: 700; margin-bottom: 14px;}
            .fail { color: #e05353; font-size: 1.35rem; font-weight: 700; margin-bottom: 14px;}
            .back-btn { margin-top: 25px; padding: 8px 26px; border-radius: 8px; background: #0070e0; color: #fff; border: none; font-size: 1.1rem; cursor: pointer;}
            .back-btn:hover { background: #005bb5; }
        </style>
    </head>
    <body>
        <div class="status-box">
            <?php if ($response['success']): ?>
                <div class="success"><i class="fa fa-check-circle"></i> Order marked as delivered.</div>
            <?php else: ?>
                <div class="fail"><i class="fa fa-times-circle"></i> Failed to mark as delivered.</div>
                <div style="color:#a00;font-size:1.1rem;margin-top:8px;">
                    <?= htmlspecialchars($response['results'][0]['message'] ?? ($response['message'] ?? '')) ?>
                </div>
            <?php endif; ?>
            <button class="back-btn" onclick="window.close();window.history.back();">Back</button>
        </div>
    </body>
    </html>
    <?php
    exit;
}