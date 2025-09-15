<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';

// SUPPORT BOTH id and ids IN QUERY PARAMS
$ids = [];
if (isset($_GET['ids'])) {
    // Filter: Only positive integers, remove zero/empty values
    $ids = array_filter(array_map('intval', explode(',', $_GET['ids'])), function($v) { return $v > 0; });
} elseif (isset($_GET['id'])) {
    $ids = [intval($_GET['id'])];
}
if (empty($ids)) die("No order IDs provided.");

$orders = [];
if (!empty($ids)) {
    // Always build the correct number of placeholders for the filtered IDs
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM sales_orders WHERE id IN ($in)");
    $stmt->execute(array_values($ids));
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $orders[$row['id']] = $row;
    }
}
if (empty($orders)) die("No valid orders found.");

$customer_ids = [];
foreach ($orders as $order) $customer_ids[] = $order['customer_id'];
$customer_ids = array_unique($customer_ids);
$customers = [];
if ($customer_ids) {
    // Fix: build correct number of placeholders for customer IDs too!
    $in  = implode(',', array_fill(0, count($customer_ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id IN ($in)");
    $stmt->execute(array_values($customer_ids));
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $customers[$row['id']] = $row;
    }
}

function get_items($pdo, $order_id) {
    $stmt = $pdo->prepare("
        SELECT oi.*, i.name as item_name
        FROM order_items oi
        LEFT JOIN items i ON oi.item_id = i.id
        WHERE oi.order_id = ? AND oi.meal_id IS NULL
    ");
    $stmt->execute([$order_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_meals($pdo, $order_id) {
    $stmt = $pdo->prepare("
        SELECT om.*, m.name as meal_name
        FROM order_meals om
        LEFT JOIN meals m ON om.meal_id = m.id
        WHERE om.order_id = ?
    ");
    $stmt->execute([$order_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Print Invoices</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

<style>
body {
    background: #f6f9fc;
    margin: 0;
    padding: 0;
    font-family: 'Segoe UI', Arial, sans-serif;
    color: #222;
}
.no-print-center {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    margin-top: 32px;
    margin-bottom: 16px;
}
.print-btn {
    margin-bottom: 10px;
}
.print-text {
    margin-bottom: 0;
    font-size: 1.08rem;
    color: #333;
}
.invoice-box {
    max-width: 700px;
    margin: 30px auto 60px auto;
    padding: 32px 32px 24px 32px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 32px rgba(0,0,0,0.07);
    font-size: 1.08rem;
    position: relative;
    page-break-after: always;
}
.invoice-box:last-child {
    page-break-after: auto;
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
.invoice-action-row {
    display: flex;
    justify-content: flex-end;
    gap: 18px;
    margin-bottom: 16px;
    width: 100%;
}
.icon-btn {
    background: #1877F2;
    color: #fff;
    border: none;
    border-radius: 50%;
    font-size: 1.7rem;
    width: 52px;
    height: 52px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background .18s;
    box-shadow: 0 1px 6px rgba(0,0,0,0.11);
    outline: none;
}
.icon-btn i {
    font-size: 1.3rem;
}
.icon-btn.print-btn {
    background: #1877F2;
}
.icon-btn.print-btn:hover {
    background: #1451a3;
}
.icon-btn.whatsapp {
    background: #25D366;
}
.icon-btn.whatsapp:hover {
    background: #128C7E;
}
@media print {
    .print-btn, .invoice-action-row, .no-print, .no-print-center, .modal-backdrop, .modal-print-size {
        display: none !important;
    }
    body, .invoice-box {
        background: #fff !important;
        box-shadow: none !important;
    }
    .invoice-logo-row {
        margin-bottom: 12px !important;
    }
    .invoice-box {
        page-break-after: always;
    }
    .invoice-box:last-child {
        page-break-after: auto;
    }
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

/* Modal Styles */
.modal-backdrop {
    position: fixed;
    top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(0,0,0,0.25);
    z-index: 2000;
}
.modal-print-size {
    position: fixed;
    top: 50%; left: 50%; transform: translate(-50%, -50%);
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 32px rgba(0,0,0,0.15);
    z-index: 2100;
    padding: 32px 28px 24px 28px;
    min-width: 300px;
    max-width: 95vw;
}
.modal-print-size h2 {
    margin-top: 0;
    font-size: 1.27rem;
    color: #1877F2;
    letter-spacing: -0.5px;
    margin-bottom: 19px;
}
.modal-print-size label {
    display: block;
    font-size: 1.06rem;
    margin: 9px 0 5px 0;
}
.modal-print-size select, .modal-print-size button {
    margin-bottom: 10px;
}
.modal-print-size .btn-row {
    margin-top: 18px;
    text-align: right;
}
.modal-print-size button {
    background: #1877F2;
    color: #fff;
    border: none;
    border-radius: 7px;
    font-size: 1rem;
    padding: 7px 18px;
    margin-left: 8px;
    cursor: pointer;
    transition: background .15s;
    outline: none;
}
.modal-print-size button.cancel {
    background: #aaa;
}
</style>
</head>
<body>
<div class="no-print no-print-center">
    <button class="icon-btn print-btn" id="printInvoicesBtn" title="Print All Invoices">
        <i class="fa fa-print"></i>
    </button>
    <span class="print-text">Print all invoices at once</span>
</div>
<?php foreach ($ids as $order_id): 
    if (!isset($orders[$order_id])) continue;
    $order = $orders[$order_id];
    $customer = isset($customers[$order['customer_id']]) ? $customers[$order['customer_id']] : null;
    $items = get_items($pdo, $order_id);
    $meals = get_meals($pdo, $order_id);
    $order_date = format_datetime($order['order_date'], get_user_timezone(), 'Y-m-d');
    $print_date = format_datetime(date('Y-m-d H:i:s'), get_user_timezone(), 'Y-m-d');
    $customer_name = $customer ? $customer['name'] : 'N/A';
    $customer_phone = $customer ? $customer['contact'] : '';
    $customer_address = '';
    if ($customer) {
        $address_parts = [];
        if (!empty($customer['house_no'])) $address_parts[] = $customer['house_no'];
        if (!empty($customer['area'])) $address_parts[] = $customer['area'];
        if (!empty($customer['city'])) $address_parts[] = $customer['city'];
        $customer_address = implode(', ', array_filter($address_parts));
    }
    $discount = (float)$order['discount'];
    $delivery = (float)$order['delivery_charges'];
    $grand_total = (float)$order['grand_total'];

    // Use standard order number formatting
    $display_order_no = format_order_number($order_id);

    // WhatsApp message
    $wa_number = preg_replace('/\D/', '', $customer_phone);
    $wa_message = "🧾 *Invoice*\n";
    $wa_message .= "*Order # {$display_order_no}*\n";
    $wa_message .= "*Order Date:* {$order_date}\n";
    $wa_message .= "----------------------------\n";
    
    // Add meals to WhatsApp message
    foreach ($meals as $meal) {
        $meal_name = $meal['meal_name'];
        $qty = (float)$meal['qty'];
        $price_per_meal = (float)$meal['price_per_meal'];
        $total = $qty * $price_per_meal;
        $wa_message .= "  {$meal_name} x{$qty} = *" . format_currency($total, false) . "*\n";
        
        // Add meal sub-items
        $meal_items = get_meal_items($pdo, $order_id, $meal['meal_id']);
        foreach ($meal_items as $meal_item) {
            $item_name = $meal_item['item_name'];
            $item_qty = (int)$meal_item['qty'];
            $wa_message .= "    - {$item_name} x{$item_qty}\n";
        }
    }
    
    // Add individual items to WhatsApp message
    foreach ($items as $i => $item) {
        $item_name = $item['item_name'];
        $pack_size = (int)$item['pack_size'];
        $qty = (int)$item['qty'];
        // Calculate packs: qty/pack_size, but if pack_size is 0, default to qty
        $packs = ($pack_size > 0) ? ($qty / $pack_size) : $qty;
        $packs_disp = ($pack_size > 0) ? $pack_size . " x " . (int)$packs : $qty;
        $wa_message .= "  {$item_name} {$packs_disp} = *" . format_currency($item['total'], false) . "*\n";
    }
    $wa_message .= "----------------------------\n";
    $wa_message .= "  *Order Total:* " . format_currency((float)$order['amount'], false) . "\n";
    if ($discount) $wa_message .= "  *Discount:* " . format_currency($discount, false) . "\n";
    if ($delivery) $wa_message .= "  *D.C:* " . format_currency($delivery, false) . "\n";
    $wa_message .= "  *Grand Total:* _" . format_currency($grand_total, false) . "_\n";
    $wa_message .= "----------------------------\n";
    $wa_message .= "  Thank you for your order! We'll be looking forward to serve you again! ";
    $wa_link = "https://wa.me/{$wa_number}?text=" . rawurlencode($wa_message);
?>
<div class="invoice-box">
    <div class="invoice-logo-row">
        <img src="/assets/img/logo.png" class="invoice-logo" alt="Logo">
    </div>
    <div class="invoice-action-row no-print">
        <!-- Print icon button (opens modal for all) -->
        <button class="icon-btn print-single-btn" title="Print Invoice" data-order="<?= $order_id ?>">
            <i class="fa fa-print"></i>
        </button>
        <!-- WhatsApp icon button -->
        <?php if ($wa_number): ?>
        <a
            class="icon-btn whatsapp"
            title="Send by WhatsApp"
            href="<?= $wa_link ?>"
            target="_blank"
        >
            <i class="fab fa-whatsapp"></i>
        </a>
        <?php endif; ?>
    </div>
    <div class="invoice-header">
        <div class="flex-align-center">
            <div>
                <div class="invoice-title">INVOICE</div>
                <div class="order-no-text">Order #<?= htmlspecialchars($display_order_no) ?></div>
            </div>
        </div>
        <div class="invoice-meta">
            <div><b>Order Date:</b> <?= htmlspecialchars($order_date) ?></div>
            <div><b>Print Date:</b> <?= htmlspecialchars($print_date) ?></div>
        </div>
    </div>
    <div class="invoice-customer">
        <strong><?= htmlspecialchars($customer_name) ?></strong><br>
        <?php if ($customer_phone): ?>
        <span>
            <a href="https://wa.me/<?= $wa_number ?>" target="_blank" style="color:#25D366;text-decoration:none;">
                <?= htmlspecialchars($customer_phone) ?>
            </a>
        </span><br>
        <?php endif; ?>
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
            ?>
            
            <?php 
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
<?php endforeach; ?>

<!-- Print Size Modal (hidden by default, global for all) -->
<div class="modal-backdrop" id="printModalBackdrop" style="display:none;"></div>
<div class="modal-print-size" id="printModal" style="display:none;">
    <h2>Select Invoice Size</h2>
    <label for="printType">Choose Type:</label>
    <select id="printType">
        <option value="A4">A4 (Standard)</option>
        <option value="Thermal">Thermal Printer</option>
    </select>
    <div id="thermalOptions" style="display:none;">
        <label for="thermalSize">Thermal Size:</label>
        <select id="thermalSize">
            <option value="58mm">58mm</option>
            <option value="80mm">80mm</option>
            <option value="other">Other</option>
        </select>
    </div>
    <div class="btn-row">
        <button type="button" class="cancel" id="cancelPrintBtn">Cancel</button>
        <button type="button" id="confirmPrintBtn">Print</button>
    </div>
</div>
<script>
const printBtn = document.getElementById('printInvoicesBtn');
const printModal = document.getElementById('printModal');
const printBackdrop = document.getElementById('printModalBackdrop');
const printType = document.getElementById('printType');
const thermalOptions = document.getElementById('thermalOptions');
const thermalSize = document.getElementById('thermalSize');
const confirmPrintBtn = document.getElementById('confirmPrintBtn');
const cancelPrintBtn = document.getElementById('cancelPrintBtn');
let selectedPrintType = "A4";
let selectedThermalSize = "58mm";

// Print All Invoices
if (printBtn) {
    printBtn.addEventListener('click', function(e) {
        e.preventDefault();
        printModal.style.display = "block";
        printBackdrop.style.display = "block";
        document.body.style.overflow = "hidden";
    });
}
// Also allow per-invoice print button to open modal
document.querySelectorAll('.print-single-btn').forEach(btn => {
    btn.addEventListener('click', function(e){
        e.preventDefault();
        printModal.style.display = "block";
        printBackdrop.style.display = "block";
        document.body.style.overflow = "hidden";
    });
});

cancelPrintBtn.addEventListener('click', function() {
    printModal.style.display = "none";
    printBackdrop.style.display = "none";
    document.body.style.overflow = "";
});

printType.addEventListener('change', function() {
    if (printType.value === "Thermal") {
        thermalOptions.style.display = 'block';
    } else {
        thermalOptions.style.display = 'none';
    }
});

confirmPrintBtn.addEventListener('click', function() {
    selectedPrintType = printType.value;
    selectedThermalSize = thermalSize.value;
    printModal.style.display = "none";
    printBackdrop.style.display = "none";
    document.body.style.overflow = "";

    document.body.classList.remove('print-a4', 'print-thermal-58', 'print-thermal-80', 'print-thermal-other');
    if (selectedPrintType === "A4") {
        document.body.classList.add('print-a4');
        window.print();
    } else if (selectedPrintType === "Thermal") {
        if (selectedThermalSize === "58mm") {
            document.body.classList.add('print-thermal-58');
        } else if (selectedThermalSize === "80mm") {
            document.body.classList.add('print-thermal-80');
        } else {
            document.body.classList.add('print-thermal-other');
        }
        window.print();
    }
});

// Print CSS for different printer types
const style = document.createElement('style');
style.innerHTML = `
@media print {
    body.print-a4 .invoice-box {
        max-width: 700px !important;
        width: 100% !important;
        font-size: 1.08rem !important;
    }
    body.print-thermal-58 .invoice-box {
        max-width: 200px !important;
        width: 58mm !important;
        font-size: 0.95rem !important;
        padding: 8px 4px !important;
        border-radius: 0 !important;
    }
    body.print-thermal-80 .invoice-box {
        max-width: 285px !important;
        width: 80mm !important;
        font-size: 1.08rem !important;
        padding: 10px 8px !important;
        border-radius: 0 !important;
    }
    body.print-thermal-other .invoice-box {
        max-width: 250px !important;
        width: 70mm !important;
        font-size: 1rem !important;
        padding: 8px 4px !important;
        border-radius: 0 !important;
    }
    body.print-thermal-58 .invoice-logo,
    body.print-thermal-80 .invoice-logo,
    body.print-thermal-other .invoice-logo {
        height: 38px !important;
        max-width: 120px !important;
    }
    body.print-thermal-58 .invoice-header,
    body.print-thermal-80 .invoice-header,
    body.print-thermal-other .invoice-header {
        flex-direction: column !important;
        gap: 6px !important;
        align-items: flex-start !important;
        margin-bottom: 8px !important;
    }
    body.print-thermal-58 .invoice-title,
    body.print-thermal-80 .invoice-title,
    body.print-thermal-other .invoice-title {
        font-size: 1.13rem !important;
    }
    body.print-thermal-58 .invoice-meta,
    body.print-thermal-80 .invoice-meta,
    body.print-thermal-other .invoice-meta {
        font-size: 0.93rem !important;
    }
    body.print-thermal-58 .invoice-customer,
    body.print-thermal-80 .invoice-customer,
    body.print-thermal-other .invoice-customer {
        font-size: 0.95rem !important;
        margin-bottom: 10px !important;
    }
    body.print-thermal-58 .invoice-table,
    body.print-thermal-80 .invoice-table,
    body.print-thermal-other .invoice-table {
        font-size: 0.95rem !important;
    }
    body.print-thermal-58 .invoice-summary,
    body.print-thermal-80 .invoice-summary,
    body.print-thermal-other .invoice-summary {
        font-size: 0.95rem !important;
    }
}
`;
document.head.appendChild(style);
</script>
</body>
</html>