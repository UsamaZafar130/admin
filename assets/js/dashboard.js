/**
 * Dashboard functionality - Index page
 * Extracted from index.php for better maintainability
 * Includes fallbacks for missing dependencies
 */

// Dashboard state variables
let isEditMode = false;
let rowSortableInstances = [];
let dashboardRowsSortable = null;

// jQuery fallback check
if (typeof $ === 'undefined') {
    console.warn('jQuery is not loaded. Some dashboard features may not work properly.');
    // Basic fallback functionality
    window.$ = window.jQuery = function(selector) {
        if (typeof selector === 'function') {
            // Document ready fallback
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', selector);
            } else {
                selector();
            }
        }
        return {
            on: function() { return this; },
            click: function() { return this; },
            html: function() { return this; },
            show: function() { return this; },
            hide: function() { return this; },
            addClass: function() { return this; },
            removeClass: function() { return this; },
            toggleClass: function() { return this; },
            length: 0
        };
    };
    $.ajax = function() { console.warn('AJAX not available'); };
}

$(function(){
    // Only initialize if dependencies are loaded
    if (typeof $ !== 'undefined' && $.fn) {
        // Load Business Analytics
        loadBusinessAnalytics();

        // Initialize edit mode functionality
        initEditMode();
    } else {
        console.warn('Dashboard initialization skipped due to missing dependencies');
    }

    // Lightweight open handlers for Dailies modals (fallback if dailies.js not bound yet)
    document.addEventListener('click', function(ev){
        const t = ev.target;
        if (!t) return;
        if (t.id === 'open-journal-modal') {
            ev.preventDefault();
            const modalEl = document.getElementById('journalModal');
            if (modalEl) {
                if (typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                } else {
                    modalEl.style.display = 'block';
                }
            }
        }
        if (t.id === 'open-report-modal') {
            ev.preventDefault();
            const modalEl = document.getElementById('reportModal');
            if (modalEl) {
                if (typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                } else {
                    modalEl.style.display = 'block';
                }
            }
        }
    });
});

// Initialize edit mode functionality
function initEditMode() {
    $('#edit-layout-btn').on('click', function() {
        toggleEditMode();
    });
}

// Toggle edit mode
function toggleEditMode() {
    isEditMode = !isEditMode;
    const $btn = $('#edit-layout-btn');
    const $handles = $('.drag-handle');
    const $rowHandles = $('.row-drag-handle');
    const $cards = $('.dashboard-card');
    
    if (isEditMode) {
        // Enter edit mode
        $btn.html('<i class="fas fa-save me-1"></i>Save Layout')
            .removeClass('btn-outline-primary')
            .addClass('btn-success');
        
        // Show drag handles
        $handles.show();
        $rowHandles.removeClass('d-none').show();
        
        // Add edit mode styling to cards
        $cards.addClass('edit-mode');
        
        // Initialize row-based sortable
        initRowBasedSorting();
        
        // Show edit mode notification
        showEditModeNotification();
        
    } else {
        // Exit edit mode
        $btn.html('<i class="fas fa-edit me-1"></i>Edit Layout')
            .removeClass('btn-success')
            .addClass('btn-outline-primary');
        
        // Hide drag handles
        $handles.hide();
        $rowHandles.addClass('d-none').hide();
        
        // Remove edit mode styling from cards
        $cards.removeClass('edit-mode');
        
        // Destroy sortables
        destroyRowBasedSorting();
        
        // Save the current layout
        saveDashboardOrder();
        
        // Hide edit mode notification
        hideEditModeNotification();
    }
}

// Show edit mode notification  
function showEditModeNotification() {
    if ($('#edit-mode-notification').length === 0) {
        const notification = $(`
            <div id="edit-mode-notification" class="alert alert-info alert-dismissible fade show position-fixed shadow-modern" 
                 style="top: 20px; right: 20px; z-index: 1050; min-width: 350px;">
                <i class="fas fa-edit me-2"></i>
                <strong>Edit Mode Active</strong><br>
                <small>• Drag cards horizontally within their row<br>
                • Drag row handles to move entire rows up/down</small>
                <button type="button" class="btn-close" onclick="toggleEditMode()" aria-label="Close"></button>
            </div>
        `);
        $('body').append(notification);
    }
}

// Hide edit mode notification
function hideEditModeNotification() {
    $('#edit-mode-notification').remove();
}

// Initialize row-based sorting functionality
function initRowBasedSorting() {
    // Only initialize if Sortable is available
    if (typeof Sortable === 'undefined') {
        console.warn('Sortable library not loaded. Drag and drop functionality disabled.');
        return;
    }
    
    // Destroy any existing sortables first
    destroyRowBasedSorting();
    
    // Make rows sortable (vertical movement)
    const dashboardRows = document.getElementById('dashboard-rows');
    if (dashboardRows) {
        dashboardRowsSortable = new Sortable(dashboardRows, {
            handle: '.row-drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost-row',
            chosenClass: 'sortable-chosen-row', 
            dragClass: 'sortable-drag-row',
            disabled: !isEditMode,
            onEnd: function(evt) {
                console.log('Row moved from position', evt.oldIndex, 'to', evt.newIndex);
                if (isEditMode) {
                    saveDashboardOrder();
                }
            }
        });
    }
    
    // Make individual cards sortable within their rows (horizontal movement)
    const rowContents = document.querySelectorAll('.dashboard-row-content');
    rowContents.forEach(function(rowContent) {
        const sortableInstance = new Sortable(rowContent, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            disabled: !isEditMode,
            group: false, // Prevent cards from moving between rows
            onEnd: function(evt) {
                console.log('Card moved within row from position', evt.oldIndex, 'to', evt.newIndex);
                if (isEditMode) {
                    saveDashboardOrder();
                }
            }
        });
        rowSortableInstances.push(sortableInstance);
    });
}

// Destroy all sorting instances
function destroyRowBasedSorting() {
    // Destroy row sortable
    if (dashboardRowsSortable) {
        dashboardRowsSortable.destroy();
        dashboardRowsSortable = null;
    }
    
    // Destroy card sortables
    rowSortableInstances.forEach(function(instance) {
        instance.destroy();
    });
    rowSortableInstances = [];
}

// Save new dashboard layout (rows and card positions)
function saveDashboardOrder() {
    if (typeof $ === 'undefined' || !$.ajax) {
        console.warn('Cannot save dashboard order: AJAX not available');
        return;
    }
    
    const preferences = {};
    
    // Get row order
    const rows = document.querySelectorAll('#dashboard-rows .dashboard-row');
    const rowOrder = {};
    rows.forEach((row, index) => {
        const rowId = row.getAttribute('data-row-id');
        rowOrder[rowId] = index + 1;
    });
    
    // Get card positions within each row
    let globalCardOrder = 1;
    rows.forEach((row, rowIndex) => {
        const rowContent = row.querySelector('.dashboard-row-content');
        const cards = rowContent.querySelectorAll('.dashboard-card');
        
        cards.forEach((card, cardIndex) => {
            const cardId = card.getAttribute('data-card-id');
            preferences[cardId] = {
                visible: true,
                order: globalCardOrder++,
                row: rowIndex + 1,
                position_in_row: cardIndex + 1
            };
        });
    });
    
    // Include hidden cards from settings to maintain their visibility state
    const checkboxes = document.querySelectorAll('#widget-preferences input[type="checkbox"]');
    if (checkboxes.length > 0) {
        checkboxes.forEach(checkbox => {
            const widgetId = checkbox.getAttribute('data-widget');
            if (!preferences[widgetId]) {
                preferences[widgetId] = {
                    visible: checkbox.checked,
                    order: globalCardOrder++,
                    row: 999, // Put hidden cards at the end
                    position_in_row: 1
                };
            } else {
                // Update visibility from checkbox state
                preferences[widgetId].visible = checkbox.checked;
            }
        });
    }

    console.log('Saving row-based dashboard order:', preferences);

    $.ajax({
        url: '/api/dashboard_preferences.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            preferences: preferences
        }),
        success: function(data) {
            if (data.success) {
                console.log('Dashboard order saved successfully');
                if (!isEditMode) {
                    // Show success message briefly when saving on exit
                    showSuccessToast('Layout saved successfully!');
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Error saving dashboard order:', error);
            alert('Error saving layout. Please try again.');
        }
    });
}

// Show success toast notification
function showSuccessToast(message) {
    const toast = $(`
        <div class="toast position-fixed shadow-modern-hover" style="top: 20px; right: 20px; z-index: 1051;">
            <div class="toast-body bg-success text-white border-radius-modern">
                <i class="fas fa-check me-2"></i>${message}
            </div>
        </div>
    `);
    $('body').append(toast);
    
    if (typeof bootstrap !== 'undefined') {
        toast.toast({delay: 2000}).toast('show');
        toast.on('hidden.bs.toast', function () {
            $(this).remove();
        });
    } else {
        // Fallback without bootstrap
        setTimeout(() => {
            toast.fadeOut(() => toast.remove());
        }, 2000);
    }
}

// Function to load business analytics data
function loadBusinessAnalytics() {
    if (typeof $ === 'undefined' || !$.ajax) {
        console.warn('Cannot load analytics: AJAX not available');
        displayFallbackAnalytics();
        return;
    }
    
    console.log('Loading business analytics...');
    $.ajax({
        url: '/api/dashboard_analytics.php',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            console.log('Analytics data received:', data);
            displayBusinessAnalytics(data);
        },
        error: function(xhr, status, error) {
            console.error('Analytics API error:', error);
            // If API fails, show fallback analytics
            displayFallbackAnalytics();
        }
    });
}

// Display business analytics with real data
function displayBusinessAnalytics(data) {
    const analyticsHtml = `
        <div class="col-lg-6 col-md-12 mb-4">
            <div class="card modern-card">
                <div class="card-header">
                    <h6 class="mb-0">Top 10 Items by Quantity (This Month)</h6>
                </div>
                <div class="card-body">
                    <canvas id="top10ItemsChart" width="400" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6 col-md-12 mb-4">
            <div class="card modern-card">
                <div class="card-header">
                    <h6 class="mb-0">Monthly Sales (Last 6 Months)</h6>
                </div>
                <div class="card-body">
                    <canvas id="monthlySalesChart" width="400" height="300"></canvas>
                </div>
            </div>
        </div>
    `;
    $('#business-analytics-content').html(analyticsHtml);
    
    // Create charts with a more reliable method to ensure DOM is ready
    setTimeout(() => {
        initializeDashboardCharts(data);
    }, 500);
}

// Fallback analytics when API is not available
function displayFallbackAnalytics()() {
    const fallbackHtml = `
        <div class="col-lg-6 col-md-12 mb-4">
            <div class="card modern-card">
                <div class="card-header">
                    <h6 class="mb-0">Top 10 Items by Quantity (This Month)</h6>
                </div>
                <div class="card-body text-center">
                    <div class="p-4">
                        <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Chart data will load when analytics service is available</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 col-md-12 mb-4">
            <div class="card modern-card">
                <div class="card-header">
                    <h6 class="mb-0">Monthly Sales (Last 6 Months)</h6>
                </div>
                <div class="card-body text-center">
                    <div class="p-4">
                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No sales data available</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    $('#business-analytics-content').html(fallbackHtml);
}

// Function to initialize dashboard charts
function initializeDashboardCharts(data) {
    console.log('Initializing dashboard charts with data:', data);
    
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js not loaded. Charts will not be displayed.');
        return;
    }
    
    // Verify canvas elements exist before creating charts
    if (!document.getElementById('top10ItemsChart')) {
        console.error('Canvas element top10ItemsChart not found');
        return;
    }
    
    // Top 10 Items by Quantity Chart - from top_selling.php
    if (data.top10Items && data.top10Items.length > 0) {
        const items = data.top10Items;
        const top10 = items.slice(0, 10);
        
        const ctx1 = document.getElementById('top10ItemsChart');
        if (ctx1) {
            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: top10.map(item => item.name.length > 20 ? item.name.substring(0, 20) + '...' : item.name),
                    datasets: [{
                        label: 'Quantity Sold',
                        data: top10.map(item => parseInt(item.total_sold)),
                        backgroundColor: 'rgba(102, 126, 234, 0.8)',
                        borderColor: 'rgba(102, 126, 234, 1)',
                        borderWidth: 2,
                        borderRadius: 8,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
    } else {
        // Show no data message for items chart
        const ctx1 = document.getElementById('top10ItemsChart');
        if (ctx1) {
            const parentDiv = ctx1.parentElement;
            parentDiv.innerHTML = '<div class="text-center p-4"><i class="fas fa-inbox fa-3x text-muted mb-3"></i><p class="text-muted">No items data available</p></div>';
        }
    }

    // Monthly Sales Chart - from monthly.php
    if (data.monthlySales && data.monthlySales.length > 0) {
        const monthlySales = data.monthlySales;
        
        const ctx2 = document.getElementById('monthlySalesChart');
        if (ctx2) {
            new Chart(ctx2, {
                type: 'line',
                data: {
                    labels: monthlySales.map(item => item.month_name || item.month),
                    datasets: [{
                        label: 'Revenue',
                        data: monthlySales.map(item => parseFloat(item.revenue)),
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 3,
                        pointBackgroundColor: '#667eea',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return 'Rs. ' + value.toLocaleString();
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
    } else {
        // Show no data message for sales chart
        const ctx2 = document.getElementById('monthlySalesChart');
        if (ctx2) {
            const parentDiv = ctx2.parentElement;
            parentDiv.innerHTML = '<div class="text-center p-4"><i class="fas fa-chart-line fa-3x text-muted mb-3"></i><p class="text-muted">No sales data available</p></div>';
        }
    }
}