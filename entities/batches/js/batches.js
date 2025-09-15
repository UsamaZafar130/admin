$(function(){
    // --- Batch status fix on page load ---
    var batchId = $('#batch-orders-table').data('batch-id') || $('[data-batch-id]').first().data('batch-id');
    if (batchId) {
        $.get('batch_orders_api.php', {action: 'fix_batch_status', batch_id: batchId}, function(resp){
            if (resp && typeof resp.batch_status !== 'undefined') {
                let badge = $("#batch-status-badge");
                if (resp.batch_status == 0) {
                    badge.text("Pending").removeClass().addClass("badge-order-status badge-order-status-pending");
                } else if (resp.batch_status == 1) {
                    badge.text("In Process").removeClass().addClass("badge-order-status badge-order-status-inprocess");
                } else if (resp.batch_status == 2) {
                    badge.text("Delivered").removeClass().addClass("badge-order-status badge-order-status-delivered");
                }
            }
        }, 'json');
    }

    // --- Print Invoice for Selected Orders ---
    $(document).on('click', '#print-invoice-selected-btn', function(e){
        e.preventDefault();
        let ids = $('.batch-order-checkbox:checked').map(function(){ return $(this).val(); }).get();
        if (!ids.length) {
            alert('Please select at least one order to print invoice.');
            return;
        }
        window.open('/entities/sales/print_invoice.php?ids=' + encodeURIComponent(ids.join(',')), '_blank');
    });

    // --- Move Order Modal ---
    $(document).on('click', '.btn-unlink-order', function(e){
        e.preventDefault();
        var $btn = $(this);
        var orderId = $btn.data('order-id');
        var batchId = $btn.data('batch-id');
        $('#moveOrderModal').data('order-id', orderId).data('batch-id', batchId);
        $('#moveOrderModal .move-error').hide();
        loadMoveBatchDropdown();
        openModal('moveOrderModal');
    });

    $(document).on('click', '#refresh-batch-list', function(e){
        e.preventDefault();
        loadMoveBatchDropdown();
    });

    function loadMoveBatchDropdown() {
        var $dropdown = $('#move-batch-dropdown');
        $dropdown.prop('disabled', true).html('<option>Loading...</option>');
        $.get('list.php', {ajax: 1, eligible: 1}, function(data){
            if (Array.isArray(data)) {
                var opts = data.map(function(batch){
                    let statusStr = batch.status == 0 ? 'Pending' : batch.status == 1 ? 'In Process' : batch.status == 2 ? 'Delivered' : batch.status;
                    return `<option value="${batch.id}">#${batch.id} - ${batch.batch_name} (${statusStr})</option>`;
                });
                if (opts.length === 0) opts = ['<option disabled>No eligible batches found</option>'];
                $dropdown.html(opts.join(''));
            } else {
                $dropdown.html('<option disabled>Error loading batches</option>');
            }
            $dropdown.prop('disabled', false);
        }, 'json');
    }

    $(document).on('submit', '#move-order-form', function(e){
        e.preventDefault();
        var newBatchId = $('#move-batch-dropdown').val();
        var orderId = $('#moveOrderModal').data('order-id');
        var batchId = $('#moveOrderModal').data('batch-id');
        if (!newBatchId) {
            $('#moveOrderModal .move-error').text('Please select a batch!').show();
            return;
        }
        $.post('batch_orders_api.php', {action:'move_order', batch_id:batchId, order_id:orderId, new_batch_id:newBatchId}, function(resp){
            if(resp.status==='success') {
                closeModal('moveOrderModal');
                location.reload();
            } else {
                $('#moveOrderModal .move-error').text(resp.message || 'Failed to move order').show();
            }
        },'json');
    });

    $(document).on('click', '#create-new-batch-btn', function(){
        window.open('list.php', '_blank');
    });

    // --- Link Orders Modal ---
    $(document).on('click', '.btn-link-orders', function(){
        var batchId = $('#batch-orders-table').data('batch-id') || $('[data-batch-id]').first().data('batch-id');
        $('#linkOrdersModal').data('batch-id', batchId);
        $('#link-orders-form .link-error').hide();
        $('#link-orders-form .link-success').hide();
        $('#link-orders-select').prop('disabled', true).html('');
        $('#link-orders-select').parent().append('<div id="orders-loading-spinner" style="margin-top:8px;text-align:center;"><span class="fa fa-spinner fa-spin"></span> Loading...</div>');
        $.get('batch_orders_api.php', {action:'get_unbatched_orders', batch_id: batchId}, function(data){
            $('#orders-loading-spinner').remove();
            if (data && Array.isArray(data.orders)) {
                var opts = data.orders.map(function(o){
                    return `<option value="${o.id}">${o.formatted_order_number} - ${o.customer_name} (${o.grand_total})</option>`;
                });
                if (opts.length === 0) opts = ['<option disabled>No eligible orders found</option>'];
                $('#link-orders-select').html(opts.join(''));
            } else {
                $('#link-orders-select').html('<option disabled>Error loading orders</option>');
            }
            $('#link-orders-select').prop('disabled', false);

            if ($('#link-orders-select')[0].tomselect) {
                $('#link-orders-select')[0].tomselect.destroy();
            }
            new TomSelect('#link-orders-select', {
                plugins: ['remove_button'],
                maxItems: null,
                create: false,
                sortField: {field: "text", direction: "asc"},
                render: {
                    no_results: function(data, escape) {
                        return '<div class="no-orders-message" style="padding:8px;color:#c0392b;">No eligible orders found</div>';
                    }
                }
            });
        }, 'json');
        openModal('linkOrdersModal');
    });

    $(document).on('submit', '#link-orders-form', function(e){
        e.preventDefault();
        var orderIds = $('#link-orders-select').val() || [];
        var batchId = $('#linkOrdersModal').data('batch-id');
        if (!orderIds.length) {
            $('#link-orders-form .link-error').text('Please select at least one order!').show();
            return;
        }
        $.post('batch_orders_api.php', {action:'add_orders', batch_id:batchId, order_ids:orderIds}, function(resp){
            if(resp.status==='success') {
                $('#link-orders-form .link-error').hide();
                $('#link-orders-form .link-success').text('Orders linked successfully.').show();
                setTimeout(function() {
                    closeModal('linkOrdersModal');
                    location.reload();
                }, 900);
            } else {
                $('#link-orders-form .link-success').hide();
                $('#link-orders-form .link-error').text(resp.message || 'Failed to link orders').show();
            }
        },'json');
    });

    // --- Checkbox logic ---
    $(document).on('change', '.batch-select-all-checkbox', function() {
        var checked = this.checked;
        $('.batch-order-checkbox').prop('checked', checked);
    });
    $(document).on('change', '.batch-order-checkbox', function() {
        var $all = $('.batch-order-checkbox');
        var $checked = $('.batch-order-checkbox:checked');
        $('.batch-select-all-checkbox').prop('checked', $all.length && $all.length === $checked.length);
    });

    // --- Bulk Mark Delivered ---
    $(document).on('click', '#batch-bulk-delivered-btn', function() {
        let ids = $('.batch-order-checkbox:checked').map(function(){ return $(this).val(); }).get();
        if (ids.length === 0) {
            alert('No orders selected.');
            return;
        }
        if (!confirm('Mark selected orders as delivered?')) return;

        $.post('/entities/sales/mark_delivered.php', {order_ids: ids, source: 'batch-page'}, function(resp){
            if(resp.success){
                location.reload();
            } else {
                alert('Some orders failed. Please check and try again.');
                location.reload();
            }
        },'json');
    });

    // --- Bulk Mark Payment ---
    $(document).on('click', '#batch-bulk-paid-btn', function() {
        let $checked = $('.batch-order-checkbox:checked');
        if (!$checked.length) {
            alert('No orders selected.');
            return;
        }

        let paidExcluded = [];
        let payableIds = [];

        $checked.each(function(){
            const paidFlag = parseInt($(this).data('paid'), 10);
            const oid = $(this).val();
            if (paidFlag === 1) {
                paidExcluded.push(oid);
            } else {
                payableIds.push(oid);
            }
        });

        if (!payableIds.length) {
            alert('All selected orders are already fully paid.');
            return;
        }

        $.get('/entities/sales/mark_payment.php', {order_ids: payableIds}, function(html){
            const modal = document.getElementById('payment-modal');
            const modalContent = modal.querySelector('.modal-content');
            if (modalContent) {
                modalContent.innerHTML = html;
            }
            window.UnifiedModals.show(modal);
        });
    });

    // Bulk Payment Form Submit
    $(document).on('submit', '#bulk-payment-form', function(e){
        e.preventDefault();
        var $form = $(this);
        $.post('/entities/sales/mark_payment.php', $form.serialize(), function(resp){
            if(resp.success) {
                location.reload();
            } else {
                alert(resp.message || 'Failed to mark payments.');
            }
        }, 'json');
    });

    // --- Single Mark Payment ---
    $(document).on('click', '.batch-mark-paid-btn', function() {
        var orderId = $(this).data('order-id');
        if (!orderId) {
            alert('Order ID not found!');
            return;
        }
        $.get('/entities/sales/mark_payment.php', {id: orderId}, function(html){
            const modal = document.getElementById('payment-modal');
            const modalContent = modal.querySelector('.modal-content');
            if (modalContent) {
                modalContent.innerHTML = html;
            }
            window.UnifiedModals.show(modal);
        });
    });

    // --- Show Batch Summary (UPDATED: explicit init call) ---
    $(document).on('click', '.btn-show-summary', function() {
        var batchId = $('#batch-orders-table').data('batch-id');
        $.get('/entities/batches/batch_summary.php', {batch_id: batchId}, function(html) {
            const modal = document.getElementById('summary-modal');
            const modalBody = modal.querySelector('#summary-modal-body');
            if (modalBody) {
                modalBody.innerHTML = html;
                const root = modalBody.querySelector('#batch-summary-root');
                if (root && typeof window.initBatchSummary === 'function') {
                    window.initBatchSummary(root);
                }
            }
            window.UnifiedModals.show(modal);
        });
    });

    // --- Single Mark Delivered ---
    $(document).on('click', '.batch-mark-delivered-btn', function() {
        var orderId = $(this).data('order-id');
        if (!confirm('Mark this order as delivered?')) return;
        $.post('/entities/sales/mark_delivered.php', {order_ids: [orderId], source: 'batch-page'}, function(resp){
            if(resp.success){
                location.reload();
            } else {
                alert('Failed to mark order as delivered: ' + (resp.results && resp.results[0] && resp.results[0].message ? resp.results[0].message : 'Unknown error.'));
                location.reload();
            }
        },'json');
    });

    // --- Provide order modal logic if missing ---
    if (typeof window.showOrderModal !== 'function') {
        window.showOrderModal = function(orderId = null) {
            var modal = document.getElementById('orderModal');
            if (!modal) {
                alert('Order modal markup not found.');
                return;
            }
            $('#orderModalLabel').text(orderId ? 'Edit Sales Order' : 'New Sales Order');
            $('#order-modal-body').html('<div class="text-center p-4">Loading form...</div>');
            var url = '../sales/new_order.php?inline=1';
            if (orderId) url += '&id=' + encodeURIComponent(orderId);
            window.UnifiedModals.show(modal);
            $.get(url, function(html){
                $('#order-modal-body').html(html);
                if (typeof window.initOrderFormJS === 'function') {
                    window.initOrderFormJS(orderId ? { order_id: parseInt(orderId) } : null);
                }
            }).fail(function() {
                $('#order-modal-body').html('<div class="text-danger p-3">Failed to load form.</div>');
            });
        };
    }

    // --- Edit button handler ---
    $(document).on('click', '.batch-edit-order-btn', function(e){
        e.preventDefault();
        var orderId = $(this).data('order-id');
        if (orderId) showOrderModal(orderId);
    });

    // --- Modal helpers ---
    window.openModal = function(modalId) {
        window.UnifiedModals.show(modalId);
    };
    window.closeModal = function(modalId) {
        window.UnifiedModals.hide(modalId);
    };

    // Close handlers
    $(document).on('click', '#pod-cancel, #bulk-pay-cancel', function(){
        const modal = document.getElementById('payment-modal');
        window.UnifiedModals.hide(modal);
    });
    $(document).on('click', '#summary-modal .btn-close', function() {
        const modal = document.getElementById('summary-modal');
        window.UnifiedModals.hide(modal);
    });
});

/**
 * Batch Summary Initializer (moved from inline script)
 * Idempotent: safe to call multiple times.
 */
window.initBatchSummary = function(root) {
    if (!root || root.dataset.initialized === '1') return;
    root.dataset.initialized = '1';

    // Enlarge modal
    const dialog = root.closest('.modal-dialog');
    if (dialog) dialog.classList.add('modal-xl');

    const tbody = root.querySelector('tbody');
    if (!tbody) return;

    const chkPaid    = root.querySelector('#toggle-paid');
    const chkPartial = root.querySelector('#toggle-partial');
    const chkUnpaid  = root.querySelector('#toggle-unpaid');

    function applyFilters() {
        const showPaid    = chkPaid.checked;
        const showPartial = chkPartial.checked;
        const showUnpaid  = chkUnpaid.checked;

        tbody.querySelectorAll('tr.order-row').forEach(row => {
            const status = row.getAttribute('data-status');
            let visible = true;
            if (status === 'paid' && !showPaid) visible = false;
            if (status === 'partial' && !showPartial) visible = false;
            if (status === 'unpaid' && !showUnpaid) visible = false;

            const orderId = row.getAttribute('data-order-id');
            const details = tbody.querySelector('tr.details-row[data-details-for="'+orderId+'"]');
            if (!visible) {
                row.classList.add('d-none');
                if (details) details.classList.add('d-none');
            } else {
                row.classList.remove('d-none');
                if (details && details.classList.contains('expanded')) {
                    details.classList.remove('d-none');
                }
            }
        });
    }

    [chkPaid, chkPartial, chkUnpaid].forEach(chk => {
        chk.addEventListener('change', applyFilters);
    });

    tbody.addEventListener('click', function(e) {
        const row = e.target.closest('tr.order-row');
        if (!row) return;

        const orderId = row.getAttribute('data-order-id');
        const details = tbody.querySelector('tr.details-row[data-details-for="'+orderId+'"]');
        if (!details) return;

        const open = !details.classList.contains('d-none');
        if (open) {
            details.classList.add('d-none');
            details.classList.remove('expanded');
            row.classList.remove('active');
            return;
        }

        // Single-open mode
        tbody.querySelectorAll('tr.details-row.expanded').forEach(dr => {
            dr.classList.add('d-none');
            dr.classList.remove('expanded');
            const pid = dr.getAttribute('data-details-for');
            const prow = tbody.querySelector('tr.order-row[data-order-id="'+pid+'"]');
            if (prow) prow.classList.remove('active');
        });

        row.classList.add('active');
        details.classList.remove('d-none');
        details.classList.add('expanded');

        if (!details.dataset.loaded) {
            const url = '/entities/batches/batch_summary.php?order_items=1&order_id=' + encodeURIComponent(orderId);
            fetch(url, { credentials: 'same-origin' })
                .then(r => r.text())
                .then(html => {
                    details.dataset.loaded = '1';
                    const cell = details.querySelector('td');
                    if (cell) cell.innerHTML = html;
                })
                .catch(() => {
                    const cell = details.querySelector('td');
                    if (cell) cell.innerHTML = '<div class="p-3 text-danger small">Failed to load items.</div>';
                });
        }
    });

    applyFilters();
};