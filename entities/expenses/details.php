<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : null;
if (!$id) { echo "No expense specified."; exit; }

$stmt = $pdo->prepare("SELECT e.*, v.name AS vendor_name FROM expenses e LEFT JOIN vendors v ON e.vendor_id = v.id WHERE e.id=? AND e.deleted_at IS NULL LIMIT 1");
$stmt->execute([$id]);
$e = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$e) { echo "Expense not found."; exit; }

// Get paid amount
$paid = $pdo->prepare("SELECT SUM(amount) FROM expense_payments WHERE expense_id=? AND deleted_at IS NULL");
$paid->execute([$id]);
$amount_paid = $paid->fetchColumn() ?: 0;
$status = ($amount_paid >= floatval($e['amount'])) ? 'Paid' : ($amount_paid > 0 ? 'Partial' : 'Unpaid');
$badgeClass = $status === 'Paid' ? 'badge-surplus' : ($status === 'Partial' ? 'badge-settled' : 'badge-outstanding');

// Show any advances auto-applied to this expense
$advances = [];
$stmt2 = $pdo->prepare("SELECT * FROM vendor_advances WHERE applied_to_expense_id=?");
$stmt2->execute([$id]);
$advances = $stmt2->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="modal-header">
    <h5 class="modal-title">Expense Details</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <div class="entity-details">
        <div class="mb-3"><b>Date:</b> <?= h(date('Y-m-d', strtotime($e['date']))) ?></div>
        <div class="mb-3"><b>Vendor:</b> <?= h($e['vendor_name']) ?: 'None (Admin Expense)' ?></div>
        <div class="mb-3"><b>Type:</b> <?= ucfirst($e['type']) ?></div>
        <div class="mb-3"><b>Amount:</b> <?= number_format($e['amount'],2) ?></div>
        <div class="mb-3"><b>Description:</b> <?= h($e['description']) ?></div>
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
        <div class="mb-3"><b>Created At:</b> <?= h($e['created_at']) ?></div>
    </div>
</div>
<div class="modal-footer">
    <?php if($status !== 'Paid'): ?>
        <button class="btn btn-primary" onclick="ExpenseUI.openEditModal(<?= $e['id'] ?>)">Edit</button>
        <button class="btn btn-success" onclick="ExpenseUI.openPaymentModal(<?= $e['id'] ?>, <?= $e['amount']-$amount_paid ?>)">Record Payment</button>
    <?php endif; ?>
    <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>