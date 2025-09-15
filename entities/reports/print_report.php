<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = require __DIR__ . '/../../includes/db_connection.php';

if (!isset($_GET['id'])) {
    die('Report ID not provided');
}

$reportId = intval($_GET['id']);

// Get report data
$stmt = $pdo->prepare("SELECT * FROM profit_loss WHERE id = ?");
$stmt->execute([$reportId]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    die('Report not found');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profit & Loss Report - <?= format_datetime($report['report_month'] . '-01', get_user_timezone(), 'F Y') ?></title>
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
<body>
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
            <h3 class="text-muted mb-1"><?= format_datetime($report['report_month'] . '-01', get_user_timezone(), 'F Y') ?></h3>
            <p class="mb-0 text-muted">Report Date: <?= format_datetime($report['report_date'], get_user_timezone(), 'M d, Y') ?></p>
        </div>

        <!-- Key Metrics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center border-success">
                    <div class="card-body">
                        <h6 class="text-success">REVENUE</h6>
                        <h4 class="text-success"><?= format_currency($report['sales_revenue']) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center border-warning">
                    <div class="card-body">
                        <h6 class="text-warning">GROSS PROFIT</h6>
                        <h4 class="<?= $report['gross_profit'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= format_currency($report['gross_profit']) ?>
                        </h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center border-primary">
                    <div class="card-body">
                        <h6 class="text-primary">NET PROFIT</h6>
                        <h4 class="<?= $report['net_profit'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= format_currency($report['net_profit']) ?>
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
                    <td class="text-end"><strong><?= format_currency($report['sales_revenue']) ?></strong></td>
                </tr>
                
                <tr class="table-warning">
                    <td colspan="2" class="text-center"><strong>COST OF GOODS SOLD</strong></td>
                </tr>
                <tr>
                    <td>Purchases</td>
                    <td class="text-end"><?= format_currency($report['purchases']) ?></td>
                </tr>
                <tr>
                    <td>Raw Material Stock</td>
                    <td class="text-end"><?= format_currency($report['raw_material_stock']) ?></td>
                </tr>
                <tr>
                    <td>Excess Stock Value (45% applied)</td>
                    <td class="text-end">
                        <?php 
                        $excess_45_percent = $report['excess_stock_value'] * 0.45;
                        echo format_currency($excess_45_percent);
                        ?>
                        <small class="text-muted d-block">Total: <?= format_currency($report['excess_stock_value']) ?></small>
                    </td>
                </tr>
                <tr class="table-light">
                    <td><strong>Total COGS</strong></td>
                    <td class="text-end"><strong><?= format_currency($report['cogs']) ?></strong></td>
                </tr>
                
                <tr class="table-success">
                    <td><strong>GROSS PROFIT (Revenue - COGS)</strong></td>
                    <td class="text-end <?= $report['gross_profit'] >= 0 ? 'text-success' : 'text-danger' ?>">
                        <strong><?= format_currency($report['gross_profit']) ?></strong>
                    </td>
                </tr>
                
                <tr class="table-info">
                    <td colspan="2" class="text-center"><strong>OPERATING EXPENSES</strong></td>
                </tr>
                <tr>
                    <td>Total Expenses</td>
                    <td class="text-end"><?= format_currency($report['expenses']) ?></td>
                </tr>
                
                <tr class="table-dark">
                    <td><strong>NET PROFIT (Gross Profit - Expenses)</strong></td>
                    <td class="text-end <?= $report['net_profit'] >= 0 ? 'text-success' : 'text-danger' ?>">
                        <strong><?= format_currency($report['net_profit']) ?></strong>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Profit Margins -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="text-center p-3 border rounded">
                    <h6 class="text-muted">GROSS MARGIN</h6>
                    <h4 class="<?= $report['gross_profit'] >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= $report['sales_revenue'] > 0 ? number_format(($report['gross_profit'] / $report['sales_revenue']) * 100, 2) : 0 ?>%
                    </h4>
                </div>
            </div>
            <div class="col-md-6">
                <div class="text-center p-3 border rounded">
                    <h6 class="text-muted">NET MARGIN</h6>
                    <h4 class="<?= $report['net_profit'] >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= $report['sales_revenue'] > 0 ? number_format(($report['net_profit'] / $report['sales_revenue']) * 100, 2) : 0 ?>%
                    </h4>
                </div>
            </div>
        </div>

        <?php if (!empty($report['notes'])): ?>
        <div class="mt-4">
            <h6>Notes:</h6>
            <p class="border p-3 rounded bg-light"><?= nl2br(htmlspecialchars($report['notes'])) ?></p>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="mt-5 pt-3 border-top text-center">
            <div class="row">
                <div class="col-md-6 text-start">
                    <small class="text-muted">Generated: <?= date('M d, Y g:i A', strtotime($report['created_at'])) ?></small>
                </div>
                <div class="col-md-6 text-end">
                    <small class="text-muted">Report ID: #<?= $report['id'] ?></small>
                </div>
            </div>
        </div>
    </div>

    <script>
        function shareOnWhatsApp() {
            const reportData = {
                month: '<?= date('F Y', strtotime($report['report_month'] . '-01')) ?>',
                revenue: '<?= format_currency($report['sales_revenue']) ?>',
                grossProfit: '<?= format_currency($report['gross_profit']) ?>',
                netProfit: '<?= format_currency($report['net_profit']) ?>',
                grossMargin: '<?= $report['sales_revenue'] > 0 ? number_format(($report['gross_profit'] / $report['sales_revenue']) * 100, 2) : 0 ?>%',
                netMargin: '<?= $report['sales_revenue'] > 0 ? number_format(($report['net_profit'] / $report['sales_revenue']) * 100, 2) : 0 ?>%',
                cogs: '<?= format_currency($report['cogs']) ?>',
                expenses: '<?= format_currency($report['expenses']) ?>'
            };
            
            const profitIcon = parseFloat('<?= $report['net_profit'] ?>') >= 0 ? '📈' : '📉';
            const grossIcon = parseFloat('<?= $report['gross_profit'] ?>') >= 0 ? '💚' : '❌';
            
            const message = `🏢 *PROFIT & LOSS STATEMENT*\n` +
                          `📅 *Month:* ${reportData.month}\n` +
                          `⏰ *Generated:* <?= date('M d, Y g:i A') ?>\n\n` +
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