<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Items";
require_once __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

// Fetch all items (not trashed) - handle null PDO gracefully
$items = [];
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM items WHERE deleted_at IS NULL ORDER BY created_at DESC");
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $items = [];
    }
} else {
    // Demo data when database is not available
    $items = [
        [
            'id' => 1,
            'name' => 'Premium Ice Cream - Vanilla',
            'category_id' => 1,
            'price' => 299.99,
            'unit' => 'piece',
            'description' => 'Premium vanilla ice cream 500ml',
            'stock_quantity' => 45,
            'image_url' => null,
            'created_at' => '2024-01-15 10:30:00'
        ],
        [
            'id' => 2,
            'name' => 'Chocolate Ice Cream Bar',
            'category_id' => 1,
            'price' => 89.99,
            'unit' => 'piece',
            'description' => 'Rich chocolate ice cream bar',
            'stock_quantity' => 120,
            'image_url' => null,
            'created_at' => '2024-01-14 15:20:00'
        ],
        [
            'id' => 3,
            'name' => 'Strawberry Popsicle',
            'category_id' => 2,
            'price' => 49.99,
            'unit' => 'piece',
            'description' => 'Fresh strawberry popsicle',
            'stock_quantity' => 200,
            'image_url' => null,
            'created_at' => '2024-01-13 12:10:00'
        ]
    ];
}

function get_category_name($category_id, $pdo) {
    if (!$category_id) return 'Uncategorized';
    if (!$pdo) {
        // Demo category names
        $categories = [1 => 'Ice Cream', 2 => 'Popsicles', 3 => 'Frozen Treats'];
        return $categories[$category_id] ?? 'Uncategorized';
    }
    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id=? LIMIT 1");
    $stmt->execute([$category_id]);
    return $stmt->fetchColumn() ?: 'Uncategorized';
}
?>
<div class="page-content-wrapper">
    <div class="container mt-3">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="text-primary"><i class="fas fa-cube me-2"></i> Items Management</h2>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-primary btn-3d" onclick="ItemUI.openAddModal()">
                    <i class="fas fa-plus me-1"></i> Add Item
                </button>
            </div>
        </div>
    <div class="alert alert-info max-width-700 mb-4">
        <strong>Manage your items.</strong> Use <strong>Add Item</strong> to create new products and <strong>Trash</strong> to view or restore deleted items.<br>
        All columns are searchable and sortable. Click any row for details and editing.
    </div>
    <div class="mb-3 header-buttons-secondary">
        <a class="btn btn-outline-danger btn-3d" href="trash.php" title="View Trash">
            <i class="fas fa-trash me-1"></i> Trash
        </a>
    </div>
    
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="entity-table table table-striped table-hover table-consistent" id="items-table">
                    <thead class="table-light">
                        <tr>
                            <th data-priority="6" class="text-center width-80px">Image</th>
                            <th data-priority="4">Code</th>
                            <th data-priority="1">Name</th>
                            <th data-priority="2">Price/Unit</th>
                            <th data-priority="3">Category</th>
                            <th data-priority="7">Created</th>
                            <th data-priority="1" class="text-center width-150px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            <?php foreach ($items as $item): ?>
                <tr data-item-id="<?= $item['id'] ?>" data-item='<?= h(json_encode($item)) ?>' class="cursor-pointer">
                    <td class="text-center" data-label="Image">
                        <?php if (!empty($item['image_url'])): ?>
                            <img src="<?= h($item['image_url']) ?>" alt="Image" class="rounded size-40px object-fit-cover">
                        <?php else: ?>
                            <div class="bg-light rounded d-flex align-items-center justify-content-center size-40px">
                                <i class="fas fa-image text-muted"></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td data-label="Code"><code class="text-primary"><?= h($item['id']) ?></code></td>
                    <td data-label="Name">
                        <div class="fw-semibold"><?= h($item['name']) ?></div>
                        <small class="text-muted"><?= h(substr($item['description'] ?? '', 0, 50)) ?><?= strlen($item['description'] ?? '') > 50 ? '...' : '' ?></small>
                    </td>
                    <td data-label="Price/Unit">
                        <span class="fw-bold text-success"><?= format_currency($item['price_per_unit']) ?></span>
                    </td>
                    <td data-label="Category">
                        <span class="badge-consistent badge-secondary"><?= h(get_category_name($item['category_id'], $pdo)) ?></span>
                    </td>
                    <td data-label="Created">
                        <small class="text-muted"><?= date('M j, Y', strtotime($item['created_at'])) ?></small>
                    </td>
                    <td class="text-center" data-label="Actions">
                        <div class="btn-group btn-group-sm action-icons" role="group">
                            <button class="btn btn-outline-primary btn-3d" title="Details" onclick="ItemUI.openDetails(<?= $item['id'] ?>)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-outline-warning btn-3d" title="Edit" onclick="ItemUI.openEditModal(<?= $item['id'] ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-outline-danger btn-3d item-delete-btn" title="Delete"
                                    data-item-id="<?= $item['id'] ?>"
                                    data-item-name="<?= h($item['name']) ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </div>
</div>

<!-- Bootstrap Modals -->
<div class="modal fade" id="item-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="itemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<div class="modal fade" id="item-details-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="itemDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="item-delete-confirm-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="deleteItemModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteItemModalLabel">Delete Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="item-delete-modal-message">Are you sure you want to delete <span id="item-delete-name" class="fw-bold"></span>?</p>
                <div id="item-delete-modal-error" class="alert alert-danger d-none"></div>
            </div>
            <div class="modal-footer">
                <button id="item-delete-confirm-btn" class="btn btn-danger">Delete</button>
                <button id="item-delete-cancel-btn" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>
<script>
window.FROZO_ITEM_CODES = <?= json_encode(array_column($items, 'code')) ?>;

// Initialize DataTable with UnifiedTables
$(document).ready(function() {
    if ($('#items-table').length && window.UnifiedTables) {
        UnifiedTables.init('#items-table', 'items');
    }
});
</script>
<script src="js/items.js"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>