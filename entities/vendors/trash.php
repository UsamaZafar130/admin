<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Vendors Trash";
include __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

// List soft-deleted vendors
$stmt = $pdo->prepare("SELECT * FROM vendors WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
$stmt->execute();
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="entity-header">
    <h2>Vendors Trash</h2>
    <a class="btn btn-primary" href="list.php">&larr; Back to Vendors</a>
</div>
<div id="vendors-trash-list-wrap">
    <table class="entity-table" id="vendors-trash-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Contact</th>
                <th>Address</th>
                <th>Location</th>
                <th>Deleted At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($vendors as $v): ?>
            <tr data-vendor-id="<?= $v['id'] ?>">
                <td><?= h($v['name']) ?></td>
                <td><?= h($v['contact']) ?></td>
                <td><?= h($v['address']) ?><?= $v['area'] ? ', ' . h($v['area']) : '' ?><?= $v['city'] ? ', ' . h($v['city']) : '' ?></td>
                <td><?= h($v['location']) ?></td>
                <td><?= date('Y-m-d H:i', strtotime($v['deleted_at'])) ?></td>
                <td>
                    <button class="btn-ico" title="Restore" onclick="VendorUI.restoreVendor(<?= $v['id'] ?>)"><i class="fa fa-undo"></i></button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Enable sorting/filter on trash table if needed
        if (document.getElementById('vendors-trash-table')) {
            initTableFeatures('vendors-trash-table', null, 'frozo_vendors_trash');
        }
    });
</script>
<script src="js/vendors.js"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>