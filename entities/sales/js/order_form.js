window.initOrderFormJS = function(prefill) {
    let customersLoaded = false;
    let itemsLoaded = false;
    let mealsLoaded = false;
    let prefillHeaderLoaded = false;
    let prefillItemsLoaded = false;
    let prefillMealsLoaded = false;
    let prefillOrderHeader = {};
    let prefillOrderItems = [];
    let prefillOrderMeals = [];
    let currentOrderId = null; // Track current order ID for edit

    function tryRenderPrefillRows() {
        if (customersLoaded && itemsLoaded && mealsLoaded && prefillHeaderLoaded && prefillItemsLoaded && prefillMealsLoaded) {
            if (prefillOrderHeader.customer_id) {
                let $sel = $('#customer-select');
                if ($sel[0] && $sel[0].tomselect) {
                    $sel[0].tomselect.setValue(prefillOrderHeader.customer_id, true);
                } else {
                    $sel.val(prefillOrderHeader.customer_id);
                }
            }
            $('#discount-amount').val(prefillOrderHeader.discount || 0);
            $('#discount-percent').val(
                prefillOrderHeader.discount && prefillOrderHeader.amount
                    ? ((prefillOrderHeader.discount/prefillOrderHeader.amount)*100).toFixed(2)
                    : 0
            );
            $('#delivery-charges').val(prefillOrderHeader.delivery_charges || 0);
            $('#grand-total').val(prefillOrderHeader.grand_total || 0);
            $('#order-amount').val(prefillOrderHeader.amount || 0);
            $('#hidden-amount').val(prefillOrderHeader.amount || 0);
            $('#hidden-discount').val(prefillOrderHeader.discount || 0);
            $('#hidden-delivery-charges').val(prefillOrderHeader.delivery_charges || 0);
            $('#hidden-grand-total').val(prefillOrderHeader.grand_total || 0);

            $('#items-table tbody').empty();
            if (Array.isArray(prefillOrderItems) && prefillOrderItems.length > 0) {
                prefillOrderItems.forEach(function(it, idx){
                    if (it && typeof it === 'object') addRow(it, idx === 0);
                });
            } else {
                addRow({}, true); // Always pass an object, never null
            }
            
            $('#meals-table tbody').empty();
            if (Array.isArray(prefillOrderMeals) && prefillOrderMeals.length > 0) {
                prefillOrderMeals.forEach(function(meal, idx){
                    if (meal && typeof meal === 'object') addMealRow(meal, idx === 0);
                });
            } else {
                addMealRow({}, true);
            }
        }
    }

    function loadCustomers(selectedId) {
        var $sel = $('#customer-select');
        if ($sel[0] && $sel[0].tomselect) $sel[0].tomselect.destroy();
        $.getJSON('/api/customers.php?action=list', function(customers){
            $sel.empty().append('<option value="">Select Customer</option>');
            customers.forEach(function(c){
                $sel.append('<option value="'+c.id+'">'+c.name+'</option>');
            });
            new TomSelect($sel[0], {
                create:false,
                sortField:'text'
            });
            customersLoaded = true;
            tryRenderPrefillRows();
            // If a selectedId was passed, restore it
            if (selectedId) {
                try {
                    if ($sel[0] && $sel[0].tomselect) $sel[0].tomselect.setValue(String(selectedId), true);
                    else $sel.val(String(selectedId));
                } catch(e) {}
            }
        });
    }
    $('#refresh-customer').off('click').on('click', function(e){
        e.preventDefault();
        loadCustomers($('#customer-select').val());
    });

    // Helper: show success/error notification using your notification modal if available
    function showSuccess(message) {
        if (window.NotificationModal && typeof window.NotificationModal.show === 'function') {
            window.NotificationModal.show({ type: 'success', message: message });
        } else if (typeof window.showNotificationModal === 'function') {
            // Fallback API name (if your modal exposes this)
            window.showNotificationModal('success', message);
        } else {
            alert(message);
        }
    }
    function showError(message) {
        if (window.NotificationModal && typeof window.NotificationModal.show === 'function') {
            window.NotificationModal.show({ type: 'error', message: message });
        } else if (typeof window.showNotificationModal === 'function') {
            window.showNotificationModal('error', message);
        } else {
            alert(message);
        }
    }

    // Open /entities/customers/form.php as a stacked modal without closing this (parent) modal
    $(document).off('click', '#add-customer-btn').on('click', '#add-customer-btn', function(e) {
        e.preventDefault();
        var $container = $('#customer-form-modal-container');
        // Prefer inline mode if supported so we don't get full page layout
        $.get('/entities/customers/form.php?inline=1', function(html) {
            // Inject the received markup
            $container.html(html);

            // Try to find a modal inside the loaded content
            var $modal = $container.find('.modal').first();

            // If no modal wrapper is present, wrap the form content into a modal shell
            if ($modal.length === 0) {
                var wrapper = $(
                    '<div class="modal fade" id="customer-inline-modal" tabindex="-1" aria-hidden="true">' +
                      '<div class="modal-dialog"><div class="modal-content"></div></div>' +
                    '</div>'
                );
                wrapper.find('.modal-content').html(html);
                $container.empty().append(wrapper);
                $modal = wrapper;
            }

            // Ensure the new modal doesn't dismiss the parent on backdrop click
            $modal.attr('data-bs-backdrop', 'static').attr('data-bs-keyboard', 'true');

            // Attach AJAX submit handler to the first form inside the customer modal
            var $form = $modal.find('form').first();
            $form.off('submit.customer').on('submit.customer', function(ev) {
                ev.preventDefault();
                var formData = $form.serialize();
                var endpoint = $form.attr('action') || '/api/customers.php?action=add';
                // Force add action if none given
                if (endpoint.indexOf('action=') === -1) {
                    endpoint += (endpoint.indexOf('?') === -1 ? '?' : '&') + 'action=add';
                }
                $.ajax({
                    url: endpoint,
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(resp) {
                        if (resp && (resp.success || resp.status === 'success')) {
                            var newId = resp.id || (resp.customer && resp.customer.id);
                            var newName = resp.name || (resp.customer && resp.customer.name) || 'Customer';
                            showSuccess((resp.message) ? resp.message : (newName + ' added successfully.'));
                            // Close the customer modal
                            var bsModal = bootstrap.Modal.getOrCreateInstance($modal[0]);
                            bsModal.hide();
                            // Refresh customer dropdown and select the new customer (if id returned)
                            if (newId) {
                                loadCustomers(String(newId));
                            } else {
                                // Fallback: just reload list without selecting
                                loadCustomers($('#customer-select').val());
                            }
                        } else {
                            var msg = (resp && (resp.error || resp.message)) ? (resp.error || resp.message) : 'Could not add customer.';
                            showError(msg);
                        }
                    },
                    error: function(xhr) {
                        var msg = 'Could not add customer. ' + (xhr.responseText || '');
                        showError(msg);
                    }
                });
            });

            // When the stacked modal closes, clean up and optionally refresh the list
            $modal.off('hidden.bs.modal.customer').on('hidden.bs.modal.customer', function() {
                $container.empty();
                // If the form was dismissed without submit, you can still refresh if desired:
                // loadCustomers($('#customer-select').val());
            });

            // Show the modal
            var bsModal = bootstrap.Modal.getOrCreateInstance($modal[0], { backdrop: 'static', keyboard: true });
            bsModal.show();
        }).fail(function() {
            showError('Could not load the Customer form. Please try again.');
        });
    });

    // Optional: handle z-index so stacked modals/backdrops look correct
    $(document).off('shown.bs.modal.stacked').on('shown.bs.modal.stacked', '.modal', function () {
        var zIndex = 1050 + (10 * $('.modal:visible').length);
        $(this).css('z-index', zIndex);
        setTimeout(function() {
            $('.modal-backdrop').not('.stacked').css('z-index', zIndex - 1).addClass('stacked');
        }, 0);
    });

    var itemsList = [];
    function loadItems(callback) {
        $.getJSON('/entities/sales/new_order.php?ajax=item_list', function(items){
            itemsList = items;
            itemsLoaded = true;
            if (typeof callback === 'function') callback();
            tryRenderPrefillRows();
        });
    }

    var mealsList = [];
    function loadMeals(callback) {
        $.getJSON('/entities/sales/new_order.php?ajax=meal_list', function(meals){
            mealsList = meals;
            mealsLoaded = true;
            if (typeof callback === 'function') callback();
            tryRenderPrefillRows();
        });
    }

    function resetAddRowButton() {
        $('#add-row').off('click').on('click', function(){ addRow(); });
    }

    function refreshItemRowsDropdowns() {
        $('#items-table tbody tr').each(function(){
            var $row = $(this);
            var $itemSel = $row.find('.item-select');
            var currentVal = $itemSel.val();
            if ($itemSel[0] && $itemSel[0].tomselect) {
                $itemSel[0].tomselect.destroy();
            }
            $itemSel.empty().append('<option value="">Select Item</option>');
            itemsList.forEach(function(it){
                $itemSel.append('<option value="'+it.id+'" data-pack="'+it.default_pack_size+'" data-price="'+it.price_per_unit+'">'+it.name+'</option>');
            });
            if (currentVal) $itemSel.val(currentVal);
            new TomSelect($itemSel[0], {create:false, sortField:'text'});
        });
    }

    $('#refresh-items').off('click').on('click', function(e){
        e.preventDefault();
        loadItems(function(){
            refreshItemRowsDropdowns();
            resetAddRowButton();
        });
    });

    $('#refresh-meals').off('click').on('click', function(e){
        e.preventDefault();
        loadMeals(function(){
            refreshMealRowsDropdowns();
            resetAddMealRowButton();
        });
    });

    function refreshMealRowsDropdowns() {
        $('#meals-table tbody tr').each(function(){
            var $row = $(this);
            var $mealSel = $row.find('.meal-select');
            var currentVal = $mealSel.val();
            if ($mealSel[0] && $mealSel[0].tomselect) {
                $mealSel[0].tomselect.destroy();
            }
            $mealSel.empty().append('<option value="">Select Meal</option>');
            mealsList.forEach(function(meal){
                $mealSel.append('<option value="'+meal.id+'" data-price="'+meal.price+'">'+meal.name+'</option>');
            });
            if (currentVal) $mealSel.val(currentVal);
            new TomSelect($mealSel[0], {create:false, sortField:'text'});
        });
    }

    function resetAddMealRowButton() {
        $('#add-meal-row').off('click').on('click', function(){ addMealRow(); });
    }

    function addRow(item = {}, focus = false) {
        item = item || {};
        var idx = $('#items-table tbody tr').length;
        var $row = $('<tr>');
        var $itemSel = $('<select class="form-control item-select" name="items['+idx+'][item_id]"></select>');
        $itemSel.append('<option value="">Select Item</option>');
        itemsList.forEach(function(it){
            $itemSel.append('<option value="'+it.id+'" data-pack="'+it.default_pack_size+'" data-price="'+it.price_per_unit+'">'+it.name+'</option>');
        });
        $row.append($('<td>').append($itemSel));
        var qtyVal = (typeof item.qty !== 'undefined') ? item.qty : 1;
        var packVal = (typeof item.pack_size !== 'undefined') ? item.pack_size : "";
        var priceVal = (typeof item.price_per_unit !== 'undefined') ? item.price_per_unit : "";
        var totalVal = (typeof item.total !== 'undefined') ? item.total : "";
        $row.append('<td><input type="number" class="form-control qty" name="items['+idx+'][qty]" value="'+qtyVal+'" min="0.01" step="0.01"></td>');
        $row.append('<td><input type="number" class="form-control pack-size" name="items['+idx+'][pack_size]" value="'+packVal+'" min="0.01" step="0.01"></td>');
        $row.append('<td><input type="number" class="form-control price" name="items['+idx+'][price_per_unit]" value="'+priceVal+'" min="0" step="0.01"></td>');
        $row.append('<td><input type="number" class="form-control total" name="items['+idx+'][total]" value="'+(totalVal !== "" ? totalVal : "0.00")+'" step="0.01" min="0"></td>');
        $row.append('<td><button type="button" class="icon-btn trash-btn" tabindex="-1" title="Remove Row"><i class="fa fa-trash"></i></button></td>');
        $('#items-table tbody').append($row);
        var tomselect = new TomSelect($itemSel[0], {create:false, sortField:'text'});
        if(item && item.item_id) {
            tomselect.setValue(item.item_id, true);
        }
        if (focus) {
            setTimeout(function() {
                tomselect.focus();
            }, 30);
        }

        // Store the default pack size for logic reference (on the row data)
        var defaultPackSize = null;

        // --- MODIFIED: Always update fields when item changes ---
        $itemSel.on('change', function(){
            var op = $(this).find('option:selected');
            var defPack = op.data('pack') || '';
            var defPrice = op.data('price') || '';
            var $pack = $row.find('.pack-size');
            var $qty = $row.find('.qty');
            var $price = $row.find('.price');

            // Set the default pack size for this row for future reference
            defaultPackSize = parseFloat(defPack) || 1;
            $row.data('defaultPackSize', defaultPackSize);

            // Always update pack size and price when item changes (even if row reused)
            $pack.val(defPack);
            $price.val(defPrice);

            // If qty < pack size, set pack size = qty; else, set pack size = defaultPackSize
            var qty = parseFloat($qty.val()) || 0.01;
            if (qty < defaultPackSize) {
                $pack.val(qty);
            } else {
                $pack.val(defaultPackSize);
            }
            recalc();
        });

        // Qty change logic: if item selected, adjust pack size as per rule
        $row.on('input', '.qty', function(e){
            var $qty = $row.find('.qty');
            var $pack = $row.find('.pack-size');
            var $itemSel = $row.find('.item-select');
            var op = $itemSel.find('option:selected');
            var defPack = op.data('pack');
            var defaultPack = $row.data('defaultPackSize');
            if (!defaultPack && defPack) defaultPack = parseFloat(defPack) || 1;
            if (!defaultPack) defaultPack = 1;
            var qty = parseFloat($qty.val()) || 0.01;
            if (qty < defaultPack) {
                $pack.val(qty);
            } else {
                $pack.val(defaultPack);
            }
            recalc();
        });

        // Pack-size and price editable, just recalc
        $row.on('input', '.pack-size, .price', function(e){
            recalc();
        });

        // Total field: when changed, update price per unit for this row
        $row.find('.total').on('input', function() {
            var qty = parseFloat($row.find('.qty').val()) || 1;
            var total = parseFloat($(this).val()) || 0;
            var newPrice = qty > 0 ? (total / qty) : 0;
            $row.find('.price').val(newPrice.toFixed(2));
            recalc();
        });

        // Set initial values if this is a meal-derived item
        if (item.item_id) {
            $itemSel.val(item.item_id);
        }

        recalc();
        return $row;
    }

    function addMealRow(meal = {}, focus = false) {
        meal = meal || {};
        var idx = $('#meals-table tbody tr').length;
        var $row = $('<tr data-meal-index="'+idx+'"></tr>');

        // Meal select
        var $mealCell = $('<td></td>');
        var $mealSelect = $('<select class="meal-select"></select>');
        $mealSelect.append('<option value="">Select Meal</option>');
        mealsList.forEach(function(m){
            var selected = (meal.meal_id && meal.meal_id == m.id) ? 'selected' : '';
            $mealSelect.append('<option value="'+m.id+'" data-price="'+m.price+'" '+selected+'>'+m.name+'</option>');
        });
        $mealCell.append($mealSelect);
        $row.append($mealCell);

        // Qty
        var $qtyCell = $('<td></td>');
        var $qtyInput = $('<input type="number" class="qty" min="0.01" step="0.01" value="'+(meal.qty || 1)+'">');
        $qtyCell.append($qtyInput);
        $row.append($qtyCell);

        // Price per meal
        var $priceCell = $('<td></td>');
        var $priceInput = $('<input type="number" class="price-per-meal" min="0" step="0.01" value="'+(meal.price_per_meal || 0)+'">');
        $priceCell.append($priceInput);
        $row.append($priceCell);

        // Total
        var $totalCell = $('<td></td>');
        var $totalInput = $('<input type="number" class="total" min="0" step="0.01" readonly value="'+(meal.total || 0)+'">');
        $totalCell.append($totalInput);
        $row.append($totalCell);

        // Remove button
        var $removeCell = $('<td></td>');
        var $removeBtn = $('<button type="button" class="trash-btn icon-btn"><i class="fa fa-trash"></i></button>');
        $removeCell.append($removeBtn);
        $row.append($removeCell);

        $('#meals-table tbody').append($row);

        // Initialize TomSelect for the meal dropdown
        new TomSelect($mealSelect[0], {create:false, sortField:'text'});

        // When meal is selected, update price and add items to items table
        $row.on('change', '.meal-select', function(){
            var mealId = $(this).val();
            var $priceInput = $row.find('.price-per-meal');
            
            if (mealId) {
                var selectedMeal = mealsList.find(m => m.id == mealId);
                if (selectedMeal) {
                    $priceInput.val(selectedMeal.price);
                    addMealItemsToOrder(mealId, parseFloat($row.find('.qty').val()) || 1);
                    recalcMeal($row);
                }
            } else {
                $priceInput.val(0);
                recalcMeal($row);
            }
        });

        // When qty changes, update total and meal items
        $row.on('input', '.qty', function(){
            var mealId = $row.find('.meal-select').val();
            if (mealId) {
                addMealItemsToOrder(mealId, parseFloat($(this).val()) || 1);
            }
            recalcMeal($row);
        });

        // When price changes, update total
        $row.on('input', '.price-per-meal', function(){
            recalcMeal($row);
        });

        if (focus && $mealSelect[0] && $mealSelect[0].tomselect) {
            setTimeout(function() { $mealSelect[0].tomselect.focus(); }, 100);
        }

        recalcMeal($row);
        return $row;
    }

    function recalcMeal($row) {
        var qty = parseFloat($row.find('.qty').val()) || 1;
        var pricePerMeal = parseFloat($row.find('.price-per-meal').val()) || 0;
        var total = qty * pricePerMeal;
        $row.find('.total').val(total.toFixed(2));
        recalc();
    }

    function addMealItemsToOrder(mealId, qty) {
        // Get meal items from API and add them to items table
        $.getJSON('/api/meals.php?action=get_meal_items&meal_id=' + mealId, function(resp) {
            if (resp.success && resp.items) {
                // Remove existing meal items for this meal
                $('#items-table tbody tr').each(function() {
                    if ($(this).data('from-meal') == mealId) {
                        $(this).remove();
                    }
                });

                // Add new meal items
                resp.items.forEach(function(item) {
                    var totalQty = parseFloat(item.qty) * qty;
                    var itemData = {
                        item_id: item.item_id,
                        qty: totalQty,
                        pack_size: item.pack_size || 1,
                        price_per_unit: 0, // Price is 0 for meal items
                        total: 0,
                        from_meal: mealId,
                        meal_id: mealId
                    };
                    
                    var $newRow = addRow(itemData, false);
                    $newRow.data('from-meal', mealId);
                    $newRow.find('input').css('background-color', '#f8f9fa');
                    $newRow.find('select').css('background-color', '#f8f9fa');
                });
            }
        });
    }

    $('#items-table').off('click', '.trash-btn').on('click', '.trash-btn', function(){
        $(this).closest('tr').remove();
        recalc();
    });

    $('#meals-table').off('click', '.trash-btn').on('click', '.trash-btn', function(){
        var $row = $(this).closest('tr');
        var mealId = $row.find('.meal-select').val();
        
        // Remove associated meal items from items table
        if (mealId) {
            $('#items-table tbody tr').each(function() {
                if ($(this).data('from-meal') == mealId) {
                    $(this).remove();
                }
            });
        }
        
        $row.remove();
        recalc();
    });

    function roundToNearest(value, nearest) {
        return Math.round(value / nearest) * nearest;
    }
    function floorToNearest(value, nearest) {
        return Math.floor(value / nearest) * nearest;
    }

    var lastGrandTotalRaw = 0;
    var lastGrandTotalRounded = 0;
    var lastRoundingDiff = 0;

    function recalc() {
        var orderTotal = 0;
        
        // Calculate items total (direct items only, not meal-derived)
        $('#items-table tbody tr').each(function(){
            var $row = $(this);
            var qty = parseFloat($row.find('.qty').val())||0;
            var price = parseFloat($row.find('.price').val())||0;
            var $total = $row.find('.total');
            var total = qty * price;
            var roundedTotal = total < 1000
                ? roundToNearest(total, 5)
                : roundToNearest(total, 10);
            if (document.activeElement !== $total[0]) {
                $total.val(roundedTotal.toFixed(2));
            } else {
                var totalField = parseFloat($total.val()) || 0;
                var newPrice = qty > 0 ? (totalField / qty) : 0;
                $row.find('.price').val(newPrice.toFixed(2));
                orderTotal += totalField;
                return;
            }
            orderTotal += roundedTotal;
        });

        // Add meals total
        $('#meals-table tbody tr').each(function(){
            var $row = $(this);
            var total = parseFloat($row.find('.total').val())||0;
            orderTotal += total;
        });

        $('#order-amount').val(orderTotal.toFixed(2));

        var discountAmount = parseFloat($('#discount-amount').val()) || 0;
        var discountPercent = parseFloat($('#discount-percent').val()) || 0;
        var deliveryCharges = parseFloat($('#delivery-charges').val()) || 0;

        var focused = $(':focus').attr('id');
        if (focused === 'discount-amount') {
            if (orderTotal > 0) {
                discountPercent = (discountAmount/orderTotal)*100;
                $('#discount-percent').val(discountPercent.toFixed(2));
            }
        } else if (focused === 'discount-percent') {
            discountAmount = (orderTotal * discountPercent)/100;
            $('#discount-amount').val(discountAmount.toFixed(2));
        } else {
            if (orderTotal > 0) {
                discountPercent = (discountAmount/orderTotal)*100;
                $('#discount-percent').val(discountPercent.toFixed(2));
            }
        }

        var grandTotalRaw = orderTotal - discountAmount + deliveryCharges;

        var roundingTarget = (grandTotalRaw < 5000) ? 50 : 100;
        var grandTotalRounded = floorToNearest(grandTotalRaw, roundingTarget);
        var roundingDiff = grandTotalRaw - grandTotalRounded;

        lastGrandTotalRaw = grandTotalRaw;
        lastGrandTotalRounded = grandTotalRounded;
        lastRoundingDiff = roundingDiff;

        if (roundingDiff > 0.009) {
            $('#apply-rounding-btn').show();
        } else {
            $('#apply-rounding-btn').hide();
        }

        if ($('#apply-rounding-btn').data('applied')) {
            $('#grand-total').val(grandTotalRounded.toFixed(2));
        } else {
            $('#grand-total').val(grandTotalRaw.toFixed(2));
        }

        $('#hidden-amount').val(orderTotal.toFixed(2));
        $('#hidden-discount').val(discountAmount.toFixed(2));
        $('#hidden-delivery-charges').val(deliveryCharges.toFixed(2));
        $('#hidden-grand-total').val(
            ($('#apply-rounding-btn').data('applied'))
                ? grandTotalRounded.toFixed(2)
                : grandTotalRaw.toFixed(2)
        );
    }

    // --- API-based submit handler ---
    $('#order-form').off('submit').on('submit', function(e){
        e.preventDefault();

        let items = [];
        $('#items-table tbody tr').each(function(){
            var $row = $(this);
            var itemId = $row.find('.item-select').val();
            if (itemId) {
                var mealId = $row.data('from-meal') || null;
                items.push({
                    item_id: itemId,
                    qty: $row.find('.qty').val(),
                    pack_size: $row.find('.pack-size').val(),
                    price_per_unit: $row.find('.price').val(),
                    total: $row.find('.total').val(),
                    meal_id: mealId
                });
            }
        });

        let meals = [];
        $('#meals-table tbody tr').each(function(){
            var $row = $(this);
            var mealId = $row.find('.meal-select').val();
            if (mealId) {
                meals.push({
                    meal_id: mealId,
                    qty: $row.find('.qty').val(),
                    price_per_meal: $row.find('.price-per-meal').val()
                });
            }
        });

        // Validate that at least one item or meal is selected
        if (items.length === 0 && meals.length === 0) {
            alert('Please add at least one item or meal to the order.');
            return;
        }

        let customer_id = $('#customer-select')[0] && $('#customer-select')[0].tomselect
            ? $('#customer-select')[0].tomselect.getValue()
            : $('#customer-select').val();

        if (!customer_id) {
            alert('Please select a customer.');
            return;
        }

        let payload = {
            customer_id: customer_id,
            discount: $('#discount-amount').val(),
            delivery_charges: $('#delivery-charges').val(),
            amount: $('#order-amount').val(),
            grand_total: $('#grand-total').val(),
            items: items,
            meals: meals
        };

        let action = currentOrderId ? 'edit' : 'add';
        if (action === 'edit') payload.order_id = currentOrderId;

        $.ajax({
            url: '/api/orders.php?action=' + action,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            dataType: 'json',
            success: function(resp) {
                if(resp.success) {
                    alert('Order saved!');
                    location.reload();
                } else {
                    alert('Order could not be saved: ' + (resp.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, err) {
                alert('Order could not be saved (AJAX error): ' + err + "\n" + xhr.responseText);
            }
        });
    });

    $(document).off('click', '#apply-rounding-btn').on('click', '#apply-rounding-btn', function(){
        var discountAmount = parseFloat($('#discount-amount').val()) || 0;
        var newDiscount = discountAmount + lastRoundingDiff;
        $('#discount-amount').val(newDiscount.toFixed(2));
        $('#apply-rounding-btn').data('applied', true);
        recalc();
    });

    $('#discount-amount, #discount-percent, #delivery-charges').off('input').on('input', function() {
        $('#apply-rounding-btn').data('applied', false);
        recalc();
    });

    // --- Prefill logic for editing using API ---
    if (prefill && prefill.order_id) {
        $.getJSON('/api/orders.php?action=get&id=' + encodeURIComponent(prefill.order_id), function(resp){
            if (resp.success && resp.order) {
                prefillOrderHeader = {
                    customer_id: resp.order.customer_id,
                    amount: resp.order.amount,
                    discount: resp.order.discount,
                    delivery_charges: resp.order.delivery_charges,
                    grand_total: resp.order.grand_total
                };
                prefillHeaderLoaded = true;
                prefillOrderItems = [];
                prefillOrderMeals = [];
                
                // Load items (filter out meal-derived items for separate handling)
                if (Array.isArray(resp.order.items)) {
                    resp.order.items.forEach(function(item){
                        prefillOrderItems.push({
                            item_id: item.item_id,
                            qty: item.qty,
                            pack_size: item.pack_size,
                            price_per_unit: item.price_per_unit,
                            total: item.total,
                            meal_id: item.meal_id
                        });
                    });
                }
                
                // Load meals
                if (Array.isArray(resp.order.meals)) {
                    resp.order.meals.forEach(function(meal){
                        prefillOrderMeals.push({
                            meal_id: meal.meal_id,
                            qty: meal.qty,
                            price_per_meal: meal.price_per_meal
                        });
                    });
                }
                
                prefillItemsLoaded = true;
                prefillMealsLoaded = true;
                currentOrderId = resp.order.id;
                tryRenderPrefillRows();
            } else {
                prefillHeaderLoaded = true;
                prefillItemsLoaded = true;
                prefillMealsLoaded = true;
                prefillOrderHeader = {};
                prefillOrderItems = [];
                prefillOrderMeals = [];
                currentOrderId = null;
                tryRenderPrefillRows();
            }
        });
    } else {
        prefillHeaderLoaded = true;
        prefillItemsLoaded = true;
        prefillMealsLoaded = true;
        prefillOrderHeader = {};
        prefillOrderItems = [];
        prefillOrderMeals = [];
        currentOrderId = null;
        tryRenderPrefillRows();
    }

    loadCustomers();
    loadItems(function(){
        resetAddRowButton();
    });
    loadMeals(function(){
        resetAddMealRowButton();
    });

    recalc();
};