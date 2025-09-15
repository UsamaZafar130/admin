<?php
require_once __DIR__ . '/db_connection.php';

/**
 * Mark the given order as delivered.
 * - Only works if cancelled=0 and delivered=0
 * - Makes -ve entries in inventory_ledger for all order items
 * - Sets delivered=1 in sales_orders
 * - Updates batch status in shipping_batches (0=pending, 1=processing, 2=delivered)
 * Returns: ['success'=>bool, 'message'=>str]
 */
function markOrderAsDelivered($order_id, $user_id) {
    $pdo = require __DIR__ . '/db_connection.php'; // get PDO connection

    try {
        $pdo->beginTransaction();

        // Fetch order
        $stmt = $pdo->prepare("SELECT * FROM sales_orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();

        if (!$order) {
            $pdo->rollBack();
            return ['success' => false, 'message' => "Order not found"];
        }
        if (intval($order['cancelled']) === 1) {
            $pdo->rollBack();
            return ['success' => false, 'message' => "Order is cancelled"];
        }
        if (intval($order['delivered']) === 1) {
            $pdo->rollBack();
            return ['success' => false, 'message' => "Order already delivered"];
        }

        // 1. Inventory ledger entries (negative for delivery)
        $q = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $q->execute([$order_id]);
        $order_items = $q->fetchAll();

        foreach ($order_items as $oi) {
            $ins = $pdo->prepare("INSERT INTO inventory_ledger 
                (item_id, change_type, qty, ref_type, ref_id, comment, created_by) 
                VALUES (?, 'delivery', ?, 'order', ?, ?, ?)");
            $ins->execute([
                $oi['item_id'],
                -abs(intval($oi['qty'])), // negative qty
                $order_id,
                "Order delivery (Order #$order_id)",
                $user_id
            ]);
        }

        // 2. Mark delivered=1 and set delivered_at timestamp in sales_orders
        $upd = $pdo->prepare("UPDATE sales_orders SET delivered=1, delivered_at=NOW(), updated_at=NOW() WHERE id=?");
        $upd->execute([$order_id]);

        // 3. Shipping batch status
        // Find batch for this order
        $q = $pdo->prepare("SELECT batch_id FROM shipping_batch_orders WHERE order_id=?");
        $q->execute([$order_id]);
        $batch_row = $q->fetch();
        if ($batch_row && $batch_row['batch_id']) {
            $batch_id = $batch_row['batch_id'];
            // Find all orders in this batch
            $q = $pdo->prepare("SELECT o.delivered FROM shipping_batch_orders sbo JOIN sales_orders o ON sbo.order_id = o.id WHERE sbo.batch_id=?");
            $q->execute([$batch_id]);
            $delivered_states = $q->fetchAll(PDO::FETCH_COLUMN);

            $all_delivered = !in_array(0, $delivered_states);
            $none_delivered = !in_array(1, $delivered_states);
            $batch_status = 1; // processing default

            if ($all_delivered) $batch_status = 2;
            elseif ($none_delivered) $batch_status = 0;
            else $batch_status = 1;

            $upd = $pdo->prepare("UPDATE shipping_batches SET status=?, updated_at=NOW() WHERE id=?");
            $upd->execute([$batch_status, $batch_id]);
        }

        $pdo->commit();
        return ['success' => true, 'message' => "Order marked as delivered"];

    } catch (Exception $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['success' => false, 'message' => "Error: " . $ex->getMessage()];
    }
}