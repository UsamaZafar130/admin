<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

function error_json($msg) {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}
function success_json($extra = []) {
    echo json_encode(['success' => true] + $extra);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

switch ($action) {
    case 'list':
        /*
         * Unified date filtering ONLY (status filtering is now client-side).
         * Query params:
         *   - month=YYYY-MM
         *   - from_date=YYYY-MM-DD
         *   - to_date=YYYY-MM-DD
         * Default: current month (first day -> today).
         */
        $today = new DateTime('today');
        $defaultFrom = (clone $today)->modify('first day of this month')->format('Y-m-d');
        $defaultTo   = $today->format('Y-m-d');

        $from_date = null;
        $to_date   = null;
        $selectedMonth = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : '';

        if ($selectedMonth) {
            $monthStart = DateTime::createFromFormat('Y-m-d', $selectedMonth . '-01');
            if ($monthStart) {
                $from_date = $monthStart->format('Y-m-d');
                $to_date   = $monthStart->modify('last day of this month')->format('Y-m-d');
            }
        }
        if (isset($_GET['from_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from_date'])) {
            $from_date = $_GET['from_date'];
        }
        if (isset($_GET['to_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to_date'])) {
            $to_date = $_GET['to_date'];
        }
        $from_date = $from_date ?? $defaultFrom;
        $to_date   = $to_date ?? $defaultTo;
        if ($from_date > $to_date) {
            $tmp = $from_date;
            $from_date = $to_date;
            $to_date = $tmp;
        }

        $baseQuery = "SELECT o.id, o.public_token, o.order_date, o.customer_id,
                             c.name AS customer_name, c.contact AS contact,
                             o.amount, o.discount, o.delivery_charges, o.grand_total,
                             o.delivered, o.paid, o.cancelled,
                             sbo.batch_id, sb.batch_name
                      FROM sales_orders o
                      LEFT JOIN customers c ON o.customer_id = c.id
                      LEFT JOIN shipping_batch_orders sbo ON o.id = sbo.order_id
                      LEFT JOIN shipping_batches sb ON sbo.batch_id = sb.id
                      WHERE o.order_date >= :from_dt
                        AND o.order_date <= :to_dt";

        // We do NOT automatically exclude cancelled now; client can filter them out if needed.
        $orderBy = " ORDER BY o.id DESC";

        $stmt = $pdo->prepare($baseQuery . $orderBy);
        // Ensure full day coverage (append time)
        $stmt->execute([
            ':from_dt' => $from_date . ' 00:00:00',
            ':to_dt'   => $to_date . ' 23:59:59'
        ]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($orders as &$order) {
            $order['formatted_order_number'] = format_order_number($order['id']);
            $order['formatted_customer'] = format_customer_contact($order['customer_name'], $order['contact']);
            $order['formatted_order_with_batch'] = format_order_with_batch($order['id'], $order['batch_id'], $order['batch_name']);
        }

        echo json_encode([
            'success' => true,
            'orders' => $orders,
            'from_date' => $from_date,
            'to_date' => $to_date
        ]);
        break;

    case 'add':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) error_json("No input received or invalid JSON");
        if (empty($input['customer_id'])) error_json("Customer is required");

        $items = $input['items'] ?? [];
        $meals = $input['meals'] ?? [];
        if (empty($items) && empty($meals)) error_json("At least one item or meal is required");

        $customer_id = intval($input['customer_id']);
        $amount = floatval($input['amount'] ?? 0);
        $discount = floatval($input['discount'] ?? 0);
        $delivery_charges = floatval($input['delivery_charges'] ?? 0);
        $grand_total = floatval($input['grand_total'] ?? 0);
        $order_date = date('Y-m-d H:i:s');
        $public_token = bin2hex(random_bytes(16));
        $paid = ($grand_total == 0.0) ? 1 : 0;

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO sales_orders
                (public_token, customer_id, amount, discount, delivery_charges, grand_total, paid, order_date, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$public_token, $customer_id, $amount, $discount, $delivery_charges, $grand_total, $paid, $order_date]);
            $order_id = $pdo->lastInsertId();

            if (!empty($items)) {
                $item_stmt = $pdo->prepare("INSERT INTO order_items
                    (order_id, item_id, qty, pack_size, price_per_unit, total, meal_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($items as $item) {
                    if (!is_array($item) || empty($item['item_id']) || empty($item['qty']) || $item['qty'] <= 0) continue;
                    $item_stmt->execute([
                        $order_id,
                        intval($item['item_id']),
                        floatval($item['qty']),
                        floatval($item['pack_size'] ?? 1),
                        floatval($item['price_per_unit'] ?? 0),
                        floatval($item['total'] ?? (floatval($item['qty']) * floatval($item['price_per_unit'] ?? 0))),
                        !empty($item['meal_id']) ? intval($item['meal_id']) : null
                    ]);
                }
            }

            if (!empty($meals)) {
                $meal_stmt = $pdo->prepare("INSERT INTO order_meals
                    (order_id, meal_id, qty, price_per_meal, created_at, updated_at)
                    VALUES (?, ?, ?, ?, NOW(), NOW())");
                foreach ($meals as $meal) {
                    if (!is_array($meal) || empty($meal['meal_id']) || empty($meal['qty']) || $meal['qty'] <= 0) continue;
                    $meal_stmt->execute([
                        $order_id,
                        intval($meal['meal_id']),
                        floatval($meal['qty']),
                        floatval($meal['price_per_meal'] ?? 0)
                    ]);
                }
            }

            $pdo->commit();
            success_json(['order_id' => $order_id, 'public_token' => $public_token]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_json("Failed to add order: " . $e->getMessage());
        }
        break;

    case 'edit':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) error_json("No input received or invalid JSON");
        if (empty($input['order_id'])) error_json("Order ID is required");
        if (empty($input['customer_id'])) error_json("Customer is required");

        $items = $input['items'] ?? [];
        $meals = $input['meals'] ?? [];
        if (empty($items) && empty($meals)) error_json("At least one item or meal is required");

        $order_id = intval($input['order_id']);
        $customer_id = intval($input['customer_id']);
        $amount = floatval($input['amount'] ?? 0);
        $discount = floatval($input['discount'] ?? 0);
        $delivery_charges = floatval($input['delivery_charges'] ?? 0);
        $grand_total = floatval($input['grand_total'] ?? 0);
        $paid = ($grand_total == 0.0) ? 1 : 0;

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE sales_orders
                SET customer_id=?, amount=?, discount=?, delivery_charges=?, grand_total=?, paid=?, updated_at=NOW()
                WHERE id=?");
            $stmt->execute([$customer_id, $amount, $discount, $delivery_charges, $grand_total, $paid, $order_id]);

            $pdo->prepare("DELETE FROM order_items WHERE order_id=?")->execute([$order_id]);
            $pdo->prepare("DELETE FROM order_meals WHERE order_id=?")->execute([$order_id]);

            if (!empty($items)) {
                $item_stmt = $pdo->prepare("INSERT INTO order_items
                    (order_id, item_id, qty, pack_size, price_per_unit, total, meal_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($items as $item) {
                    if (!is_array($item) || empty($item['item_id']) || empty($item['qty']) || $item['qty'] <= 0) continue;
                    $item_stmt->execute([
                        $order_id,
                        intval($item['item_id']),
                        floatval($item['qty']),
                        floatval($item['pack_size'] ?? 1),
                        floatval($item['price_per_unit'] ?? 0),
                        floatval($item['total'] ?? (floatval($item['qty']) * floatval($item['price_per_unit'] ?? 0))),
                        !empty($item['meal_id']) ? intval($item['meal_id']) : null
                    ]);
                }
            }

            if (!empty($meals)) {
                $meal_stmt = $pdo->prepare("INSERT INTO order_meals
                    (order_id, meal_id, qty, price_per_meal, created_at, updated_at)
                    VALUES (?, ?, ?, ?, NOW(), NOW())");
                foreach ($meals as $meal) {
                    if (!is_array($meal) || empty($meal['meal_id']) || empty($meal['qty']) || $meal['qty'] <= 0) continue;
                    $meal_stmt->execute([
                        $order_id,
                        intval($meal['meal_id']),
                        floatval($meal['qty']),
                        floatval($meal['price_per_meal'] ?? 0)
                    ]);
                }
            }

            $pdo->commit();
            success_json(['order_id' => $order_id]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_json("Failed to edit order: " . $e->getMessage());
        }
        break;

    case 'get':
        $order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$order_id) error_json("Order ID required");

        $stmt = $pdo->prepare("SELECT id, public_token, customer_id, amount, discount, delivery_charges, grand_total, order_date
                               FROM sales_orders WHERE id=?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) error_json("Order not found");

        $item_stmt = $pdo->prepare("SELECT item_id, qty, pack_size, price_per_unit, total, meal_id FROM order_items WHERE order_id=?");
        $item_stmt->execute([$order_id]);
        $items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);

        $meal_stmt = $pdo->prepare("SELECT meal_id, qty, price_per_meal FROM order_meals WHERE order_id=?");
        $meal_stmt->execute([$order_id]);
        $meals = $meal_stmt->fetchAll(PDO::FETCH_ASSOC);

        $order['items'] = $items;
        $order['meals'] = $meals;
        success_json(['order' => $order]);
        break;

    case 'mark_cancel':
        $order_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$order_id) error_json("Order ID required");

        $stmt = $pdo->prepare("SELECT cancelled FROM sales_orders WHERE id=?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) error_json("Order not found");
        if ($order['cancelled'] == 1) error_json("Already cancelled");

        try {
            $pdo->beginTransaction();

            $pdo->prepare("UPDATE sales_orders
                           SET cancelled=1,
                               paid=0,
                               delivered=0,
                               delivered_at=NULL,
                               updated_at=NOW()
                           WHERE id=?")->execute([$order_id]);

            $tables = [
                'order_items' => "DELETE FROM order_items WHERE order_id=?",
                'order_meals' => "DELETE FROM order_meals WHERE order_id=?",
                'order_payments' => "DELETE FROM order_payments WHERE order_id=?",
                'shipping_batch_orders' => "DELETE FROM shipping_batch_orders WHERE order_id=?",
                'shipping_docs' => "DELETE FROM shipping_docs WHERE order_id=?",
                'delivery_riders' => "DELETE FROM delivery_riders WHERE order_id=?",
            ];
            $deleted_counts = [];
            foreach ($tables as $key => $sql) {
                $delStmt = $pdo->prepare($sql);
                $delStmt->execute([$order_id]);
                $deleted_counts[$key] = $delStmt->rowCount();
            }

            $pdo->commit();
            success_json([
                'order_id' => $order_id,
                'cancelled' => true,
                'deleted' => $deleted_counts
            ]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_json("Failed to cancel order: " . $e->getMessage());
        }
        break;

    case 'mark_paid':
        error_json("Order payment must be submitted via the Payment modal.");
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}