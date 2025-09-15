<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$editing = $id > 0;
$item = [
    'code'=>'', 'name'=>'', 'price_per_unit'=>'', 'default_pack_size'=>'', 'category_id'=>'', 'image'=>'',
    'seo_title'=>'', 'seo_short_description'=>'', 'seo_description'=>'', 'slug'=>'', 'is_public'=>1
];
if ($editing) {
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id=?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
}
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$categoryName = '';
if ($editing && $item['category_id']) {
    foreach ($categories as $cat) {
        if ($cat['id'] == $item['category_id']) {
            $categoryName = $cat['name'];
            break;
        }
    }
}
// --- category map for JS ---
$catMap = [];
foreach ($categories as $cat) {
    $catMap[$cat['name']] = $cat['id'];
}
?>
<div class="modal-header">
    <h5 class="modal-title">
        <?php if ($editing): ?>
            Edit <?= h($item['name']) ?>
        <?php else: ?>
            Add New Item
        <?php endif; ?>
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <form id="item-form" class="entity-form" method="post" action="actions.php" data-editing="<?= $editing ? 1 : 0 ?>" data-refresh-on-success="true" enctype="multipart/form-data">
        <input type="hidden" name="action" value="<?= $editing ? 'edit' : 'add' ?>">
        <input type="hidden" name="id" value="<?= h($id) ?>">
        <div class="mb-3">
            <label class="form-label">Item Code *</label>
            <input name="code" id="item-code" type="text" class="form-control" required value="<?= h($item['code']) ?>" <?= $editing ? '' : 'readonly' ?> autocomplete="off">
        </div>
        <div class="mb-3">
            <label class="form-label">Name *</label>
            <input name="name" id="item-name" type="text" class="form-control" required value="<?= h($item['name']) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Price Per Unit *</label>
            <input name="price_per_unit" type="number" class="form-control" min="0" step="0.01" required value="<?= h($item['price_per_unit']) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Default Pack Size *</label>
            <input name="default_pack_size" type="number" class="form-control" min="1" required value="<?= h($item['default_pack_size']) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Category</label>
            <input
                list="category-list"
                name="category_name"
                id="item-category-input"
                class="form-control"
                value="<?= h($categoryName) ?>"
                autocomplete="off"
                data-catmap='<?= json_encode($catMap) ?>'
            >
            <input type="hidden" name="category_id" id="item-category-id" value="<?= h($item['category_id']) ?>">
            <datalist id="category-list">
                <option value="">-- None --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= h($cat['name']) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </div>
        <div class="mb-3">
            <label class="form-label">SEO Title</label>
            <input name="seo_title" type="text" class="form-control" value="<?= h($item['seo_title']) ?>" placeholder="Leave empty to auto-generate from name">
        </div>
        <div class="mb-3">
            <label class="form-label">SEO Short Description</label>
            <textarea name="seo_short_description" class="form-control" rows="2" placeholder="Brief description for search engines"><?= h($item['seo_short_description']) ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">SEO Description</label>
            <textarea name="seo_description" class="form-control" rows="3" placeholder="Detailed description for search engines"><?= h($item['seo_description']) ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Public Visibility</label>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="is_public" id="item-public-yes" value="1" <?= (empty($item['is_public']) || $item['is_public'] == 1) ? 'checked' : '' ?>>
                <label class="form-check-label" for="item-public-yes">Public</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="is_public" id="item-public-no" value="0" <?= (isset($item['is_public']) && $item['is_public'] == 0) ? 'checked' : '' ?>>
                <label class="form-check-label" for="item-public-no">Private</label>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Image</label>
            <?php if (!empty($item['image'])): ?>
                <div class="mb-2">
                    <img src="/uploads/items/<?= h($item['image']) ?>" alt="Image" class="item-form-image img-thumbnail" style="max-width: 200px;">
                </div>
            <?php endif; ?>
            <input name="image" type="file" class="form-control" accept="image/*">
        </div>
        <div class="d-flex justify-content-end gap-2">
            <button type="submit" class="btn btn-primary"><?= $editing ? "Update" : "Add" ?> Item</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
    </form>
</div>
<script>
// Make category map available globally for ItemUI._setupCategoryAssignment
window.ITEM_CATEGORY_MAP = <?= json_encode($catMap) ?>;
</script>