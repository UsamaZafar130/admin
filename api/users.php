<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/functions.php';

// Simple REST-like API for Users entity.

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

switch ($action) {
    case 'list':
        // TODO: Fetch and return users (with filters, pagination)
        echo json_encode(['success' => true, 'users' => []]);
        break;

    case 'get':
        $id = intval($_GET['id'] ?? 0);
        // TODO: Get single user by id
        echo json_encode(['success' => true, 'user' => null]);
        break;

    case 'create':
        // TODO: Create new user (validate fields, hash password)
        echo json_encode(['success' => true, 'message' => 'User created']);
        break;

    case 'update':
        // TODO: Update user by id (optionally update password, permissions)
        echo json_encode(['success' => true, 'message' => 'User updated']);
        break;

    case 'delete':
        $id = intval($_POST['id'] ?? 0);
        // TODO: Soft delete/deactivate user
        echo json_encode(['success' => true, 'message' => 'User deleted']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}