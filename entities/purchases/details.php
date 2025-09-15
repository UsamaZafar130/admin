<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : null;
if (!$id) { echo "No purchase specified."; exit; }

$stmt = $pdo->prepare("SELECT p.*, v.name AS vendor_name FROM purchases p LEFT JOIN vendors v ON p.vendor_id = v.id WHERE p.id=? AND p.deleted_at IS NULL LIMIT 1");
$stmt->execute([$id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) { echo "Purchase not found."; exit; }

// Get paid amount
$paid = $pdo->prepare("SELECT SUM(amount) FROM purchase_payments WHERE purchase_id=? AND deleted_at IS NULL");
$paid->execute([$id]);
$amount_paid = $paid->fetchColumn() ?: 0;
$status = ($amount_paid >= floatval($p['amount'])) ? 'Paid' : ($amount_paid > 0 ? 'Partial' : 'Unpaid');
$badgeClass = $status === 'Paid' ? 'badge-surplus' : ($status === 'Partial' ? 'badge-settled' : 'badge-outstanding');

// Show any advances auto-applied to this purchase
$advances = [];
$stmt2 = $pdo->prepare("SELECT * FROM vendor_advances WHERE applied_to_purchase_id=?");
$stmt2->execute([$id]);
$advances = $stmt2->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="modal-header">
    <h5 class="modal-title">Purchase Details</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <div class="entity-details">
        <div class="mb-3"><b>Date:</b> <?= h(date('Y-m-d', strtotime($p['date']))) ?></div>
        <div class="mb-3"><b>Vendor:</b> <?= h($p['vendor_name']) ?></div>
        <div class="mb-3"><b>Type:</b> <?= ucfirst($p['type']) ?></div>
        <div class="mb-3"><b>Amount:</b> <?= number_format($p['amount'],2) ?></div>
        <div class="mb-3"><b>Description:</b> <?= h($p['description']) ?></div>
        <div class="mb-3"><b>Status:</b>
            <span class="badge <?= $badgeClass ?>">
                <?= h($status) ?><?= $status !== 'Paid' && $amount_paid > 0 ? " ($amount_paid paid)" : "" ?>
            </span>
        </div>
        <?php if ($advances && count($advances) > 0): ?>
            <div class="mb-3 alert alert-success">
                <b>Advance(s) Applied:</b>
                <ul class="mb-0 mt-2">
                    <?php foreach ($advances as $a): ?>
                        <li>Advance #<?= $a['id'] ?>: <?= number_format($a['amount'],2) ?> <?= $a['note'] ? '(' . h($a['note']) . ')' : '' ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <div class="mb-3"><b>Created At:</b> <?= h($p['created_at']) ?></div>
    </div>
</div>
<div class="modal-footer">
    <button class="btn btn-primary" onclick="PurchaseUI.openEditModal(<?= $p['id'] ?>)">Edit</button>
    <?php if($status !== 'Paid'): ?>
        <button class="btn btn-success" onclick="PurchaseUI.openPaymentModal(<?= $p['id'] ?>, <?= $p['amount']-$amount_paid ?>)">Record Payment</button>
    <?php endif; ?>
    <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>