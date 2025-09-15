<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Customer Segmentation";
require_once __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

// Get customer segmentation data
$segmentData = [];
$totalCustomers = 0;
$totalRevenue = 0;

try {
    if ($pdo) {
        // RFM Analysis - Recency, Frequency, Monetary
        $stmt = $pdo->prepare("
            WITH CustomerMetrics AS (
                SELECT 
                    c.id,
                    c.name,
                    c.contact,
                    c.created_at,
                    COALESCE(COUNT(so.id), 0) as frequency,
                    COALESCE(SUM(so.grand_total), 0) as monetary,
                    COALESCE(MAX(so.order_date), c.created_at) as last_order_date,
                    DATEDIFF(NOW(), COALESCE(MAX(so.order_date), c.created_at)) as recency_days
                FROM customers c
                LEFT JOIN sales_orders so ON c.id = so.customer_id AND so.cancelled = 0 AND so.delivered = 1
                WHERE c.deleted_at IS NULL
                GROUP BY c.id, c.name, c.contact, c.created_at
            ),
            CustomerScores AS (
                SELECT *,
                    CASE 
                        WHEN recency_days <= 30 THEN 5
                        WHEN recency_days <= 60 THEN 4
                        WHEN recency_days <= 90 THEN 3
                        WHEN recency_days <= 180 THEN 2
                        ELSE 1
                    END as recency_score,
                    CASE 
                        WHEN frequency >= 10 THEN 5
                        WHEN frequency >= 6 THEN 4
                        WHEN frequency >= 3 THEN 3
                        WHEN frequency >= 2 THEN 2
                        ELSE 1
                    END as frequency_score,
                    CASE 
                        WHEN monetary >= 50000 THEN 5
                        WHEN monetary >= 20000 THEN 4
                        WHEN monetary >= 10000 THEN 3
                        WHEN monetary >= 5000 THEN 2
                        ELSE 1
                    END as monetary_score
                FROM CustomerMetrics
            )
            SELECT *,
                (recency_score + frequency_score + monetary_score) as rfm_score,
                CASE 
                    WHEN (recency_score + frequency_score + monetary_score) >= 13 THEN 'Champions'
                    WHEN (recency_score + frequency_score + monetary_score) >= 11 THEN 'Loyal Customers'
                    WHEN (recency_score + frequency_score + monetary_score) >= 9 AND recency_score >= 3 THEN 'Potential Loyalists'
                    WHEN (recency_score + frequency_score + monetary_score) >= 8 AND recency_score >= 4 THEN 'New Customers'
                    WHEN (recency_score + frequency_score + monetary_score) >= 7 AND frequency_score <= 2 THEN 'Promising'
                    WHEN (recency_score + frequency_score + monetary_score) >= 6 AND recency_score <= 2 THEN 'Cannot Lose Them'
                    WHEN (recency_score + frequency_score + monetary_score) >= 5 AND recency_score <= 2 THEN 'At Risk'
                    WHEN frequency_score <= 2 AND monetary_score <= 2 THEN 'Lost'
                    ELSE 'Others'
                END as segment
            FROM CustomerScores
            ORDER BY rfm_score DESC, monetary DESC
        ");
        $stmt->execute();
        $segmentData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalCustomers = count($segmentData);
        $totalRevenue = array_sum(array_column($segmentData, 'monetary'));
    }
} catch (Exception $e) {
    error_log('Customer segmentation error: ' . $e->getMessage());
}

// Calculate segment statistics
$segmentStats = [];
foreach ($segmentData as $customer) {
    $segment = $customer['segment'];
    if (!isset($segmentStats[$segment])) {
        $segmentStats[$segment] = [
            'count' => 0,
            'revenue' => 0,
            'avg_order_value' => 0,
            'avg_frequency' => 0,
            'avg_recency' => 0
        ];
    }
    $segmentStats[$segment]['count']++;
    $segmentStats[$segment]['revenue'] += $customer['monetary'];
    $segmentStats[$segment]['avg_order_value'] += $customer['frequency'] > 0 ? $customer['monetary'] / $customer['frequency'] : 0;
    $segmentStats[$segment]['avg_frequency'] += $customer['frequency'];
    $segmentStats[$segment]['avg_recency'] += $customer['recency_days'];
}

// Calculate averages
foreach ($segmentStats as $segment => &$stats) {
    if ($stats['count'] > 0) {
        $stats['avg_order_value'] = $stats['avg_order_value'] / $stats['count'];
        $stats['avg_frequency'] = $stats['avg_frequency'] / $stats['count'];
        $stats['avg_recency'] = $stats['avg_recency'] / $stats['count'];
    }
}
?>

<div class="page-content-wrapper">
    <div class="container mt-3">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="text-primary"><i class="fas fa-users-cog me-2"></i> Customer Segmentation</h2>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-success btn-3d" onclick="exportData()">
                    <i class="fas fa-download me-1"></i> Export
                </button>
            </div>
        </div>

        <div class="alert alert-info max-width-700 mb-4">
            <strong>RFM Customer Segmentation Analysis.</strong> Categorizes customers by Recency (days since last order), Frequency (total orders), and Monetary (total spent).<br>
            Only <strong>delivered orders</strong> considered. Segments: Champions, Loyal, Potential Loyalists, At Risk, Lost.
        </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Customers</h5>
                    <h3><?= number_format($totalCustomers) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Champions</h5>
                    <h3><?= $segmentStats['Champions']['count'] ?? 0 ?></h3>
                    <small>High value customers</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">At Risk</h5>
                    <h3><?= ($segmentStats['At Risk']['count'] ?? 0) + ($segmentStats['Cannot Lose Them']['count'] ?? 0) ?></h3>
                    <small>Need attention</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title">Lost Customers</h5>
                    <h3><?= $segmentStats['Lost']['count'] ?? 0 ?></h3>
                    <small>Re-engagement needed</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Customer Segments Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="segmentChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Revenue by Segment</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Segment Summary Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Segment Summary</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Segment</th>
                            <th>Count</th>
                            <th>% of Customers</th>
                            <th>Total Revenue</th>
                            <th>% of Revenue</th>
                            <th>Avg Order Value</th>
                            <th>Avg Frequency</th>
                            <th>Avg Recency (Days)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($segmentStats as $segment => $stats): ?>
                        <tr>
                            <td><strong><?= h($segment) ?></strong></td>
                            <td><?= number_format($stats['count']) ?></td>
                            <td><?= number_format(($stats['count'] / max(1, $totalCustomers)) * 100, 1) ?>%</td>
                            <td><?= format_currency($stats['revenue']) ?></td>
                            <td><?= number_format(($stats['revenue'] / max(1, $totalRevenue)) * 100, 1) ?>%</td>
                            <td><?= format_currency($stats['avg_order_value']) ?></td>
                            <td><?= number_format($stats['avg_frequency'], 1) ?></td>
                            <td><?= number_format($stats['avg_recency'], 0) ?></td>
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
            <h5>Customer Segmentation Details</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="segmentationTable" class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Segment</th>
                            <th>RFM Score</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Avg Order</th>
                            <th>Last Order</th>
                            <th>Recency</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($segmentData as $customer): ?>
                        <?php 
                            $segmentClass = match($customer['segment']) {
                                'Champions' => 'success',
                                'Loyal Customers' => 'primary',
                                'Potential Loyalists' => 'info',
                                'New Customers' => 'light',
                                'Promising' => 'warning',
                                'Cannot Lose Them' => 'danger',
                                'At Risk' => 'danger',
                                'Lost' => 'dark',
                                default => 'secondary'
                            };
                        ?>
                        <tr>
                            <td>
                                <strong><?= h($customer['name']) ?></strong><br>
                                <small class="text-muted"><?= h($customer['contact']) ?></small>
                            </td>
                            <td><span class="badge bg-<?= $segmentClass ?>"><?= h($customer['segment']) ?></span></td>
                            <td>
                                <span class="badge bg-secondary"><?= $customer['rfm_score'] ?></span>
                                <small class="text-muted d-block">R:<?= $customer['recency_score'] ?> F:<?= $customer['frequency_score'] ?> M:<?= $customer['monetary_score'] ?></small>
                            </td>
                            <td><?= number_format($customer['frequency']) ?></td>
                            <td><?= format_currency($customer['monetary']) ?></td>
                            <td><?= $customer['frequency'] > 0 ? format_currency($customer['monetary'] / $customer['frequency']) : format_currency(0) ?></td>
                            <td><?= $customer['frequency'] > 0 ? format_datetime($customer['last_order_date'], get_user_timezone(), 'M j, Y') : 'Never' ?></td>
                            <td><?= number_format($customer['recency_days']) ?> days</td>
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
    $('#segmentationTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[2, 'desc']],
        columnDefs: [
            { targets: [3, 4, 5, 7], className: 'text-end' }
        ]
    });

    initializeCharts();
});

function initializeCharts() {
    const segmentStats = <?= json_encode($segmentStats) ?>;
    
    // Segment distribution chart
    const segmentLabels = Object.keys(segmentStats);
    const segmentCounts = segmentLabels.map(segment => segmentStats[segment].count);
    
    new Chart(document.getElementById('segmentChart'), {
        type: 'doughnut',
        data: {
            labels: segmentLabels,
            datasets: [{
                data: segmentCounts,
                backgroundColor: [
                    '#28a745', '#007bff', '#17a2b8', '#f8f9fa', 
                    '#ffc107', '#dc3545', '#dc3545', '#343a40', '#6c757d'
                ]
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
    
    // Revenue by segment chart
    const segmentRevenues = segmentLabels.map(segment => segmentStats[segment].revenue);
    
    new Chart(document.getElementById('revenueChart'), {
        type: 'bar',
        data: {
            labels: segmentLabels.map(label => label.length > 10 ? label.substring(0, 10) + '...' : label),
            datasets: [{
                label: 'Revenue',
                data: segmentRevenues,
                backgroundColor: 'rgba(54, 162, 235, 0.8)',
                borderColor: 'rgba(54, 162, 235, 1)',
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
}

function exportData() {
    const table = document.getElementById('segmentationTable');
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
    downloadLink.download = 'customer_segmentation.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.click();
}
</script>

</div> <!-- Close container -->
</div> <!-- Close page-content-wrapper -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>