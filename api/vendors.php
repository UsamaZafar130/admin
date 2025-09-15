<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/functions.php';

// Simple REST-like API for Vendors entity.

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

switch ($action) {
    case 'list':
        // TODO: Fetch and return vendors (with search/filter)
        echo json_encode(['success' => true, 'vendors' => []]);
        break;

    case 'get':
        $id = intval($_GET['id'] ?? 0);
        // TODO: Get single vendor by id
        echo json_encode(['success' => true, 'vendor' => null]);
        break;

    case 'create':
        // TODO: Create new vendor (validate fields)
        echo json_encode(['success' => true, 'message' => 'Vendor created']);
        break;

    case 'update':
        // TODO: Update vendor by id
        echo json_encode(['success' => true, 'message' => 'Vendor updated']);
        break;

    case 'delete':
        $id = intval($_POST['id'] ?? 0);
        // TODO: Soft delete vendor (set deleted_at)
        echo json_encode(['success' => true, 'message' => 'Vendor deleted']);
        break;

    case 'payment':
        // TODO: Record vendor payment, allocate FIFO to purchases
        echo json_encode(['success' => true, 'message' => 'Payment recorded']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}