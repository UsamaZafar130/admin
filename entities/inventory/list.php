<?php
require_once '../../includes/auth_check.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
$pageTitle = "Inventory Management";
require_once '../../includes/header.php';
?>

<?php
// Fetch undelivered orders and their items
$stmt = $pdo->query("SELECT so.id, so.order_date, c.name AS customer_name, so.grand_total
    FROM sales_orders so
    JOIN customers c ON so.customer_id = c.id
    WHERE so.delivered = 0 AND so.cancelled = 0
    ORDER BY so.order_date ASC");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="page-content-wrapper">
    <div class="container mt-3">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="text-primary"><i class="fa fa-warehouse me-2"></i> Inventory Management</h2>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-success btn-3d me-2" id="btn-stock-req">
                    <i class="fa fa-chart-bar me-1"></i> Stock Requirements
                </button>
                <button class="btn btn-primary btn-3d" id="btn-add-stock">
                    <i class="fa fa-plus me-1"></i> Add Stock
                </button>
            </div>
        </div>
    <div class="alert alert-info max-width-700 mb-4">
        <strong>Select orders to view combined item requirements.</strong><br>
        Use <strong>Add Stock</strong> to record manufactured stock for extra/buffer items.
    </div>
    <div class="mb-3 header-buttons-secondary">
        <button class="btn btn-secondary btn-3d me-2" id="btn-excess-stock">
            <i class="fa fa-arrow-up me-1"></i> Excess Stock
        </button>
        <button class="btn btn-info btn-3d me-2" id="btn-packing-log">
            <i class="fa fa-box me-1"></i> Packing Log
        </button>
        <button class="btn btn-warning btn-3d" id="btn-packing-labels">
            <i class="fa fa-tag me-1"></i> Packing Labels
        </button>
    </div>
    <form id="orders-form">
        <table class="entity-table table table-striped table-hover table-consistent" id="orders-table">
            <thead class="table-light">
                <tr>
                    <th class="width-38px">
                        <input type="checkbox" id="select-all-orders" />
                    </th>
                    <th data-priority="2">Order #</th>
                    <th data-priority="1">Customer</th>
                    <th data-priority="3">Grand Total</th>
                    <th data-priority="4">Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td>
                        <input type="checkbox" class="order-checkbox" value="<?= $o['id'] ?>">
                    </td>
                    <td><?= htmlspecialchars($o['id']) ?></td>
                    <td><?= htmlspecialchars($o['customer_name']) ?></td>
                    <td><?= format_currency($o['grand_total']) ?></td>
                    <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($o['order_date']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </form>
    </div>
</div>
</div>

<!-- Note:
     The Add Stock and Excess Stock modals are provided globally via includes/floating-shortcuts/floater.php,
     which is already included by header.php. We intentionally do NOT duplicate them here to avoid ID conflicts. -->

<script src="js/inventory.js"></script>
<script>
// Initialize DataTable with UnifiedTables
$(document).ready(function() {
    if ($('#orders-table').length && window.UnifiedTables) {
        UnifiedTables.init('#orders-table');
    }
});
</script>
<?php require_once '../../includes/footer.php'; ?>