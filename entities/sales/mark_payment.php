<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Bulk payment: orders[] + amounts[] + methods[] arrays
    $orders = $_POST['orders'] ?? [];
    $amounts = $_POST['amounts'] ?? [];
    $methods = $_POST['methods'] ?? [];
    $user_id = $_SESSION['user_id'] ?? null;
    $now = date('Y-m-d H:i:s');

    // If bulk (arrays sent), process all, else fallback to single (for compatibility)
    if (is_array($orders) && count($orders) > 0) {
        $pdo->beginTransaction();
        try {
            foreach ($orders as $idx => $order_id) {
                $order_id = intval($order_id);
                $amount = isset($amounts[$idx]) ? floatval($amounts[$idx]) : 0;
                $method = isset($methods[$idx]) ? $methods[$idx] : '';
                if (!$order_id || $amount <= 0 || !in_array($method, ['cash', 'bank'])) {
                    throw new Exception("Order ID, method, and valid amount required (order #$order_id)");
                }
                // Get order info
                $stmt = $pdo->prepare("SELECT grand_total, paid FROM sales_orders WHERE id=?");
                $stmt->execute([$order_id]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$order) throw new Exception("Order #$order_id not found");

                // Insert payment into order_payments
                $stmt = $pdo->prepare("INSERT INTO order_payments (order_id, amount, paid_at, payment_method, created_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$order_id, $amount, $now, $method, $user_id]);

                // Calculate total paid for this order
                $stmt = $pdo->prepare("SELECT SUM(amount) FROM order_payments WHERE order_id=?");
                $stmt->execute([$order_id]);
                $total_paid = floatval($stmt->fetchColumn());

                // Mark as paid, partial paid or unpaid
                $grand_total = floatval($order['grand_total']);
                if ($total_paid <= 0) {
                    $paid_val = 0; // Unpaid
                } elseif ($total_paid >= $grand_total) {
                    $paid_val = 1; // Fully Paid
                } else {
                    $paid_val = 2; // Partial Paid
                }
                $stmt = $pdo->prepare("UPDATE sales_orders SET paid=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$paid_val, $order_id]);
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Single payment fallback (legacy)
    $order_id = intval($_POST['order_id'] ?? 0);
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $method = $_POST['method'] ?? '';
    if (!$order_id || $amount <= 0 || !in_array($method, ['cash', 'bank'])) {
        echo json_encode(['success' => false, 'message' => 'Order ID, method, and valid amount required']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        // Get order info
        $stmt = $pdo->prepare("SELECT grand_total, paid FROM sales_orders WHERE id=?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) throw new Exception('Order not found');

        // Insert payment into order_payments
        $stmt = $pdo->prepare("INSERT INTO order_payments (order_id, amount, paid_at, payment_method, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$order_id, $amount, $now, $method, $user_id]);

        // Calculate total paid for this order
        $stmt = $pdo->prepare("SELECT SUM(amount) FROM order_payments WHERE order_id=?");
        $stmt->execute([$order_id]);
        $total_paid = floatval($stmt->fetchColumn());

        // Mark as paid, partial paid or unpaid
        $grand_total = floatval($order['grand_total']);
        if ($total_paid <= 0) {
            $paid_val = 0; // Unpaid
        } elseif ($total_paid >= $grand_total) {
            $paid_val = 1; // Fully Paid
        } else {
            $paid_val = 2; // Partial Paid
        }
        $stmt = $pdo->prepare("UPDATE sales_orders SET paid=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$paid_val, $order_id]);

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// GET: single or bulk form (order_ids[] for bulk)
header('Content-Type: text/html; charset=UTF-8');
$order_ids = isset($_GET['order_ids']) ? $_GET['order_ids'] : null;
if ($order_ids && is_array($order_ids) && count($order_ids) >= 1) {
    // Bulk payment form
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $pdo = require __DIR__ . '/../../includes/db_connection.php';
    $stmt = $pdo->prepare("SELECT so.id, so.grand_total, so.paid, c.name AS customer_name
        FROM sales_orders so
        LEFT JOIN customers c ON so.customer_id=c.id
        WHERE so.id IN ($placeholders)");
    $stmt->execute(array_map('intval', $order_ids));
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="padding-18-0">
        <form id="bulk-payment-form">
            <table class="table table-bordered table-full-width">
                <thead>
                    <tr>
                        <th>Order Name</th>
                        <th>Grand Total</th>
                        <th>Source</th>
                        <th>Payment Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>
                            #<?= htmlspecialchars($order['id']) ?>
                            <br>
                            <?= htmlspecialchars($order['customer_name']) ?>
                        </td>
                        <td><?= format_currency($order['grand_total']) ?></td>
                        <td>
                            <select name="methods[]" class="form-control">
                                <option value="cash">Cash</option>
                                <option value="bank">Bank</option>
                            </select>
                        </td>
                        <td>
                            <input type="number" min="1" step="0.01" name="amounts[]" class="form-control" value="<?= htmlspecialchars($order['grand_total']) ?>" required />
                            <input type="hidden" name="orders[]" value="<?= intval($order['id']) ?>">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="flex-gap-center">
                <button type="submit" class="btn btn-primary" id="bulk-pay-submit">Submit</button>
                <button type="button" class="btn btn-secondary" id="bulk-pay-cancel">Cancel</button>
            </div>
        </form>
    </div>
    <script>
    // Example usage: handle the cancel button and form submission via AJAX
    $(function(){
        $('#bulk-pay-cancel').on('click', function(){ $('#payment-modal').modal('hide'); });
        $('#bulk-payment-form').on('submit', function(e){
            e.preventDefault();
            var $form = $(this);
            $.post('/entities/sales/mark_payment.php', $form.serialize(), function(resp){
                if(resp.success) {
                    showSuccess('Payments marked successfully');
                    location.reload();
                } else {
                    showError(resp.message || 'Failed to mark payments.');
                }
            }, 'json').fail(function() {
                showError('Network error occurred while marking payments');
            });
        });
    });
    </script>
    <?php
    exit;
}

// Single order fallback
$order_id = intval($_GET['id'] ?? 0);
$grand_total = isset($_GET['grand_total']) ? floatval($_GET['grand_total']) : '';
if (!$order_id) {
    echo '<div class="alert alert-danger">Invalid order.</div>';
    exit;
}

$pdo = require __DIR__ . '/../../includes/db_connection.php';
$stmt = $pdo->prepare("SELECT grand_total, paid FROM sales_orders WHERE id=?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate already paid amount
$stmt = $pdo->prepare("SELECT SUM(amount) as paid_total FROM order_payments WHERE order_id=?");
$stmt->execute([$order_id]);
$paid_total = floatval($stmt->fetchColumn());

// Determine prefill amount for the payment input field
if ($order) {
    $grand_total_val = floatval($order['grand_total']);
    // If partially paid, default to remaining, unless remaining is zero or negative
    if (intval($order['paid']) === 2) {
        $remaining = $grand_total_val - $paid_total;
        $prefill_amount = $remaining > 0 ? $remaining : $grand_total_val;
    } else {
        $prefill_amount = $grand_total !== '' ? $grand_total : $grand_total_val;
    }
} else {
    $prefill_amount = '';
}
?>
<div class="padding-18-0">
    <form id="payment-on-delivery-form">
        <div class="margin-bottom-14px">
            <label>How much was received?</label>
            <input type="number" min="1" step="0.01" id="pod-amount" name="amount" class="form-control" style="margin-top:6px;" value="<?php echo htmlspecialchars($prefill_amount); ?>" required />
            <?php if ($order): ?>
                <small>
                    Order Total: <b><?php echo format_currency($order['grand_total']); ?></b>
                    &nbsp; | &nbsp; Already Paid: <b><?php echo format_currency($paid_total); ?></b>
                    &nbsp; | &nbsp; Remaining: <b><?php echo format_currency($order['grand_total'] - $paid_total); ?></b>
                </small>
            <?php endif; ?>
        </div>
        <div style="margin-bottom:18px;">
            <label>Where was it received?</label>
            <select id="pod-method" name="method" class="form-control" style="margin-top:6px;">
                <option value="cash">Cash</option>
                <option value="bank">Bank</option>
            </select>
        </div>
        <div style="display:flex;gap:14px;justify-content:center;">
            <button type="submit" class="btn btn-primary" id="pod-pay-and-deliver">Submit</button>
            <button type="button" class="btn btn-secondary" id="pod-cancel">Cancel</button>
        </div>
        <input type="hidden" name="order_id" value="<?php echo intval($order_id); ?>">
    </form>
</div>
<script>
$(function(){
    $('#pod-cancel').on('click', function(){ $('#payment-modal').modal('hide'); });
    $('#payment-on-delivery-form').on('submit', function(e){
        e.preventDefault();
        var $form = $(this);
        $.post('/entities/sales/mark_payment.php', $form.serialize(), function(resp){
            if(resp.success) {
                showSuccess('Payment marked successfully');
                location.reload();
            } else {
                showError(resp.message || 'Failed to mark payment.');
            }
        }, 'json').fail(function() {
            showError('Network error occurred while marking payment');
        });
    });
});
</script>