<?php
$pageTitle = "Batch Details";
require_once __DIR__.'/../../includes/auth_check.php';
require_once __DIR__.'/../../includes/db_connection.php';
require_once __DIR__.'/../../includes/functions.php';
include_once __DIR__.'/../../includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/batches.css">
<?php

$batch_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$batch_id) { die("Batch not found."); }

$batch = $pdo->prepare("SELECT * FROM shipping_batches WHERE id=?");
$batch->execute([$batch_id]);
$batch = $batch->fetch(PDO::FETCH_ASSOC);
if (!$batch) { die("Batch not found."); }

$ordersStmt = $pdo->prepare("
    SELECT so.*, c.name as customer_name, c.contact, cbo.collection_amount
    FROM shipping_batch_orders cbo
    JOIN sales_orders so ON cbo.order_id=so.id
    LEFT JOIN customers c ON so.customer_id=c.id
    WHERE cbo.batch_id=?
    ORDER BY so.id ASC
");
$ordersStmt->execute([$batch_id]);
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all items for each order for the items modal
$order_items_map = [];
if (count($orders)) {
    $order_ids = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT oi.order_id, i.name, oi.pack_size, oi.qty
        FROM order_items oi
        JOIN items i ON oi.item_id = i.id
        WHERE oi.order_id IN ($placeholders)
    ");
    $stmt->execute($order_ids);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $packs_required = floor($row['qty'] / $row['pack_size']);
        $order_items_map[$row['order_id']][] = [
            'name' => $row['name'],
            'pack_size' => $row['pack_size'],
            'packs_required' => $packs_required,
        ];
    }
}

$status_count = ['delivered' => 0, 'undelivered' => 0];
foreach ($orders as $o) {
    if ($o['delivered']) $status_count['delivered']++;
    else $status_count['undelivered']++;
}
$order_count = count($orders);

// --- Batch status badge from the shipping_batches.status column ---
$batch_status_db = isset($batch['status']) ? (int)$batch['status'] : 0;
if ($batch_status_db === 0) {
    $batch_status_label = 'Pending';
    $batch_status_class = 'badge-consistent badge-warning';
} elseif ($batch_status_db === 1) {
    $batch_status_label = 'In Process';
    $batch_status_class = 'badge-consistent badge-info';
} elseif ($batch_status_db === 2) {
    $batch_status_label = 'Delivered';
    $batch_status_class = 'badge-consistent badge-success';
} else {
    $batch_status_label = 'Unknown';
    $batch_status_class = 'badge-consistent badge-secondary';
}

// Compose CSV for all order IDs in this batch, and only undelivered order IDs
$order_ids_undelivered = [];
$any_delivered = false;
foreach ($orders as $order) {
    if (!$order['delivered']) {
        $order_ids_undelivered[] = $order['id'];
    } else {
        $any_delivered = true;
    }
}
$order_ids_undelivered_csv = implode(',', $order_ids_undelivered);
$all_delivered = count($order_ids_undelivered) === 0;

?>

<div class="page-content-wrapper">
    <div class="container mt-3">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="text-primary"><i class="fa fa-box me-2"></i> <?= htmlspecialchars($batch['batch_name']) ?></h2>
            </div>
            <div class="col-md-4 text-end">
                <a href="list.php" class="btn btn-success btn-3d me-2" title="Back to Batches">
                    <i class="fa fa-arrow-left me-1"></i> Back
                </a>
                <button type="button" class="btn btn-primary btn-3d" id="open-order-modal">
                    <i class="fa fa-plus me-1"></i> Add New Order
                </button>
            </div>
        </div>
<div class="alert alert-info max-width-700 mb-4">
    <strong>Batch Management - <?= $order_count ?> orders (<?= $status_count['delivered'] ?> delivered, <?= $status_count['undelivered'] ?> pending).</strong><br>
    <strong>Date:</strong> <?= htmlspecialchars($batch['batch_date']) ?> | 
    <strong>Created:</strong> <?= htmlspecialchars($batch['created_at']) ?> | 
    <strong>Status:</strong> <span id="batch-status-badge" class="badge <?= $batch_status_class ?>"><?= htmlspecialchars($batch_status_label) ?></span><br>
    Use bulk actions to manage deliveries and payments. Generate stock requirements and shipping documents for undelivered orders.
</div>

<div class="mb-3 header-buttons-secondary">
    <button type="button" class="btn btn-secondary btn-3d me-2" id="print-invoice-selected-btn" title="Print Invoice for Selected Orders">
        <i class="fa fa-print me-1"></i> Print Invoice
    </button>
    <button class="btn btn-warning btn-3d me-2"
            id="btn-stock-req"
            title="Show Stock Requirements for selected orders">
        <i class="fa fa-list-ul me-1"></i> Stock Req.
    </button>
    <a href="/entities/sales/shipping_docs.php?ids=<?= htmlspecialchars($order_ids_undelivered_csv) ?>"
       class="btn btn-info btn-3d"
       target="_blank"
       title="Shipping Documents (All undelivered orders)">
        <i class="fa fa-truck me-1"></i> Shipping Docs.
    </a>
</div>

<!-- Bulk action icons row (TOP of table as requested) -->
<div class="batch-bulk-row-inside-table margin-bottom-12px">
    <button type="button" class="batch-bulk-btn" id="batch-bulk-delivered-btn" title="Mark Delivered"><i class="fa fa-truck"></i></button>
    <button type="button" class="batch-bulk-btn" id="batch-bulk-paid-btn" title="Mark Paid"><i class="fa fa-money-bill"></i></button>
    <button type="button" class="btn btn-primary btn-link-orders" title="Link Orders"><i class="fa fa-link"></i></button>
    <button type="button" class="batch-bulk-btn btn-show-summary" title="Show Summary"><i class="fa fa-list"></i></button>
</div>

<div>
    <table class="entity-table table table-striped table-hover table-consistent" id="batch-orders-table" data-batch-id="<?= $batch['id'] ?>">
        <thead class="table-light">
            <tr>
                <th><input type="checkbox" class="batch-select-all-checkbox"></th>
                <th>Order #</th>
                <th>Customer</th>
                <th>Grand Total</th>
                <th>Order Status</th>
                <th>Paid Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($orders as $order): ?>
            <?php
                // Paid status label (0=Unpaid,1=Paid,2=Partial)
                if ($order['paid'] == 1) {
                    $paid_label = '<span class="badge bg-success">Paid</span>';
                } elseif ($order['paid'] == 2) {
                    $paid_label = '<span class="badge bg-warning text-dark">Partial</span>';
                } else {
                    $paid_label = '<span class="badge bg-secondary">Unpaid</span>';
                }
                $can_edit = !$order['delivered'] && !$order['cancelled'];
                $show_invoice = !$order['cancelled'];
            ?>
            <tr data-order-id="<?= $order['id'] ?>"
                data-paid="<?= (int)$order['paid'] ?>"
                data-delivered="<?= (int)$order['delivered'] ?>"
                data-cancelled="<?= (int)$order['cancelled'] ?>">
                <td><input type="checkbox" class="batch-order-checkbox" value="<?= $order['id'] ?>" data-paid="<?= (int)$order['paid'] ?>"></td>
                <td><?= format_order_number($order['id']) ?></td>
                <td>
                    <?= format_customer_contact($order['customer_name'], $order['contact']) ?>
                    <?php if (!$order['delivered'] && !$order['cancelled']): ?>
                        <button
                            class="btn btn-ico btn-unlink-order"
                            data-order-id="<?= $order['id'] ?>"
                            data-batch-id="<?= $batch['id'] ?>"
                            title="Change Batch"
                            class="margin-left-4px padding-2-5 font-size-13">
                            <i class="fa fa-random"></i>
                        </button>
                    <?php endif; ?>
                </td>
                <td><?= format_currency($order['grand_total']) ?></td>
                <td>
                    <?php if ($order['delivered']): ?>
                        <span class="order-status-badge order-status-delivered">Delivered</span>
                    <?php else: ?>
                        <span class="order-status-badge order-status-undelivered">Undelivered</span>
                    <?php endif; ?>
                </td>
                <td><?= $paid_label ?></td>
                <td>
                    <div class="action-btns-inline">
                        <button class="btn btn-outline-info btn-3d btn-show-items-modal" data-order-id="<?= $order['id'] ?>" title="Show Items">
                            <i class="fa fa-cubes"></i>
                        </button>
                        <?php if ($can_edit): ?>
                            <button class="btn btn-outline-warning btn-3d batch-edit-order-btn" data-order-id="<?= $order['id'] ?>" title="Edit">
                                <i class="fa fa-edit"></i>
                            </button>
                        <?php endif; ?>
                        <?php if ($show_invoice): ?>
                            <a href="/entities/sales/print_invoice.php?id=<?= $order['id'] ?>"
                               class="btn btn-outline-info btn-3d"
                               target="_blank"
                               title="Invoice">
                                <i class="fa fa-file-invoice"></i>
                            </a>
                        <?php endif; ?>
                        <button class="btn btn-outline-success btn-3d batch-mark-delivered-btn batch-bulk-btn" data-order-id="<?= $order['id'] ?>" title="Mark Delivered"><i class="fa fa-truck"></i></button>
                        <?php if ((int)$order['paid'] !== 1): ?>
                            <button class="btn btn-outline-primary btn-3d batch-mark-paid-btn batch-bulk-btn" data-order-id="<?= $order['id'] ?>" title="Mark Paid">
                                <i class="fa fa-check-circle"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
</div>

<!-- Move Order Modal -->
<div class="modal fade" id="moveOrderModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="moveOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="moveOrderModalLabel"><i class="fa fa-random"></i> Move Order to Batch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="move-order-form" class="entity-form">
                    <div class="mb-3">
                        <label for="move-batch-dropdown" class="form-label">Select Target Batch</label>
                        <div class="d-flex gap-2 align-items-center">
                            <select id="move-batch-dropdown" class="form-control flex-1" required></select>
                            <button type="button" class="btn btn-secondary btn-sm" id="refresh-batch-list" title="Refresh list"><i class="fa fa-refresh"></i></button>
                        </div>
                    </div>
                    <div class="d-flex justify-content-start gap-2 mb-3">
                        <button type="submit" class="btn btn-primary">Move</button>
                        <button type="button" class="btn btn-light" id="create-new-batch-btn">Create New Batch</button>
                    </div>
                    <div class="move-error error-hidden"></div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Link Orders Modal -->
<div class="modal fade" id="linkOrdersModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="linkOrdersModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="linkOrdersModalLabel"><i class="fa fa-link"></i> Link Orders to Batch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="link-orders-form" class="entity-form">
                    <div class="mb-3">
                        <label for="link-orders-select" class="form-label">Select Orders to Link</label>
                        <select id="link-orders-select" name="order_ids[]" class="form-control width-100" multiple size="8" required>
                            <option value="">Loading...</option>
                        </select>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Link Selected Orders</button>
                    </div>
                    <div class="link-error"></div>
                    <div class="link-success"></div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Payment Modal for Mark Payment (dynamically filled) -->
<div class="modal fade" id="payment-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <!-- AJAX content will load here -->
        </div>
    </div>
</div>
<!-- Items Modal for Show Items (dynamically filled) -->
<div class="modal fade" id="items-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="itemsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="itemsModalLabel"><i class="fa fa-cubes"></i> Items Required</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="items-modal-body">
                <!-- JS will fill here -->
            </div>
        </div>
    </div>
</div>
<!-- Summary Modal -->
<div class="modal fade" id="summary-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="summaryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="summaryModalLabel"><i class="fa fa-list"></i> Batch Summary</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="summary-modal-body">
                <!-- AJAX content will load here -->
            </div>
        </div>
    </div>
</div>

<?php
// Include reusable order modal (must have #orderModal, #orderModalLabel, #order-modal-body)
include_once __DIR__.'/../../includes/order_modal.php';
?>

<script src="js/batches.js"></script>
<script src="../sales/js/order_form.js"></script>
<script>
$(function() {
    if (window.UnifiedTables && typeof window.UnifiedTables.init === 'function') {
        window.UnifiedTables.init('#batch-orders-table', 'default');
    }

    // Stock Requirements button
    $('#btn-stock-req').on('click', function(e) {
        e.preventDefault();
        let selectedOrderIds = $('.batch-order-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        if (selectedOrderIds.length === 0) {
            if (typeof showWarning === 'function') showWarning('Please select at least one order to generate stock requirements.');
            else alert('Please select at least one order.');
            return;
        }
        let hasDelivered = false;
        $('.batch-order-checkbox:checked').each(function() {
            if ($(this).closest('tr').find('.order-status-delivered').length) {
                hasDelivered = true;
                return false;
            }
        });
        if (hasDelivered) {
            if (typeof showError === 'function') showError('Cannot generate stock requirements: some selected orders are already delivered.', 'Delivery Status Error');
            else alert('Some selected orders are already delivered.');
            return;
        }
        window.open('/entities/inventory/stock_requirements.php?order_ids=' + encodeURIComponent(selectedOrderIds.join(',')), '_blank');
    });

    // Show Items Modal
    $(document).on('click', '.btn-show-items-modal', function() {
        var orderId = $(this).data('order-id');
        var itemsMap = <?php echo json_encode($order_items_map); ?>;
        var items = itemsMap[orderId] || [];
        var html = '';
        if (items.length > 0) {
            html += '<ul class="item-list-margin">';
            for (var i = 0; i < items.length; ++i) {
                html += '<li>' +
                    $('<div>').text(items[i].name).html() + ' ' +
                    items[i].pack_size + ' x ' +
                    items[i].packs_required +
                    '</li>';
            }
            html += '</ul>';
        } else {
            html = '<i class="no-items-text">No items found for this order.</i>';
        }
        $('#items-modal-body').html(html);
        window.UnifiedModals.show('items-modal');
    });

    // Add new order button
    $(document).on('click', '#open-order-modal', function(e){
        e.preventDefault();
        if (typeof showOrderModal === 'function') showOrderModal();
    });
});
</script>
<?php include_once __DIR__.'/../../includes/footer.php'; ?>