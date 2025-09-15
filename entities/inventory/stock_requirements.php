<?php
require_once '../../includes/auth_check.php';
require_once '../../includes/db_connection.php';
$pageTitle = "Stock Requirements";
require_once '../../includes/header.php';
?>

<?php
// Get selected order IDs from GET or POST (comma separated)
$order_ids = [];
if (!empty($_REQUEST['order_ids'])) {
    $order_ids = array_filter(array_map('intval', explode(',', $_REQUEST['order_ids'])));
}
if (!$order_ids) {
    echo "<div class='alert alert-danger'>No orders selected!</div>";
    require_once '../../includes/footer.php';
    exit;
}

// Get item requirements for selected orders, including category name
$in  = str_repeat('?,', count($order_ids) - 1) . '?';
$stmt = $pdo->prepare("SELECT oi.item_id, i.name, c.name AS category, SUM(oi.qty) as total_qty
    FROM order_items oi
    JOIN items i ON oi.item_id = i.id
    LEFT JOIN categories c ON i.category_id = c.id
    WHERE oi.order_id IN ($in)
    GROUP BY oi.item_id, i.name, c.name
    ORDER BY i.name");
$stmt->execute($order_ids);
$req_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get net stock (sum of all qty, regardless of change_type)
$item_ids = array_column($req_items, 'item_id');
$in_items = $item_ids ? (str_repeat('?,', count($item_ids) - 1) . '?') : '';
$stocked = [];
if ($item_ids) {
    $st2 = $pdo->prepare("
        SELECT item_id, SUM(qty) AS total_stock
        FROM inventory_ledger
        WHERE item_id IN ($in_items)
        GROUP BY item_id
    ");
    $st2->execute($item_ids);
    foreach ($st2->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $stocked[$row['item_id']] = floatval($row['total_stock']);
    }
}
?>
<div class="orders-content">
    <div class="container mt-3">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="text-primary"><i class="fa fa-list-ul me-2"></i> Stock Requirements</h2>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-success btn-3d me-2" id="btn-excess-stock" data-bs-toggle="modal" data-bs-target="#excess-stock-modal">
                    <i class="fa fa-chart-bar me-1"></i> Show Excess Stock  
                </button>
                <button class="btn btn-primary btn-3d" id="btn-add-stock" data-bs-toggle="modal" data-bs-target="#add-stock-modal">
                    <i class="fa fa-plus me-1"></i> Add Stock
                </button>
            </div>
        </div>
        <div class="alert alert-info max-width-700 mb-4">
            <strong>Track item manufacturing requirements.</strong> Use <strong>Add Stock</strong> to record manufactured quantities.<br>
            Monitor surplus/shortfall for each item and manage stock levels efficiently.
        </div>

        <div class="table-responsive">
            <table class="entity-table table table-striped table-hover table-consistent" id="stock-req-table">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Total Required</th>
                        <th>Manufactured</th>
                            <th>Surplus/Shortfall</th>
                            <th>Add/Update</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $serial = 1; foreach ($req_items as $it):
                        $manufactured = $stocked[$it['item_id']] ?? 0;
                        $required = floatval($it['total_qty']) - $manufactured;
                        $badge = $required < 0 ? 'badge-surplus' : ($required > 0 ? 'badge-outstanding' : 'badge-settled');
                        $badge_prefix = $required < 0 ? '+' : ($required > 0 ? '-' : '');
                    ?>
                        <tr>
                            <td><?= $serial++ ?></td>
                            <td><?= htmlspecialchars($it['name']) ?></td>
                            <td><?= htmlspecialchars($it['category'] ?? 'Uncategorized') ?></td>
                            <td><?= number_format($it['total_qty'], 2) ?></td>
                            <td>
                                <span class="manufactured-val"><?= number_format($manufactured, 2) ?></span>
                            </td>
                            <td>
                                <span class="badge <?= $badge ?>">
                                    <?= $required == 0 ? '0.00' : $badge_prefix . number_format(abs($required), 2) ?>
                                </span>
                            </td>
                            <td>
                                <input type="number" class="form-control manufactured-input" min="0" step="0.01" max="<?= $it['total_qty'] ?>" style="width:90px;display:inline-block;" value="0">
                                <input type="hidden" class="row-item-id" value="<?= $it['item_id'] ?>">
                                <button type="button" class="btn btn-primary btn-update-stock" title="Update"><i class="fa fa-check-circle"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<!-- Add Stock Modal (Bootstrap Modal) -->
<div class="modal fade" id="add-stock-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="addStockModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addStockModalLabel">
                    <i class="fa fa-plus me-2"></i>Add Stock
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="add-stock-form" class="entity-form">
                    <div class="mb-3">
                        <label for="stock-item-select" class="form-label">Item</label>
                        <select id="stock-item-select" name="item_id" class="form-control tom-select"></select>
                    </div>
                    <div class="mb-3">
                        <label for="stock-qty" class="form-label">Quantity Manufactured</label>
                        <input type="number" id="stock-qty" name="qty" class="form-control" step="0.01" required>
                    </div>
                    <div class="mb-3" id="stock-comment-row" style="display:none;">
                        <label for="stock-comment" class="form-label">Comment <span class="text-danger">*</span></label>
                        <input type="text" id="stock-comment" name="comment" class="form-control" maxlength="250">
                    </div>
                </form>
                <div id="add-stock-feedback" class="alert" style="display:none"></div>
            </div>
            <div class="modal-footer">
                <button type="submit" form="add-stock-form" class="btn btn-primary">Add Stock</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Excess Stock Modal (Bootstrap Modal) -->
<div class="modal fade" id="excess-stock-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="excessStockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="excessStockModalLabel">
                    <i class="fa fa-boxes me-2"></i>Excess Stock (Surplus Items)
                </h5>
                <button type="button" class="btn btn-outline-secondary btn-sm ms-auto me-2" id="copy-excess-btn" title="Copy Excess List">
                    <i class="fa fa-copy me-1"></i>Copy
                </button>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="excess-stock-table-container">
                    <!-- Table will be injected by JS -->
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/entities/inventory/js/inventory.js"></script>
<script>
// Initialize DataTable with UnifiedTables
$(document).ready(function() {
    if ($('#stock-req-table').length && window.UnifiedTables) {
        UnifiedTables.init('#stock-req-table', 'stock-requirements');
    }
});
</script>
<?php require_once '../../includes/footer.php'; ?>