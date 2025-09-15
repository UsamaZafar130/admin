<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

header('Content-Type: application/json');
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$id = intval($_POST['id'] ?? $_GET['id'] ?? 0);

// --- GET UNPAID PURCHASES (for payment modal) ---
if ($action === 'get_unpaid_purchases' && isset($_GET['vendor_id'])) {
    $vendor_id = intval($_GET['vendor_id']);
    // Get purchases for this vendor, unpaid or partially paid, FIFO
    $stmt = $pdo->prepare("
        SELECT p.id, p.amount, p.description, p.date, 
        (SELECT COALESCE(SUM(pp.amount),0) FROM purchase_payments pp WHERE pp.purchase_id = p.id AND pp.deleted_at IS NULL) AS paid
        FROM purchases p
        WHERE p.vendor_id=? AND p.deleted_at IS NULL
        HAVING paid < p.amount
        ORDER BY p.date ASC, p.created_at ASC
    ");
    $stmt->execute([$vendor_id]);
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Outstanding = sum of their unpaid parts (should match vendor balance)
    $outstanding = 0;
    foreach ($purchases as $p) {
        $outstanding += (floatval($p['amount']) - floatval($p['paid']));
    }

    // Get vendor surplus (advance)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS surplus FROM vendor_advances WHERE vendor_id=? AND applied=0");
    $stmt->execute([$vendor_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $surplus = $row ? floatval($row['surplus']) : 0;

    echo json_encode([
        'success' => true,
        'purchases' => $purchases,
        'outstanding' => round($outstanding,2),
        'surplus' => round($surplus,2),
        'csrf_token' => csrf_token()
    ]);
    exit;
}

// --- GET VENDOR PAYMENT INFO (for modal with both purchase & expense support) ---
if ($action === 'get_vendor_payment_info' && isset($_GET['vendor_id'])) {
    $vendor_id = intval($_GET['vendor_id']);

    // Purchases
    $stmt = $pdo->prepare("
        SELECT p.id, p.amount, p.description, p.date, 
        (SELECT COALESCE(SUM(pp.amount),0) FROM purchase_payments pp WHERE pp.purchase_id = p.id AND pp.deleted_at IS NULL) AS paid
        FROM purchases p
        WHERE p.vendor_id=? AND p.deleted_at IS NULL
        HAVING paid < p.amount
        ORDER BY p.date ASC, p.created_at ASC
    ");
    $stmt->execute([$vendor_id]);
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Expenses
    $stmt = $pdo->prepare("
        SELECT e.id, e.amount, e.description, e.date,
        (SELECT COALESCE(SUM(ep.amount),0) FROM expense_payments ep WHERE ep.expense_id = e.id AND ep.deleted_at IS NULL) AS paid
        FROM expenses e
        WHERE e.vendor_id=? AND e.deleted_at IS NULL
        HAVING paid < e.amount
        ORDER BY e.date ASC, e.created_at ASC
    ");
    $stmt->execute([$vendor_id]);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Outstanding = sum of their unpaid parts (should match vendor balance)
    $outstanding = 0;
    foreach ($purchases as $p) {
        $outstanding += (floatval($p['amount']) - floatval($p['paid']));
    }
    foreach ($expenses as $e) {
        $outstanding += (floatval($e['amount']) - floatval($e['paid']));
    }

    // Surplus (advance)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS surplus FROM vendor_advances WHERE vendor_id=? AND applied=0");
    $stmt->execute([$vendor_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $surplus = $row ? floatval($row['surplus']) : 0;

    echo json_encode([
        'success' => true,
        'purchases' => $purchases,
        'expenses' => $expenses,
        'outstanding' => round($outstanding,2),
        'surplus' => round($surplus,2),
        'csrf_token' => csrf_token()
    ]);
    exit;
}

// --- VENDOR PAYMENT: FIFO ALLOCATION TO PURCHASES & EXPENSES, THEN ADVANCE ---
if ($action === 'pay' && isset($_POST['vendor_id'])) {
    $vendor_id = intval($_POST['vendor_id']);
    $amount = floatval($_POST['payment_amount']);
    $user_id = current_user_id();
    $note = trim($_POST['payment_description'] ?? '');

    if ($amount == 0) {
        echo json_encode(['success'=>false, 'error'=>'Amount must not be zero.']);
        exit;
    }
    if ($amount < 0 && !$note) {
        echo json_encode(['success'=>false, 'error'=>'Note is required for negative adjustments.']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $remaining = $amount;

        // 1. Apply to unpaid purchases (FIFO)
        $stmt = $pdo->prepare("
            SELECT p.id, p.amount, 
            (SELECT COALESCE(SUM(pp.amount),0) FROM purchase_payments pp WHERE pp.purchase_id = p.id AND pp.deleted_at IS NULL) AS paid
            FROM purchases p
            WHERE p.vendor_id=? AND p.deleted_at IS NULL
            HAVING paid < p.amount
            ORDER BY p.date ASC, p.created_at ASC
        ");
        $stmt->execute([$vendor_id]);
        $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($purchases as $p) {
            $due = floatval($p['amount']) - floatval($p['paid']);
            if ($due <= 0) continue;
            $pay = min($due, $remaining);
            if ($pay != 0) { // allow negative
                $stmt2 = $pdo->prepare("INSERT INTO purchase_payments (purchase_id, amount, route, paid_at, created_by, description) VALUES (?, ?, ?, NOW(), ?, ?)");
                $stmt2->execute([$p['id'], $pay, 'cash', $user_id, $note]);
                $remaining -= $pay;
            }
            if ($remaining == 0) break;
        }

        // 2. Apply to unpaid expenses (FIFO)
        if ($remaining != 0) {
            $stmt = $pdo->prepare("
                SELECT e.id, e.amount, 
                (SELECT COALESCE(SUM(ep.amount),0) FROM expense_payments ep WHERE ep.expense_id = e.id AND ep.deleted_at IS NULL) AS paid
                FROM expenses e
                WHERE e.vendor_id=? AND e.deleted_at IS NULL
                HAVING paid < e.amount
                ORDER BY e.date ASC, e.created_at ASC
            ");
            $stmt->execute([$vendor_id]);
            $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($expenses as $e) {
                $due = floatval($e['amount']) - floatval($e['paid']);
                if ($due <= 0) continue;
                $pay = min($due, $remaining);
                if ($pay != 0) { // allow negative
                    $stmt2 = $pdo->prepare("INSERT INTO expense_payments (expense_id, amount, route, paid_at, created_by, description) VALUES (?, ?, ?, NOW(), ?, ?)");
                    $stmt2->execute([$e['id'], $pay, 'cash', $user_id, $note]);
                    $remaining -= $pay;
                }
                if ($remaining == 0) break;
            }
        }

        // 3. If anything remains, record as vendor advance (surplus)
        if ($remaining != 0) {
            $stmt = $pdo->prepare("INSERT INTO vendor_advances (vendor_id, amount, recorded_at, applied, created_by, description) VALUES (?, ?, NOW(), 0, ?, ?)");
            $stmt->execute([$vendor_id, $remaining, $user_id, $note]);
        }

        $pdo->commit();
        echo json_encode(['success'=>true]);
    } catch (\Throwable $ex) {
        $pdo->rollBack();
        echo json_encode(['success'=>false, 'error'=>'Server error. '.$ex->getMessage()]);
    }
    exit;
}

// --- ADD, EDIT, DISABLE, ENABLE (no special logic for advances needed here) ---
switch ($action) {
    case 'add':
        $name = trim($_POST['name'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $area = trim($_POST['area'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $location = trim($_POST['location'] ?? '');
        if (!$name) {
            echo json_encode(['success'=>false, 'error'=>'Name is required']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO vendors (name, contact, address, area, city, location, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $res = $stmt->execute([$name, $contact, $address, $area, $city, $location]);
        echo json_encode(['success'=>$res]);
        exit;

    case 'edit':
        if (!$id) {
            echo json_encode(['success'=>false, 'error'=>'Invalid ID']);
            exit;
        }
        $name = trim($_POST['name'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $area = trim($_POST['area'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $location = trim($_POST['location'] ?? '');
        if (!$name) {
            echo json_encode(['success'=>false, 'error'=>'Name is required']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE vendors SET name=?, contact=?, address=?, area=?, city=?, location=? WHERE id=?");
        $res = $stmt->execute([$name, $contact, $address, $area, $city, $location, $id]);
        echo json_encode(['success'=>$res]);
        exit;

    case 'disable':
        if (!$id) {
            echo json_encode(['success'=>false, 'error'=>'Invalid ID']);
            exit;
        }
        // Check vendor balance before disabling
        $balance = get_vendor_balance_details($id, $pdo);
        if (($balance['outstanding'] ?? 0) > 0 || ($balance['surplus'] ?? 0) > 0) {
            echo json_encode(['success'=>false, 'error'=>'Cannot disable vendor: Outstanding or Surplus must be zero before disabling.']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE vendors SET deleted_at=NOW() WHERE id=?");
        $res = $stmt->execute([$id]);
        echo json_encode(['success'=>$res]);
        exit;

    case 'enable':
        if (!$id) {
            echo json_encode(['success'=>false, 'error'=>'Invalid ID']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE vendors SET deleted_at=NULL WHERE id=?");
        $res = $stmt->execute([$id]);
        echo json_encode(['success'=>$res]);
        exit;

    default:
        echo json_encode(['success'=>false, 'error'=>'Unknown action']);
        exit;
}