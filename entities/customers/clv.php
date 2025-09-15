<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Customer Lifetime Value (CLV)";
require_once __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

// Get customer lifetime value data
$clvData = [];
$totalCLV = 0;
$avgCLV = 0;
$totalCustomers = 0;

try {
    if ($pdo) {
        // Calculate CLV using customer sales data (sales_orders, order_items, order_payments)
        $stmt = $pdo->prepare("
            WITH CustomerSalesAnalytics AS (
                SELECT 
                    c.id,
                    c.name,
                    c.contact,
                    c.created_at as customer_since,
                    DATEDIFF(NOW(), c.created_at) as customer_age_days,
                    COUNT(DISTINCT so.id) as total_orders,
                    COALESCE(SUM(so.grand_total), 0) as total_revenue,
                    COALESCE(AVG(so.grand_total), 0) as avg_order_value,
                    MAX(so.order_date) as last_order_date,
                    MIN(so.order_date) as first_order_date,
                    CASE 
                        WHEN COUNT(DISTINCT so.id) > 1 AND MIN(so.order_date) != MAX(so.order_date)
                        THEN DATEDIFF(MAX(so.order_date), MIN(so.order_date)) / (COUNT(DISTINCT so.id) - 1)
                        ELSE 30 
                    END as avg_order_interval_days,
                    DATEDIFF(NOW(), MAX(so.order_date)) as days_since_last_order,
                    COALESCE(SUM(op.amount), 0) as total_payments_received
                FROM customers c
                LEFT JOIN sales_orders so ON c.id = so.customer_id AND so.cancelled = 0 AND so.delivered = 1
                LEFT JOIN order_payments op ON so.id = op.order_id
                WHERE c.deleted_at IS NULL
                GROUP BY c.id, c.name, c.contact, c.created_at
            ),
            CLVCalculation AS (
                SELECT *,
                    ROUND(365.0 / GREATEST(avg_order_interval_days, 1), 2) as estimated_orders_per_year,
                    CASE 
                        WHEN total_orders = 0 THEN 0
                        WHEN total_orders = 1 THEN total_revenue * 0.5
                        WHEN days_since_last_order > (avg_order_interval_days * 3) THEN total_revenue * 0.8
                        ELSE total_revenue + (avg_order_value * (365.0 / GREATEST(avg_order_interval_days, 1)) * 2)
                    END as estimated_clv,
                    CASE 
                        WHEN total_orders = 0 THEN 'No Purchase'
                        WHEN total_orders = 1 THEN 'One-time'
                        WHEN days_since_last_order > (avg_order_interval_days * 3) THEN 'Churned'
                        WHEN estimated_orders_per_year >= 12 THEN 'High Frequency'
                        WHEN estimated_orders_per_year >= 4 THEN 'Medium Frequency'
                        ELSE 'Low Frequency'
                    END as customer_status,
                    CASE 
                        WHEN total_revenue >= 15000 THEN 'Platinum'
                        WHEN total_revenue >= 9000 THEN 'Gold'
                        WHEN total_revenue >= 6000 THEN 'Silver'
                        WHEN total_revenue >= 3000 THEN 'Bronze'
                        ELSE 'Basic'
                    END as value_tier
                FROM CustomerSalesAnalytics
            )
            SELECT *
            FROM CLVCalculation
            ORDER BY estimated_clv DESC
        ");
        $stmt->execute();
        $clvData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalCustomers = count($clvData);
        $totalCLV = array_sum(array_column($clvData, 'estimated_clv'));
        $avgCLV = $totalCustomers > 0 ? $totalCLV / $totalCustomers : 0;
    }
} catch (Exception $e) {
    error_log('Customer lifetime value error: ' . $e->getMessage());
}

// Calculate tier statistics
$tierStats = [];
foreach ($clvData as $customer) {
    $tier = $customer['value_tier'];
    if (!isset($tierStats[$tier])) {
        $tierStats[$tier] = ['count' => 0, 'clv' => 0];
    }
    $tierStats[$tier]['count']++;
    $tierStats[$tier]['clv'] += $customer['estimated_clv'];
}
?>

<div class="page-content-wrapper">
    <div class="container mt-3">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="text-primary"><i class="fas fa-chart-pie me-2"></i> Customer Lifetime Value (CLV)</h2>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-success btn-3d" onclick="exportData()">
                    <i class="fas fa-download me-1"></i> Export
                </button>
            </div>
        </div>

        <div class="alert alert-info max-width-700 mb-4">
            <strong>Customer Lifetime Value Analysis.</strong> CLV is calculated based on historical <strong>sales orders</strong> data and projected future value using purchase frequency, average order value, and customer behavior patterns.<br>
            Only delivered orders are considered. Formula: (Historical Revenue + Projected Future Orders × Average Order Value) adjusted for customer status and recency.
        </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total CLV</h5>
                    <h3><?= format_currency($totalCLV) ?></h3>
                    <small>All customers combined</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Average CLV</h5>
                    <h3><?= format_currency($avgCLV) ?></h3>
                    <small>Per customer</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">High Value Customers</h5>
                    <h3><?= count(array_filter($clvData, function($c) { return $c['estimated_clv'] > 5000; })) ?></h3>
                    <small>CLV > Rs. 5,000</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">At Risk Customers</h5>
                    <h3><?= count(array_filter($clvData, function($c) { return $c['customer_status'] == 'Churned'; })) ?></h3>
                    <small>Likely to churn</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>CLV Distribution by Tier</h5>
                </div>
                <div class="card-body">
                    <canvas id="tierChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Customer Status Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tier Summary -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Value Tier Summary</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Tier</th>
                            <th>Customers</th>
                            <th>% of Total</th>
                            <th>Total CLV</th>
                            <th>Avg CLV</th>
                            <th>% of Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $tierOrder = ['Platinum', 'Gold', 'Silver', 'Bronze', 'Basic'];
                        foreach ($tierOrder as $tier): 
                            if (!isset($tierStats[$tier])) continue;
                            $stats = $tierStats[$tier];
                        ?>
                        <tr>
                            <td><strong><?= h($tier) ?></strong></td>
                            <td><?= number_format($stats['count']) ?></td>
                            <td><?= number_format(($stats['count'] / max(1, $totalCustomers)) * 100, 1) ?>%</td>
                            <td><?= format_currency($stats['clv']) ?></td>
                            <td><?= format_currency($stats['count'] > 0 ? $stats['clv'] / $stats['count'] : 0) ?></td>
                            <td><?= number_format(($stats['clv'] / max(1, $totalCLV)) * 100, 1) ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Customer Details Table -->
    <div class="card">
        <div class="card-header">
            <h5>Customer Lifetime Value Details</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="clvTable" class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Tier</th>
                            <th>Status</th>
                            <th>Estimated CLV</th>
                            <th>Total Spent</th>
                            <th>Orders</th>
                            <th>Avg Order</th>
                            <th>Order Frequency</th>
                            <th>Customer Age</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clvData as $customer): ?>
                        <?php 
                            $tierClass = match($customer['value_tier']) {
                                'Platinum' => 'dark',
                                'Gold' => 'warning',
                                'Silver' => 'secondary',
                                'Bronze' => 'info',
                                default => 'light'
                            };
                            
                            $statusClass = match($customer['customer_status']) {
                                'High Frequency' => 'success',
                                'Medium Frequency' => 'primary',
                                'Low Frequency' => 'info',
                                'One-time' => 'warning',
                                'Churned' => 'danger',
                                default => 'secondary'
                            };
                        ?>
                        <tr>
                            <td>
                                <strong><?= h($customer['name']) ?></strong><br>
                                <small class="text-muted"><?= h($customer['contact']) ?></small>
                            </td>
                            <td><span class="badge bg-<?= $tierClass ?>"><?= h($customer['value_tier']) ?></span></td>
                            <td><span class="badge bg-<?= $statusClass ?>"><?= h($customer['customer_status']) ?></span></td>
                            <td><strong><?= format_currency($customer['estimated_clv']) ?></strong></td>
                            <td><?= format_currency($customer['total_spent']) ?></td>
                            <td><?= number_format($customer['total_orders']) ?></td>
                            <td><?= format_currency($customer['avg_order_value']) ?></td>
                            <td><?= number_format($customer['estimated_orders_per_year'], 1) ?>/year</td>
                            <td><?= number_format($customer['customer_age_days']) ?> days</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#clvTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[3, 'desc']],
        columnDefs: [
            { targets: [3, 4, 5, 6, 7, 8], className: 'text-end' }
        ]
    });

    initializeCharts();
});

function initializeCharts() {
    const tierStats = <?= json_encode($tierStats) ?>;
    const clvData = <?= json_encode($clvData) ?>;
    
    // Tier distribution chart
    const tierOrder = ['Platinum', 'Gold', 'Silver', 'Bronze', 'Basic'];
    const tierLabels = tierOrder.filter(tier => tierStats[tier]);
    const tierValues = tierLabels.map(tier => tierStats[tier].clv);
    
    new Chart(document.getElementById('tierChart'), {
        type: 'bar',
        data: {
            labels: tierLabels,
            datasets: [{
                label: 'Total CLV',
                data: tierValues,
                backgroundColor: ['#343a40', '#ffc107', '#6c757d', '#17a2b8', '#f8f9fa'],
                borderColor: ['#343a40', '#ffc107', '#6c757d', '#17a2b8', '#f8f9fa'],
                borderWidth: 1
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
                    ticks: {
                        callback: function(value) {
                            return 'Rs. ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    
    // Customer status distribution
    const statusCounts = {};
    clvData.forEach(customer => {
        statusCounts[customer.customer_status] = (statusCounts[customer.customer_status] || 0) + 1;
    });
    
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: Object.keys(statusCounts),
            datasets: [{
                data: Object.values(statusCounts),
                backgroundColor: ['#28a745', '#007bff', '#17a2b8', '#ffc107', '#dc3545', '#6c757d']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

function exportData() {
    const table = document.getElementById('clvTable');
    let csv = [];
    for (let row of table.rows) {
        let csvRow = [];
        for (let cell of row.cells) {
            csvRow.push('"' + cell.innerText.replace(/"/g, '""') + '"');
        }
        csv.push(csvRow.join(','));
    }
    
    const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
    const downloadLink = document.createElement('a');
    downloadLink.download = 'customer_lifetime_value.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.click();
}
</script>

</div> <!-- Close container -->
</div> <!-- Close page-content-wrapper -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>