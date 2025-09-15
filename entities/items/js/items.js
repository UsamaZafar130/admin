/**
 * Items Management JavaScript - Uses UnifiedModals for Bootstrap 5 modal handling
 * Contains only entity-specific logic (image preview, form validation, etc.)
 */

const ITEM_BASE = '/entities/items/';
const ItemUI = {
    
    // Open Add Item Modal using UnifiedModals
    openAddModal: function() {
        window.UnifiedModals.loadAndShow('item-modal', ITEM_BASE + 'form.php', {
            onLoaded: (modal, bsModal) => {
                ItemUI._setupItemFormHandlers(modal);
            }
        });
    },
    
    // Open Edit Item Modal using UnifiedModals
    openEditModal: function(itemId, fromDetails = false) {
        window.UnifiedModals.loadAndShow('item-modal', ITEM_BASE + 'form.php?id=' + itemId, {
            onLoaded: (modal, bsModal) => {
                ItemUI._setupItemFormHandlers(modal);
            }
        });
    },
    
    // Open Item Details Modal using UnifiedModals
    openDetails: function(itemId) {
        window.UnifiedModals.loadAndShow('item-details-modal', ITEM_BASE + 'details.php?id=' + itemId);
    },
    
    // Set up form handlers (entity-specific functionality)
    _setupItemFormHandlers: function(modal) {
        const form = modal.querySelector('form');
        if (form) {
            // Set up code auto-generation
            ItemUI._setupCodeAutogeneration(form);
            
            // Set up category assignment
            ItemUI._setupCategoryAssignment(form);
            
            // Set up image preview (entity-specific functionality)
            const itemImageInput = form.querySelector('input[name="item_image"]');
            if (itemImageInput) {
                itemImageInput.addEventListener('change', function () {
                    const [file] = itemImageInput.files;
                    if (file) {
                        const imgPreview = form.querySelector('#item-image-preview');
                        if (imgPreview) {
                            imgPreview.src = URL.createObjectURL(file);
                            imgPreview.style.display = 'block';
                        }
                    }
                });
            }
        }
    },
    
    // Update item row in table (entity-specific logic)
    _updateItemRow: function(item) {
        const row = document.querySelector(`#items-table tbody tr[data-item-id='${item.id}']`);
        if (!row) return;
        
        // Update row data
        row.setAttribute('data-item', JSON.stringify(item));
        
        // Update visible cells
        const nameCell = row.querySelector('[data-label="Name"] .fw-semibold');
        if (nameCell) nameCell.textContent = item.name;
        
        const priceCell = row.querySelector('[data-label="Price/Unit"] .fw-bold');
        if (priceCell) priceCell.textContent = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(item.price_per_unit);
    },
    
    // Delete item
    deleteItem: function(id) {
        if (!confirm('Are you sure you want to delete this item?')) return;
        
        const data = new FormData();
        data.append('action', 'delete');
        data.append('id', id);
        
        fetch(ITEM_BASE + 'actions.php', {
            method:'POST',
            body:data
        }).then(r=>r.json()).then(resp=>{
            if (resp.success) {
                if (typeof window.showSuccess === 'function') {
                    window.showSuccess('Item deleted successfully');
                }
                location.reload();
            } else {
                if (typeof window.showError === 'function') {
                    window.showError(resp.error || 'Could not delete item!');
                } else {
                    alert(resp.error || 'Could not delete item!');
                }
            }
        }).catch(()=>{
            if (typeof window.showError === 'function') {
                window.showError('Network error occurred while deleting item');
            } else {
                alert('Network error occurred while deleting item');
            }
        });
    },
    
    // Restore item (for trash view)
    restoreItem: function(id) {
        if (!confirm('Restore this item?')) return;
        
        const data = new FormData();
        data.append('action', 'restore');
        data.append('id', id);
        
        fetch(ITEM_BASE + 'actions.php', {
            method:'POST',
            body:data
        }).then(r=>r.json()).then(resp=>{
            if (resp.success) {
                if (typeof window.showSuccess === 'function') {
                    window.showSuccess('Item restored successfully');
                }
                location.reload();
            } else {
                if (typeof window.showError === 'function') {
                    window.showError(resp.error || 'Could not restore item!');
                } else {
                    alert(resp.error || 'Could not restore item!');
                }
            }
        }).catch(()=>{
            if (typeof window.showError === 'function') {
                window.showError('Network error occurred while restoring item');
            } else {
                alert('Network error occurred while restoring item');
            }
        });
    },
    
    // Delete item permanently (for trash view)
    deleteItemPermanent: function(id) {
        if (!confirm('Permanently delete this item? This cannot be undone!')) return;
        
        const data = new FormData();
        data.append('action', 'delete_permanent');
        data.append('id', id);
        
        fetch(ITEM_BASE + 'actions.php', {
            method:'POST',
            body:data
        }).then(r=>r.json()).then(resp=>{
            if (resp.success) {
                if (typeof window.showSuccess === 'function') {
                    window.showSuccess('Item permanently deleted');
                }
                location.reload();
            } else {
                if (typeof window.showError === 'function') {
                    window.showError(resp.error || 'Could not permanently delete!');
                } else {
                    alert(resp.error || 'Could not permanently delete!');
                }
            }
        }).catch(()=>{
            if (typeof window.showError === 'function') {
                window.showError('Network error occurred while permanently deleting item');
            } else {
                alert('Network error occurred while permanently deleting item');
            }
        });
    },

    // Set up code auto-generation on name input
    _setupCodeAutogeneration: function(form) {
        const nameInput = form.querySelector('#item-name');
        const codeInput = form.querySelector('#item-code');
        const editing = form.getAttribute('data-editing') === '1';
        
        if (nameInput && codeInput && !editing) {
            let timeout;
            nameInput.addEventListener('input', function() {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    const name = nameInput.value.trim();
                    if (name) {
                        ItemUI._generateCode(name, codeInput);
                    } else {
                        codeInput.value = '';
                    }
                }, 300); // Debounce for 300ms
            });
        }
    },

    // Generate code from name using the codegen.php endpoint
    _generateCode: function(name, codeInput) {
        fetch(ITEM_BASE + 'codegen.php?name=' + encodeURIComponent(name))
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    codeInput.value = data.code;
                } else {
                    console.error('Code generation failed:', data.error);
                }
            })
            .catch(error => {
                console.error('Code generation error:', error);
            });
    },

    // Set up category assignment functionality
    _setupCategoryAssignment: function(form) {
        const categoryInput = form.querySelector('#item-category-input');
        const categoryIdInput = form.querySelector('#item-category-id');
        
        if (categoryInput && categoryIdInput) {
            // Get category map from the form data attribute
            let categoryMap = {};
            const catMapAttr = categoryInput.getAttribute('data-catmap');
            if (catMapAttr) {
                try {
                    categoryMap = JSON.parse(catMapAttr);
                } catch (e) {
                    console.error('Error parsing category map:', e);
                }
            }

            // Also try from global variable as fallback
            if (Object.keys(categoryMap).length === 0 && window.ITEM_CATEGORY_MAP) {
                categoryMap = window.ITEM_CATEGORY_MAP;
            }
            
            // Handle input change
            categoryInput.addEventListener('input', function() {
                const selectedName = this.value.trim();
                if (categoryMap[selectedName]) {
                    categoryIdInput.value = categoryMap[selectedName];
                    console.log('Category assigned:', selectedName, '->', categoryMap[selectedName]);
                } else {
                    categoryIdInput.value = '';
                }
            });

            // Handle blur event to ensure proper mapping
            categoryInput.addEventListener('blur', function() {
                const selectedName = this.value.trim();
                if (selectedName && !categoryMap[selectedName] && selectedName !== '-- None --' && selectedName !== '') {
                    // If a name was entered that doesn't exist in the map, clear both fields
                    console.log('Invalid category name entered:', selectedName);
                    this.value = '';
                    categoryIdInput.value = '';
                }
            });
            
            // Handle datalist selection
            categoryInput.addEventListener('change', function() {
                const selectedName = this.value.trim();
                if (categoryMap[selectedName]) {
                    categoryIdInput.value = categoryMap[selectedName];
                    console.log('Category selected via datalist:', selectedName, '->', categoryMap[selectedName]);
                } else if (selectedName === '' || selectedName === '-- None --') {
                    categoryIdInput.value = '';
                } else {
                    // Invalid selection, clear both
                    this.value = '';
                    categoryIdInput.value = '';
                }
            });
        }
    }
};

// Handle delete button clicks with confirmation modal using UnifiedModals
document.addEventListener('DOMContentLoaded', function() {
    const deleteModal = document.getElementById('item-delete-confirm-modal');
    const deleteConfirmBtn = document.getElementById('item-delete-confirm-btn');
    const deleteNameSpan = document.getElementById('item-delete-name');
    
    if (deleteModal && deleteConfirmBtn && deleteNameSpan) {
        // Handle delete button clicks
        document.addEventListener('click', function(e) {
            if (e.target.closest('.item-delete-btn')) {
                e.preventDefault();
                const btn = e.target.closest('.item-delete-btn');
                const itemId = btn.getAttribute('data-item-id');
                const itemName = btn.getAttribute('data-item-name');
                
                deleteNameSpan.textContent = itemName;
                deleteConfirmBtn.setAttribute('data-item-id', itemId);
                
                // Use UnifiedModals instead of custom Bootstrap modal code
                window.UnifiedModals.show(deleteModal);
            }
        });
        
        // Handle confirm delete
        deleteConfirmBtn.addEventListener('click', function() {
            const itemId = this.getAttribute('data-item-id');
            if (itemId) {
                ItemUI.deleteItem(itemId);
                window.UnifiedModals.hide(deleteModal);
            }
        });
    }
});

/**
 * Additional enhancement from legacy assets/js/entity-items.js:
 * Enable image preview on static (non-modal) item forms too.
 */
document.addEventListener('DOMContentLoaded', function () {
    const itemImageInput = document.querySelector('input[name="item_image"]');
    if (itemImageInput) {
        itemImageInput.addEventListener('change', function () {
            const [file] = itemImageInput.files;
            if (file) {
                const imgPreview = document.getElementById('item-image-preview');
                if (imgPreview) {
                    imgPreview.src = URL.createObjectURL(file);
                    imgPreview.style.display = 'block';
                }
            }
        });
    }
});