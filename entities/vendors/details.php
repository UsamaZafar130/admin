<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$stmt = $pdo->prepare("SELECT * FROM vendors WHERE id=? LIMIT 1");
$stmt->execute([$id]);
$v = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$v) { echo "Vendor not found."; exit; }

$balance = get_vendor_balance_details($id, $pdo);

function render_location($loc) {
    $loc = trim($loc);
    if (filter_var($loc, FILTER_VALIDATE_URL)) {
        return '<a href="' . htmlspecialchars($loc) . '" target="_blank">' . htmlspecialchars($loc) . '</a>';
    } elseif (preg_match('/^(https?:\/\/[^\s]+)$/', $loc)) {
        return '<a href="' . htmlspecialchars($loc) . '" target="_blank">' . htmlspecialchars($loc) . '</a>';
    }
    return htmlspecialchars($loc);
}
?>
<div class="modal-header">
    <h5 class="modal-title"><?= h($v['name']) ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <div class="entity-details" data-vendor-id="<?= $v['id'] ?>">
        <div class="mb-3">
            <strong>Contact:</strong>
            <span><?= h($v['contact']) ?></span>
        </div>
        <div class="mb-3">
            <strong>Address:</strong>
            <span><?= h($v['address']) ?><?= $v['area'] ? ', ' . h($v['area']) : '' ?><?= $v['city'] ? ', ' . h($v['city']) : '' ?></span>
        </div>
        <div class="mb-3">
            <strong>Location:</strong>
            <span><?= render_location($v['location']) ?></span>
        </div>
        <div class="mb-3">
            <strong>Balance:</strong>
            <?php if ($balance['outstanding'] > 0): ?>
                <span class="vendor-balance badge badge-outstanding"><?= format_currency($balance['outstanding']) ?> Outstanding</span>
            <?php elseif ($balance['surplus'] > 0): ?>
                <span class="vendor-balance badge badge-surplus"><?= format_currency($balance['surplus']) ?> Surplus</span>
            <?php elseif ($balance['surplus'] < 0): ?>
                <span class="vendor-balance badge badge-outstanding"><?= format_currency(abs($balance['surplus'])) ?> Outstanding</span>
            <?php else: ?>
                <span class="vendor-balance badge badge-settled">0 Outstanding</span>
            <?php endif; ?>
        </div>
        <div class="mb-3">
            <strong>Created at:</strong>
            <span><?= format_datetime($v['created_at'], get_user_timezone(), 'Y-m-d') ?></span>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button class="btn btn-primary" onclick="VendorUI.openEditModal(<?= $v['id'] ?>, true)">Edit</button>
    <button class="btn btn-success" onclick="VendorUI.openPaymentModal(<?= $v['id'] ?>, <?= ($balance['outstanding'] > 0 ? $balance['outstanding'] : ($balance['surplus'] < 0 ? abs($balance['surplus']) : 0)) ?>)">Record Payment</button>
    <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>