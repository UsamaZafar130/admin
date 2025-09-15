<?php
require_once '../../includes/auth_check.php';
require_once '../../includes/db_connection.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'add_stock') {
    $item_id = intval($_POST['item_id'] ?? 0);
    $qty = floatval($_POST['qty'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    if ($item_id && $qty != 0) {
        // If negative, require comment
        if ($qty < 0 && $comment === '') {
            echo json_encode(['success'=>false, 'error'=>'Comment is required for negative stock (reconciliation).']);
            exit;
        }
        $change_type = $qty > 0 ? 'manufacture' : 'reconcile';
        $stmt = $pdo->prepare("INSERT INTO inventory_ledger (item_id, change_type, qty, ref_type, comment, created_by) VALUES (?, ?, ?, 'manual', ?, ?)");
        $stmt->execute([$item_id, $change_type, $qty, $comment ?: 'Manual stock added', $_SESSION['user_id']]);
        echo json_encode(['success'=>true]);
    } else {
        echo json_encode(['success'=>false, 'error'=>'Please select an item and enter a non-zero quantity.']);
    }
    exit;
}

if ($action === 'update_manufactured') {
    $item_id = intval($_POST['item_id'] ?? 0);
    $qty = floatval($_POST['qty'] ?? 0);
    // Prevent zero or negative stock additions
    if ($item_id && $qty > 0) {
        $stmt = $pdo->prepare("INSERT INTO inventory_ledger (item_id, change_type, qty, ref_type, comment, created_by) VALUES (?, 'manufacture', ?, 'manual', 'Manual stock added (inline)', ?)");
        $stmt->execute([$item_id, $qty, $_SESSION['user_id']]);
        echo json_encode(['success'=>true]);
    } else {
        echo json_encode(['success'=>false, 'error'=>'Please enter a positive quantity.']);
    }
    exit;
}

// ===== PACKING LOG ACTIONS (now general, not per order) =====

if ($action === 'add_packed_packs') {
    $item_id = intval($_POST['item_id'] ?? 0);
    $pack_size = intval($_POST['pack_size'] ?? 0);
    $pack_count = intval($_POST['pack_count'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    $barcode = isset($_POST['barcode']) ? trim($_POST['barcode']) : null;

    if ($item_id && $pack_size > 0 && $pack_count != 0) {
        if ($pack_count < 0 && $comment === '') {
            echo json_encode(['success' => false, 'error' => 'Comment is required for negative (reversal/reconciliation) pack entries.']);
            exit;
        }
        if ($pack_count > 0 && $comment === '') {
            $comment = 'manual entry';
        }
        $stmt = $pdo->prepare("INSERT INTO packing_log (item_id, pack_size, packs_packed, barcode, packed_by, comment) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$item_id, $pack_size, $pack_count, $barcode, $_SESSION['user_id'], $comment]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Please select item & pack size and enter a non-zero pack count.']);
    }
    exit;
}

// ====== PACK SCAN ACTION: process by barcode, lookup item/pack ======
if ($action === 'scan_pack_barcode') {
    $barcode = trim($_POST['barcode'] ?? '');
    if (!$barcode) {
        echo json_encode(['success' => false, 'error' => 'No barcode provided.']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT item_id, pack_size FROM item_pack_codes WHERE barcode = ?");
    $stmt->execute([$barcode]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Barcode not found.']);
        exit;
    }
    // Log to packing_log
    $stmt_log = $pdo->prepare("INSERT INTO packing_log (item_id, pack_size, barcode, packs_packed, packed_by, comment) VALUES (?, ?, ?, 1, ?, ?)");
    $stmt_log->execute([
        $row['item_id'], $row['pack_size'], $barcode, $_SESSION['user_id'], 'scanned'
    ]);
    // Get item name for UI
    $stmt_item = $pdo->prepare("SELECT name FROM items WHERE id = ?");
    $stmt_item->execute([$row['item_id']]);
    $item_name = $stmt_item->fetchColumn() ?: 'Item #' . $row['item_id'];
    echo json_encode(['success' => true, 'item_name' => $item_name, 'pack_size' => $row['pack_size']]);
    exit;
}

// ========== GET ROW INFO ==========

if ($action === 'get_item_stock_info') {
    $item_id = intval($_POST['item_id'] ?? 0);
    $order_ids = array_filter(array_map('intval', explode(',', $_POST['order_ids'] ?? '')));
    if ($item_id && $order_ids) {
        // Get total required for this item in selected orders
        $in  = str_repeat('?,', count($order_ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT SUM(oi.qty) as total_qty
            FROM order_items oi
            WHERE oi.item_id = ? AND oi.order_id IN ($in)");
        $stmt->execute(array_merge([$item_id], $order_ids));
        $total_required = floatval($stmt->fetchColumn() ?: 0);

        // Get manufactured (net stock)
        $stmt2 = $pdo->prepare("SELECT SUM(qty) FROM inventory_ledger WHERE item_id=?");
        $stmt2->execute([$item_id]);
        $manufactured = floatval($stmt2->fetchColumn() ?: 0);

        $diff = $total_required - $manufactured;

        echo json_encode([
            'success' => true,
            'total_required' => $total_required,
            'manufactured' => $manufactured,
            'diff' => $diff
        ]);
        exit;
    }
    echo json_encode(['success'=>false, 'error'=>'Invalid input']);
    exit;
}

// ============ Excess Stock Feature =============
if (
    (isset($_GET['action']) && $_GET['action'] === 'get_excess_stock')
    || (isset($_POST['action']) && $_POST['action'] === 'get_excess_stock')
) {
    // Get all undelivered, not cancelled orders
    $orders = $pdo->query("SELECT id FROM sales_orders WHERE delivered=0 AND cancelled=0")->fetchAll(PDO::FETCH_COLUMN);
    $order_ids = $orders ?: [];
    $required_by_item = [];
    $items = [];

    // Get all items (for the tweak: even if not in any order)
    $sql_items = "SELECT i.id AS item_id, i.name, i.price_per_unit, c.name AS category
                  FROM items i
                  LEFT JOIN categories c ON i.category_id = c.id
                  WHERE i.deleted_at IS NULL";
    $stmt_items = $pdo->query($sql_items);
    $items_data = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    // Get total required for each item (only for items in current orders)
    if ($order_ids) {
        $in  = str_repeat('?,', count($order_ids) - 1) . '?';
        $sql_req = "SELECT oi.item_id, SUM(oi.qty) AS required
                    FROM order_items oi
                    WHERE oi.order_id IN ($in)
                    GROUP BY oi.item_id";
        $stmt_req = $pdo->prepare($sql_req);
        $stmt_req->execute($order_ids);
        foreach ($stmt_req->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $required_by_item[$row['item_id']] = floatval($row['required']);
        }
    }

    // Get manufactured for all items (even those not in orders)
    $sql_manuf = "SELECT item_id, SUM(qty) AS manufactured FROM inventory_ledger GROUP BY item_id";
    $stmt_manuf = $pdo->query($sql_manuf);
    $manufactured_by_item = [];
    foreach ($stmt_manuf->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $manufactured_by_item[$row['item_id']] = floatval($row['manufactured']);
    }

    // Build result: items with manufactured > required (required defaults to 0 if not in any order)
    $result = [];
    foreach ($items_data as $it) {
        $mid = $it['item_id'];
        $manuf = $manufactured_by_item[$mid] ?? 0;
        $required = $required_by_item[$mid] ?? 0;
        $excess = $manuf - $required;
        if ($excess > 0) {
            $result[] = [
                'name' => $it['name'],
                'category' => $it['category'] ?? '',
                'manufactured' => $manuf,
                'required' => $required,
                'excess' => $excess,
                'price_per_unit' => $it['price_per_unit'] ?? 0,
                'excess_value' => $excess * (float)($it['price_per_unit'] ?? 0)
            ];
        }
    }
    echo json_encode(['success'=>true, 'data'=>$result]);
    exit;
}
// ============== End Excess Stock Feature ==============

echo json_encode(['success'=>false, 'error'=>'Invalid action']);