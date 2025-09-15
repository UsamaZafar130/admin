<?php
// Details view for a meal
$meal = $meal ?? null;
$meal_items = $meal_items ?? [];
?>

<div class="modal-header">
    <h5 class="modal-title" id="mealDetailsModalLabel">
        <i class="fas fa-utensils me-2"></i>
        Meal Details: <?= h($meal['name']) ?>
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">
    <div class="row mb-4">
        <div class="col-md-6">
            <h6 class="text-muted mb-2">Meal Information</h6>
            <table class="table table-sm table-borderless">
                <tr>
                    <td width="40%" class="fw-semibold">ID:</td>
                    <td><code class="text-primary"><?= h($meal['id']) ?></code></td>
                </tr>
                <tr>
                    <td class="fw-semibold">Name:</td>
                    <td><?= h($meal['name']) ?></td>
                </tr>
                <tr>
                    <td class="fw-semibold">Price:</td>
                    <td class="fw-bold text-success"><?= format_currency($meal['price']) ?></td>
                </tr>
                <tr>
                    <td class="fw-semibold">Status:</td>
                    <td>
                        <?php if ($meal['active']): ?>
                            <span class="badge badge-consistent badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-consistent badge-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td class="fw-semibold">Total Items:</td>
                    <td><span class="badge badge-consistent badge-info"><?= $meal['item_count'] ?> items</span></td>
                </tr>
                <tr>
                    <td class="fw-semibold">Created:</td>
                    <td class="text-muted"><?= date('M j, Y g:i A', strtotime($meal['created_at'])) ?></td>
                </tr>
                <?php if ($meal['updated_at'] !== $meal['created_at']): ?>
                <tr>
                    <td class="fw-semibold">Updated:</td>
                    <td class="text-muted"><?= date('M j, Y g:i A', strtotime($meal['updated_at'])) ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
    
    <?php if (!empty($meal_items)): ?>
    <div class="row">
        <div class="col-12">
            <h6 class="text-muted mb-3">
                <i class="fas fa-list me-2"></i>Items in this Meal
            </h6>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Item Name</th>
                            <th>Quantity</th>
                            <th>Pack Size</th>
                            <th>Unit Price</th>
                            <th>Item Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($meal_items as $item): ?>
                        <tr>
                            <td class="fw-semibold"><?= h($item['item_name']) ?></td>
                            <td><?= number_format($item['qty'], 2) ?></td>
                            <td><?= number_format($item['pack_size'], 2) ?></td>
                            <td><?= format_currency($item['price_per_unit']) ?></td>
                            <td class="fw-bold">
                                <?= format_currency($item['qty'] * $item['price_per_unit']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="4">Items Subtotal:</th>
                            <th class="text-success">
                                <?= format_currency(array_sum(array_map(function($item) {
                                    return $item['qty'] * $item['price_per_unit'];
                                }, $meal_items))) ?>
                            </th>
                        </tr>
                        <tr>
                            <th colspan="4">Meal Price:</th>
                            <th class="text-primary"><?= format_currency($meal['price']) ?></th>
                        </tr>
                        <tr>
                            <th colspan="4">Savings:</th>
                            <th class="<?= (array_sum(array_map(function($item) {
                                return $item['qty'] * $item['price_per_unit'];
                            }, $meal_items)) - $meal['price']) > 0 ? 'text-success' : 'text-danger' ?>">
                                <?= format_currency(array_sum(array_map(function($item) {
                                    return $item['qty'] * $item['price_per_unit'];
                                }, $meal_items)) - $meal['price']) ?>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>
        This meal has no items configured.
    </div>
    <?php endif; ?>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button type="button" class="btn btn-primary" onclick="MealUI.openEditModal(<?= $meal['id'] ?>)" data-bs-dismiss="modal">
        <i class="fas fa-edit me-1"></i> Edit Meal
    </button>
</div>