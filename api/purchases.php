<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/functions.php';

// Simple REST-like API for Purchases/Expenses entity.

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

switch ($action) {
    case 'list':
        // TODO: Fetch and return purchases/expenses
        echo json_encode(['success' => true, 'purchases' => []]);
        break;

    case 'get':
        $id = intval($_GET['id'] ?? 0);
        // TODO: Get single purchase by id
        echo json_encode(['success' => true, 'purchase' => null]);
        break;

    case 'create':
        // TODO: Create new purchase/expense (validate fields)
        echo json_encode(['success' => true, 'message' => 'Purchase/Expense created']);
        break;

    case 'update':
        // TODO: Update purchase/expense by id
        echo json_encode(['success' => true, 'message' => 'Purchase/Expense updated']);
        break;

    case 'delete':
        $id = intval($_POST['id'] ?? 0);
        // TODO: Soft delete purchase (set deleted_at)
        echo json_encode(['success' => true, 'message' => 'Purchase/Expense deleted']);
        break;

    case 'payment':
        // TODO: Record payment against purchase (FIFO allocation)
        echo json_encode(['success' => true, 'message' => 'Payment recorded']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}