<?php
require_once '../../includes/auth_check.php';
require_once '../../includes/db_connection.php';
$pageTitle = "Packing Log";
require_once '../../includes/header.php';
?>

<?php
$order_ids = [];
if (!empty($_REQUEST['order_ids'])) {
    $order_ids = array_filter(array_map('intval', explode(',', $_REQUEST['order_ids'])));
}
if (!$order_ids) {
    echo "<div class='alert alert-danger'>No orders selected!</div>";
    require_once '../../includes/footer.php';
    exit;
}
$order_ids_str = implode(',', $order_ids);

$in  = str_repeat('?,', count($order_ids) - 1) . '?';
$stmt = $pdo->prepare("SELECT oi.item_id, i.name AS item_name, i.category_id, c.name AS category_name, oi.pack_size, SUM(oi.qty) as total_qty
    FROM order_items oi
    JOIN items i ON oi.item_id = i.id
    LEFT JOIN categories c ON i.category_id = c.id
    WHERE oi.order_id IN ($in)
    GROUP BY oi.item_id, oi.pack_size
    ORDER BY i.name, oi.pack_size");
$stmt->execute($order_ids);
$req_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$packed = [];
if ($req_items) {
    $sql = "SELECT item_id, pack_size, SUM(packs_packed) AS packs_packed
            FROM packing_log
            GROUP BY item_id, pack_size";
    $st2 = $pdo->prepare($sql);
    $st2->execute();
    foreach ($st2->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = $row['item_id'] . '_' . $row['pack_size'];
        $packed[$key] = intval($row['packs_packed']);
    }
}
?>
<div class="orders-content">
    <div class="container mt-3">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="text-primary"><i class="fa fa-box me-2"></i> Packing Log</h2>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-success btn-3d me-2" id="btn-scan-pack">
                    <i class="fa fa-chart-bar me-1"></i> Scan Packs
                </button>
                <button class="btn btn-primary btn-3d" id="btn-add-pack">
                    <i class="fa fa-plus me-1"></i> Add Packed Packs
                </button>
            </div>
        </div>
        <div class="alert alert-info max-width-700 mb-4">
            <strong>Track packing progress for selected orders.</strong> Use <strong>Add Packed Packs</strong> to manually record packing counts or <strong>Scan Packs</strong> for barcode scanning.<br>
            Monitor surplus/shortfall status and update packing quantities as needed. All values are real-time and affect inventory calculations.
        </div>
    <div class="table-responsive">
    <table class="entity-table table table-striped table-hover table-consistent" id="packing-log-table">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Item &amp; Pack Size</th>
                <th>Packs Required</th>
                <th>Packs Packed</th>
                <th>Surplus/Shortfall</th>
                <th>Add/Update</th>
            </tr>
        </thead>
        <tbody>
        <?php $serial = 1; foreach ($req_items as $it):
            $item_id = $it['item_id'];
            $pack_size = $it['pack_size'];
            $total_qty = $it['total_qty'];
            $packs_required = (int)ceil($total_qty / $pack_size);
            $key = $item_id . '_' . $pack_size;
            $packs_packed = $packed[$key] ?? 0;
            $surplus = $packs_packed - $packs_required;
            $badge = $surplus > 0 ? 'badge-surplus' : ($surplus < 0 ? 'badge-outstanding' : 'badge-settled');
            $badge_text = ($surplus === 0) ? '0' : (($surplus > 0 ? '+' : '') . $surplus);
        ?>
            <tr>
                <td><?= $serial++ ?></td>
                <td>
                    <?= htmlspecialchars($it['item_name']) . ' ' . intval($pack_size) ?>
                    <div class="text-muted" style="font-size: 11px;">
                        <?= htmlspecialchars($it['category_name'] ?? 'Uncategorized') ?>
                    </div>
                </td>
                <td><?= $packs_required ?></td>
                <td>
                    <span class="packs-packed-val"><?= $packs_packed ?></span>
                </td>
                <td>
                    <span class="badge <?= $badge ?>">
                        <?= $badge_text ?>
                    </span>
                </td>
                <td>
                    <input type="number" class="form-control packs-input" min="-100" max="100" step="1" style="width:90px;display:inline-block;" value="">
                    <input type="hidden" class="row-item-id" value="<?= $item_id ?>">
                    <input type="hidden" class="row-pack-size" value="<?= $pack_size ?>">
                    <button type="button" class="btn btn-primary btn-update-pack" title="Add Packed Packs"><i class="fa fa-check-circle"></i></button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    </div>
</div>

<!-- Add Pack Modal -->
<div class="modal fade" id="add-pack-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="addPackModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPackModalLabel"><i class="fa fa-plus"></i> Add Packed Packs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="add-pack-form" class="entity-form">
                    <div class="form-row">
                        <label for="pack-item-select">Item & Pack Size</label>
                        <select id="pack-item-select" name="item_pack" class="form-control tom-select"></select>
                    </div>
                    <div class="form-row">
                        <label for="pack-count">Number of Packs Packed</label>
                        <input type="number" id="pack-count" name="pack_count" class="form-control" min="-100" max="100" step="1" required>
                    </div>
                    <div class="form-row">
                        <label for="pack-comment">Comment (required for negative values)</label>
                        <input type="text" id="pack-comment" name="comment" class="form-control" maxlength="250">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Add Packs</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
                <div id="add-pack-feedback" class="alert" style="display:none"></div>
            </div>
        </div>
    </div>
</div>

<!-- Scan Pack Modal (content loaded via AJAX) -->
<div class="modal fade" id="scan-pack-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="scanPackModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" id="scan-pack-modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scanPackModalLabel"><i class="fa fa-qrcode"></i> Scan Pack</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Content will be loaded here -->
                <div style="text-align:center; padding: 40px;">Loading scan module...</div>
            </div>
        </div>
    </div>
</div>

<script>
window.packing_order_ids = "<?= htmlspecialchars($order_ids_str) ?>";

// Initialize DataTables
$(document).ready(function() {
    if ($('#packing-log-table').length && window.UnifiedTables) {
        UnifiedTables.init('#packing-log-table', 'packing');
    }
});
</script>
<script src="/entities/inventory/js/packing.js"></script>
<?php require_once '../../includes/footer.php'; ?>