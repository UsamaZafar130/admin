/**
 * Floating Action Button JavaScript
 * Handles inventory shortcuts menu functionality globally across all pages
 */

// Global keyboard shortcuts for floating actions - works on all pages
$(document).on('keydown', function(e) {
    // Check if we're in the order modal - if so, handle order-specific shortcuts
    if ($('#orderModal').hasClass('show') || $('#orderModal').is(':visible')) {
        // Order Modal Shortcuts
        
        // Ctrl+Alt+C - Focus customer dropdown
        if (e.ctrlKey && e.altKey && e.key === 'c') {
            e.preventDefault();
            const customerSelect = $('#customer-select');
            if (customerSelect.length && customerSelect[0].tomselect) {
                customerSelect[0].tomselect.focus();
            }
            return;
        }
        
        // Ctrl+Alt+I - Focus empty items dropdown or add new item row
        if (e.ctrlKey && e.altKey && e.key === 'i') {
            e.preventDefault();
            const emptyItemSelect = $('#items-table tbody tr').find('.item-select').filter(function() {
                return !$(this).val();
            }).first();
            
            if (emptyItemSelect.length && emptyItemSelect[0].tomselect) {
                emptyItemSelect[0].tomselect.focus();
            } else {
                $('#add-row').trigger('click');
            }
            return;
        }
        
        // Ctrl+Alt+M - Focus empty meals dropdown or add new meal row
        if (e.ctrlKey && e.altKey && e.key === 'm') {
            e.preventDefault();
            const emptyMealSelect = $('#meals-table tbody tr').find('.meal-select').filter(function() {
                return !$(this).val();
            }).first();
            
            if (emptyMealSelect.length && emptyMealSelect[0].tomselect) {
                emptyMealSelect[0].tomselect.focus();
            } else {
                $('#add-meal-row').trigger('click');
            }
            return;
        }
        
        // Ctrl+Alt+1,2,3... - Focus specific item rows
        if (e.ctrlKey && e.altKey && /^[1-9]$/.test(e.key)) {
            e.preventDefault();
            const rowIndex = parseInt(e.key) - 1;
            const itemRows = $('#items-table tbody tr');
            if (rowIndex < itemRows.length) {
                const targetRow = itemRows.eq(rowIndex);
                const itemSelect = targetRow.find('.item-select');
                if (itemSelect.length && itemSelect[0].tomselect) {
                    itemSelect[0].tomselect.focus();
                }
            }
            return;
        }
        
        // Ctrl+Alt+Shift+1,2,3... - Focus specific meal rows
        if (e.ctrlKey && e.altKey && e.shiftKey && /^[1-9]$/.test(e.key)) {
            e.preventDefault();
            const rowIndex = parseInt(e.key) - 1;
            const mealRows = $('#meals-table tbody tr');
            if (rowIndex < mealRows.length) {
                const targetRow = mealRows.eq(rowIndex);
                const mealSelect = targetRow.find('.meal-select');
                if (mealSelect.length && mealSelect[0].tomselect) {
                    mealSelect[0].tomselect.focus();
                }
            }
            return;
        }
        
        return; // don't process global shortcuts if in order modal
    }
    
    // Global floating menu shortcuts (only when not in order modal)
    
    // Ctrl+Alt+F - Toggle floating shortcuts menu
    if (e.ctrlKey && e.altKey && e.key === 'f') {
        e.preventDefault();
        if ($('#floating-inventory-btn').length) {
            $('#floating-inventory-btn').trigger('click');
        }
        return;
    }
    
    // Ctrl+Alt+1 through Ctrl+Alt+9 - Trigger specific floating actions
    if (e.ctrlKey && e.altKey && ['1', '2', '3', '4', '5', '6', '7', '8', '9'].includes(e.key)) {
        e.preventDefault();
        
        const actionMap = {
            '1': '#show-stock-required',
            '2': '#show-excess-stock',
            '3': '#show-add-stock',
            '4': '#show-add-order',
            '5': '#show-scan-packs',
            '6': '#show-add-customer',
            '7': '#show-add-purchase',
            '8': '#show-add-expense',
            '9': '#show-current-batch'
        };
        
        const targetButton = actionMap[e.key];
        if (targetButton && $(targetButton).length) {
            const actionsMenu = $('#floating-inventory-actions');
            if (actionsMenu.is(':visible')) {
                actionsMenu.hide();
            }
            $(targetButton).trigger('click');
        }
        return;
    }
});

$(function(){
    // Only initialize if floating button exists on the page
    if ($('#floating-inventory-btn').length === 0) {
        return;
    }

    // Floating Actions Menu
    let actionsVisible = false;
    $('#floating-inventory-btn').on('click', function(e){
        e.preventDefault();
        if (actionsVisible) {
            $('#floating-inventory-actions').hide();
            actionsVisible = false;
        } else {
            $('#floating-inventory-actions').show();
            actionsVisible = true;
        }
    });
    
    // Hide actions if clicked elsewhere
    $(document).on('mousedown touchstart', function(e){
        if (!$(e.target).closest('#floating-inventory-btn, #floating-inventory-actions').length) {
            $('#floating-inventory-actions').hide();
            actionsVisible = false;
        }
    });

    // Show Stock Required (glimpse) - using Bootstrap modal
    $('#show-stock-required, #view-stock-requirements').on('click', function(){
        $('#floating-inventory-actions').hide();
        actionsVisible = false;

        const modal = new bootstrap.Modal(document.getElementById('floating-inventory-modal'));
        modal.show();
        
        $('#floating-inventory-modal-body').html(
            '<div class="text-center p-4">' +
            '<i class="fa fa-spinner fa-spin me-2"></i>' +
            'Loading stock requirements...</div>'
        );
        $.get('/entities/batches/api_upcoming_batch_stock_glimpse.php', function(html){
            $('#floating-inventory-modal-body').html(html);
        });
    });

    // Show Excess Stock (Bootstrap modal)
    $('#show-excess-stock').on('click', function(){
        $('#floating-inventory-actions').hide();
        actionsVisible = false;

        const modal = new bootstrap.Modal(document.getElementById('excess-stock-modal'));
        modal.show();
        
        $('#excess-stock-table-container').html('<div class="text-center p-4">Loading...</div>');
        $.get('/entities/inventory/actions.php', {action: 'get_excess_stock'}, function(resp) {
            if(!resp.success) {
                $('#excess-stock-table-container').html('<div class="alert alert-danger">'+(resp.error||'Could not fetch excess stock.')+'</div>');
                return;
            }
            if(!resp.data.length) {
                $('#excess-stock-table-container').html('<div class="alert alert-warning">No items have excess stock!</div>');
                return;
            }
            // Build table
            let table = `<table class="table table-striped" id="excess-stock-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Manufactured</th>
                        <th>Required</th>
                        <th>Excess</th>
                    </tr>
                </thead>
                <tbody>`;
            resp.data.forEach(function(row){
                table += `<tr>
                    <td>${escapeHtml(row.name)}</td>
                    <td>${escapeHtml(row.category || 'Uncategorized')}</td>
                    <td>${row.manufactured}</td>
                    <td>${row.required}</td>
                    <td><span class="badge bg-warning">+${row.excess}</span></td>
                </tr>`;
            });
            table += '</tbody></table>';
            $('#excess-stock-table-container').html(table);
        }, 'json');
    });

    // Copy excess list to clipboard for WhatsApp (modal)
    $(document).on('click', '#copy-excess-btn', function() {
        let rows = $('#excess-stock-table tbody tr');
        if (!rows.length) {
            alert('No excess items to copy!');
            return;
        }
        let lines = [];
        rows.each(function() {
            let item = $(this).find('td').eq(0).text().trim();
            let excess = $(this).find('td').eq(4).text().replace('+','').trim();
            if (item && excess) {
                lines.push(item + ': ' + excess);
            }
        });
        if (lines.length) {
            const textToCopy = lines.join('\n');
            navigator.clipboard.writeText(textToCopy).then(function() {
                alert('Copied to clipboard!\n\n' + textToCopy);
            }, function() {
                alert('Failed to copy!');
            });
        } else {
            alert('No excess items to copy!');
        }
    });

    // ---------------------------
    // Add Stock (multi-row) logic
    // ---------------------------

    // Client-side list of entries to submit
    let floatingAddStockList = [];

    // Show Add Stock Modal (Bootstrap modal)
    $('#show-add-stock').on('click', function(){
        $('#floating-inventory-actions').hide();
        actionsVisible = false;

        const modalEl = document.getElementById('floating-add-stock-modal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
        
        // Initialize the form and table when modal opens
        openFloatingAddStockModal();
    });

    // Initialize TomSelect and reset state for floating Add Stock modal
    function openFloatingAddStockModal() {
        // Reset list and UI
        floatingAddStockList = [];
        renderFloatingAddStockTable();
        clearFloatingEntryForm();

        let $sel = $('#floating-stock-item-select');

        // Destroy old TomSelect instance if exists (important for modal reuse)
        if ($sel[0].tomselect) {
            $sel[0].tomselect.destroy();
        }

        // Clear and show Loading...
        $sel.empty().append('<option value="">Loading...</option>');

        // Fetch items from the server
        $.getJSON('/entities/items/items_list.php', function(items) {
            $sel.empty().append('<option value="">Select Item</option>');
            items.forEach(function(it) {
                $sel.append('<option value="'+it.id+'">'+escapeHtml(it.name)+'</option>');
            });

            // Initialize TomSelect
            if (typeof TomSelect !== "undefined") {
                new TomSelect($sel[0], {
                    create: false,
                    sortField: 'text'
                });
            } else {
                console.warn("TomSelect not loaded!");
            }
        });

        // Ensure feedback is hidden
        $('#floating-add-stock-feedback')
            .removeClass('alert-danger alert-success')
            .hide();

        // Bind handlers once (remove old to avoid duplicates)
        $('#floating-add-stock-entry-form').off('submit').on('submit', function(e) {
            e.preventDefault();
            addEntryToFloatingList();
        });

        $('#floating-add-stock-rows').off('click', '.delete-row').on('click', '.delete-row', function() {
            const index = parseInt($(this).data('index'));
            if (!isNaN(index)) {
                floatingAddStockList.splice(index, 1);
                renderFloatingAddStockTable();
            }
        });

        $('#floating-add-stock-clear').off('click').on('click', function() {
            if (!floatingAddStockList.length) return;
            if (confirm('Clear all pending entries?')) {
                floatingAddStockList = [];
                renderFloatingAddStockTable();
            }
        });

        $('#floating-add-stock-submit-all').off('click').on('click', async function() {
            await submitAllFloatingAddStock();
        });
    }

    // Show/hide comment field for negative stock in floating modal
    $('#floating-add-stock-modal').off('input change', '#floating-stock-qty').on('input change', '#floating-stock-qty', function() {
        let qty = parseFloat($(this).val());
        if (!isNaN(qty) && qty < 0) {
            $('#floating-stock-comment-row').show();
            $('#floating-stock-comment').attr('required', true);
        } else {
            $('#floating-stock-comment-row').hide();
            $('#floating-stock-comment').removeAttr('required');
        }
    });

    // Add one entry to the client-side list
    function addEntryToFloatingList() {
        const $item = $('#floating-stock-item-select');
        const item_id = $item.val();
        const item_name = $item.find('option:selected').text();
        const qtyStr = $('#floating-stock-qty').val();
        const qty = parseFloat(qtyStr);
        const comment = $('#floating-stock-comment').val() || '';

        // Validate
        if (!item_id) {
            showFloatingFeedback('Please select an item.', true);
            return;
        }
        if (qtyStr === "" || isNaN(qty) || qty === 0) {
            showFloatingFeedback('Please enter a non-zero quantity.', true);
            return;
        }
        if (qty < 0 && (!comment || comment.trim() === "")) {
            showFloatingFeedback('Please enter a comment for negative stock (reconciliation).', true);
            return;
        }

        // Push to list
        floatingAddStockList.push({
            item_id: item_id,
            item_name: item_name,
            qty: qty,
            comment: comment.trim()
        });

        // Update UI
        renderFloatingAddStockTable();
        clearFloatingEntryForm();
        showFloatingFeedback('Added to list.', false, true);
    }

    function clearFloatingEntryForm() {
        const $item = $('#floating-stock-item-select');
        if ($item[0] && $item[0].tomselect) {
            $item[0].tomselect.clear();
            $item[0].tomselect.focus();
        } else {
            $item.val('');
        }
        $('#floating-stock-qty').val('');
        $('#floating-stock-comment').val('');
        $('#floating-stock-comment-row').hide();
        $('#floating-stock-comment').removeAttr('required');
    }

    function renderFloatingAddStockTable() {
        const $tbody = $('#floating-add-stock-rows');
        $tbody.empty();

        if (!floatingAddStockList.length) {
            $tbody.append('<tr class="no-rows"><td colspan="5" class="text-muted text-center py-3">No items added yet. Use the form above to add entries.</td></tr>');
        } else {
            floatingAddStockList.forEach(function(row, idx) {
                $tbody.append(`
                    <tr>
                        <td>${idx + 1}</td>
                        <td>${escapeHtml(row.item_name)}</td>
                        <td>${Number(row.qty).toFixed(2)}</td>
                        <td>${escapeHtml(row.comment || '')}</td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-danger delete-row" data-index="${idx}" title="Remove">
                                <i class="fa fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });
        }

        $('#floating-add-stock-count').text(String(floatingAddStockList.length));

        // Enable/disable actions
        const hasRows = floatingAddStockList.length > 0;
        $('#floating-add-stock-submit-all').prop('disabled', !hasRows);
        $('#floating-add-stock-clear').prop('disabled', !hasRows);
    }

    async function submitAllFloatingAddStock() {
        if (!floatingAddStockList.length) {
            showFloatingFeedback('Please add at least one entry to submit.', true);
            return;
        }

        // Disable controls during submission
        const $submitBtn = $('#floating-add-stock-submit-all');
        const $clearBtn = $('#floating-add-stock-clear');
        const $form = $('#floating-add-stock-entry-form');
        $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Submitting...');
        $clearBtn.prop('disabled', true);
        $form.find('input, select, button').prop('disabled', true);

        let successCount = 0;
        let failureCount = 0;
        let failedRows = [];

        for (let i = 0; i < floatingAddStockList.length; i++) {
            const row = floatingAddStockList[i];
            try {
                // Submit each entry individually to existing endpoint
                // action:add_stock expects single item_id, qty, comment
                const resp = await $.post('/entities/inventory/actions.php', {
                    action: 'add_stock',
                    item_id: row.item_id,
                    qty: row.qty,
                    comment: row.comment
                }, null, 'json');

                if (resp && resp.success) {
                    successCount++;
                } else {
                    failureCount++;
                    failedRows.push({ index: i, row: row, error: (resp && resp.error) || 'Unknown error' });
                }
            } catch (err) {
                failureCount++;
                failedRows.push({ index: i, row: row, error: (err && err.statusText) || 'Network error' });
            }
        }

        // After submission, keep only failed rows for potential retry
        floatingAddStockList = failedRows.map(f => f.row);
        renderFloatingAddStockTable();

        // Restore controls
        $submitBtn.prop('disabled', false).html('<i class="fa fa-check me-1"></i> Submit All');
        $clearBtn.prop('disabled', floatingAddStockList.length === 0);
        $form.find('input, select, button').prop('disabled', false);

        if (failureCount === 0) {
            showFloatingFeedback(`All ${successCount} entries submitted successfully!`, false);
            setTimeout(()=>{ 
                const modal = bootstrap.Modal.getInstance(document.getElementById('floating-add-stock-modal'));
                if (modal) modal.hide();
                // Reset for next time
                floatingAddStockList = [];
                renderFloatingAddStockTable();
                clearFloatingEntryForm();
                $('#floating-add-stock-feedback').hide();
            }, 800);
        } else {
            let msg = `${successCount} submitted successfully, ${failureCount} failed. Failed entries remain in the list.`;
            showFloatingFeedback(msg, true);
        }
    }

    function showFloatingFeedback(message, isError = false, autoHide = false) {
        const $fb = $('#floating-add-stock-feedback');
        $fb.text(message)
           .removeClass('alert-danger alert-success')
           .addClass(isError ? 'alert-danger' : 'alert-success')
           .show();
        if (autoHide) {
            setTimeout(() => $fb.fadeOut(200), 1200);
        }
    }

    // Add New Order floating action button - show modal on current page
    $('#show-add-order').on('click', function(e){
        e.preventDefault();
        $('#floating-inventory-actions').hide();
        actionsVisible = false;
        
        if (typeof window.showOrderModal === 'function') {
            showOrderModal();
        } else {
            window.location.href = '/entities/sales/new_order.php';
        }
    });

    // Add Customer Modal shortcut
    $('#show-add-customer').on('click', function(e){
        e.preventDefault();
        $('#floating-inventory-actions').hide();
        actionsVisible = false;
        
        if (typeof window.showCustomerModal === 'function') {
            showCustomerModal();
        } else {
            window.location.href = '/entities/customers/list.php';
        }
    });

    // Add Purchase Modal shortcut
    $('#show-add-purchase').on('click', function(e){
        e.preventDefault();
        $('#floating-inventory-actions').hide();
        actionsVisible = false;
        
        if (typeof window.showPurchaseModal === 'function') {
            showPurchaseModal();
        } else {
            window.location.href = '/entities/purchases/list.php';
        }
    });

    // Add Expense Modal shortcut
    $('#show-add-expense').on('click', function(e){
        e.preventDefault();
        $('#floating-inventory-actions').hide();
        actionsVisible = false;
        
        if (typeof window.showExpenseModal === 'function') {
            showExpenseModal();
        } else {
            window.location.href = '/entities/expenses/list.php';
        }
    });

    // Current Batch shortcut - opens current batch page
    $('#show-current-batch').on('click', function(e){
        e.preventDefault();
        $('#floating-inventory-actions').hide();
        actionsVisible = false;
        
        $.ajax({
            url: '/api/get_current_batch.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.batch_id) {
                    window.open('/entities/batches/batch.php?id=' + response.batch_id, '_blank');
                } else {
                    window.open('/entities/batches/list.php', '_blank');
                }
            },
            error: function() {
                window.open('/entities/batches/list.php', '_blank');
            }
        });
    });

    // Utility to escape HTML in JS
    function escapeHtml(text) {
        return $('<div>').text(text).html();
    }

    // Expose helpers to call floating modals from page JS
    window.showFloatingAddStockModal = function() {
        $('#show-add-stock').trigger('click');
    };
    window.showExcessStockModal = function() {
        $('#show-excess-stock').trigger('click');
    };
});