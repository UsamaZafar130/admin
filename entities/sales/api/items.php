<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/db_connection.php';
require_once __DIR__ . '/../../../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');
$action = $_REQUEST['action'] ?? '';
$response = ['status' => 'error', 'message' => 'Unknown error'];

switch ($action) {
    case 'search':
        $term = $_GET['term'] ?? '';
        $stmt = $pdo->prepare("SELECT id, name, code, price_per_unit FROM items WHERE deleted_at IS NULL AND (name LIKE ? OR code LIKE ?) ORDER BY name ASC LIMIT 20");
        $search = "%$term%";
        $stmt->execute([$search, $search]);
        $data = [];
        while ($row = $stmt->fetch()) {
            $label = "{$row['name']} [{$row['code']}] - " . format_currency($row['price_per_unit']);
            $data[] = [
                'id' => $row['id'],
                'text' => $label,
                'price_per_unit' => $row['price_per_unit']
            ];
        }
        echo json_encode(['results' => $data]);
        exit;
    case 'add':
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $price_per_unit = floatval($_POST['price_per_unit'] ?? 0);
        $default_pack_size = intval($_POST['default_pack_size'] ?? 1);
        $category_id = intval($_POST['category_id'] ?? 0);

        if (!$name || !$code || $price_per_unit <= 0) {
            $response['message'] = "Name, code, and price per unit are required.";
        } else {
            // Check if code exists
            $stmt = $pdo->prepare("SELECT id FROM items WHERE code=? AND deleted_at IS NULL");
            $stmt->execute([$code]);
            if ($stmt->fetch()) {
                $response['message'] = "Item with this code already exists.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO items (name, code, price_per_unit, default_pack_size, category_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$name, $code, $price_per_unit, $default_pack_size, $category_id ?: null]);
                $response = ['status' => 'success', 'message' => 'Item added!', 'id' => $pdo->lastInsertId()];
            }
        }
        break;
    case 'get':
        $id = intval($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM items WHERE id=? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if ($item) {
            $response = ['status' => 'success', 'item' => $item];
        } else {
            $response['message'] = "Item not found.";
        }
        break;
    case 'delete':
        $id = intval($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE items SET deleted_at=NOW() WHERE id=?");
        $stmt->execute([$id]);
        $response = ['status' => 'success', 'message' => 'Item deleted.'];
        break;
    default:
        $response['message'] = "Invalid action.";
}

if ($_GET['action'] === 'get_default_pack_size' && isset($_GET['id'])) {
    // DB connection assumed as $pdo
    $stmt = $pdo->prepare("SELECT default_pack_size FROM items WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['default_pack_size' => $row ? $row['default_pack_size'] : 1]);
    exit;
}
echo json_encode($response);