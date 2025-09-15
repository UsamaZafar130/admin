<?php
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/functions.php';

// Fetch order by public token
$order = null;
$order_id = null;
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $stmt = $pdo->prepare("SELECT * FROM sales_orders WHERE public_token = ?");
    $stmt->execute([$token]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($order) {
        $order_id = $order['id'];
    }
}

if (!$order) {
    die("Invoice not found or invalid token.");
}

// --- New section: If order is paid, show modal and exit ---
if ($order['paid'] == 1): ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Invoice Paid</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body {
      background: #f6f9fc;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Arial, sans-serif;
    }
    .paid-modal-backdrop {
      position: fixed;
      z-index: 9999;
      top: 0; left: 0; width: 100vw; height: 100vh;
      background: rgba(30,40,60,0.24);
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .paid-modal {
      background: #fff;
      border-radius: 14px;
      max-width: 380px;
      width: 98vw;
      margin: 40px auto;
      box-shadow: 0 4px 40px rgba(0,0,0,0.17);
      padding: 32px 28px 24px 28px;
      position: relative;
      text-align: center;
    }
    .paid-modal h2 {
      margin: 0 0 18px 0;
      color: #1565c0;
      font-size: 1.35em;
      font-weight: 700;
    }
    .paid-modal p {
      color: #444;
      font-size: 1.11em;
      margin-bottom: 20px;
    }
    .admin-wa-link {
      display: inline-block;
      background: #25D366;
      color: #fff !important;
      font-weight: 600;
      border-radius: 6px;
      padding: 10px 24px;
      font-size: 1.07em;
      text-decoration: none;
      transition: background 0.15s, color 0.15s;
    }
    .admin-wa-link:hover {
      background: #128C7E;
      color: #fff;
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="paid-modal-backdrop">
    <div class="paid-modal">
      <h2>This Invoice is Already Paid</h2>
      <p>
        Either this invoice has already been paid,<br>
        or you need some assistance from the <br>
        <b>FrozoFun Admin</b>.
      </p>
      <a href="https://wa.me/923223300130" target="_blank" class="admin-wa-link">
        Contact FrozoFun Admin on WhatsApp
      </a>
    </div>
  </div>
</body>
</html>
<?php exit; endif; ?>

<?php
// --- FETCH CUSTOMER ---
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$order['customer_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// --- FETCH ITEMS ---
$stmt = $pdo->prepare("
    SELECT oi.*, i.name as item_name
    FROM order_items oi
    LEFT JOIN items i ON oi.item_id = i.id
    WHERE oi.order_id = ? AND oi.meal_id IS NULL
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- FETCH MEALS ---
$stmt = $pdo->prepare("
    SELECT om.*, m.name as meal_name
    FROM order_meals om
    LEFT JOIN meals m ON om.meal_id = m.id
    WHERE om.order_id = ?
");
$stmt->execute([$order_id]);
$meals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- FUNCTION TO GET MEAL ITEMS ---
function get_meal_items($pdo, $order_id, $meal_id) {
    $stmt = $pdo->prepare("
        SELECT oi.*, i.name as item_name
        FROM order_items oi
        LEFT JOIN items i ON oi.item_id = i.id
        WHERE oi.order_id = ? AND oi.meal_id = ?
    ");
    $stmt->execute([$order_id, $meal_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- DATA FOR INVOICE ---
$order_date = date('Y-m-d', strtotime($order['order_date']));
$customer_name = $customer ? $customer['name'] : 'N/A';
$customer_address = '';
if ($customer) {
    $address_parts = [];
    if (!empty($customer['house_no'])) $address_parts[] = $customer['house_no'];
    if (!empty($customer['area'])) $address_parts[] = $customer['area'];
    if (!empty($customer['city'])) $address_parts[] = $customer['city'];
    $customer_address = implode(', ', array_filter($address_parts));
}

// --- INVOICE TOTALS ---
$discount = (float)$order['discount'];
$delivery = (float)$order['delivery_charges'];
$grand_total = (float)$order['grand_total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice #<?= format_order_number($order_id) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- Font Awesome for icons (optional, for design) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
body {
    background: #f6f9fc;
    margin: 0;
    padding: 0;
    font-family: 'Segoe UI', Arial, sans-serif;
    color: #222;
}
.invoice-box {
    max-width: 700px;
    margin: 30px auto;
    padding: 32px 32px 24px 32px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 32px rgba(0,0,0,0.07);
    font-size: 1.08rem;
    position: relative;
}
.invoice-logo-row {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 22px;
}
.invoice-logo {
    height: 105px;
    max-width: 450px;
    display: block;
}
.invoice-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
}
.invoice-title {
    color: #1877F2;
    font-size: 2.2rem;
    font-weight: 700;
    margin: 0;
    letter-spacing: -0.5px;
}
.invoice-meta {
    text-align: right;
    font-size: 1.02rem;
    color: #444;
}
.invoice-customer {
    margin-bottom: 28px;
}
.invoice-customer strong {
    color: #0070e0;
    font-size: 1.12rem;
}
.invoice-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 18px;
}
.invoice-table th, .invoice-table td {
    border-bottom: 1.5px solid #eaf0fa;
    padding: 10px 6px;
    text-align: left;
}
.invoice-table th {
    background: #f1f6fd;
    color: #1877F2;
    font-weight: 700;
}
.invoice-table td {
    color: #24282e;
}
.invoice-summary {
    margin-top: 10px;
    width: 100%;
    font-size: 1.08rem;
}
.invoice-summary td {
    text-align: right;
    padding: 6px 6px;
}
.invoice-summary .label {
    color: #888;
    font-weight: 500;
}
.invoice-summary .value {
    color: #1849a8;
    font-weight: 600;
}
.invoice-summary .grand {
    color: #1877F2;
    font-size: 1.18rem;
    font-weight: 700;
    border-top: 2px solid #eaf0fa;
}
@media print {
    body, .invoice-box {
        background: #fff !important;
        box-shadow: none !important;
    }
    .invoice-logo-row {
        margin-bottom: 12px !important;
    }
}
</style>
</head>
<body>
<div class="invoice-box" id="invoiceBox">
    <div class="invoice-logo-row">
        <img src="/assets/img/logo.png" class="invoice-logo" alt="Logo">
    </div>
    <div class="invoice-header">
        <div style="display: flex; align-items: center;">
            <div>
                <div class="invoice-title">INVOICE</div>
                <div style="margin-top:6px;color:#888;font-size:1.07rem;">Order #<?= format_order_number($order_id) ?></div>
            </div>
        </div>
        <div class="invoice-meta">
            <div><b>Order Date:</b> <?= htmlspecialchars($order_date) ?></div>
            <div><b>Print Date:</b> <?= date('Y-m-d') ?></div>
        </div>
    </div>
    <div class="invoice-customer">
        <strong><?= htmlspecialchars($customer_name) ?></strong><br>
        <?php if ($customer_address): ?>
        <span><?= htmlspecialchars($customer_address) ?></span>
        <?php endif; ?>
    </div>
    <table class="invoice-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>Qty</th>
                <th>Pack Size</th>
                <th>Price/Unit</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $row_counter = 1;
            // Display meals first with their sub-items
            if (!empty($meals)):
                foreach ($meals as $meal): 
                    $meal_total = (float)$meal['qty'] * (float)$meal['price_per_meal'];
            ?>
            <tr>
                <td><?= $row_counter++ ?></td>
                <td><?= htmlspecialchars($meal['meal_name'] ?? 'Meal #'.$meal['meal_id']) ?> <em>(Meal)</em></td>
                <td><?= number_format((float)$meal['qty'], 2) ?></td>
                <td>1</td>
                <td><?= format_currency((float)$meal['price_per_meal']) ?></td>
                <td><?= format_currency($meal_total) ?></td>
            </tr>
            <?php 
                    // Display meal sub-items
                    $meal_items = get_meal_items($pdo, $order_id, $meal['meal_id']);
                    foreach ($meal_items as $meal_item): 
            ?>
            <tr style="background-color: #f8f9fa;">
                <td></td>
                <td style="padding-left: 20px;">- <?= htmlspecialchars($meal_item['item_name'] ?? 'Item #'.$meal_item['item_id']) ?></td>
                <td><?= number_format((float)$meal_item['qty'], 2) ?></td>
                <td><?= number_format((float)$meal_item['pack_size'], 2) ?></td>
                <td>-</td>
                <td>-</td>
            </tr>
            <?php 
                    endforeach;
                endforeach;
            endif;
            
            // Display individual items
            foreach ($items as $item): 
            ?>
            <tr>
                <td><?= $row_counter++ ?></td>
                <td><?= htmlspecialchars($item['item_name'] ?? 'Item #'.$item['item_id']) ?></td>
                <td><?= number_format((float)$item['qty'], 2) ?></td>
                <td><?= number_format((float)$item['pack_size'], 2) ?></td>
                <td><?= format_currency((float)$item['price_per_unit']) ?></td>
                <td><?= format_currency((float)$item['total']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <table class="invoice-summary">
        <tr>
            <td class="label">Order Total</td>
            <td class="value"><?= format_currency((float)$order['amount']) ?></td>
        </tr>
        <tr>
            <td class="label">Discount</td>
            <td class="value"><?= format_currency($discount) ?></td>
        </tr>
        <tr>
            <td class="label">Delivery Charges</td>
            <td class="value"><?= format_currency($delivery) ?></td>
        </tr>
        <tr>
            <td class="label grand">Grand Total</td>
            <td class="value grand"><?= format_currency($grand_total) ?></td>
        </tr>
    </table>
    <div style="margin-top:30px;color:#888;font-size:1.01rem;text-align:center;">
        Thank you for your business!
    </div>
</div>
</body>
</html>