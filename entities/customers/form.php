<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$editing = $id > 0;
$customer = [
    'name'=>'', 'contact'=>'', 'contact_normalized'=>'', 'house_no'=>'', 'area'=>'', 'city'=>'', 'location'=>''
];
if ($editing) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id=?");
    $stmt->execute([$id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<div class="modal-body">
    <form id="customer-form" class="entity-form" method="post" action="/api/customers.php" data-editing="<?= $editing ? 1 : 0 ?>" data-refresh-on-success="true">
        <input type="hidden" name="action" value="<?= $editing ? 'edit' : 'add' ?>">
        <input type="hidden" name="id" value="<?= h($id) ?>">
        <div class="mb-3">
            <label class="form-label">Name *</label>
            <input name="name" id="customer-name" type="text" class="form-control" required value="<?= h($customer['name']) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Contact</label>
            <input name="contact" type="text" class="form-control" value="<?= h($customer['contact']) ?>" id="customer-contact">
        </div>
        <div class="mb-3">
            <label class="form-label">House No</label>
            <input name="house_no" type="text" class="form-control" value="<?= h($customer['house_no']) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Area</label>
            <input name="area" type="text" class="form-control" value="<?= h($customer['area']) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">City</label>
            <input name="city" type="text" class="form-control" value="<?= h($customer['city']) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Location (URL or description)</label>
            <input name="location" type="text" class="form-control" value="<?= h($customer['location']) ?>">
        </div>
        <div class="d-flex justify-content-end gap-2">
            <button type="submit" class="btn btn-primary"><?= $editing ? "Update" : "Add" ?> Customer</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
    </form>
</div>