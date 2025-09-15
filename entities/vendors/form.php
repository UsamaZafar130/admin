<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$editing = $id > 0;
$vendor = [
    'name'=>'', 'contact'=>'', 'address'=>'', 'area'=>'', 'city'=>'', 'location'=>''
];
if ($editing) {
    $stmt = $pdo->prepare("SELECT * FROM vendors WHERE id=?");
    $stmt->execute([$id]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<div class="modal-header">
    <h5 class="modal-title"><?= $editing ? "Edit" : "Add" ?> Vendor</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <form id="vendor-form" class="entity-form" data-editing="<?= $editing ? 1 : 0 ?>" data-refresh-on-success="true">
        <input type="hidden" name="id" value="<?= h($id) ?>">
        <div class="mb-3">
            <label class="form-label">Name *</label>
            <input name="name" id="vendor-name" type="text" class="form-control" required value="<?= h($vendor['name']) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Contact</label>
            <input name="contact" type="text" class="form-control" value="<?= h($vendor['contact']) ?>" id="vendor-contact">
        </div>
        <div class="mb-3">
            <label class="form-label">Address</label>
            <input name="address" type="text" class="form-control" value="<?= h($vendor['address']) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Area</label>
            <input name="area" type="text" class="form-control" value="<?= h($vendor['area']) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">City</label>
            <input name="city" type="text" class="form-control" value="<?= h($vendor['city']) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Location (URL or description)</label>
            <input name="location" type="text" class="form-control" value="<?= h($vendor['location']) ?>">
        </div>
        <div class="d-flex justify-content-end gap-2">
            <button type="submit" class="btn btn-primary"><?= $editing ? "Update" : "Add" ?> Vendor</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
    </form>
</div>
<script>
if (window.VendorUI) {
    VendorUI.enhanceForm(document.getElementById('vendor-form'));
}
</script>