$(function() {
    function initDataTable() {
        if (window.UnifiedTables && typeof window.UnifiedTables.init === 'function') {
            window.UnifiedTables.init('#orders-table', 'orders');
        } else if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#orders-table')) {
            $('#orders-table').DataTable();
        }
    }

    let ordersDataMap = {};

    function loadOrdersAndInitTable(callback) {
        let apiUrl = '/api/orders.php?action=list';
        if (window.ordersFromDate) apiUrl += '&from_date=' + encodeURIComponent(window.ordersFromDate);
        if (window.ordersToDate) apiUrl   += '&to_date=' + encodeURIComponent(window.ordersToDate);

        $.get(apiUrl, function(resp) {
            if (resp.success) {
                let rows = '';
                ordersDataMap = {};
                (resp.orders || []).forEach(function(order) {
                    ordersDataMap[order.id] = order;

                    let statusBadge =
                        Number(order.cancelled) === 1 ? '<span class="badge-order-status cancelled">Cancelled</span>' :
                        Number(order.delivered) === 1 ? '<span class="badge-order-status delivered">Delivered</span>' :
                        '<span class="badge-order-status undelivered">Undelivered</span>';

                    let paidBadge =
                        Number(order.cancelled) === 1 ? '<span class="badge-order-status cancelled">N/A</span>' :
                        Number(order.paid) === 1 ? '<span class="badge-order-status paid">Paid</span>' :
                        Number(order.paid) === 2 ? '<span class="badge-order-status pending">Partial Paid</span>' :
                        '<span class="badge-order-status unpaid">Unpaid</span>';

                    let orderWithBatch = order.formatted_order_with_batch;
                    let customerInfo = order.formatted_customer;

                    let actions = '';
                    if(Number(order.cancelled) !== 1) {
                        if(Number(order.delivered) === 0){
                            actions += `<button class="btn btn-outline-success btn-3d mark-delivered" data-id="${order.id}" data-source="list" title="Mark Delivered"><i class="fa fa-truck"></i></button> `;
                        }
                        if(Number(order.paid) === 0 || Number(order.paid) === 2){
                            actions += `<button class="btn btn-outline-primary btn-3d mark-paid" data-id="${order.id}" data-grand-total="${order.grand_total}" title="Mark Paid"><i class="fa fa-check-circle"></i></button> `;
                        }
                        if(Number(order.delivered) === 0){
                            actions += `<button class="btn btn-outline-warning btn-3d edit" data-id="${order.id}" title="Edit"><i class="fa fa-edit"></i></button> `;
                        }
                        actions += `<a href="print_invoice.php?id=${order.id}" class="btn btn-outline-info btn-3d" target="_blank" title="Invoice"><i class="fa fa-file-invoice"></i></a> `;
                        if(Number(order.paid) === 0 && Number(order.delivered) === 0 && Number(order.cancelled) === 0) {
                            actions += `<button class="btn btn-outline-danger btn-3d cancel-order" data-id="${order.id}" title="Cancel"><i class="fa fa-ban"></i></button>`;
                        }
                    }

                    let checkbox = `<input type="checkbox" class="order-select-box" value="${order.id}">`;

                    rows += `<tr data-delivered="${Number(order.delivered)}" data-paid="${Number(order.paid)}" data-cancelled="${Number(order.cancelled)}">
                        <td>${checkbox}</td>
                        <td>${orderWithBatch}</td>
                        <td>${customerInfo}</td>
                        <td>${parseFloat(order.grand_total).toFixed(2)}</td>
                        <td>${statusBadge}</td>
                        <td>${parseFloat(order.amount).toFixed(2)}</td>
                        <td>${parseFloat(order.discount).toFixed(2)}</td>
                        <td>${parseFloat(order.delivery_charges).toFixed(2)}</td>
                        <td>${paidBadge}</td>
                        <td>${actions}</td>
                    </tr>`;
                });
                $('#orders-table tbody').html(rows);
                initDataTable();
                $('#orders-status-filter').trigger('change');
                if (typeof callback === "function") callback();
            } else {
                console.error('Orders API error:', resp.message);
                $('#orders-table tbody').html('<tr><td colspan="10" class="text-center text-danger">Error loading orders: ' +
                    (resp.message || 'Unknown error') + '</td></tr>');
                initDataTable();
            }
        }, 'json').fail(function(_, __, error) {
            console.error('Orders API request failed:', error);
            $('#orders-table tbody').html('<tr><td colspan="10" class="text-center text-danger">Failed to load orders. Please refresh.</td></tr>');
            initDataTable();
        });
    }

    loadOrdersAndInitTable();

    // Client-side status filter
    $('#orders-status-filter').off('change').on('change', function() {
        $('#select-all-orders').prop('checked', false);
        $('.order-select-box').prop('checked', false);

        var selected = $(this).val();
        $('#orders-table tbody tr').each(function() {
            var $tr = $(this);
            var delivered = Number($tr.attr('data-delivered'));
            var paid = Number($tr.attr('data-paid'));
            var cancelled = Number($tr.attr('data-cancelled'));

            var show = true;
            if(selected === 'delivered') {
                show = (cancelled !== 1 && delivered === 1);
            } else if(selected === 'undelivered') {
                show = (cancelled !== 1 && delivered === 0);
            } else if(selected === 'paid') {
                show = (cancelled !== 1 && paid === 1);
            } else if(selected === 'partial_paid') {
                show = (cancelled !== 1 && paid === 2);
            } else if(selected === 'unpaid') {
                show = (cancelled !== 1 && paid === 0);
            } else if(selected === 'cancelled') {
                show = (cancelled === 1);
            }
            $tr.toggle(show);
        });
    });

    // (Existing modal / bulk logic below unchanged) --------------------------------

    function showPaymentOnDeliveryAfterDeliveryModal(orderId, grandTotal) {
        $('#payment-on-delivery-after-delivery-body').html(`
            <div style="padding:18px 0;">
                <div class="alert alert-success" style="font-size:1.14rem;">
                    <strong>Order marked as delivered!</strong>
                </div>
                <p style="font-size:1.12rem;">Was payment received with this delivery?</p>
                <div style="display:flex;gap:18px;justify-content:center;">
                    <button class="btn btn-success" id="pod-yes">Yes</button>
                    <button class="btn btn-secondary" id="pod-no">No</button>
                </div>
            </div>
        `);
        const modal = document.getElementById('paymentOnDeliveryAfterDeliveryModal');
        window.UnifiedModals.show(modal);

        $('#pod-yes').off('click').on('click', function() {
            window.UnifiedModals.hide(modal);
            showPaymentOnDeliveryFormModal(orderId, grandTotal);
        });
        $('#pod-no').off('click').on('click', function() {
            var scrollPos = $(window).scrollTop();
            var page = $('#orders-table').DataTable().page();
            window.UnifiedModals.hide(modal);
            localStorage.setItem('ordersListScroll', scrollPos);
            localStorage.setItem('ordersListPage', page);
            location.reload();
        });
    }

    function showPaymentOnDeliveryFormModal(orderId, grandTotal) {
        $.get('/entities/sales/mark_payment.php?inline=1&id=' + encodeURIComponent(orderId) + '&grand_total=' + encodeURIComponent(grandTotal || ''), function(html){
            $('#payment-on-delivery-body').html(html);
            const modal = document.getElementById('paymentOnDeliveryModal');
            window.UnifiedModals.show(modal);
        });
    }

    function showMarkPaymentModal(orderId, grandTotal) {
        $.get('/entities/sales/mark_payment.php?inline=1&id=' + encodeURIComponent(orderId) + '&grand_total=' + encodeURIComponent(grandTotal || ''), function(html){
            $('#mark-payment-modal-body').html(html);
            const modal = document.getElementById('markPaymentModal');
            window.UnifiedModals.show(modal);
        });
    }

    $('#orders-table').on('click', '.mark-paid', function() {
        let id = $(this).data('id');
        let grandTotal = $(this).data('grand-total');
        showMarkPaymentModal(id, grandTotal);
    });

    $('#orders-table').on('click', '.mark-delivered', function() {
        let id = $(this).data('id');
        if(confirm('Mark this order as delivered?')){
            $.post('/entities/sales/mark_delivered.php', {order_ids: [id], source: 'list'}, function(resp){
                if(resp.success){
                    if(resp.ask_payment) {
                        showPaymentOnDeliveryAfterDeliveryModal(resp.order_id, resp.grand_total);
                    } else {
                        var scrollPos = $(window).scrollTop();
                        var page = $('#orders-table').DataTable().page();
                        localStorage.setItem('ordersListScroll', scrollPos);
                        localStorage.setItem('ordersListPage', page);
                        location.reload();
                    }
                } else {
                    showBulkDeliveryResultModal(resp);
                }
            },'json');
        }
    });

    $(document).on('submit', '#mark-payment-form', function(e){
        e.preventDefault();
        var $form = $(this);
        var amount = parseFloat($form.find('#pod-amount').val());
        var method = $form.find('#pod-method').val();
        var order_id = $form.find('input[name=order_id]').val();
        if(!amount || amount <= 0){ alert('Enter a valid amount'); return; }
        var scrollPos = $(window).scrollTop();
        var page = $('#orders-table').length ? $('#orders-table').DataTable().page() : 0;
        $.post('/entities/sales/mark_payment.php', {
            order_id: order_id,
            amount: amount,
            method: method
        }, function(resp){
            if(resp.success){
                const modal = document.getElementById('markPaymentModal');
                window.UnifiedModals.hide(modal);
                localStorage.setItem('ordersListScroll', scrollPos);
                localStorage.setItem('ordersListPage', page);
                location.reload();
            } else {
                alert(resp.message || "Failed to mark as paid");
            }
        },'json');
    });

    $(document).on('click', '#mark-payment-cancel', function() {
        const modal = document.getElementById('markPaymentModal');
        window.UnifiedModals.hide(modal);
        var scrollPos = $(window).scrollTop();
        var page = $('#orders-table').length ? $('#orders-table').DataTable().page() : 0;
        localStorage.setItem('ordersListScroll', scrollPos);
        localStorage.setItem('ordersListPage', page);
        location.reload();
    });

    $(document).on('click', '#pod-cancel', function() {
        const modal = document.getElementById('paymentOnDeliveryModal');
        window.UnifiedModals.hide(modal);
        var scrollPos = $(window).scrollTop();
        var page = $('#orders-table').length ? $('#orders-table').DataTable().page() : 0;
        localStorage.setItem('ordersListScroll', scrollPos);
        localStorage.setItem('ordersListPage', page);
        location.reload();
    });

    function showBulkDeliveryResultModal(resp, onClose) {
        function getCustomerName(order_id) {
            order_id = parseInt(order_id);
            if (ordersDataMap[order_id] && ordersDataMap[order_id].customer_name)
                return ordersDataMap[order_id].customer_name;
            if (resp.results) {
                let found = resp.results.find(r => parseInt(r.order_id) === order_id);
                if (found && found.customer_name) return found.customer_name;
            }
            return "Order #" + order_id;
        }

        let successes = [], failures = [];
        if (resp.results && Array.isArray(resp.results)) {
            resp.results.forEach(function(r) {
                if (r.success) successes.push(r); else failures.push(r);
            });
        }
        let html = `<div style="padding:18px 0;">
            <div class="alert ${failures.length ? 'alert-warning' : 'alert-success'}" style="font-size:1.14rem;">
                <strong>${failures.length ? 'Some orders could not be delivered:' : 'Orders successfully delivered!'}</strong>
            </div>`;
        if (successes.length) {
            html += `<div style="margin-bottom:8px;"><b style="color:green;">Delivered:</b> `;
            html += successes.map(r =>
                `<span style="color:green;font-weight:600;">${escapeHtml(getCustomerName(r.order_id))}</span>`
            ).join(', ');
            html += `</div>`;
        }
        if (failures.length) {
            html += `<div><b style="color:red;">Failed:</b> `;
            html += failures.map(r =>
                `<span style="color:red;font-weight:600;">${escapeHtml(getCustomerName(r.order_id))}</span> <span style="color:#a00;">(${escapeHtml(r.message)})</span>`
            ).join('<br>');
            html += `</div>`;
        }
        html += `<div style="text-align:center;margin-top:16px;">
            <button type="button" class="btn btn-primary" id="bulk-delivery-result-close-btn">OK</button>
        </div></div>`;

        $('#bulkDeliveryResultModal .modal-body').html(html);
        const modal = document.getElementById('bulkDeliveryResultModal');
        window.UnifiedModals.show(modal);
        window.bulkDeliveryModalCallback = function() {
            if(typeof onClose === "function") onClose();
        };
    }

    function escapeHtml(str) {
        if(typeof str !== "string") return str;
        return str.replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;");
    }

    $(document).off('click', '#bulk-delivery-result-close-btn').on('click', '#bulk-delivery-result-close-btn', function() {
        const modal = document.getElementById('bulkDeliveryResultModal');
        window.UnifiedModals.hide(modal);
        if (typeof window.bulkDeliveryModalCallback === 'function') {
            window.bulkDeliveryModalCallback();
            window.bulkDeliveryModalCallback = null;
        }
    });

    $('#bulk-delivered-btn').on('click', function() {
        let ids = $('#orders-table tbody tr:visible .order-select-box:checked').map(function(){ return $(this).val(); }).get();
        if (ids.length === 0) { alert('No orders selected.'); return; }
        if (!confirm('Mark selected orders as delivered?')) return;
        var scrollPos = $(window).scrollTop();
        var page = $('#orders-table').DataTable().page();
        $.post('/entities/sales/mark_delivered.php', {order_ids: ids, source: 'bulk'}, function(resp){
            if(resp.success){
                if(resp.partial || (resp.failures && resp.failures.length)) {
                    showBulkDeliveryResultModal(resp, function() {
                        localStorage.setItem('ordersListScroll', scrollPos);
                        localStorage.setItem('ordersListPage', page);
                        location.reload();
                    });
                } else {
                    let deliveredNames = [];
                    if (resp.results && Array.isArray(resp.results)) {
                        deliveredNames = resp.results.map(r =>
                            '<span style="color:green;font-weight:600;">' +
                            escapeHtml((ordersDataMap[r.order_id] && ordersDataMap[r.order_id].customer_name) ? ordersDataMap[r.order_id].customer_name : "Order #" + r.order_id) +
                            '</span>');
                    }
                    let html = `<div style="padding:18px 0;">
                        <div class="alert alert-success" style="font-size:1.14rem;">
                            <h3 style="margin:0;font-weight:800;">These Orders Delivered Successfully</h3>
                        </div>
                        <div style="margin-bottom:8px;">
                            <b style="color:green;">Delivered:</b> ${deliveredNames.join(', ')}
                        </div>
                        <div style="text-align:center;margin-top:16px;">
                            <button type="button" class="btn btn-primary" id="bulk-delivery-result-close-btn">OK</button>
                        </div>
                    </div>`;
                    $('#bulkDeliveryResultModal .modal-body').html(html);
                    const modal = document.getElementById('bulkDeliveryResultModal');
                    window.UnifiedModals.show(modal);
                    window.bulkDeliveryModalCallback = function() {
                        localStorage.setItem('ordersListScroll', scrollPos);
                        localStorage.setItem('ordersListPage', page);
                        location.reload();
                    };
                }
            } else {
                showBulkDeliveryResultModal(resp, function() {
                    localStorage.setItem('ordersListScroll', scrollPos);
                    localStorage.setItem('ordersListPage', page);
                    location.reload();
                });
            }
        },'json');
    });

    $('#orders-table').on('click', '.cancel-order', function(){
        let id = $(this).data('id');
        if(confirm('Cancel this order?')){
            var scrollPos = $(window).scrollTop();
            $.post('/api/orders.php?action=mark_cancel', {id}, function(resp){
                if(resp.success){
                    loadOrdersAndInitTable(function() { $(window).scrollTop(scrollPos); });
                }
                else{ alert(resp.message); }
            },'json');
        }
    });

    $(document).on('change', '#select-all-orders', function() {
        var checked = this.checked;
        var $boxes = $('#orders-table tbody tr:visible .order-select-box').prop('checked', checked);
        if (checked) { /* optionally show message */ }
    });

    $(document).on('change', '.order-select-box', function() {
        var $visible = $('#orders-table tbody tr:visible .order-select-box');
        if (!this.checked) {
            $('#select-all-orders').prop('checked', false);
        } else if ($visible.length && $visible.not(':checked').length === 0) {
            $('#select-all-orders').prop('checked', true);
        }
    });

    $('#bulk-shipping-docs-btn').on('click', function() {
        let ids = $('.order-select-box:checked').map(function(){ return $(this).val(); }).get();
        if (ids.length === 0) { alert('No orders selected.'); return; }
        window.open('shipping_docs.php?ids=' + encodeURIComponent(ids.join(',')), '_blank');
    });

    $('#open-order-modal').on('click', function() { showOrderModal(); });

    $(document).on('click', '.edit', function(e) {
        e.preventDefault();
        var orderId = $(this).data('id');
        showOrderModal(orderId);
    });

    window.showOrderModal = function(orderId = null) {
        $('#orderModalLabel').text(orderId ? 'Edit Sales Order' : 'New Sales Order');
        $('#order-modal-body').html('<div class="text-center p-4">Loading form...</div>');
        let url = 'new_order.php?inline=1';
        if(orderId) url += '&id=' + encodeURIComponent(orderId);
        const modal = document.getElementById('orderModal');
        window.UnifiedModals.show(modal);
        $.get(url, function(html){
            $('#order-modal-body').html(html);
            if(typeof window.initOrderFormJS === 'function')
                window.initOrderFormJS(orderId ? { order_id: parseInt(orderId) } : null);
        });
    };

    if(localStorage.getItem('ordersListScroll')) {
        $(window).scrollTop(localStorage.getItem('ordersListScroll'));
        localStorage.removeItem('ordersListScroll');
    }
    if(localStorage.getItem('ordersListPage')){
        $('#orders-table').on('init.dt', function(){
            $('#orders-table').DataTable().page(parseInt(localStorage.getItem('ordersListPage'))).draw(false);
            localStorage.removeItem('ordersListPage');
        });
    }
});