// FrozoFun Admin - Main JS
// Handles global UI, theme switch, modals, persistent state, tomselect, etc.
document.addEventListener('keydown', function(e) {
    // Ctrl+O
    if (e.ctrlKey && e.key === 'o') {
        e.preventDefault();
        const btn = document.getElementById('show-add-order');
        if (btn) btn.click();
        setTimeout(function() {
            let barcodeInput = document.getElementById('barcode-input');
            if (barcodeInput) barcodeInput.focus();
        }, 500);
    }
});

document.addEventListener("DOMContentLoaded", function () {
    // Flash messages (fade out)
    const flash = document.querySelector('.flash-message');
    if (flash) {
        setTimeout(() => flash.classList.add('d-none-important'), 4000);
    }

    // Initialize TomSelect
    if (window.TomSelect) {
        document.querySelectorAll('select.tom-select').forEach(function(sel) {
            if (!sel.tomselect) {
                new TomSelect(sel, {
                    create: false,
                    sortField: {field: "text", direction: "asc"},
                    dropdownParent: sel.closest('.modal-content') || document.body
                });
            }
        });

        if (window.MutationObserver) {
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) {
                            const selects = node.matches && node.matches('select.tom-select') ? [node] : node.querySelectorAll ? node.querySelectorAll('select.tom-select') : [];
                            selects.forEach(function(sel) {
                                if (!sel.tomselect) {
                                    new TomSelect(sel, {
                                        create: false,
                                        sortField: {field: "text", direction: "asc"},
                                        dropdownParent: sel.closest('.modal-content') || document.body
                                    });
                                }
                            });
                        }
                    });
                });
            });
            observer.observe(document.body, {childList: true, subtree: true});
        }
    }

    // Bootstrap modal event handlers for state preservation ONLY (no reload)
    if (window.jQuery) {
        $(document).on('hidden.bs.modal', '.modal', function () {
            const scrollPos = window.scrollY || window.pageYOffset || document.documentElement.scrollTop || 0;
            let page = null;
            if (window.jQuery && window.jQuery.fn.dataTable) {
                const tableIds = ['#orders-table', '#batches-table', '#customers-table', '#items-table', '#vendors-table', '#inventory-table'];
                for (let id of tableIds) {
                    if ($(id).length && $.fn.DataTable.isDataTable(id)) {
                        page = $(id).DataTable().page();
                        break;
                    }
                }
            }
            if (page !== null) localStorage.setItem('lastDataTablePage', page);
            localStorage.setItem('lastScrollPos', scrollPos);
        });
        // Removed: refresh-on-close flag setter and page reload on hidden
    }

    // Restore scroll/page on reload, if applicable
    if (localStorage.getItem('lastScrollPos')) {
        window.scrollTo(0, parseInt(localStorage.getItem('lastScrollPos')));
        localStorage.removeItem('lastScrollPos');
    }
    if (localStorage.getItem('lastDataTablePage')) {
        const page = parseInt(localStorage.getItem('lastDataTablePage'));
        setTimeout(function() {
            ['#orders-table','#batches-table','#customers-table','#items-table','#vendors-table','#inventory-table'].forEach(function(id){
                if (window.jQuery && $(id).length && $.fn.DataTable && $.fn.DataTable.isDataTable(id)) {
                    $(id).DataTable().page(page).draw(false);
                }
            });
            localStorage.removeItem('lastDataTablePage');
        }, 300);
    }
});

// Global live data reload hook (no full page reload)
window.reloadLiveData = function() {
    try {
        // Prefer explicit entity reloaders if present
        if (window.EntityUI && typeof window.EntityUI.reload === 'function') return void window.EntityUI.reload();
        if (window.CustomerUI && typeof window.CustomerUI.reload === 'function') return void window.CustomerUI.reload();
        if (typeof window.fetchCustomersList === 'function') return void window.fetchCustomersList();
        if (window.OrdersUI && typeof window.OrdersUI.reload === 'function') return void window.OrdersUI.reload();
    } catch (e) {
        console.warn('Error calling entity reload function:', e);
    }

    // DataTables AJAX reload fallbacks
    if (window.jQuery && $.fn.DataTable) {
        const selectors = ['#orders-table', '#batches-table', '#customers-table', '#items-table', '#vendors-table', '#inventory-table'];
        let reloaded = false;
        selectors.forEach(function(id){
            if ($(id).length && $.fn.DataTable.isDataTable(id)) {
                const dt = $(id).DataTable();
                if (dt.ajax && typeof dt.ajax.reload === 'function') {
                    dt.ajax.reload(null, false);
                } else {
                    dt.draw(false);
                }
                reloaded = true;
            }
        });
        if (reloaded) return;
    }

    // Broadcast event so page scripts can refresh themselves
    document.dispatchEvent(new CustomEvent('app:reload-data'));
};

// Note: DataTables initialization is handled by unified-tables.js