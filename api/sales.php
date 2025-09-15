<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/functions.php';

// Simple REST-like API for Sales/Orders entity.

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

switch ($action) {
    case 'list':
        // TODO: Fetch and return sales/orders (with filters, search, status)
        echo json_encode(['success' => true, 'orders' => []]);
        break;

    case 'get':
        $id = intval($_GET['id'] ?? 0);
        // TODO: Get single order by id
        echo json_encode(['success' => true, 'order' => null]);
        break;

    case 'create':
        // TODO: Create new order (validate fields, items)
        echo json_encode(['success' => true, 'message' => 'Order created']);
        break;

    case 'update':
        // TODO: Update order by id
        echo json_encode(['success' => true, 'message' => 'Order updated']);
        break;

    case 'delete':
        $id = intval($_POST['id'] ?? 0);
        // TODO: Cancel/delete order
        echo json_encode(['success' => true, 'message' => 'Order deleted']);
        break;

    case 'status':
        // TODO: Change order status (paid/delivered/cancelled, etc.)
        echo json_encode(['success' => true, 'message' => 'Order status updated']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}