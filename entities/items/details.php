<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$stmt = $pdo->prepare("SELECT * FROM items WHERE id=?");
$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) { echo "Item not found."; exit; }

$category = '';
if ($item && $item['category_id']) {
    $cat = $pdo->prepare("SELECT name FROM categories WHERE id=?");
    $cat->execute([$item['category_id']]);
    $category = $cat->fetchColumn();
}
?>
<div class="modal-header">
    <h5 class="modal-title"><?= h($item['name']) ?> Details</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <div class="entity-details" data-item-id="<?= $item['id'] ?>">
        <div class="mb-3">
            <strong>Code:</strong> 
            <span><?= h($item['code']) ?></span>
        </div>
        <div class="mb-3">
            <strong>Price Per Unit:</strong>
            <span><?= number_format($item['price_per_unit'], 2) ?></span>
        </div>
        <div class="mb-3">
            <strong>Default Pack Size:</strong>
            <span><?= h($item['default_pack_size']) ?></span>
        </div>
        <div class="mb-3">
            <strong>Category:</strong>
            <span><?= h($category) ?></span>
        </div>
        <div class="mb-3">
            <strong>Created at:</strong>
            <span><?= date('Y-m-d', strtotime($item['created_at'])) ?></span>
        </div>
        <?php if ($item['image']): ?>
            <div class="mb-3">
                <strong>Image:</strong>
                <div class="mt-2">
                    <img src="/uploads/items/<?= h($item['image']) ?>" alt="Image" class="img-thumbnail" style="max-width: 300px;">
                </div>
            </div>
        <?php else: ?>
            <div class="mb-3">
                <strong>Image:</strong>
                <span class="text-muted">No image</span>
            </div>
        <?php endif; ?>
    </div>
</div>
<div class="modal-footer">
    <button class="btn btn-primary" onclick="ItemUI.openEditModal(<?= $item['id'] ?>, true)">Edit</button>
    <button class="btn btn-danger" onclick="ItemUI.deleteItem(<?= $item['id'] ?>)" title="Delete"><i class="fa fa-trash"></i> Delete</button>
    <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>