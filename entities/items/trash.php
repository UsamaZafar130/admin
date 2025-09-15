<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Deleted Items";
include __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

$stmt = $pdo->prepare("SELECT * FROM items WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

function get_category_name($category_id, $pdo) {
    if (!$category_id) return '';
    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id=? LIMIT 1");
    $stmt->execute([$category_id]);
    return $stmt->fetchColumn() ?: '';
}
?>
<div class="entity-header">
    <h2>Deleted Items (Trash)</h2>
    <a class="btn btn-secondary" href="list.php">← Back to Items</a>
</div>
<div id="items-trash-wrap">
    <table class="entity-table" id="items-trash-table">
        <thead>
            <tr>
                <th>Image</th>
                <th>Code</th>
                <th>Name</th>
                <th>Deleted At</th>
                <th>Category</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr data-item-id="<?= $item['id'] ?>">
                <td data-label="Image">
                    <?php if ($item['image']): ?>
                        <img src="/uploads/items/<?= h($item['image']) ?>" alt="Image" style="width:38px; height:38px; object-fit:cover; border-radius:8px;">
                    <?php else: ?>
                        <span style="color:#999;">No image</span>
                    <?php endif; ?>
                </td>
                <td data-label="Code"><?= h($item['code']) ?></td>
                <td data-label="Name"><?= h($item['name']) ?></td>
                <td data-label="Deleted At"><?= date('Y-m-d H:i', strtotime($item['deleted_at'])) ?></td>
                <td data-label="Category"><?= h(get_category_name($item['category_id'], $pdo)) ?></td>
                <td data-label="Actions">
                    <button class="btn-ico restore" title="Restore" onclick="ItemUI.restoreItem(<?= $item['id'] ?>)"><i class="fa fa-undo"></i></button>
                    <button class="btn-ico danger" title="Delete Permanently" onclick="ItemUI.deleteItemPermanent(<?= $item['id'] ?>)"><i class="fa fa-trash"></i></button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="js/items.js"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>