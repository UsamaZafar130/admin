<?php
// --- AJAX Save Handler must be FIRST! ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_shipping_batch_orders'])) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    require_once __DIR__ . '/../../includes/db_connection.php';
    require_once __DIR__ . '/../../includes/functions.php';

    $response = ['success' => false, 'message' => ''];
    $pack_nos = $_POST['pack_no'] ?? [];
    $collection_amounts = $_POST['collection_amount'] ?? [];
    $order_ids = $_POST['order_ids'] ?? [];

    if (!is_array($order_ids) || !is_array($pack_nos) || !is_array($collection_amounts)) {
        $response['message'] = "Invalid input.";
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // Fetch existing batch_ids for the given order_ids
    $existing_batch_ids = [];
    if (!empty($order_ids)) {
        $in = implode(',', array_fill(0, count($order_ids), '?'));
        $stmt = $pdo->prepare("SELECT order_id, batch_id FROM shipping_batch_orders WHERE order_id IN ($in)");
        $stmt->execute($order_ids);
        foreach ($stmt as $row) {
            $existing_batch_ids[$row['order_id']] = $row['batch_id'];
        }
    }

    $success_count = 0;
    $errors = [];
    foreach ($order_ids as $order_id) {
        $order_id = intval($order_id);
        // Accept empty string as NULL for pack_no
        $pack_no = isset($pack_nos[$order_id]) && $pack_nos[$order_id] !== '' ? intval($pack_nos[$order_id]) : null;
        // Accept empty string or zero as NULL for collection_amount
        if (isset($collection_amounts[$order_id]) && $collection_amounts[$order_id] !== '') {
            $ca = floatval($collection_amounts[$order_id]);
            $collection_amount = ($ca == 0) ? null : $ca;
        } else {
            $collection_amount = null;
        }
        if (!$order_id) {
            $errors[] = "Missing values for order $order_id.";
            continue;
        }

        // Fetch batch_id for this order
        $batch_id = isset($existing_batch_ids[$order_id]) ? $existing_batch_ids[$order_id] : null;
        if ($batch_id === null) {
            $errors[] = "No batch_id found for order $order_id.";
            continue;
        }

        try {
            $stmt = $pdo->prepare("UPDATE shipping_batch_orders SET pack_no=?, collection_amount=?, batch_id=? WHERE order_id=?");
            $stmt->execute([$pack_no, $collection_amount, $batch_id, $order_id]);
            if ($stmt->rowCount() === 0) {
                $stmt = $pdo->prepare("INSERT INTO shipping_batch_orders (order_id, pack_no, collection_amount, batch_id) VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE pack_no=VALUES(pack_no), collection_amount=VALUES(collection_amount), batch_id=VALUES(batch_id)");
                $stmt->execute([$order_id, $pack_no, $collection_amount, $batch_id]);
            }
            $success_count++;
        } catch (Exception $e) {
            $errors[] = "Error for order $order_id: " . $e->getMessage();
        }
    }
    $response['success'] = $success_count === count($order_ids);
    $response['message'] = $success_count . " orders saved. " . (count($errors) ? implode("; ", $errors) : "");
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// --- Main PHP Page Logic Below ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$pageTitle = "Shipping Docs - Selected Orders";
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
include_once __DIR__ . '/../../includes/header.php';

// Parse order IDs from GET parameter
$ids = [];
if (!empty($_GET['ids'])) {
    $ids = array_filter(array_map('intval', explode(',', $_GET['ids'])));
}

// Helper function to split item packs
function split_item_packs($name, $qty, $pack_size) {
    $result = [];
    $qty = (int)$qty;
    $pack_size = (int)$pack_size;
    $name = htmlspecialchars($name);
    if ($pack_size > 0 && $qty > 0) {
        $full_packs = intdiv($qty, $pack_size);
        $remainder = $qty % $pack_size;
        if ($full_packs > 0) {
            $result[] = "{$name} {$pack_size} x {$full_packs}";
        }
        if ($remainder > 0) {
            $result[] = "{$name} {$remainder} x 1";
        }
    } elseif ($qty > 0) {
        $result[] = "{$name} {$qty} x 1";
    }
    return $result;
}

$orders = [];
$totalGrand = 0;
$order_items = [];
$delivery_riders = [];

// Fetch existing saved values for pack_no, collection_amount, batch_id
$saved_batch_orders = [];
if (!empty($ids)) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    require_once __DIR__ . '/../../includes/db_connection.php';
    $stmt = $pdo->prepare("SELECT order_id, pack_no, collection_amount, batch_id FROM shipping_batch_orders WHERE order_id IN ($in)");
    $stmt->execute($ids);
    foreach ($stmt as $row) {
        $saved_batch_orders[$row['order_id']] = [
            'pack_no' => $row['pack_no'], // could be null
            'collection_amount' => $row['collection_amount'], // could be null
            'batch_id' => $row['batch_id'], // could be null
        ];
    }
}

if (!empty($ids)) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT 
                o.id, o.order_date, o.customer_id, o.grand_total, o.paid, o.delivered, o.cancelled,
                c.name AS customer_name, c.contact AS customer_contact, c.house_no, c.area, c.city, c.location,
                (SELECT IFNULL(SUM(amount),0) FROM order_payments WHERE order_id = o.id) AS amount_paid
            FROM sales_orders o
            LEFT JOIN customers c ON o.customer_id = c.id
            WHERE o.id IN ($in)
            ORDER BY FIELD(o.id, $in)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($ids, $ids)); // For both IN and FIELD
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($orders as $k => &$order) {
        $paid = floatval($order['amount_paid']);
        $grand_total = floatval($order['grand_total']);
        $order['outstanding'] = max($grand_total - $paid, 0);

        // Use saved pack_no/collection_amount, fallback to default
        if (isset($saved_batch_orders[$order['id']])) {
            $order['pack_no'] = $saved_batch_orders[$order['id']]['pack_no'];
            // Logic: if collection_amount is NULL, show grand_total
            $order['collection_amount'] = ($saved_batch_orders[$order['id']]['collection_amount'] === null)
                ? $grand_total
                : $saved_batch_orders[$order['id']]['collection_amount'];
            $order['batch_id'] = $saved_batch_orders[$order['id']]['batch_id'];
        } else {
            $order['pack_no'] = $k + 1;
            $order['collection_amount'] = $order['outstanding'];
            $order['batch_id'] = null;
        }

        $order['full_address'] = trim(
            ($order['house_no'] ?? '') . ' ' .
            ($order['area'] ?? '') . ' ' .
            ($order['city'] ?? '')
        );
        $totalGrand += $grand_total;
    }
    unset($order);

    // Get items for all orders
    if (count($ids) > 0) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $itemStmt = $pdo->prepare("
            SELECT oi.order_id, i.name as item_name, oi.qty, oi.pack_size
            FROM order_items oi
            LEFT JOIN items i ON oi.item_id = i.id
            WHERE oi.order_id IN ($in)
        ");
        $itemStmt->execute($ids);
        foreach ($itemStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $packs = split_item_packs($row['item_name'], $row['qty'], $row['pack_size']);
            foreach ($packs as $packStr) {
                $order_items[$row['order_id']][] = $packStr;
            }
        }
    }

    // Fetch current rider assignments for orders
    if (count($ids) > 0) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT order_id, rider_id FROM delivery_riders WHERE order_id IN ($in)");
        $stmt->execute($ids);
        foreach ($stmt as $row) {
            $delivery_riders[$row['order_id']] = $row['rider_id'];
        }
    }
}

// Get riders (and admin for testing)
$riderStmt = $pdo->prepare("SELECT id, username, name, role FROM users WHERE role IN ('rider','admin') AND deleted_at IS NULL ORDER BY FIELD(role,'rider','admin'), name, username");
$riderStmt->execute();
$riders = $riderStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="css/sales.css" rel="stylesheet">
    <link href="/assets/css/list-pages-consistency.css" rel="stylesheet">

    <style>
        .shipping-edit-input { border-radius: 7px; border: 1px solid #d3e5fa; padding: 6px 7px; font-size: 1rem; width: 80px; text-align: center; }
        .shipping-edit-input:focus { border: 1.5px solid #0070e0; outline: none; background: #f6fbff; }
        .order-customer-cell {
            text-align: left;
            padding-left: 6px;
            padding-right: 6px;
        }
        .order-area-cell {
            text-align: left;
            padding-left: 6px;
            padding-right: 6px;
        }
        .order-customer-name {
            color: #1869e3;
            font-weight: 700;
            font-size: 1rem;
            word-break: break-word;
            display: inline-block;
        }
        .order-customer-contact {
            color: #23b367 !important;
            font-size: 0.99em;
            font-weight: 500;
            display: block;
            margin-top: 2px;
            text-decoration: none;
            word-break: break-all;
        }
        .order-customer-contact:hover {
            text-decoration: underline;
            color: #128C7E !important;
        }
        .shipping-actions-bar .btn i, .shipping-save-btn i {
            font-size: 1.25em;
            margin-right: 3px;
        }
        .modal {
            display: none;
            position: fixed;
            left: 0; top: 0;
            width: 100vw; height: 100vh;
            background: rgba(32,32,32,0.15);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        .modal.show { display: flex !important; }
        .modal .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #aaa;
            cursor: pointer;
            margin-left: 18px;
        }
        .modal .modal-close:hover { color: #e05353; }
        .modal .modal-body { padding: 0; margin: 0 0 16px 0; overflow-x: auto; min-width: 350px;}
        .modal-footer { border: none; background: none; }
        .modal-footer .btn { min-width: 100px; }
        .modal-footer .btn-primary { margin-right: 7px; }
        @media print {
            .modal, .shipping-edit-input, .select-order-checkbox, .select-all-checkbox, .top-row, .header-buttons-secondary { display:none !important; }
            body { background: #fff; }
            .entity-table th, .entity-table td { display: none; }
            .entity-table th.print-items, .entity-table td.print-items { display: table-cell !important; }
            .entity-table th:nth-child(4), .entity-table td:nth-child(4) { display: table-cell !important; }
        }
    </style>
</head>
<body>
<div class="page-content-wrapper">
    <div class="container mt-3">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="text-primary"><i class="fa fa-truck me-2"></i> Shipping Docs - Selected Orders</h2>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-success btn-3d me-2" id="btn-shipping-labels">
                    <i class="fa fa-barcode me-1"></i> Shipping Labels
                </button>
                <button class="btn btn-primary btn-3d" id="btn-dispatch-list">
                    <i class="fa fa-list-check me-1"></i> Route Packing List
                </button>
            </div>
        </div>
        <div class="alert alert-info max-width-700 mb-4">
            <strong>Manage shipping documentation for selected orders.</strong><br>
            Use <strong>Shipping Labels</strong> to generate barcode labels, <strong>Route Packing List</strong> to create dispatch lists, or <strong>Delivery Manifest</strong> for delivery sheets.<br>
            Edit Pack# and Collection Amount directly in the table. Click <strong>Save</strong> to store your changes.<br>
            Batch Total Amount: <strong><?= format_currency($totalGrand) ?></strong>
        </div>
        <div class="mb-3 header-buttons-secondary">
            <button class="btn btn-primary btn-3d me-2" id="btn-delivery-sheet">
                <i class="fa fa-file-signature me-1"></i> Delivery Manifest
            </button>
            <button class="btn btn-success btn-3d me-2" id="btn-save-batch-orders">
                <i class="fa fa-save me-1"></i> Save
            </button>
            <a href="orders_list.php" class="btn btn-outline-secondary btn-3d">
                <i class="fa fa-arrow-left me-1"></i> Back to Orders
            </a>
        </div>
    <div class="table-responsive">
        <form id="shipping-batch-orders-form" autocomplete="off">
        <table class="entity-table table table-striped table-hover table-consistent" id="shipping-orders-table" style="width:100%">
            <thead class="table-light">
                <tr>
                    <th data-priority="1"><input type="checkbox" id="select-all-orders" class="select-all-checkbox"></th>
                    <th data-priority="2">Customer</th>
                    <th data-priority="99">Area</th>
                    <th data-priority="99">Full Address</th>
                    <th class="print-items d-none">Items</th>
                    <th data-priority="3">Pack #</th>
                    <th data-priority="99">Grand Total</th>
                    <th data-priority="99">Paid</th>
                    <th data-priority="4">Collection Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($orders)): ?>
                <?php foreach($orders as $k=>$order): ?>
                    <?php
                        // --- WhatsApp Invoice Generation (unchanged) ---
                    ?>
                    <tr data-order-id="<?= $order['id'] ?>" data-location="<?= htmlspecialchars($order['location'] ?? '', ENT_QUOTES) ?>">
                        <td>
                            <input type="checkbox" class="select-order-checkbox" value="<?= $order['id'] ?>">
                        </td>
                        <td class="order-customer-cell">
                            <?= format_customer_contact($order['customer_name'], $order['customer_contact']) ?>
                        </td>
                        <td class="order-area-cell"><?= htmlspecialchars($order['area']) ?></td>
                        <td class="order-address-cell"><?= htmlspecialchars($order['full_address']) ?></td>
                        <td class="print-items d-none">
                            <?php if (!empty($order_items[$order['id']])): ?>
                                <span class="order-items-list">
                                    <?= implode(', ', $order_items[$order['id']]) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">No items</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <input type="number" class="shipping-edit-input input-pack-no form-control"
                                   value="<?= $order['pack_no'] === null ? '' : htmlspecialchars($order['pack_no']) ?>"
                                   min="1" name="pack_no[<?= $order['id'] ?>]" data-pack-no="<?= $order['pack_no'] === null ? '' : htmlspecialchars($order['pack_no']) ?>">
                        </td>
                        <td><?= format_currency($order['grand_total']) ?></td>
                        <td>
                            <?php if($order['amount_paid'] > 0): ?>
                                <span class="badge-consistent badge-success"><?= format_currency($order['amount_paid']) ?></span>
                            <?php else: ?>
                                <span class="badge-consistent badge-danger">Unpaid</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <input type="number" class="shipping-edit-input input-collection-amount form-control"
                                   value="<?= htmlspecialchars($order['collection_amount']) ?>"
                                   min="0" step="0.01" name="collection_amount[<?= $order['id'] ?>]">
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" style="text-align:center;color:#888;padding:40px;">
                        <?php if (empty($ids)): ?>
                            <b>No order IDs provided. Please select orders from the list page.</b>
                        <?php else: ?>
                            <b>No orders found for the selected IDs.</b>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
        </form>
    </div>
    <!-- Rider Selection Modal (stylized) -->
    <div class="modal" id="riderModal" tabindex="-1" aria-labelledby="riderModalLabel" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-title" id="riderModalLabel"><i class="fa fa-motorcycle"></i> Select Rider</span>
                <button type="button" class="modal-close" id="modalCancelBtn" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <select id="riderSelect" class="form-control" style="width:100%;margin-bottom:10px;">
                    <option value="">-- Select Rider --</option>
                    <?php foreach($riders as $rider): ?>
                        <option value="<?= $rider['id'] ?>">
                            <?= htmlspecialchars(($rider['role'] === 'admin' ? '[Admin] ' : '') . ($rider['name'] ?: $rider['username'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" id="modal-action-type" value="">
            </div>
            <div class="modal-footer d-flex justify-content-end">
                <button type="button" class="btn btn-primary" id="modalOkBtn">Proceed</button>
                <button type="button" class="btn btn-secondary" id="modalCancelBtnFooter">Cancel</button>
            </div>
        </div>
    </div>
    <script>
    // Custom sorting for columns with input type number
    $.fn.dataTable.ext.order['dom-text-numeric'] = function  ( settings, col ) {
        return this.api().column( col, {order:'index'} ).nodes().map( function ( td, i ) {
            return parseFloat($('input', td).val()) || 0;
        });
    };
    </script>
    <script>
    window.shippingOrdersData = <?= json_encode([
        'orders' => $orders,
        'order_items' => $order_items,
        'riders' => $riders,
        'delivery_riders' => $delivery_riders
    ]);
    ?>;
    </script>

    <script>
    // --- Custom ordering plug-in for columns with input fields ---
    $.fn.dataTable.ext.order['dom-text-numeric'] = function(settings, col) {
        return this.api().column(col, {order:'index'}).nodes().map(function(td, i) {
            // Always get the current value of the input
            var v = $('input', td).val();
            return v === undefined || v === null || v === '' ? 0 : parseFloat(v);
        });
    };
    </script>
    <script>
    $(function() {
        var table = $('#shipping-orders-table').DataTable({
            responsive: true,
            paging: true,
            searching: true,
            info: true,
            order: [[1, 'asc']],
            dom: '<"top-row d-flex align-items-center justify-content-between"lfi>rtp',
            columnDefs: [
                { orderable: false, targets: [0,4] }, // Checkbox, Items not sortable
                { orderDataType: 'dom-text-numeric', targets: 5 }, // Pack # input
                { orderDataType: 'dom-text-numeric', targets: 8 }, // Collection Amount input
                { responsivePriority: 1, targets: 0 }, // Checkbox always visible
                { responsivePriority: 2, targets: 1 }, // Customer always visible
                { responsivePriority: 3, targets: 5 }, // Pack # always visible
                { responsivePriority: 4, targets: 8 }, // Collection always visible
                { responsivePriority: 99, targets: [2,3,6,7] } // Collapse area, address, grand total, paid as needed
            ],
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ orders",
                info: "Showing _START_ to _END_ of _TOTAL_ orders",
                infoEmpty: "No orders found",
                zeroRecords: "No matching orders found"
            }
        });

        // On blur (focus change): update DataTables cache, and if sorted, re-sort
        // This prevents live sorting while typing (e.g., when typing "11", it won't sort after just "1")
        $('#shipping-orders-table').on('blur', 'input', function() {
            var cell = table.cell($(this).closest('td'));
            cell.invalidate(); // update the cache
            var order = table.order();
            // If currently sorted by Pack # or Collection Amount (5 or 8), re-draw to re-sort
            if (order.length && (order[0][0] === 5 || order[0][0] === 8)) {
                table.order(order).draw(false);
            }
        });

        // Modal close for both header and footer close buttons
        $('#modalCancelBtn, #modalCancelBtnFooter').on('click', function() {
            $('#riderModal').removeClass('show').hide();
        });
    });
    </script>
    <script>
    // Save batch orders
    $(function() {
        $('#btn-save-batch-orders').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
            var formData = $('#shipping-batch-orders-form').serializeArray();
            // Add explicit order_ids array so backend can map
            var order_ids = [];
            $('#shipping-orders-table tbody tr').each(function() {
                order_ids.push($(this).data('order-id'));
            });
            formData.push({name: 'save_shipping_batch_orders', value: '1'});
            order_ids.forEach(function(id) {
                formData.push({name: 'order_ids[]', value: id});
            });
            $.ajax({
                url: '',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(resp) {
                    $btn.prop('disabled', false).html('<i class="fa fa-save me-1"></i> Save');
                    if (resp.success) {
                        showSuccess(resp.message || 'Saved successfully');
                    } else {
                        showError(resp.message || 'Save failed');
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).html('<i class="fa fa-save me-1"></i> Save');
                    showError('Save failed: ' + (xhr.responseText || xhr.statusText));
                }
            });
        });
    });
    </script>
    <script src="js/shipping_docs.js"></script>
    </div> <!-- End container mt-3 -->
</div> <!-- End page-content-wrapper -->
</body>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
</html>