<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: text/html');

$pdo = require __DIR__ . '/../../includes/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request method');
}

$reportType = $_POST['type'] ?? '';
$reportMonth = $_POST['month'] ?? '';
$rawMaterialValue = floatval($_POST['raw_material_value'] ?? 0);

if ($rawMaterialValue < 0) {
    die('Invalid raw material value');
}

try {
    // Determine date range based on report type
    if ($reportType === 'today') {
        $startDate = date('Y-m-01'); // First day of current month
        $endDate = date('Y-m-d'); // Today
        $reportLabel = 'As of ' . format_datetime(date('Y-m-d'), get_user_timezone(), 'F d, Y');
    } elseif ($reportType === 'monthly' && $reportMonth) {
        // Check if the selected month is the current month
        $currentMonth = date('Y-m');
        if ($reportMonth === $currentMonth) {
            die('Cannot generate Monthly P&L for current month. Please use "As of Today" option instead.');
        }
        
        $startDate = $reportMonth . '-01';
        $endDate = date('Y-m-t', strtotime($startDate)); // Last day of month
        $reportLabel = format_datetime($startDate, get_user_timezone(), 'F Y');
    } else {
        die('Invalid report parameters');
    }

    // Use centralized financial calculation functions
    $financial_data = get_financial_summary($pdo, $startDate, $endDate, $rawMaterialValue);
    
    // Extract individual values for display
    $salesRevenue = $financial_data['revenue'];
    $purchases = $financial_data['purchases'];
    $cogs = $financial_data['cogs'];
    $excessStockValue = $financial_data['excess_stock_value'];
    $excessStock45Percent = $financial_data['excess_stock_45_percent'];
    $grossProfit = $financial_data['gross_profit'];
    $expenses = $financial_data['operating_expenses'];
    $netProfit = $financial_data['net_profit'];

} catch (Exception $e) {
    die('Error generating report: ' . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profit & Loss Report - <?= $reportLabel ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: white;
            margin: 0;
            padding: 20px;
        }
        
        .print-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .no-print {
            margin-bottom: 20px;
            text-align: center;
        }
        
        @media print {
            body {
                padding: 0;
            }
            .print-container {
                box-shadow: none;
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
        
        .report-header {
            text-align: center;
            border-bottom: 3px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .logo {
            max-height: 60px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body onload="window.print()">
    <div class="print-container">
        <!-- No Print Controls -->
        <div class="no-print">
            <button class="btn btn-primary me-2" onclick="window.print()">
                <i class="fas fa-print me-1"></i>Print Report
            </button>
            <button class="btn btn-success me-2" onclick="shareOnWhatsApp()">
                <i class="fab fa-whatsapp me-1"></i>Share on WhatsApp
            </button>
            <button class="btn btn-secondary" onclick="window.close()">
                <i class="fas fa-times me-1"></i>Close
            </button>
        </div>

        <!-- Report Header -->
        <div class="report-header">
            <img src="/assets/img/logo.png" alt="Company Logo" class="logo">
            <h1 class="text-primary mb-2">PROFIT & LOSS STATEMENT</h1>
            <h3 class="text-muted mb-1"><?= $reportLabel ?></h3>
            <p class="mb-0 text-muted">Generated on: <?= format_datetime(date('Y-m-d H:i:s'), get_user_timezone(), 'M d, Y g:i A') ?></p>
            <?php if ($reportType === 'today'): ?>
            <small class="text-warning"><i class="fas fa-info-circle me-1"></i>This is a current snapshot and not stored</small>
            <?php endif; ?>
        </div>

        <!-- Key Metrics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center border-success">
                    <div class="card-body">
                        <h6 class="text-success">REVENUE</h6>
                        <h4 class="text-success"><?= format_currency($salesRevenue) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center border-warning">
                    <div class="card-body">
                        <h6 class="text-warning">GROSS PROFIT</h6>
                        <h4 class="<?= $grossProfit >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= format_currency($grossProfit) ?>
                        </h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center border-primary">
                    <div class="card-body">
                        <h6 class="text-primary">NET PROFIT</h6>
                        <h4 class="<?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= format_currency($netProfit) ?>
                        </h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Statement -->
        <table class="table table-bordered">
            <tbody>
                <tr class="table-primary">
                    <td colspan="2" class="text-center"><strong>REVENUE</strong></td>
                </tr>
                <tr>
                    <td>Sales Revenue (Delivered Orders)</td>
                    <td class="text-end"><strong><?= format_currency($salesRevenue) ?></strong></td>
                </tr>
                
                <tr class="table-warning">
                    <td colspan="2" class="text-center"><strong>COST OF GOODS SOLD</strong></td>
                </tr>
                <tr>
                    <td>Purchases</td>
                    <td class="text-end"><?= format_currency($purchases) ?></td>
                </tr>
                <tr>
                    <td>Raw Material Stock</td>
                    <td class="text-end"><?= format_currency($rawMaterialValue) ?></td>
                </tr>
                <tr>
                    <td>Excess Stock Value (45% applied)</td>
                    <td class="text-end">
                        <?= format_currency($excessStock45Percent) ?>
                        <small class="text-muted d-block">Total: <?= format_currency($excessStockValue) ?></small>
                    </td>
                </tr>
                <tr class="table-light">
                    <td><strong>Total COGS</strong></td>
                    <td class="text-end"><strong><?= format_currency($cogs) ?></strong></td>
                </tr>
                
                <tr class="table-success">
                    <td><strong>GROSS PROFIT (Revenue - COGS)</strong></td>
                    <td class="text-end <?= $grossProfit >= 0 ? 'text-success' : 'text-danger' ?>">
                        <strong><?= format_currency($grossProfit) ?></strong>
                    </td>
                </tr>
                
                <tr class="table-info">
                    <td colspan="2" class="text-center"><strong>OPERATING EXPENSES</strong></td>
                </tr>
                <tr>
                    <td>Total Expenses</td>
                    <td class="text-end"><?= format_currency($expenses) ?></td>
                </tr>
                
                <tr class="table-dark">
                    <td><strong>NET PROFIT (Gross Profit - Expenses)</strong></td>
                    <td class="text-end <?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?>">
                        <strong><?= format_currency($netProfit) ?></strong>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Calculation Details -->
        <div class="alert alert-light">
            <h6><i class="fas fa-info-circle me-2"></i>Calculation Details:</h6>
            <ul class="mb-0">
                <li><strong>Date Range:</strong> <?= format_datetime($startDate, get_user_timezone(), 'M d, Y') ?> to <?= format_datetime($endDate, get_user_timezone(), 'M d, Y') ?></li>
                <li><strong>Revenue:</strong> Sum of grand_total from delivered orders (delivered_at within period)</li>
                <li><strong>COGS:</strong> Purchases - Raw Material Stock</li>
                <li><strong>Purchases:</strong> Sum of purchase amounts (created_at within period)</li>
                <li><strong>Raw Material:</strong> User-provided current stock value (deducted from purchases)</li>
                <li><strong>Excess Stock:</strong> Only 45% of total excess stock value is applied to gross profit</li>
                <li><strong>Gross Profit:</strong> Revenue - COGS + 45% of Excess Stock Value</li>
                <li><strong>Operating Expenses:</strong> Sum of expense amounts (created_at within period)</li>
                <li><strong>Net Profit:</strong> Gross Profit - Operating Expenses</li>
            </ul>
        </div>

        <!-- Profit Margins -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="text-center p-3 border rounded">
                    <h6 class="text-muted">GROSS MARGIN</h6>
                    <h4 class="<?= $grossProfit >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= $salesRevenue > 0 ? number_format(($grossProfit / $salesRevenue) * 100, 2) : 0 ?>%
                    </h4>
                </div>
            </div>
            <div class="col-md-6">
                <div class="text-center p-3 border rounded">
                    <h6 class="text-muted">NET MARGIN</h6>
                    <h4 class="<?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= $salesRevenue > 0 ? number_format(($netProfit / $salesRevenue) * 100, 2) : 0 ?>%
                    </h4>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-5 pt-3 border-top text-center">
            <div class="row">
                <div class="col-md-6 text-start">
                    <small class="text-muted">Generated: <?= format_datetime(date('Y-m-d H:i:s'), get_user_timezone(), 'M d, Y g:i A') ?></small>
                </div>
                <div class="col-md-6 text-end">
                    <small class="text-muted"><?= $reportType === 'today' ? 'Current Snapshot' : 'Monthly Report' ?></small>
                </div>
            </div>
        </div>
    </div>

    <script>
        function shareOnWhatsApp() {
            const reportData = {
                label: '<?= $reportLabel ?>',
                revenue: '<?= format_currency($salesRevenue) ?>',
                grossProfit: '<?= format_currency($grossProfit) ?>',
                netProfit: '<?= format_currency($netProfit) ?>',
                grossMargin: '<?= $salesRevenue > 0 ? number_format(($grossProfit / $salesRevenue) * 100, 2) : 0 ?>%',
                netMargin: '<?= $salesRevenue > 0 ? number_format(($netProfit / $salesRevenue) * 100, 2) : 0 ?>%',
                cogs: '<?= format_currency($cogs) ?>',
                expenses: '<?= format_currency($expenses) ?>'
            };
            
            const profitIcon = parseFloat('<?= $netProfit ?>') >= 0 ? '📈' : '📉';
            const grossIcon = parseFloat('<?= $grossProfit ?>') >= 0 ? '💚' : '❌';
            
            const message = `🏢 *PROFIT & LOSS STATEMENT*\n` +
                          `📅 *Period:* ${reportData.label}\n` +
                          `⏰ *Generated:* <?= format_datetime(date('Y-m-d H:i:s'), get_user_timezone(), 'M d, Y g:i A') ?>\n\n` +
                          `┌─────────────────────────┐\n` +
                          `│        📊 SUMMARY        │\n` +
                          `└─────────────────────────┘\n` +
                          `💰 *Revenue:* ${reportData.revenue}\n` +
                          `${grossIcon} *Gross Profit:* ${reportData.grossProfit} (${reportData.grossMargin})\n` +
                          `${profitIcon} *Net Profit:* ${reportData.netProfit} (${reportData.netMargin})\n\n` +
                          `┌─────────────────────────┐\n` +
                          `│       📋 BREAKDOWN       │\n` +
                          `└─────────────────────────┘\n` +
                          `🏭 *Cost of Goods Sold:* ${reportData.cogs}\n` +
                          `💸 *Operating Expenses:* ${reportData.expenses}\n\n` +
                          `📈 *Gross Margin:* ${reportData.grossMargin}\n` +
                          `📊 *Net Margin:* ${reportData.netMargin}\n\n` +
                          `_Generated via Admin System_`;
            
            const url = 'https://wa.me/?text=' + encodeURIComponent(message);
            window.open(url, '_blank');
        }
    </script>
</body>
</html>