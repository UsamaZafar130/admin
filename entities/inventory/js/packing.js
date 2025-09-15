// Packing Log JS

$(document).ready(function () {
    // Add Packed Packs button
    $('#btn-add-pack').on('click', function () {
        openAddPackModal();
    });

    // Scan Packs button
    $('#btn-scan-pack').on('click', function () {
        openScanPackTab();
    });

    // Open Add Pack Modal using UnifiedModals
    function openAddPackModal() {
        window.UnifiedModals.show('add-pack-modal');

        let $sel = $('#pack-item-select');
        // Destroy old TomSelect instance if exists
        if ($sel[0].tomselect) {
            $sel[0].tomselect.destroy();
        }
        $sel.empty().append('<option value="">Loading...</option>');

        // Fetch item+pack sizes from the table itself for dropdown
        let options = [];
        $('#packing-log-table tbody tr').each(function () {
            let item_id = $(this).find('.row-item-id').val();
            let pack_size = $(this).find('.row-pack-size').val();
            let label = $(this).find('td').eq(1).contents().first().text().trim();
            if (item_id && pack_size) {
                options.push({
                    value: item_id + '_' + pack_size,
                    text: label
                });
            }
        });
        $sel.empty().append('<option value="">Select Item & Pack Size</option>');
        options.forEach(function (opt) {
            $sel.append('<option value="' + opt.value + '">' + opt.text + '</option>');
        });

        if (typeof TomSelect !== "undefined") {
            new TomSelect($sel[0], {
                create: false,
                sortField: 'text'
            });
        }
    }

    // Open Scan Pack in NEW TAB (not modal)
    function openScanPackTab() {
        window.open('/entities/inventory/packing_scan.php', '_blank');
    }

    // Add Pack Modal form submit
    $('#add-pack-form').on('submit', function (e) {
        e.preventDefault();
        let item_pack = $('#pack-item-select').val();
        let pack_count = parseInt($('#pack-count').val(), 10);
        let comment = $('#pack-comment').val();
        // order_ids not needed anymore

        if (!item_pack || !pack_count || isNaN(pack_count) || pack_count < 1) {
            $('#add-pack-feedback').addClass('alert-danger').removeClass('alert-success').text('Please select item & pack size and enter a valid pack count.').show();
            return;
        }
        let [item_id, pack_size] = item_pack.split('_');
        $.post('actions.php', { action: 'add_packed_packs', item_id, pack_size, pack_count, comment }, function (resp) {
            if (resp.success) {
                $('#add-pack-feedback').removeClass('alert-danger').addClass('alert-success').text('Packed packs added!').show();
                setTimeout(() => { 
                    const modal = document.getElementById('add-pack-modal');
                    window.UnifiedModals.hide(modal); 
                    location.reload(); 
                }, 600);
            } else {
                $('#add-pack-feedback').addClass('alert-danger').removeClass('alert-success').text(resp.error || 'Error!').show();
            }
        }, 'json');
    });

    // Inline "Add Packed Packs" on table
    $(document).on('click', '.btn-update-pack', function () {
        let $tr = $(this).closest('tr');
        let item_id = $tr.find('.row-item-id').val();
        let pack_size = $tr.find('.row-pack-size').val();
        let pack_count = parseInt($tr.find('.packs-input').val(), 10);
        // order_ids not needed anymore

        if (!item_id || !pack_size || !pack_count || isNaN(pack_count) || pack_count < 1) {
            alert('Please enter a valid number of packs.');
            return;
        }

        $.post('actions.php', { action: 'add_packed_packs', item_id, pack_size, pack_count }, function (resp) {
            if (resp.success) {
                setTimeout(function () { location.reload(); }, 400);
            } else {
                alert(resp.error || 'Update failed');
            }
        }, 'json');
    });

    // Remove custom ESC key handling - Bootstrap modals handle this automatically
});