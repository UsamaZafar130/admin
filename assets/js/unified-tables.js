/**
 * UnifiedTables - Minimal wrapper around DataTables
 * Goal: Preserve server-rendered row order and rely on existing Bootstrap table classes.
 * No custom DOM restructuring, no injected CSS, no forced re-sorting.
 */

window.UnifiedTables = {

    // Base minimal config (Bootstrap classes are already on the table in HTML)
    baseConfig: {
        // Keep the exact order the server rendered (newest first, etc.)
        order: [],
        // Allow user to sort after load by clicking headers
        ordering: true,

        // Standard interactive features (can turn off per table later if desired)
        paging: true,
        searching: true,
        info: true,
        lengthChange: true,
        pageLength: 50,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],

        // Keep widths natural; DataTables sometimes still measures but this helps minimize inline width churn
        autoWidth: false,

        // Use DataTables' default DOM layout for simplicity (no custom row wrappers):
        // l - length menu, f - filter, r - processing, t - table, i - info, p - pagination
        dom: 'lfrtip',

        // Do not force columnDefs unless necessary; let DataTables detect
        // (You can add a columnDefs array here if you need to disable sorting on action columns.)
    },

    // Optional predefined configs (currently just clones; can customize later)
    configs: {
        default: function() { return $.extend(true, {}, UnifiedTables.baseConfig); },
        purchases: function() { return $.extend(true, {}, UnifiedTables.baseConfig); },
        expenses: function() { return $.extend(true, {}, UnifiedTables.baseConfig); },
        batches: function() { return $.extend(true, {}, UnifiedTables.baseConfig); },
        orders: function() {
            // For orders we previously forced [[1,'desc']], but requirement now is to keep server order.
            // If you want explicit order again, add: order: [[1,'desc']]
            return $.extend(true, {}, UnifiedTables.baseConfig);
        },
        vendors: function() { return $.extend(true, {}, UnifiedTables.baseConfig); },
        customers: function() { return $.extend(true, {}, UnifiedTables.baseConfig); },
        items: function() { return $.extend(true, {}, UnifiedTables.baseConfig); },
        'stock-requirements': function() { return $.extend(true, {}, UnifiedTables.baseConfig); }
    },

    /**
     * Initialize a DataTable.
     * @param {string} tableSelector - CSS selector for the table (e.g. '#purchases-table')
     * @param {string} configType - Key from configs (e.g. 'purchases'); defaults to 'default'
     * @param {object} extraOptions - Optional overrides/extensions
     * @returns DataTable instance
     */
    init: function(tableSelector, configType, extraOptions) {
        configType = configType || 'default';
        extraOptions = extraOptions || {};

        const getter = this.configs[configType] || this.configs.default;
        let config = getter();

        // Merge any caller-provided overrides (last wins)
        $.extend(true, config, extraOptions);

        // Destroy if already initialized
        if ($.fn.DataTable.isDataTable(tableSelector)) {
            $(tableSelector).DataTable().destroy();
        }

        // Initialize
        const dt = $(tableSelector).DataTable(config);

        // Ensure NO initial resort happened (defensive): re-assert empty ordering if DataTables decided otherwise
        if (config.order && config.order.length === 0) {
            try { dt.order([]).draw(false); } catch(e){}
        }

        return dt;
    },

    /**
     * Utility to clear any previous DataTables state (in case a different config saved state elsewhere).
     * Call this before init if you had stateSave enabled previously and want a clean load.
     */
    clearSavedStateFor: function(tableSelector) {
        try {
            const name = $(tableSelector).attr('id') || '';
            if (!name) return;
            Object.keys(localStorage).forEach(k => {
                if (k.indexOf('DataTables_' + name) !== -1) {
                    localStorage.removeItem(k);
                }
            });
        } catch(e){}
    }
};

// Minimal ready hook (no styling injection now)
$(document).ready(function() {
    // Intentionally left blank: no automatic init (call UnifiedTables.init manually per page)
});