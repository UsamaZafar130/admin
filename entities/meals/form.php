<?php
// Form for adding/editing meals
$isEdit = isset($mealData) && !empty($mealData);
$meal = $isEdit ? $mealData : null;
?>

<div class="modal-header">
    <h5 class="modal-title" id="mealModalLabel">
        <i class="fas fa-utensils me-2"></i>
        <?= $isEdit ? 'Edit Meal' : 'Add New Meal' ?>
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form id="meal-form" autocomplete="off">
    <div class="modal-body">
        <?php if ($isEdit): ?>
            <input type="hidden" name="meal_id" value="<?= h($meal['id']) ?>">
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="mb-3">
                    <label for="meal-name" class="form-label">Meal Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="meal-name" name="name" 
                           value="<?= $isEdit ? h($meal['name']) : '' ?>" required>
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="meal-price" class="form-label">Meal Price <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="meal-price" name="price" 
                           value="<?= $isEdit ? $meal['price'] : '' ?>" min="0" step="0.01" required>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="meal-active" name="active" value="1"
                               <?= (!$isEdit || (!empty($meal['active']) && $meal['active'] == 1)) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="meal-active">
                            Active (available for orders)
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="mb-3">
                    <label for="meal-seo-title" class="form-label">SEO Title</label>
                    <input type="text" class="form-control" id="meal-seo-title" name="seo_title" 
                           value="<?= $isEdit ? h($meal['seo_title'] ?? '') : '' ?>" placeholder="Leave empty to auto-generate from name">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="mb-3">
                    <label for="meal-seo-short-description" class="form-label">SEO Short Description</label>
                    <textarea class="form-control" id="meal-seo-short-description" name="seo_short_description" 
                              rows="2" placeholder="Brief description for search engines"><?= $isEdit ? h($meal['seo_short_description'] ?? '') : '' ?></textarea>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="mb-3">
                    <label for="meal-seo-description" class="form-label">SEO Description</label>
                    <textarea class="form-control" id="meal-seo-description" name="seo_description" 
                              rows="3" placeholder="Detailed description for search engines"><?= $isEdit ? h($meal['seo_description'] ?? '') : '' ?></textarea>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="mb-3">
                    <label class="form-label">Public Visibility</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="is_public" id="meal-public-yes" value="1" 
                               <?= (!$isEdit || empty($meal['is_public']) || $meal['is_public'] == 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="meal-public-yes">Public</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="is_public" id="meal-public-no" value="0" 
                               <?= ($isEdit && isset($meal['is_public']) && $meal['is_public'] == 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="meal-public-no">Private</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Display Image</label>
                    <?php if ($isEdit && !empty($meal['display_image'])): ?>
                        <div class="mb-2">
                            <img src="/uploads/meals/img/<?= h($meal['display_image']) ?>" alt="Display Image" class="meal-form-image img-thumbnail" style="max-width: 200px;">
                        </div>
                    <?php endif; ?>
                    <input name="display_image" type="file" class="form-control" accept="image/*" id="display-image-input">
                    <div class="form-text">Image for general display purposes</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Banner Image</label>
                    <?php if ($isEdit && !empty($meal['banner_image'])): ?>
                        <div class="mb-2">
                            <img src="/uploads/meals/banner/<?= h($meal['banner_image']) ?>" alt="Banner Image" class="meal-form-image img-thumbnail" style="max-width: 200px;">
                        </div>
                    <?php endif; ?>
                    <input name="banner_image" type="file" class="form-control" accept="image/*" id="banner-image-input">
                    <div class="form-text">Image for banner/hero display</div>
                </div>
            </div>
        </div>

        <hr>
        
        <h6 class="mb-3">
            <i class="fas fa-list me-2"></i>Meal Items
            <span class="text-muted small">(Select items and quantities for this meal)</span>
        </h6>
        
        <div class="table-responsive">
            <table class="table table-sm" id="meal-items-table">
                <thead class="table-light">
                    <tr>
                        <th width="40%">Item</th>
                        <th width="20%">Quantity</th>
                        <th width="20%">Pack Size</th>
                        <th width="20%">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Items will be added dynamically -->
                </tbody>
            </table>
        </div>
        
        <button type="button" id="add-meal-item" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Add Item
        </button>
    </div>

    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="save-meal-btn">
            <i class="fas fa-save me-1"></i>
            <?= $isEdit ? 'Update Meal' : 'Save Meal' ?>
        </button>
    </div>
</form>

<script>
// Initialize meal form
$(document).ready(function() {
    let availableItems = [];
    let mealItemsCount = 0;
    
    // Load available items
    function loadItems() {
        $.getJSON('/entities/sales/new_order.php?ajax=item_list', function(items) {
            availableItems = items;
            
            // If editing, load existing meal items
            <?php if ($isEdit): ?>
                loadMealItems(<?= $meal['id'] ?>);
            <?php else: ?>
                addMealItemRow(); // Add one empty row for new meals
            <?php endif; ?>
        });
    }
    
    // Load existing meal items for editing
    function loadMealItems(mealId) {
        $.getJSON('/api/meals.php?action=get&id=' + mealId, function(response) {
            if (response.success && response.meal.items) {
                response.meal.items.forEach(function(item) {
                    addMealItemRow(item);
                });
                if (response.meal.items.length === 0) {
                    addMealItemRow(); // Add empty row if no items
                }
            } else {
                addMealItemRow(); // Add empty row on error
            }
        });
    }
    
    // Add a new meal item row
    function addMealItemRow(itemData = null) {
        const rowId = 'meal-item-' + (++mealItemsCount);
        const $row = $(`
            <tr id="${rowId}">
                <td>
                    <select class="form-control item-select" name="items[${mealItemsCount}][item_id]" required>
                        <option value="">Select Item</option>
                    </select>
                </td>
                <td>
                    <input type="number" class="form-control qty-input" name="items[${mealItemsCount}][qty]" 
                           value="${itemData ? itemData.qty : '1'}" min="0.01" step="0.01" required>
                </td>
                <td>
                    <input type="number" class="form-control pack-size-input" name="items[${mealItemsCount}][pack_size]" 
                           value="${itemData ? itemData.pack_size : '1'}" min="0.01" step="0.01" required>
                </td>
                <td>
                    <button type="button" class="btn btn-outline-danger btn-sm remove-item-btn">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `);
        
        $('#meal-items-table tbody').append($row);
        
        // Populate items dropdown
        const $select = $row.find('.item-select');
        availableItems.forEach(function(item) {
            $select.append(`<option value="${item.id}" data-pack="${item.default_pack_size}">${item.name}</option>`);
        });
        
        // Set selected item if editing
        if (itemData && itemData.item_id) {
            $select.val(itemData.item_id);
        }
        
        // Initialize TomSelect for the dropdown
        new TomSelect($select[0], {
            create: false,
            sortField: 'text'
        });
        
        // Auto-fill pack size when item changes
        $select.on('change', function() {
            const $option = $(this).find('option:selected');
            const defaultPack = $option.data('pack') || 1;
            $row.find('.pack-size-input').val(defaultPack);
        });
        
        // Remove row functionality
        $row.find('.remove-item-btn').on('click', function() {
            $row.remove();
        });
    }
    
    // Add item button
    $('#add-meal-item').on('click', function() {
        addMealItemRow();
    });
    
    // Form submission
    $('#meal-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const items = [];
        
        // Debug: Log form data
        console.log('Form elements:', this.elements);
        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }
        
        // Collect meal items
        $('#meal-items-table tbody tr').each(function() {
            const $row = $(this);
            const itemId = $row.find('.item-select').val();
            const qty = $row.find('.qty-input').val();
            const packSize = $row.find('.pack-size-input').val();
            
            if (itemId && qty && packSize) {
                items.push({
                    item_id: parseInt(itemId),
                    qty: parseFloat(qty),
                    pack_size: parseFloat(packSize)
                });
            }
        });
        
        if (items.length === 0) {
            alert('Please add at least one item to the meal.');
            return;
        }
        
        // Add items as JSON string to FormData
        formData.append('items', JSON.stringify(items));
        console.log('Items JSON:', JSON.stringify(items));
        
        <?php if ($isEdit): ?>
            const action = 'edit';
        <?php else: ?>
            const action = 'add';
        <?php endif; ?>
        
        console.log('Submitting action:', action);
        
        $.ajax({
            url: '/api/meals.php?action=' + action,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                console.log('AJAX Response:', response);
                if (response.success) {
                    alert('Meal saved successfully! ID: ' + (response.meal_id || 'N/A'));
                    $('#meal-modal').modal('hide');
                    location.reload();
                } else {
                    alert('Error: ' + (response.message || 'Unknown error'));
                    console.error('API Error:', response);
                }
            },
            error: function(xhr, status, err) {
                console.error('AJAX Error:', {xhr, status, err, responseText: xhr.responseText});
                alert('AJAX Error: ' + err + "\n" + xhr.responseText);
            }
        });
    });
    
    // Image preview functionality
    $('#display-image-input').on('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                let $preview = $('#display-image-input').siblings('.mb-2');
                if ($preview.length === 0) {
                    $preview = $('<div class="mb-2"></div>');
                    $('#display-image-input').before($preview);
                }
                $preview.html('<img src="' + e.target.result + '" alt="Display Image Preview" class="meal-form-image img-thumbnail" style="max-width: 200px;">');
            };
            reader.readAsDataURL(file);
        }
    });
    
    $('#banner-image-input').on('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                let $preview = $('#banner-image-input').siblings('.mb-2');
                if ($preview.length === 0) {
                    $preview = $('<div class="mb-2"></div>');
                    $('#banner-image-input').before($preview);
                }
                $preview.html('<img src="' + e.target.result + '" alt="Banner Image Preview" class="meal-form-image img-thumbnail" style="max-width: 200px;">');
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Initialize
    loadItems();
});
</script>