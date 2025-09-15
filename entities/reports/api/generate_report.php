<?php
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/functions.php';

header('Content-Type: text/html');

$pdo = require __DIR__ . '/../../../includes/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div class="alert alert-danger">Invalid request method</div>';
    exit;
}

$reportType = $_POST['type'] ?? '';
$reportMonth = $_POST['month'] ?? '';
$rawMaterialValue = floatval($_POST['raw_material_value'] ?? 0);

if ($rawMaterialValue < 0) {
    echo '<div class="alert alert-danger">Invalid raw material value</div>';
    exit;
}

// Note: Using centralized financial calculation functions from includes/functions.php

try {
    // Determine date range based on report type
    if ($reportType === 'today') {
        $startDate = date('Y-m-01'); // First day of current month
        $endDate = date('Y-m-d'); // Today
        $reportLabel = 'As of ' . format_datetime(date('Y-m-d'), get_user_timezone(), 'F d, Y');
        $reportMonth = null; // Don't store this report
    } elseif ($reportType === 'monthly' && $reportMonth) {
        // Check if the selected month is the current month
        $currentMonth = date('Y-m');
        if ($reportMonth === $currentMonth) {
            echo '<div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Cannot generate Monthly P&L for current month</strong><br>
                Monthly P&L reports can only be generated for completed months. 
                For the current month (' . format_datetime(date('Y-m-01'), get_user_timezone(), 'F Y') . '), please use "As of Today" option instead.
                <br><small class="text-muted">Monthly P&L for ' . format_datetime(date('Y-m-01'), get_user_timezone(), 'F Y') . ' will be available from ' . format_datetime(date('Y-m-01', strtotime('first day of next month')), get_user_timezone(), 'F j, Y') . ' onwards.</small>
            </div>';
            exit;
        }
        
        $startDate = $reportMonth . '-01';
        $endDate = date('Y-m-t', strtotime($startDate)); // Last day of month
        $reportLabel = format_datetime($startDate, get_user_timezone(), 'F Y');
    } else {
        echo '<div class="alert alert-danger">Invalid report parameters</div>';
        exit;
    }

    // Use centralized financial calculation functions
    $financial_data = get_financial_summary($pdo, $startDate, $endDate, $rawMaterialValue);
    
    // Extract individual values for backward compatibility
    $salesRevenue = $financial_data['revenue'];
    $purchases = $financial_data['purchases'];
    $cogs = $financial_data['cogs'];
    $excessStockValue = $financial_data['excess_stock_value'];
    $excessStock45Percent = $financial_data['excess_stock_45_percent'];
    $grossProfit = $financial_data['gross_profit'];
    $expenses = $financial_data['operating_expenses'];
    $netProfit = $financial_data['net_profit'];

    // Store monthly report in database if it's a monthly report
    $reportId = null;
    if ($reportType === 'monthly' && $reportMonth) {
        // Check if report already exists for this month
        $stmt = $pdo->prepare("SELECT id FROM profit_loss WHERE report_month = ?");
        $stmt->execute([$reportMonth]);
        $existingReport = $stmt->fetch();

        if ($existingReport) {
            // Update existing report
            $stmt = $pdo->prepare("
                UPDATE profit_loss SET 
                    report_date = ?, sales_revenue = ?, purchases = ?, expenses = ?,
                    raw_material_stock = ?, excess_stock_value = ?, cogs = ?,
                    gross_profit = ?, net_profit = ?, updated_at = NOW()
                WHERE report_month = ?
            ");
            $stmt->execute([
                $endDate, $salesRevenue, $purchases, $expenses,
                $rawMaterialValue, $excessStockValue, $cogs,
                $grossProfit, $netProfit, $reportMonth
            ]);
            $reportId = $existingReport['id'];
        } else {
            // Insert new report
            $stmt = $pdo->prepare("
                INSERT INTO profit_loss (
                    report_month, report_date, sales_revenue, purchases, expenses,
                    raw_material_stock, excess_stock_value, cogs, gross_profit, net_profit, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $reportMonth, $endDate, $salesRevenue, $purchases, $expenses,
                $rawMaterialValue, $excessStockValue, $cogs, $grossProfit, $netProfit, $_SESSION['user_id']
            ]);
            $reportId = $pdo->lastInsertId();
        }
    }

} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error generating report: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

?>

<div class="profit-loss-report" style="font-family: 'Segoe UI', Arial, sans-serif; max-width: 800px; margin: 0 auto;">
    <!-- Header -->
    <div class="report-header text-center mb-4" style="border-bottom: 2px solid #007bff; padding-bottom: 15px;">
        <h2 class="text-primary mb-1">PROFIT & LOSS STATEMENT</h2>
        <h4 class="text-muted"><?= $reportLabel ?></h4>
        <p class="mb-0 text-muted">Generated on: <?= format_datetime(date('Y-m-d H:i:s'), get_user_timezone(), 'M d, Y g:i A') ?></p>
        <?php if ($reportType === 'today'): ?>
        <small class="text-warning"><i class="fas fa-info-circle me-1"></i>This is a current snapshot and not stored</small>
        <?php endif; ?>
    </div>

    <!-- Financial Summary -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-success">GROSS PROFIT</h6>
                    <h3 class="<?= $grossProfit >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= format_currency($grossProfit) ?>
                    </h3>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="text-primary">NET PROFIT</h6>
                    <h3 class="<?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= format_currency($netProfit) ?>
                    </h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Breakdown -->
    <div class="table-responsive">
        <table class="table table-bordered">
            <tbody>
                <tr class="table-light">
                    <td colspan="2"><strong>REVENUE</strong></td>
                </tr>
                <tr>
                    <td>Sales Revenue (Delivered Orders)</td>
                    <td class="text-end text-success"><strong><?= format_currency($salesRevenue) ?></strong></td>
                </tr>
                
                <tr class="table-light">
                    <td colspan="2"><strong>COST OF GOODS SOLD</strong></td>
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
                <tr>
                    <td><strong>Total COGS</strong></td>
                    <td class="text-end text-danger"><strong><?= format_currency($cogs) ?></strong></td>
                </tr>
                
                <tr class="table-success">
                    <td><strong>GROSS PROFIT (Revenue - COGS)</strong></td>
                    <td class="text-end <?= $grossProfit >= 0 ? 'text-success' : 'text-danger' ?>">
                        <strong><?= format_currency($grossProfit) ?></strong>
                    </td>
                </tr>
                
                <tr class="table-light">
                    <td colspan="2"><strong>OPERATING EXPENSES</strong></td>
                </tr>
                <tr>
                    <td>Total Expenses</td>
                    <td class="text-end text-warning"><?= format_currency($expenses) ?></td>
                </tr>
                
                <tr class="table-primary">
                    <td><strong>NET PROFIT (Gross Profit - Expenses)</strong></td>
                    <td class="text-end <?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?>">
                        <strong><?= format_currency($netProfit) ?></strong>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

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
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted">GROSS MARGIN</h6>
                    <h4 class="<?= $grossProfit >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= $salesRevenue > 0 ? number_format(($grossProfit / $salesRevenue) * 100, 2) : 0 ?>%
                    </h4>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted">NET MARGIN</h6>
                    <h4 class="<?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= $salesRevenue > 0 ? number_format(($netProfit / $salesRevenue) * 100, 2) : 0 ?>%
                    </h4>
                </div>
            </div>
        </div>
    </div>

    <?php if ($reportType === 'monthly' && $reportId): ?>
    <div class="alert alert-success mt-3">
        <i class="fas fa-check-circle me-2"></i>Report saved successfully! Report ID: #<?= $reportId ?>
    </div>
    <?php endif; ?>
</div>

<style>
@media print {
    .modal-header, .modal-footer {
        display: none !important;
    }
    .profit-loss-report {
        width: 100% !important;
        max-width: none !important;
    }
}
</style>