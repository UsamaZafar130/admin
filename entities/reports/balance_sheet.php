<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Balance Sheet";
require_once __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

// Default raw material stock value
$raw_material_stock = 0;
if (isset($_POST['raw_material_stock'])) {
    $raw_material_stock = floatval($_POST['raw_material_stock']);
}
?>

<div class="page-content-wrapper">
    <div class="container mt-3">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="text-primary"><i class="fas fa-balance-scale me-2"></i>Balance Sheet</h2>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-success btn-3d" data-bs-toggle="modal" data-bs-target="#balanceSheetModal">
                    <i class="fas fa-calculator me-1"></i>Generate Balance Sheet
                </button>
            </div>
        </div>
        
        <div class="alert alert-info max-width-700 mb-4">
            <strong>Balance Sheet Overview</strong><br>
            The balance sheet shows your company's financial position at a specific point in time. It displays assets, liabilities, and net worth.<br>
            <strong>Assets include:</strong> 45% of excess stock value, unpaid purchases (credit), and raw material stock.<br>
            <strong>Liabilities include:</strong> Unpaid expenses (credit).<br>
            This report is generated each time to show current values.
        </div>

        <div id="balanceSheetResults" style="display: none;">
            <!-- Balance sheet results will be loaded here -->
        </div>
    </div>
</div>

<!-- Balance Sheet Modal -->
<div class="modal fade" id="balanceSheetModal" tabindex="-1" aria-labelledby="balanceSheetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="balanceSheetModalLabel">
                    <i class="fas fa-balance-scale me-2"></i>Generate Balance Sheet
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="balanceSheetForm">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Balance Sheet Parameters</strong><br>
                        Enter the current raw material stock value to generate an accurate balance sheet.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label for="raw_material_stock" class="form-label">Raw Material Stock Value (Rs.)</label>
                            <input type="number" class="form-control" id="raw_material_stock" name="raw_material_stock" 
                                   value="<?= h($raw_material_stock) ?>" step="0.01" min="0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-calculator me-1"></i>Generate Balance Sheet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#balanceSheetForm').on('submit', function(e) {
        e.preventDefault();
        
        const rawMaterialStock = $('#raw_material_stock').val();
        
        // Show loading state
        $('#balanceSheetResults').html(`
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Generating balance sheet...</p>
            </div>
        `).show();
        
        // Close modal
        $('#balanceSheetModal').modal('hide');
        
        // Generate balance sheet via AJAX
        $.post('/entities/reports/api/generate_balance_sheet.php', {
            raw_material_stock: rawMaterialStock
        })
        .done(function(response) {
            if (response.success) {
                displayBalanceSheet(response.data, rawMaterialStock);
            } else {
                $('#balanceSheetResults').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error generating balance sheet: ${response.message || 'Unknown error'}
                    </div>
                `);
            }
        })
        .fail(function() {
            $('#balanceSheetResults').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Failed to generate balance sheet. Please try again.
                </div>
            `);
        });
    });
    
    function displayBalanceSheet(data, rawMaterialStock) {
        const formatCurrency = (amount) => {
            return 'Rs. ' + parseFloat(amount).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        };
        
        const html = `
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-balance-scale me-2"></i>Balance Sheet - ${new Date().toLocaleDateString()}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Assets Section -->
                        <div class="col-md-6">
                            <h6 class="text-success mb-3">
                                <i class="fas fa-plus-circle me-2"></i>Assets
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tbody>
                                        <tr>
                                            <td>Excess Stock Value (Total)</td>
                                            <td class="text-end">${formatCurrency(data.assets.excess_stock_value)}</td>
                                        </tr>
                                        <tr class="table-success">
                                            <td><strong>Excess Stock Value (45%)</strong></td>
                                            <td class="text-end"><strong>${formatCurrency(data.assets.excess_stock_45_percent)}</strong></td>
                                        </tr>
                                        <tr>
                                            <td>Unpaid Purchases (Credit)</td>
                                            <td class="text-end">${formatCurrency(data.assets.unpaid_purchases)}</td>
                                        </tr>
                                        <tr>
                                            <td>Raw Material Stock</td>
                                            <td class="text-end">${formatCurrency(data.assets.raw_material_stock)}</td>
                                        </tr>
                                        <tr class="table-primary">
                                            <td><strong>Total Assets</strong></td>
                                            <td class="text-end"><strong>${formatCurrency(data.assets.total_assets)}</strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Liabilities Section -->
                        <div class="col-md-6">
                            <h6 class="text-danger mb-3">
                                <i class="fas fa-minus-circle me-2"></i>Liabilities
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tbody>
                                        <tr>
                                            <td>Unpaid Expenses (Credit)</td>
                                            <td class="text-end">${formatCurrency(data.liabilities.unpaid_expenses)}</td>
                                        </tr>
                                        <tr class="table-danger">
                                            <td><strong>Total Liabilities</strong></td>
                                            <td class="text-end"><strong>${formatCurrency(data.liabilities.total_liabilities)}</strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <h6 class="text-info mt-4 mb-3">
                                <i class="fas fa-chart-line me-2"></i>Net Worth
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tbody>
                                        <tr class="table-info">
                                            <td><strong>Net Worth (Assets - Liabilities)</strong></td>
                                            <td class="text-end"><strong>${formatCurrency(data.net_worth)}</strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Summary Section -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="alert alert-light">
                                <h6><i class="fas fa-info-circle me-2"></i>Balance Sheet Summary</h6>
                                <ul class="mb-0">
                                    <li><strong>Excess Stock Value:</strong> Only 45% (${formatCurrency(data.assets.excess_stock_45_percent)}) is considered as asset from total excess stock of ${formatCurrency(data.assets.excess_stock_value)}</li>
                                    <li><strong>Unpaid Purchases:</strong> Credit purchases not yet paid (${formatCurrency(data.assets.unpaid_purchases)}) are shown as assets</li>
                                    <li><strong>Unpaid Expenses:</strong> Credit expenses not yet paid (${formatCurrency(data.liabilities.unpaid_expenses)}) are shown as liabilities</li>
                                    <li><strong>Raw Material Stock:</strong> User-provided value of ${formatCurrency(data.assets.raw_material_stock)} included in assets</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="row mt-3">
                        <div class="col-12 text-end">
                            <button class="btn btn-outline-primary" onclick="window.print()">
                                <i class="fas fa-print me-1"></i>Print Balance Sheet
                            </button>
                            <button class="btn btn-success" onclick="regenerateBalanceSheet()">
                                <i class="fas fa-sync-alt me-1"></i>Regenerate
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('#balanceSheetResults').html(html).show();
    }
    
    window.regenerateBalanceSheet = function() {
        $('#balanceSheetModal').modal('show');
    };
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>