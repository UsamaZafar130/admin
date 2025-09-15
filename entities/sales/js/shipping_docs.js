$(function () {
    const data = window.shippingOrdersData || {};
    let orders = data.orders || [];
    let orderItems = data.order_items || {};
    let riders = data.riders || [];

    function getOrderById(id) {
        return orders.find(o => o.id == id);
    }

    // ---- Add Back Icon Handler ----
    $('#shipping-back-btn').on('click', function () {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = '/'; // fallback to home if no history
        }
    });

    function getSelectedOrders() {
        let selected = [];
        $('#shipping-orders-table tbody tr').each(function () {
            let $tr = $(this);
            let orderId = $tr.data('order-id');
            if ($tr.find('.select-order-checkbox').prop('checked')) {
                selected.push({
                    id: orderId,
                    pack_no: parseInt($tr.find('.input-pack-no').val()) || 0,
                    customer_name: $tr.find('.order-customer-name').text().trim(),
                    area: $tr.find('.order-area-cell').text().trim(),
                    contact: $tr.find('.order-customer-contact').text().trim(),
                    full_address: $tr.find('.order-address-cell').text().trim(),
                    grand_total: $tr.find('td').eq(5).text().trim(),
                    paid: $tr.find('td').eq(6).text().trim(),
                    collection_amount: $tr.find('.input-collection-amount').val(),
                    location: $tr.data('location') || ''
                });
            }
        });
        selected.sort((a, b) => a.pack_no - b.pack_no);
        return selected;
    }

    $('#select-all-orders').on('change', function () {
        $('.select-order-checkbox').prop('checked', this.checked);
    });
    $(document).on('change', '.select-order-checkbox', function () {
        let all = $('.select-order-checkbox').length;
        let checked = $('.select-order-checkbox:checked').length;
        $('#select-all-orders').prop('checked', all === checked);
    });

    $('#btn-shipping-labels').on('click', function () {
        let selected = getSelectedOrders();
        if (selected.length === 0) return alert('Select at least one order.');
        let html = `<html><head><title>Shipping Labels</title>
        <style>
        @media print { body { margin:0; } .label { page-break-inside: avoid; } }
        body { font-family: sans-serif; padding:20px; background: #f7f7f7; }
        .label { border: 2px dashed #0070e0; border-radius: 9px; background: #fff; margin: 18px auto; width: 420px; padding: 24px 18px 18px 18px;}
        .label .pack { font-size: 1.2rem; color: #0070e0; font-weight: 700; margin-bottom: 7px; }
        .label .name { font-size: 1.13rem; font-weight: 600; }
        .label .address { font-size: 1.05rem; color: #222; margin: 7px 0; }
        .label .contact { color: #25D366; font-size:1.03rem; }
        .label .collect { font-weight:600; margin-top: 7px; color: #e05353; }
        </style>
        </head><body onload="window.print()">
        <h2 style="text-align:center;color:#0070e0;">Shipping Labels</h2>
        `;
        selected.forEach(order => {
            html += `
            <div class="label">
                <div class="pack">Pack #: ${order.pack_no}</div>
                <div class="name">${order.customer_name}</div>
                <div class="address">${order.full_address}</div>
                <div class="contact">${order.contact}</div>
                <div class="collect">Collection: Rs. ${parseFloat(order.collection_amount) > 0 ? order.collection_amount : '0'}</div>
            </div>
            `;
        });
        html += '</body></html>';
        let win = window.open('', '', 'width=900,height=700');
        win.document.write(html);
        win.document.close();
    });

    $('#btn-dispatch-list').on('click', function () {
        openRiderModal('dispatch');
    });
    $('#btn-delivery-sheet').on('click', function () {
        openRiderModal('delivery');
    });

    function openRiderModal(type) {
        $('#modal-action-type').val(type);
        $('#riderSelect').val('');
        // Add description/info in modal to expand height and for better UI
        $('#riderModal .modal-info').remove();
        $('#riderModal .modal-body').prepend(
            `<div class="modal-info" style="margin-bottom:18px;color:#1869e3;font-size:1.08em;">
                Please select a rider to assign selected orders for ${type === 'dispatch' ? "dispatch list printing" : "delivery sheet printing"}.
                <br>This action helps organize and track your deliveries efficiently.
            </div>`
        );
        $('#riderModal').addClass('show').fadeIn(130);
    }
    function closeRiderModal() {
        $('#riderModal').removeClass('show').fadeOut(120);
    }
    $('#modalCancelBtn, #modalCancelBtnFooter').on('click', closeRiderModal);

    $('#modalOkBtn').on('click', function () {
        let type = $('#modal-action-type').val();
        let riderId = $('#riderSelect').val();
        let riderName = $('#riderSelect option:selected').text();
        if (!riderId) return alert('Please select a rider.');
        let selected = getSelectedOrders();
        if (selected.length === 0) { closeRiderModal(); return alert('Select at least one order.'); }

        // Assign rider to orders (AJAX)
        $.ajax({
            url: 'assign_rider.php',
            method: 'POST',
            data: {
                order_ids: selected.map(o => o.id),
                rider_id: riderId
            },
            success: function(resp) {
                // --- DISPATCH LIST (Route Packing List) ---
                if (type === 'dispatch') {
                    let html = `<html><head><title>Dispatch List</title>
                    <style>
                    @media print { body{margin:0;} }
                    body { font-family: sans-serif; background:#f7f7f7; padding:18px; }
                    h2 { color:#1869e3; text-align:center; }
                    table { width:99%; margin:auto; border-collapse:collapse; background: #fff;}
                    th,td { border:1px solid #1869e3; padding:7px 8px; font-size:1.07rem; text-align:center; }
                    th { background:#1869e3; color:#fff; }
                    .rider {margin-bottom:12px;font-weight:bold;}
                    a { color: #23b367; text-decoration: underline; }
                    </style>
                    </head>
                    <body onload="window.print()" style="width:100vw;">
                    <h2>Dispatch List</h2>
                    <div class="rider">Rider: ${riderName}</div>
                    <table>
                        <thead>
                        <tr>
                            <th>Pack #</th>
                            <th>Name / WhatsApp</th>
                            <th>Area</th>
                            <th>Items</th>
                        </tr>
                        </thead>
                        <tbody>
                    `;
                    selected.forEach(order => {
                        let customer = getOrderById(order.id);
                        let itemsArr = orderItems[order.id] || [];

                        // WhatsApp invoice: Invoice line
                        // Use the following format:
                        // Invoice
                        // ----------------------------
                        //   Chicken Meat Balls (Kofta) × 12 = *800.00*
                        //   Tikka Pizza Samosa × 30 = *950.00*
                        //   Crispy Box Patties × 22 = *1,830.00*
                        // ----------------------------
                        //   *Discount:* 580.00
                        //   *Grand Total:* _3,000.00_
                        // ----------------------------
                        //   Thank you for your order

                        // We need to build each item as: ItemName × qty = *total*
                        // For that, we need to parse itemsArr (which is a list of strings like "ItemName 12 x 1", or similar).
                        // If you want to include price, you must include it in the window.shippingOrdersData.order_items structure.
                        // For now, let's parse for name and qty, and total if present.

                        // We'll assume itemsArr is an array of strings, each like "Name qty x packs = Rs. total" or "Name qty x 1"
                        // If you want to show totals, you need to pass this info from the backend!

                        let invoiceLines = [];
                        let total_items_amount = 0;
                        itemsArr.forEach(itemStr => {
                            // Try to extract name, qty, pack_size (and optionally total)
                            // Patterns: "Tikka Pizza Samosa 30 x 1 = Rs. 950.00"
                            let match = itemStr.match(/^(.*?) (\d+(?:\.\d+)?) x (\d+)(?: = Rs\.? ([\d,]+\.\d{2}))?/);
                            if (match) {
                                let name = match[1].trim();
                                let qty = parseFloat(match[2]);
                                let pack_size = parseInt(match[3]);
                                let itemTotal = match[4] ? parseFloat(match[4].replace(/,/g, '')) : '';
                                
                                // Calculate packs: qty/pack_size, but if pack_size is 0, default to qty
                                let packs = (pack_size > 0) ? (qty / pack_size) : qty;
                                let packs_disp = (pack_size > 0) ? pack_size + " x " + Math.floor(packs) : qty;
                                
                                let itemLine = `  ${name} ${packs_disp}` + (itemTotal ? ` = *${itemTotal.toFixed(2)}*` : '');
                                if (itemTotal) total_items_amount += itemTotal;
                                invoiceLines.push(itemLine);
                            } else {
                                // fallback: just show as is
                                invoiceLines.push('  ' + itemStr);
                            }
                        });

                        let order_total = customer && customer.grand_total ? parseFloat(customer.grand_total.toString().replace(/,/g, '')) : 0;
                        let discount = customer && customer.discount ? parseFloat(customer.discount) : 0;
                        let delivery_charges = customer && customer.delivery_charges ? parseFloat(customer.delivery_charges) : 0;
                        let grand_total = order_total;
                        let order_date = customer && customer.order_date ? customer.order_date : '';
                        let display_order_no = customer && customer.id ? String(customer.id).padStart(3, '0') : '';
                        
                        let discount_str = (discount && discount > 0) ? `  *Discount:* ${discount.toFixed(2)}\n` : '';
                        let delivery_str = (delivery_charges && delivery_charges > 0) ? `  *D.C:* ${delivery_charges.toFixed(2)}\n` : '';
                        let grand_total_str = `  *Grand Total:* _${grand_total.toFixed(2)}_`;

                        let wa_msg =
`🧾 *Invoice*
*Order # ${display_order_no}*
*Order Date:* ${order_date}
----------------------------
${invoiceLines.join('\n')}
----------------------------
${discount_str}${delivery_str}${grand_total_str}
----------------------------
  Thank you for your order! We'll be looking forward to serve you again! `;

                        let contact = customer && customer.customer_contact ? customer.customer_contact.trim() : '';
                        let wa_number = contact.replace(/[^0-9]/g, '');
                        let wa_link = wa_number ? `https://wa.me/${wa_number}?text=${encodeURIComponent(wa_msg)}` : '';

                        let customer_display = customer ? customer.customer_name : '';
                        if(wa_link) {
                            customer_display += `<br><a href="${wa_link}" target="_blank" style="color:#23b367;text-decoration:none;">${contact}</a>`;
                        }
                        html += `<tr>
                            <td>${order.pack_no}</td>
                            <td>${customer_display}</td>
                            <td>${customer ? (customer.area || '') : ''}</td>
                            <td>${itemsArr.join(', ')}</td>
                        </tr>`;
                    });
                    html += '</tbody></table></body></html>';
                    let win = window.open('', '', 'width=1200,height=800');
                    win.document.write(html);
                    win.document.close();
                }
                // --- DELIVERY SHEET ---
                else if (type === 'delivery') {
                    let html = `<html><head><title>Delivery Sheet</title>
                    <style>
                    @media print { body{margin:0;} }
                    body { font-family: sans-serif; background:#f7f7f7; padding:18px; }
                    h2 { color:#1869e3; text-align:center; }
                    table { width:99%; margin:auto; border-collapse:collapse; background: #fff;}
                    th,td { border:1px solid #1869e3; padding:7px 8px; font-size:1.07rem; text-align:center; }
                    th { background:#1869e3; color:#fff; }
                    .rider {margin-bottom:12px;font-weight:bold;}
                    .btn-mark-delivered {
                        background: #1ca37c; color: #fff; border: none; border-radius: 6px; cursor: pointer;
                        padding: 5px 13px; font-size: 1rem; font-weight: 600;
                        display: inline-block; text-decoration: none;
                    }
                    .btn-mark-delivered:disabled { background: #ccc; color: #888; cursor: not-allowed; }
                    a.delivery-location { color: #0070e0; text-decoration: underline; }
                    </style>
                    </head>
                    <body onload="window.print()" style="width:100vw;">
                    <h2>Delivery Sheet</h2>
                    <div class="rider">Rider: ${riderName}</div>
                    <table>
                        <thead>
                        <tr>
                            <th>Pack #</th>
                            <th>Name of Customer</th>
                            <th>Address</th>
                            <th>Contact #</th>
                            <th>Location</th>
                            <th>Mark Delivered</th>
                        </tr>
                        </thead>
                        <tbody>
                    `;
                    selected.forEach(order => {
                        let customer = getOrderById(order.id);
                        let location = order.location ? order.location : '';
                        let location_link = '';
                        if (location) {
                            if (/^(https?:\/\/)/.test(location)) {
                                location_link = location;
                            } else {
                                location_link = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(location);
                            }
                        } else {
                            location_link = '#';
                        }
                        let markDeliveredUrl = '/entities/sales/mark_delivered.php?order_id=' + encodeURIComponent(order.id);
                        html += `<tr>
                            <td>${order.pack_no}</td>
                            <td>${customer ? customer.customer_name : ''}</td>
                            <td>${order.full_address}</td>
                            <td>${order.contact}</td>
                            <td>${location ? `<a class="delivery-location" href="${location_link}" target="_blank">${location}</a>` : ''}</td>
                            <td>
                                <a class="btn-mark-delivered" href="${markDeliveredUrl}" target="_blank">Mark Delivered</a>
                            </td>
                        </tr>`;
                    });
                    html += '</tbody></table></body></html>';
                    let win = window.open('', '', 'width=1200,height=800');
                    win.document.write(html);
                    win.document.close();
                }
                closeRiderModal();
            },
            error: function(xhr) {
                alert('Error assigning rider: ' + (xhr.responseText || xhr.statusText));
            }
        });
    });

    $('.select-order-checkbox, #select-all-orders').prop('checked', false);
});