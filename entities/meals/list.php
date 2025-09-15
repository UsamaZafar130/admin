<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Meals";
require_once __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

// Fetch all meals (not deleted) - handle null PDO gracefully
$meals = [];
$dbError = false;

if ($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.id, m.name, m.price, m.active,
                   COUNT(mi.id) as item_count,
                   m.created_at, m.updated_at
            FROM meals m
            LEFT JOIN meal_items mi ON m.id = mi.meal_id
            GROUP BY m.id
            ORDER BY m.created_at DESC
        ");
        $stmt->execute();
        $meals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $meals = [];
        $dbError = true;
    }
} else {
    $dbError = true;
}
?>
<div class="page-content-wrapper">
    <div class="container mt-3">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="text-primary"><i class="fas fa-utensils me-2"></i> Meals Management</h2>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-primary btn-3d" data-bs-toggle="modal" data-bs-target="#meal-modal" onclick="MealUI.openAddModal()">
                    <i class="fas fa-plus me-1"></i> Add Meal
                </button>
            </div>
        </div>
    <div class="alert alert-info max-width-700 mb-4">
        <strong>Manage your meal deals.</strong> Use <strong>Add Meal</strong> to create new combo deals and set pricing.<br>
        Each meal can contain multiple items with specific quantities. All columns are searchable and sortable.
    </div>
    
    <?php if ($dbError): ?>
    <div class="alert alert-danger mb-4">
        <strong>Database Error:</strong> Unable to connect to the database. Please check your database configuration and try again.
    </div>
    <?php endif; ?>
    
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if ($dbError): ?>
                <div class="text-center p-4">
                    <p class="text-muted">No meals data available due to database connection error.</p>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="entity-table table table-striped table-hover table-consistent" id="meals-table">
                    <thead class="table-light">
                        <tr>
                            <th data-priority="4">Code</th>
                            <th data-priority="1">Name</th>
                            <th data-priority="2">Price</th>
                            <th data-priority="3">Items Count</th>
                            <th data-priority="5">Status</th>
                            <th data-priority="6">Created</th>
                            <th data-priority="1" class="text-center width-150px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            <?php foreach ($meals as $meal): ?>
                <tr data-meal-id="<?= $meal['id'] ?>" data-meal='<?= h(json_encode($meal)) ?>' class="cursor-pointer">
                    <td data-label="Code"><code class="text-primary"><?= h($meal['id']) ?></code></td>
                    <td data-label="Name">
                        <div class="fw-semibold"><?= h($meal['name']) ?></div>
                    </td>
                    <td data-label="Price">
                        <span class="fw-bold text-success"><?= format_currency($meal['price']) ?></span>
                    </td>
                    <td data-label="Items Count">
                        <span class="badge badge-consistent badge-info"><?= $meal['item_count'] ?> items</span>
                    </td>
                    <td data-label="Status">
                        <?php if ($meal['active']): ?>
                            <span class="badge badge-consistent badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-consistent badge-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Created">
                        <small class="text-muted"><?= date('M j, Y', strtotime($meal['created_at'])) ?></small>
                    </td>
                    <td class="text-center" data-label="Actions">
                        <div class="btn-group btn-group-sm action-icons" role="group">
                            <button class="btn btn-outline-primary btn-3d" title="Details" onclick="MealUI.openDetails(<?= $meal['id'] ?>)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-outline-warning btn-3d" title="Edit" onclick="MealUI.openEditModal(<?= $meal['id'] ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-outline-danger btn-3d meal-delete-btn" title="Delete"
                                    data-meal-id="<?= $meal['id'] ?>"
                                    data-meal-name="<?= h($meal['name']) ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    </div>
</div>

<!-- Bootstrap Modals -->
<div class="modal fade" id="meal-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="mealModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<div class="modal fade" id="meal-details-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="mealDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="meal-delete-confirm-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="deleteMealModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteMealModalLabel">Delete Meal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="meal-delete-modal-message">Are you sure you want to delete <span id="meal-delete-name" class="fw-bold"></span>?</p>
                <div id="meal-delete-modal-error" class="alert alert-danger d-none"></div>
            </div>
            <div class="modal-footer">
                <button id="meal-delete-confirm-btn" class="btn btn-danger">Delete</button>
                <button id="meal-delete-cancel-btn" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize DataTable with UnifiedTables
$(document).ready(function() {
    if ($('#meals-table').length && window.UnifiedTables) {
        UnifiedTables.init('#meals-table', 'meals');
    }
});
</script>
<script src="js/meals.js"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>