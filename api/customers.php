<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

try {
    // Prefer explicit 'action' first (for add/edit/delete/etc.)
    $action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';

    // Unified "search" for Select2 and other lookups
    // Supports:
    // - POST q (Select2)
    // - GET term (legacy)
    if ($action === 'search' || (!empty($_POST['q']) || isset($_GET['term']))) {
        $q = '';
        if (isset($_POST['q'])) $q = trim((string)$_POST['q']);
        if (!$q && isset($_GET['term'])) $q = trim((string)$_GET['term']);

        $stmt = $pdo->prepare("
            SELECT id, name 
            FROM customers 
            WHERE (name LIKE :q OR contact LIKE :q OR area LIKE :q OR city LIKE :q)
              AND deleted_at IS NULL 
            ORDER BY name 
            LIMIT 20
        ");
        $stmt->execute([':q' => "%$q%"]);
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = [
                'id' => (int)$row['id'],
                'text' => $row['name'],
            ];
        }
        echo json_encode(['results' => $results]);
        exit;
    }
    if ($action === 'list') {
        $stmt = $pdo->query("SELECT id, name FROM customers WHERE deleted_at IS NULL ORDER BY name ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }
    // Add/Edit customer (merged from entities/customers/actions.php)
    if ($action === 'add' || $action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $data = [
            'name'      => trim((string)($_POST['name'] ?? '')),
            'contact'   => trim((string)($_POST['contact'] ?? '')),
            'house_no'  => trim((string)($_POST['house_no'] ?? '')),
            'area'      => trim((string)($_POST['area'] ?? '')),
            'city'      => trim((string)($_POST['city'] ?? '')),
            'location'  => trim((string)($_POST['location'] ?? '')),
        ];

        // Normalize contact for dedupe
        $data['contact'] = normalize_contact($data['contact']);
        $data['contact_normalized'] = get_contact_normalized($data['contact']);

        // Duplicate check
        $dup_q = "SELECT id, name FROM customers WHERE contact_normalized=? AND deleted_at IS NULL";
        $dup_args = [$data['contact_normalized']];
        if ($action === 'edit') {
            $dup_q .= " AND id != ?";
            $dup_args[] = $id;
        }
        $stmt = $pdo->prepare($dup_q);
        $stmt->execute($dup_args);
        $dup = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($dup) {
            echo json_encode(['success' => false, 'error' => "Customer already exists with name '{$dup['name']}'"]);
            exit;
        }

        if ($action === 'add') {
            $stmt = $pdo->prepare("
                INSERT INTO customers (name, contact, contact_normalized, house_no, area, city, location, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $data['name'], $data['contact'], $data['contact_normalized'],
                $data['house_no'], $data['area'], $data['city'], $data['location']
            ]);
            $newId = (int)$pdo->lastInsertId();
            // Return latest customer row
            $rowStmt = $pdo->prepare("SELECT * FROM customers WHERE id=?");
            $rowStmt->execute([$newId]);
            $customer = $rowStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            // Optional: include current balance for UI badges
            if ($customer) {
                $customer['balance'] = get_customer_balance($newId, $pdo);
            }
            echo json_encode([
                'success'  => true,
                'id'       => $newId,
                'message'  => 'Customer added successfully!',
                'customer' => $customer,
                'name'     => $customer['name'] ?? $data['name'],
            ]);
            exit;
        } else {
            $stmt = $pdo->prepare("
                UPDATE customers 
                SET name=?, contact=?, contact_normalized=?, house_no=?, area=?, city=?, location=? 
                WHERE id=?
            ");
            $stmt->execute([
                $data['name'], $data['contact'], $data['contact_normalized'],
                $data['house_no'], $data['area'], $data['city'], $data['location'], $id
            ]);
            // Return latest customer row
            $rowStmt = $pdo->prepare("SELECT * FROM customers WHERE id=?");
            $rowStmt->execute([$id]);
            $customer = $rowStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            if ($customer) {
                $customer['balance'] = get_customer_balance($id, $pdo);
            }
            echo json_encode([
                'success'  => true,
                'id'       => $id,
                'message'  => 'Customer updated successfully!',
                'customer' => $customer,
                'name'     => $customer['name'] ?? $data['name'],
            ]);
            exit;
        }
    }

    // Soft delete
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE customers SET deleted_at=NOW() WHERE id=?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // Restore
    if ($action === 'restore') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE customers SET deleted_at=NULL WHERE id=?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // Permanent delete
    if ($action === 'delete_permanent') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM customers WHERE id=?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // Record payment
    if ($action === 'payment') {
        $id = intval($_POST['id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid amount']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO customer_payments (customer_id, amount, paid_at) VALUES (?, ?, NOW())");
        $stmt->execute([$id, $amount]);
        echo json_encode(['success' => true]);
        exit;
    }

    // Fetch full customer details
    // Supports:
    // - action=get with id (preferred)
    // - POST id (legacy)
    // - GET id (fallback)
    if ($action === 'get' || isset($_POST['id']) || isset($_GET['id'])) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid customer id']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id=? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$customer) {
            echo json_encode(['success' => false, 'error' => 'Customer not found']);
            exit;
        }

        // Outstanding balance calculation (as before)
        $orderStmt = $pdo->prepare("
            SELECT COALESCE(SUM(grand_total), 0) 
            FROM sales_orders 
            WHERE customer_id=? AND status='delivered' AND deleted_at IS NULL
        ");
        $orderStmt->execute([$id]);
        $delivered_total = floatval($orderStmt->fetchColumn());

        $payStmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) 
            FROM customer_payments 
            WHERE customer_id=?
        ");
        $payStmt->execute([$id]);
        $paid_total = floatval($payStmt->fetchColumn());

        $outstanding = $delivered_total - $paid_total;

        echo json_encode([
            'success' => true,
            'customer' => [
                'id' => (int)$customer['id'],
                'name' => $customer['name'],
                'contact' => $customer['contact'],
                'area' => $customer['area'],
                'city' => $customer['city'],
                'house_no' => $customer['house_no'],
                'location' => $customer['location'],
                'created_at' => $customer['created_at'],
                'updated_at' => $customer['updated_at'],
                'outstanding_balance' => round($outstanding, 2),
                'total_delivered_orders' => round($delivered_total, 2),
                'total_paid' => round($paid_total, 2),
            ]
        ]);
        exit;
    }

    // If nothing matched:
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}