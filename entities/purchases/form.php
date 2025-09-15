<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

// Determine if editing or adding
$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$purchase = null;
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM purchases WHERE id=? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$id]);
    $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get vendors for dropdown
$vendors = $pdo->query("SELECT id, name FROM vendors WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Default values
$form = [
    'vendor_id' => $purchase['vendor_id'] ?? '',
    'date' => $purchase['date'] ?? date('Y-m-d'),
    'type' => $purchase['type'] ?? 'cash',
    'amount' => $purchase['amount'] ?? '',
    'description' => $purchase['description'] ?? '',
];

// Show available advances for the selected vendor (only if adding or vendor_id exists)
$advance_notice = '';
$vendor_id_for_adv = $form['vendor_id'];
if (!$id && !empty($_GET['vendor_id'])) {
    $vendor_id_for_adv = intval($_GET['vendor_id']);
}
if ($vendor_id_for_adv) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) as surplus FROM vendor_advances WHERE vendor_id=? AND applied=0 AND applied_to_purchase_id IS NULL AND amount > 0");
    $stmt->execute([$vendor_id_for_adv]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $surplus = $row ? floatval($row['surplus']) : 0;
    if ($surplus > 0) {
        $advance_notice = "<div class='alert alert-info'><b>Note:</b> This vendor has an available advance/surplus of <b>".number_format($surplus,2)."</b>. If you create a <b>credit</b> purchase, it will be auto-applied to the new purchase.</div>";
    }
}
?>
<div class="modal-header">
    <h5 class="modal-title"><?= $id ? "Edit" : "Add" ?> Purchase</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <form method="post" action="/entities/purchases/actions.php" id="purchase-form" class="entity-form" autocomplete="off" data-refresh-on-success="true">
        <input type="hidden" name="action" value="<?= $id ? 'edit' : 'add' ?>">
        <?php if ($id): ?>
            <input type="hidden" name="id" value="<?= $id ?>">
        <?php endif; ?>
        <?php if ($advance_notice): ?>
            <?= $advance_notice ?>
        <?php endif; ?>
        <div class="mb-3">
            <label for="vendor_id" class="form-label">Vendor <span style="color:#c00">*</span></label>
            <select name="vendor_id" id="vendor_id" class="form-select" required>
                <option value="">-- Select Vendor --</option>
                <?php foreach ($vendors as $v): ?>
                    <option value="<?= $v['id'] ?>" <?= $form['vendor_id'] == $v['id'] ? 'selected' : '' ?>><?= h($v['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-primary btn-sm mt-2" onclick="VendorUI.openAddModal()">+ Add Vendor</button>
        </div>
        <div class="mb-3">
            <label for="date" class="form-label">Date <span style="color:#c00">*</span></label>
            <input type="date" name="date" id="date" class="form-control" value="<?= h($form['date']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Type <span style="color:#c00">*</span></label>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="type" value="cash" <?= $form['type'] === 'cash' ? 'checked' : '' ?> id="type_cash">
                <label class="form-check-label" for="type_cash">Cash</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="type" value="credit" <?= $form['type'] === 'credit' ? 'checked' : '' ?> id="type_credit">
                <label class="form-check-label" for="type_credit">Credit</label>
            </div>
        </div>
        <div class="mb-3">
            <label for="amount" class="form-label">Amount <span style="color:#c00">*</span></label>
            <input type="number" name="amount" id="amount" class="form-control" value="<?= h($form['amount']) ?>" step="0.01" min="0" required>
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <input type="text" name="description" id="description" class="form-control" value="<?= h($form['description']) ?>" maxlength="255">
        </div>
        <div class="d-flex justify-content-end gap-2">
            <button type="submit" class="btn btn-primary"><?= $id ? 'Update' : 'Add' ?> Purchase</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    </form>
</div>
<script>
    // If you use select2, initialize it here for vendor_id
    // $('#vendor_id').select2({width:'100%',dropdownParent: $('#purchase-modal')});
    // Optionally, update advance notice on vendor change via AJAX
    document.getElementById('vendor_id').addEventListener('change', function() {
        // You can add AJAX to update advance notice if needed
    });
</script>