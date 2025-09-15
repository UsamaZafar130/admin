<?php
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/functions.php';

header('Content-Type: text/html');

$pdo = require __DIR__ . '/../../../includes/db_connection.php';

if (!isset($_GET['id'])) {
    echo '<div class="alert alert-danger">Report ID not provided</div>';
    exit;
}

$reportId = intval($_GET['id']);

// Get report data
$stmt = $pdo->prepare("SELECT * FROM profit_loss WHERE id = ?");
$stmt->execute([$reportId]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    echo '<div class="alert alert-danger">Report not found</div>';
    exit;
}
?>

<div class="profit-loss-report" style="font-family: 'Segoe UI', Arial, sans-serif; max-width: 800px; margin: 0 auto;">
    <!-- Header -->
    <div class="report-header text-center mb-4" style="border-bottom: 2px solid #007bff; padding-bottom: 15px;">
        <h2 class="text-primary mb-1">PROFIT & LOSS STATEMENT</h2>
        <h4 class="text-muted"><?= date('F Y', strtotime($report['report_month'] . '-01')) ?></h4>
        <p class="mb-0 text-muted">Report Date: <?= date('M d, Y', strtotime($report['report_date'])) ?></p>
    </div>

    <!-- Financial Summary -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-success">GROSS PROFIT</h6>
                    <h3 class="<?= $report['gross_profit'] >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= format_currency($report['gross_profit']) ?>
                    </h3>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="text-primary">NET PROFIT</h6>
                    <h3 class="<?= $report['net_profit'] >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= format_currency($report['net_profit']) ?>
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
                    <td class="text-end text-success"><strong><?= format_currency($report['sales_revenue']) ?></strong></td>
                </tr>
                
                <tr class="table-light">
                    <td colspan="2"><strong>COST OF GOODS SOLD</strong></td>
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
                <tr>
                    <td><strong>Total COGS</strong></td>
                    <td class="text-end text-danger"><strong><?= format_currency($report['cogs']) ?></strong></td>
                </tr>
                
                <tr class="table-success">
                    <td><strong>GROSS PROFIT (Revenue - COGS)</strong></td>
                    <td class="text-end <?= $report['gross_profit'] >= 0 ? 'text-success' : 'text-danger' ?>">
                        <strong><?= format_currency($report['gross_profit']) ?></strong>
                    </td>
                </tr>
                
                <tr class="table-light">
                    <td colspan="2"><strong>OPERATING EXPENSES</strong></td>
                </tr>
                <tr>
                    <td>Total Expenses</td>
                    <td class="text-end text-warning"><?= format_currency($report['expenses']) ?></td>
                </tr>
                
                <tr class="table-primary">
                    <td><strong>NET PROFIT (Gross Profit - Expenses)</strong></td>
                    <td class="text-end <?= $report['net_profit'] >= 0 ? 'text-success' : 'text-danger' ?>">
                        <strong><?= format_currency($report['net_profit']) ?></strong>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Additional Information -->
    <?php if (!empty($report['notes'])): ?>
    <div class="alert alert-info">
        <strong>Notes:</strong><br>
        <?= nl2br(htmlspecialchars($report['notes'])) ?>
    </div>
    <?php endif; ?>

    <!-- Profit Margins -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted">GROSS MARGIN</h6>
                    <h4 class="<?= $report['gross_profit'] >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= $report['sales_revenue'] > 0 ? number_format(($report['gross_profit'] / $report['sales_revenue']) * 100, 2) : 0 ?>%
                    </h4>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted">NET MARGIN</h6>
                    <h4 class="<?= $report['net_profit'] >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= $report['sales_revenue'] > 0 ? number_format(($report['net_profit'] / $report['sales_revenue']) * 100, 2) : 0 ?>%
                    </h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="report-footer mt-4 pt-3" style="border-top: 1px solid #dee2e6;">
        <div class="row">
            <div class="col-md-6">
                <small class="text-muted">
                    Generated on: <?= date('M d, Y g:i A', strtotime($report['created_at'])) ?>
                </small>
            </div>
            <div class="col-md-6 text-end">
                <small class="text-muted">
                    Report ID: #<?= $report['id'] ?>
                </small>
            </div>
        </div>
    </div>
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