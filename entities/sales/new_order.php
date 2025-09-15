<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/auth_check.php';
$pdo = require __DIR__ . '/../../includes/db_connection.php';

// --- AJAX for dropdowns and editing ---
if (isset($_GET['ajax'])) {
    if ($_GET['ajax'] === 'item_list') {
        session_write_close();
        $list = $pdo->query("SELECT id, name, default_pack_size, price_per_unit FROM items ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode($list);
        exit;
    }
    if ($_GET['ajax'] === 'order_items' && isset($_GET['id'])) {
        session_write_close();
        $stmt = $pdo->prepare("SELECT item_id, qty, pack_size, price_per_unit FROM order_items WHERE order_id = ?");
        $stmt->execute([$_GET['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode($items);
        exit;
    }
    if ($_GET['ajax'] === 'order_header' && isset($_GET['id'])) {
        session_write_close();
        $stmt = $pdo->prepare("SELECT customer_id, amount, discount, delivery_charges, grand_total FROM sales_orders WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $orderFields = $stmt->fetch(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode($orderFields);
        exit;
    }
    if ($_GET['ajax'] === 'meal_list') {
        session_write_close();
        $list = $pdo->query("SELECT id, name, price FROM meals WHERE active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode($list);
        exit;
    }
}

// --- Inline/modal mode: only output form with no surrounding layout ---
$inline = isset($_GET['inline']) && $_GET['inline'] == 1;
$order_id = ($inline && isset($_GET['id'])) ? intval($_GET['id']) : null;
// $order variable and related PHP display logic are not needed, as JS does all the prefill

?>
<style>
body, input, select, button, .form-control, #items-table, .order-summary-wrap, .order-summary-wrap label, .order-summary-wrap input, .order-summary-wrap th, .order-summary-wrap td, .inline-flex, .refresh-btn, .icon-btn {
    font-size: 1em;
}
/* ... (all your original inline CSS unchanged) ... */
.ts-wrapper.tomselect, .ts-control, .ts-dropdown {
    min-width:220px;
    width:auto;
    font-family: inherit !important;
    font-size: 1em !important;
}
#items-table {
    width:auto;
    max-width: 1100px;
    border-collapse: collapse;
    margin-bottom: 2px;
    table-layout: auto;
    background: #fff;
    box-shadow: 0 0 2px #eee;
    font-family: inherit !important;
    font-size: 1em;
}
#meals-table {
    width:auto;
    max-width: 1100px;
    border-collapse: collapse;
    margin-bottom: 2px;
    table-layout: auto;
    background: #fff;
    box-shadow: 0 0 2px #eee;
    font-family: inherit !important;
    font-size: 1em;
}
#items-table th, #items-table td, #meals-table th, #meals-table td {
    text-align: left;
    padding: 4px 8px;
    vertical-align: middle;
    font-family: inherit !important;
    font-size: 1em;
}
#items-table th, #meals-table th {
    color: #007bff;
    font-weight: 600;
    background: none;
    border-bottom: none;
    white-space: nowrap;
}
#items-table td, #meals-table td {
    white-space: pre;
    overflow: visible;
    width:99%;
    background: none;
}
#items-table th:nth-child(1), #items-table td:nth-child(1) { width: 220px; }
#items-table th:nth-child(2), #items-table td:nth-child(2) { width: 90px; }
#items-table th:nth-child(3), #items-table td:nth-child(3) { width: 90px; }
#items-table th:nth-child(4), #items-table td:nth-child(4) { width: 90px; }
#items-table th:nth-child(5), #items-table td:nth-child(5) { width: 90px; }
#items-table th:nth-child(6), #items-table td:nth-child(6) { width: 90px; }
#meals-table th:nth-child(1), #meals-table td:nth-child(1) { width: 220px; }
#meals-table th:nth-child(2), #meals-table td:nth-child(2) { width: 90px; }
#meals-table th:nth-child(3), #meals-table td:nth-child(3) { width: 90px; }
#meals-table th:nth-child(4), #meals-table td:nth-child(4) { width: 90px; }
#meals-table th:nth-child(5), #meals-table td:nth-child(5) { width: 90px; }
#items-table input, #items-table select, #meals-table input, #meals-table select {
    width: 100%;
    min-width: 50px;
    box-sizing: border-box;
    font-size: 1em;
    padding: 4px 5px;
    margin: 0;
    font-family: inherit !important;
}
.inline-flex {
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: inherit !important;
    font-size: 1em;
}
.refresh-btn {
    background: none;
    border: none;
    color: #007bff;
    cursor: pointer;
    padding: 2px 6px;
    font-size: 1.2em;
    vertical-align: middle;
    font-family: inherit !important;
    font-size: 1em;
}
.refresh-btn:active { color: #0056b3; }
.icon-btn {
    background: none;
    border: none;
    outline: none;
    box-shadow: none;
    padding: 0;
    margin: 0;
    cursor: pointer;
    font-size: 2em;
    line-height: 1;
    color: #007bff !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    vertical-align: middle;
    transition: none;
    font-family: inherit !important;
}
.icon-btn:focus { outline: none; }
.trash-btn {
    font-size: 1em !important;
    color: #007bff !important;
}
.trash-btn .fa-trash {
    font-size: 1em !important;
    color: #007bff !important;
    border: none !important;
    box-shadow: none !important;
    background: none !important;
    outline: none !important;
}
.trash-btn:focus { outline: none; }
.center-btn {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
}
.order-summary-outer {
    display: flex;
    justify-content: flex-end;
}
.order-summary-wrap {
    background: #fafbfc;
    border: 1px solid #f1f1f1;
    border-radius: 8px;
    padding: 10px 14px;
    max-width: 400px;
    min-width: 330px;
    margin-bottom: 8px;
    position: relative;
    box-shadow: 0 4px 16px #e1e1e1, 0 1px 4px #e0e0e0;
    font-family: inherit !important;
}
.order-summary-wrap label {
    width: 130px;
    margin-bottom: 0;
    font-family: inherit !important;
    font-size: 1em;
}
.order-summary-wrap .row {
    display: flex;
    align-items: center;
    margin-bottom: 5px;
    font-family: inherit !important;
    font-size: 1em;
}
.order-summary-wrap input[type='number'],
.order-summary-wrap input[type='text'].order-output {
    width: 90px;
    display: inline-block;
    margin-left: 6px;
    margin-right: 6px;
    font-weight: bold;
    text-align: right;
    font-family: inherit !important;
    font-size: 1em;
}
.order-summary-wrap input[type='text'].order-output {
    background: #fff;
    color: #007bff;
    border: none;
    outline: none;
    pointer-events: none;
}
.order-summary-wrap .output-field {
    font-weight: bold;
    min-width: 80px;
    display: inline-block;
    color: #007bff;
    font-family: inherit !important;
    font-size: 1em;
}
.order-summary-wrap .discount-stack {
    display: flex,
    flex-direction: column;
    margin-left: 0;
    margin-right: 0;
    width: 100%;
    font-family: inherit !important;
    font-size: 1em;
}
.order-summary-wrap .discount-stack .row {
    margin-bottom: 0;
}
#apply-rounding-btn {
    display: none;
    background: none !important;
    border: none !important;
    box-shadow: none;
    font-size: 1em;
    color: #007bff !important;
    padding: 0;
    cursor: pointer;
    margin: 0 auto;
    transition: none;
    width: 1.15em;
    height: 1.15em;
    border-radius: 50%;
    position: static;
    font-family: inherit !important;
}
#apply-rounding-btn .fa-magic {
    font-size: 1.1em;
    display: block;
    color: #007bff !important;
    border: none !important;
    background: none !important;
}
#apply-rounding-btn .rounding-label {
    display: none;
}
@media (max-width: 767px) {
    .order-summary-outer {
        justify-content: flex-start;
    }
    .order-summary-wrap {
        min-width: 0;
        width: 100%;
        max-width: 100%;
    }
    #apply-rounding-btn {
        width: 1.35em;
        height: 1.35em;
    }
}
.logo-wrap { display: none; }
</style>
<div class="container<?= $inline ? '' : ' mt-4' ?>">
    <form id="order-form" autocomplete="off">
        <div class="mb-3">
            <label for="customer-select" class="form-label">Customer</label>
            <span class="inline-flex">
                <select id="customer-select" name="customer_id" class="form-control"></select>
                <button type="button" class="refresh-btn" id="refresh-customer" title="Refresh Customer List">&#x21BB;</button>
                <a href="#" class="icon-btn text-decoration-none" id="add-customer-btn" title="Add Customer">
                    <i class="fa fa-user-plus"></i>
                </a>
            </span>
        </div>
        <table class="table" id="items-table">
            <thead>
                <tr>
                    <th>
                <span class="inline-flex align-items-center">
                    Item
                    <button type="button" class="refresh-btn" id="refresh-items" title="Refresh Items List">&#x21BB;</button>
                    <a href="/entities/items/list.php" target="_blank" class="icon-btn text-decoration-none" id="add-item-btn" title="Add Item">
                        <i class="fa fa-plus"></i>
                    </a>
                </span>
                    </th>
                    <th>Qty</th>
                    <th>Pack Size</th>
                    <th>Price/Unit</th>
                    <th>Total</th>
                    <th>Remove</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <span class="inline-flex mb-3 gap-05em">
            <button type="button" id="add-row" class="icon-btn" title="Add Item"><i class="fa fa-plus"></i></button>
        </span>

        <!-- Meals Section -->
        <h5 style="color: #007bff; margin-top: 20px; margin-bottom: 10px;">Meals</h5>
        <table class="table" id="meals-table">
            <thead>
                <tr>
                    <th>
                        <span class="inline-flex align-items-center">
                            Meal
                            <button type="button" class="refresh-btn" id="refresh-meals" title="Refresh Meals List">&#x21BB;</button>
                        </span>
                    </th>
                    <th>Qty</th>
                    <th>Price/Meal</th>
                    <th>Total</th>
                    <th>Remove</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <span class="inline-flex mb-3 gap-05em">
            <button type="button" id="add-meal-row" class="icon-btn" title="Add Meal"><i class="fa fa-plus"></i></button>
        </span>
        <div class="order-summary-outer">
        <div class="order-summary-wrap mb-3">
            <input type="hidden" name="amount" id="hidden-amount">
            <input type="hidden" name="discount" id="hidden-discount">
            <input type="hidden" name="delivery_charges" id="hidden-delivery-charges">
            <input type="hidden" name="grand_total" id="hidden-grand-total">
            <div class="row">
                <label>Order Total:</label>
                <input type="text" class="order-output" id="order-amount" value="0.00" readonly tabindex="-1">
            </div>
            <div class="discount-stack">
                <div class="row">
                    <label for="discount-amount">Discount Amount:</label>
                    <input type="number" min="0" step="0.01" id="discount-amount" class="form-control" value="0">
                </div>
                <div class="row">
                    <label for="discount-percent" class="width-130px">Discount %:</label>
                    <input type="number" min="0" max="100" step="0.01" id="discount-percent" class="form-control" value="0">
                </div>
            </div>
            <div class="row">
                <label for="delivery-charges">Delivery Charges:</label>
                <input type="number" min="0" step="0.01" id="delivery-charges" class="form-control" value="0">
            </div>
            <div class="row">
                <label>Grand Total:</label>
                <input type="text" class="order-output" id="grand-total" value="0.00" readonly tabindex="-1">
            </div>
            <div style="display:flex; justify-content:center; align-items:center; margin-top:8px;">
                <button type="button" id="apply-rounding-btn" class="icon-btn" title="Apply Rounding Difference">
                    <span class="fa fa-magic"></span>
                </button>
            </div>
        </div>
        </div>
        <div class="center-btn">
            <button type="submit" class="icon-btn" id="create-order-btn" title="Create Order">
                <i class="fa fa-check-circle"></i>
            </button>
        </div>
    </form>
</div>

<!-- Container to host the stacked customer modal -->
<div id="customer-form-modal-container"></div>

<script src="/entities/sales/js/order_form.js"></script>
<script>
window.initOrderFormJS && window.initOrderFormJS(<?= $order_id ? json_encode(['order_id' => $order_id]) : 'null' ?>);
</script>