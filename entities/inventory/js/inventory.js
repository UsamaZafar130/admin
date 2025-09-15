// Inventory Management JS

$(document).ready(function () {
    // Select All functionality (and sync with individual checkboxes)
    $('#select-all-orders').on('change', function() {
        $('.order-checkbox').prop('checked', this.checked);
    });
    $(document).on('change', '.order-checkbox', function() {
        let allChecked = $('.order-checkbox').length === $('.order-checkbox:checked').length;
        $('#select-all-orders').prop('checked', allChecked);
    });

    // Stock Requirements button - open requirements in new tab
    $('#btn-stock-req').on('click', function() {
        let orderIds = $('.order-checkbox:checked').map(function(){ return $(this).val(); }).get();
        if (orderIds.length === 0) {
            alert('Please select at least one order!');
            return;
        }
        window.open('stock_requirements.php?order_ids=' + orderIds.join(','), '_blank');
    });

    // Packing Log button - open packing log in new tab
    $('#btn-packing-log').on('click', function() {
        let orderIds = $('.order-checkbox:checked').map(function(){ return $(this).val(); }).get();
        if (orderIds.length === 0) {
            alert('Please select at least one order!');
            return;
        }
        window.open('packing.php?order_ids=' + orderIds.join(','), '_blank');
    });

    // Packing Labels button - open packing_labels.php in new tab
    $('#btn-packing-labels').on('click', function() {
        let orderIds = $('.order-checkbox:checked').map(function(){ return $(this).val(); }).get();
        if (orderIds.length === 0) {
            alert('Please select at least one order!');
            return;
        }
        window.open('packing_labels.php?order_ids=' + orderIds.join(','), '_blank');
    });

    // Use global floating modals (provided by floater.php + floater.js)
    $('#btn-add-stock').on('click', function() {
        if (typeof window.showFloatingAddStockModal === 'function') {
            window.showFloatingAddStockModal();
        } else {
            console.error('Floating Add Stock modal not available. Is floater.php included via header.php?');
        }
    });

    $('#btn-excess-stock').on('click', function() {
        if (typeof window.showExcessStockModal === 'function') {
            window.showExcessStockModal();
        } else {
            console.error('Floating Excess Stock modal not available. Is floater.php included via header.php?');
        }
    });

    // Inline manufactured update (on Stock Requirements page)
    $(document).on('click', '.btn-update-stock', function() {
        let $tr = $(this).closest('tr');
        let item_id = $tr.find('.row-item-id').val();
        let qty = parseFloat($tr.find('.manufactured-input').val());

        // Updated! Find Total Required by its actual column index (4th column: #, Item, Category, Total Required)
        let required = parseFloat($tr.find('td:nth-child(4)').text().replace(/,/g, ''));
        let manufactured = parseFloat($tr.find('.manufactured-val').text().replace(/,/g, ''));

        if (!item_id || isNaN(qty) || qty <= 0) {
            alert('Please enter a positive quantity.');
            return;
        }

        // Now just add stock, like the modal
        $.post('actions.php', {action:'update_manufactured', item_id, qty}, function(resp) {
            if (resp.success) {
                setTimeout(function() { location.reload(); }, 400);
            } else {
                alert(resp.error || 'Update failed');
            }
        }, 'json');
    });
});