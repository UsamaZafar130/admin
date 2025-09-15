<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

// Determine if editing or adding
$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$expense = null;
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id=? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$id]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get vendors for dropdown
$vendors = $pdo->query("SELECT id, name FROM vendors WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Default values
$form = [
    'vendor_id' => $expense['vendor_id'] ?? '',
    'date' => $expense['date'] ?? date('Y-m-d'),
    'type' => $expense['type'] ?? 'cash',
    'amount' => $expense['amount'] ?? '',
    'description' => $expense['description'] ?? '',
];

// Show available advances for the selected vendor (only if adding or vendor_id exists)
$advance_notice = '';
$vendor_id_for_adv = $form['vendor_id'];
if (!$id && !empty($_GET['vendor_id'])) {
    $vendor_id_for_adv = intval($_GET['vendor_id']);
}
if ($vendor_id_for_adv) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) as surplus FROM vendor_advances WHERE vendor_id=? AND applied=0 AND applied_to_expense_id IS NULL AND amount > 0");
    $stmt->execute([$vendor_id_for_adv]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $surplus = $row ? floatval($row['surplus']) : 0;
    if ($surplus > 0) {
        $advance_notice = "<div class='alert alert-info'><b>Note:</b> This vendor has an available advance/surplus of <b>".number_format($surplus,2)."</b>. If you create a <b>credit</b> expense, it will be auto-applied to the new expense.</div>";
    }
}
?>
<div class="modal-header">
    <h5 class="modal-title"><?= $id ? "Edit" : "Add" ?> Expense</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <form method="post" action="/entities/expenses/actions.php" id="expense-form" class="entity-form" autocomplete="off" data-refresh-on-success="true">
        <input type="hidden" name="action" value="<?= $id ? 'edit' : 'add' ?>">
        <?php if ($id): ?>
            <input type="hidden" name="id" value="<?= $id ?>">
        <?php endif; ?>
        <?php if ($advance_notice): ?>
            <?= $advance_notice ?>
        <?php endif; ?>
        <div class="mb-3">
            <label for="vendor_id" class="form-label">Vendor</label>
            <select name="vendor_id" id="vendor_id" class="form-select">
                <option value="">-- No Vendor (Admin Expense) --</option>
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
            <small id="credit-warning" class="text-danger" style="display:none;">Credit expenses must have a vendor.</small>
        </div>
        <div class="mb-3">
            <label for="amount" class="form-label">Amount <span style="color:#c00">*</span></label>
            <input type="number" name="amount" id="amount" class="form-control" value="<?= h($form['amount']) ?>" step="0.01" required>
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description <span id="desc-required" style="color: #c00; display: none;">(required for negative)</span></label>
            <input type="text" name="description" id="description" class="form-control" value="<?= h($form['description']) ?>" maxlength="255">
        </div>
        <div class="d-flex justify-content-end gap-2">
            <button type="submit" class="btn btn-primary"><?= $id ? 'Update' : 'Add' ?> Expense</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    </form>
</div>
<script>
document.addEventListener("DOMContentLoaded", function() {
    function updateTypeOptions() {
        const vendorId = document.getElementById('vendor_id').value;
        const creditRadio = document.getElementById('type_credit');
        const cashRadio = document.getElementById('type_cash');
        const warning = document.getElementById('credit-warning');
        if (!vendorId) {
            creditRadio.disabled = true;
            if (creditRadio.checked) {
                cashRadio.checked = true;
            }
            warning.style.display = "inline";
        } else {
            creditRadio.disabled = false;
            warning.style.display = "none";
        }
    }
    document.getElementById('vendor_id').addEventListener('change', updateTypeOptions);
    updateTypeOptions();

    // Prevent form submit if credit and no vendor
    document.getElementById('expense-form').addEventListener('submit', function(e){
        const type = document.querySelector('input[name="type"]:checked').value;
        const vendorId = document.getElementById('vendor_id').value;
        if(type === 'credit' && !vendorId) {
            alert('Please select a vendor for credit expenses.');
            e.preventDefault();
            return false;
        }
    });

    // Negative amount validation
    const amountInput = document.getElementById('amount');
    const descRequired = document.getElementById('desc-required');
    const descInput = document.getElementById('description');
    
    if (amountInput) {
        amountInput.addEventListener('input', function() {
            if (parseFloat(this.value) < 0) {
                descRequired.style.display = 'inline';
                descInput.required = true;
            } else {
                descRequired.style.display = 'none';
                descInput.required = false;
            }
        });
    }
});
</script>