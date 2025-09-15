<?php
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/functions.php';

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$current_user_id = $_SESSION['user_id'] ?? 0;

function show_message($msg, $is_error = false) {
    $alert_class = $is_error ? "alert alert-danger" : "alert alert-success";
    echo '<!DOCTYPE html>
    <html lang="en"><head>
        <meta charset="UTF-8">
        <title>Mark Delivered</title>
        <style>
            body { 
                font-family: "Segoe UI", Arial, sans-serif; 
                background: #f5f7fb; 
                min-height: 100vh; 
                display: flex; 
                align-items: center; 
                justify-content: center; 
            }
            .center-msg-wrap {
                min-width: 340px;
                max-width: 440px;
                margin: 0 auto;
            }
        </style>
    </head><body>
    <div class="center-msg-wrap">
        <div class="' . $alert_class . '" style="font-size:1.2rem; padding:32px 22px; text-align:center; border-radius:12px;">
            ' . $msg . '
        </div>
    </div>
    </body></html>';
    exit;
}

// Check order exists
$stmt = $pdo->prepare("SELECT * FROM sales_orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    show_message("Order not found.", true);
}

// Check assigned rider
$stmt = $pdo->prepare("SELECT rider_id FROM delivery_riders WHERE order_id = ?");
$stmt->execute([$order_id]);
$assignment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assignment) {
    show_message("No rider assignment found for this order. Please assign a rider first.", true);
}

$assigned_rider_id = $assignment['rider_id'];

// Only assigned rider can mark delivered (no admin exception)
if ($assigned_rider_id != $current_user_id) {
    show_message("You are not authorized to mark this order as delivered. Only the assigned rider can perform this action.", true);
}

// Already delivered?
if ($order['delivered']) {
    show_message("Order #{$order_id} is already marked as delivered.");
}

// Calculate total payments so far
$stmt = $pdo->prepare("SELECT IFNULL(SUM(amount),0) AS total_paid FROM order_payments WHERE order_id = ?");
$stmt->execute([$order_id]);
$total_paid = floatval($stmt->fetchColumn());
$grand_total = floatval($order['grand_total']);
$outstanding_amount = $grand_total - $total_paid;

// If order is unpaid and no POST yet, show payment modal
if ($order['paid'] == 0 && $outstanding_amount > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $outstanding_amount_fmt = format_currency($outstanding_amount, false);
    $order_no = htmlspecialchars($order['id']);
    $customer_name = htmlspecialchars($order['customer_name'] ?? '');
    $modal_id = "markDeliveredModal";
    $overlay_id = "modalOverlay";
    $close_btn_id = "closeModalBtn";
    $error_id = "modalError";
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Mark Delivered - Payment Received</title>
        <style>
            body { background: #f5f7fb; min-height:100vh; margin:0; }
            .modal { display: flex !important; align-items: center; justify-content: center; }
            .modal .modal-close {
                position: absolute;
                top: 18px;
                right: 22px;
                background: none;
                border: none;
                font-size: 1.7em;
                color: #888;
                cursor: pointer;
                z-index: 2;
                line-height: 1;
            }
            .modal label { margin-bottom: 7px; font-weight: 500; color: #222; }
            .modal .order { color:#007bff; font-weight:600; margin-bottom:10px; text-align:center; }
            .modal .desc { color:#444; text-align:center; margin-bottom:14px; }
        </style>
    </head>
    <body>
        <div class="modal show" id="$overlay_id">
            <form class="modal-content" id="$modal_id" method="post" autocomplete="off">
                <button type="button" class="modal-close" id="$close_btn_id" title="Cancel">&times;</button>
                <h2 style="margin-top:0; color:#007bff; text-align:center;">Payment Received?</h2>
                <div class="order">Order #{$order_no} &mdash; {$customer_name}</div>
                <div class="desc">
                    Outstanding amount: <b>{$outstanding_amount_fmt}</b><br>
                    This order is <b>unpaid</b>. Did you receive a payment from the customer?
                </div>
                <div class="form-group" style="margin-bottom:16px;">
                    <label for="amount">Amount Received</label>
                    <input type="number" step="0.01" min="0" max="{$outstanding_amount_fmt}" name="amount" id="amount" value="{$outstanding_amount_fmt}" required autocomplete="off" class="form-control">
                </div>
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="payment_method">Payment Method</label>
                    <select name="payment_method" id="payment_method" required class="form-control">
                        <option value="cash">Cash</option>
                        <option value="bank">Bank</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-primary-full">Record Payment &amp; Mark Delivered</button>
                <div class="alert alert-danger" id="$error_id" style="display:none; margin-top:12px; text-align:center; font-size:1em;"></div>
            </form>
        </div>
        <script>
            document.getElementById('$close_btn_id').onclick = function() {
                window.close();
            };
            document.getElementById('$modal_id').onsubmit = function(e) {
                var amt = parseFloat(document.getElementById('amount').value);
                var maxAmt = parseFloat(document.getElementById('amount').max);
                var err = document.getElementById('$error_id');
                if(isNaN(amt) || amt <= 0) {
                    err.innerText = "Please enter a valid payment amount.";
                    err.style.display = "block";
                    e.preventDefault();
                    return false;
                }
                if(amt > maxAmt) {
                    err.innerText = "Amount cannot be greater than the outstanding balance.";
                    err.style.display = "block";
                    e.preventDefault();
                    return false;
                }
                err.innerText = "";
                err.style.display = "none";
            };
            document.getElementById("amount").focus();
        </script>
    </body>
    </html>
    HTML;
    exit;
}

// If POST (handling payment if unpaid)
if ($order['paid'] == 0 && $outstanding_amount > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_method = in_array(($_POST['payment_method'] ?? ''), ['cash','bank']) ? $_POST['payment_method'] : 'cash';
    if ($amount <= 0) {
        show_message("Error: Please enter a valid amount.", true);
    }
    if ($amount > $outstanding_amount) {
        show_message("Error: Amount cannot be greater than the outstanding balance.", true);
    }
    // Use the reusable function for marking delivered
    $result = markOrderDelivered($pdo, $order_id, $current_user_id, $amount, $payment_method);
    show_message($result['message'], !$result['success']);
}

// If paid, mark delivered as usual
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $result = markOrderDelivered($pdo, $order_id, $current_user_id);
    show_message($result['message'], !$result['success']);
}
?>