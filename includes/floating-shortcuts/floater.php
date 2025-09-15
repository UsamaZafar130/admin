<?php
/**
 * Floating Action Button for Inventory Shortcuts
 * This file contains the HTML for the floating action button and its associated modals
 * that provide quick access to inventory functions across all pages
 */
?>

<!-- Floating Action Button & Actions Menu -->
<button id="floating-inventory-btn" title="Inventory Shortcuts (CTRL + ALT + F)">
    <i class="fa fa-cubes"></i>
</button>
<div id="floating-inventory-actions" style="display: none;">
    <div class="floating-actions-menu">
        <button class="floating-action-item" id="show-stock-required" title="CTRL + ALT + 1">
            <i class="fa fa-list-alt"></i> Show Stock Required :1
        </button>
        <button class="floating-action-item" id="show-excess-stock" title="CTRL + ALT + 2">
            <i class="fa fa-boxes"></i> Show Excess Stock :2
        </button>
        <!-- Add Stock shortcut -->
        <button class="floating-action-item" id="show-add-stock" title="CTRL + ALT + 3">
            <i class="fa fa-plus"></i> Add Stock :3
        </button>
        <!-- Updated: Add New Order now shows modal on current page -->
        <button class="floating-action-item" id="show-add-order" title="CTRL + ALT + 4">
            <i class="fa fa-shopping-cart"></i> Add New Order :4
        </button>
        <!-- Scan Packs shortcut -->
        <a class="floating-action-item" id="show-scan-packs" href="/entities/inventory/packing_scan.php" target="_blank" title="CTRL + ALT + 5">
            <i class="fa fa-barcode"></i> Scan Packs :5
        </a>
        <!-- Add Customer Modal shortcut -->
        <button class="floating-action-item" id="show-add-customer" title="CTRL + ALT + 6">
            <i class="fa fa-user-plus"></i> Add Customer :6
        </button>
        <!-- Add Purchase Modal shortcut -->
        <button class="floating-action-item" id="show-add-purchase" title="CTRL + ALT + 7">
            <i class="fa fa-shopping-cart"></i> Add Purchase :7
        </button>
        <!-- Add Expenses Modal shortcut -->
        <button class="floating-action-item" id="show-add-expense" title="CTRL + ALT + 8">
            <i class="fa fa-credit-card"></i> Add Expense :8
        </button>
        <!-- Current Batch shortcut -->
        <button class="floating-action-item" id="show-current-batch" title="CTRL + ALT + 9">
            <i class="fa fa-boxes"></i> Current Batch :9
        </button>
    </div>
</div>

<!-- Excess Stock Modal (Bootstrap Modal) -->
<div class="modal fade" id="excess-stock-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="excessStockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="excessStockModalLabel">
                    <i class="fa fa-boxes me-2"></i>Excess Stock (Surplus Items)
                </h5>
                <button type="button" class="btn btn-outline-secondary btn-sm ms-auto me-2" id="copy-excess-btn" title="Copy Excess List">
                    <i class="fa fa-copy me-1"></i>Copy
                </button>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="excess-stock-table-container">
                    <!-- Table will be injected by JS -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stock Requirements Modal (Bootstrap Modal) -->
<div class="modal fade" id="floating-inventory-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="stockRequirementsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="fa fa-list-alt me-2">Stock Requirements
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="floating-inventory-modal-body">
                <div class="text-center p-4">
                    <i class="fa fa-spinner fa-spin me-2"></i>Loading stock requirements...
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Stock Modal (Bootstrap Modal) -->
<div class="modal fade" id="floating-add-stock-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="floatingAddStockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg"><!-- widened for table -->
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="floatingAddStockModalLabel">
                    <i class="fa fa-plus me-2"></i>Add Stock
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Entry form: adds one row to the list (no server call yet) -->
                <form id="floating-add-stock-entry-form" class="entity-form">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="floating-stock-item-select" class="form-label">Item</label>
                            <select id="floating-stock-item-select" name="item_id" class="form-control tom-select"></select>
                        </div>
                        <div class="col-md-3">
                            <label for="floating-stock-qty" class="form-label">Quantity Manufactured</label>
                            <input type="number" id="floating-stock-qty" name="qty" class="form-control" step="0.01" required>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100" id="floating-add-to-list-btn">
                                <i class="fa fa-plus me-1"></i> Add to List
                            </button>
                        </div>
                    </div>
                    <div class="mb-3 mt-2" id="floating-stock-comment-row" style="display:none;">
                        <label for="floating-stock-comment" class="form-label">Comment <span class="text-danger">*</span></label>
                        <input type="text" id="floating-stock-comment" name="comment" class="form-control" maxlength="250">
                    </div>
                </form>
                <div id="floating-add-stock-feedback" class="alert mt-2" style="display:none"></div>

                <hr class="my-3">

                <!-- Pending entries table (client-side list) -->
                <div class="table-responsive">
                    <table class="table table-striped align-middle" id="floating-add-stock-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 5%">#</th>
                                <th>Item</th>
                                <th style="width: 15%">Quantity</th>
                                <th>Comment</th>
                                <th style="width: 10%">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="floating-add-stock-rows">
                            <tr class="no-rows">
                                <td colspan="5" class="text-muted text-center py-3">
                                    No items added yet. Use the form above to add entries.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <div class="small text-muted">
                    Entries in list: <span id="floating-add-stock-count">0</span>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-danger me-2" id="floating-add-stock-clear">
                        <i class="fa fa-trash me-1"></i> Clear List
                    </button>
                    <button type="button" class="btn btn-primary" id="floating-add-stock-submit-all">
                        <i class="fa fa-check me-1"></i> Submit All
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include the reusable modal components for floating action button
if (file_exists(__DIR__ . '/../order_modal.php')) {
    include_once __DIR__ . '/../order_modal.php';
}
if (file_exists(__DIR__ . '/../customer_modal.php')) {
    include_once __DIR__ . '/../customer_modal.php';
}
if (file_exists(__DIR__ . '/../purchase_modal.php')) {
    include_once __DIR__ . '/../purchase_modal.php';
}
if (file_exists(__DIR__ . '/../expense_modal.php')) {
    include_once __DIR__ . '/../expense_modal.php';
}
?>