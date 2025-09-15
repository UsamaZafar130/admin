<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Customer Order Intervals";
require_once __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

// Get customer order interval data
$intervalData = [];
$avgInterval = 0;
$totalCustomers = 0;

try {
    if ($pdo) {
        // Get customer order intervals
        $stmt = $pdo->prepare("
            WITH CustomerOrderDates AS (
                SELECT 
                    c.id,
                    c.name,
                    c.contact,
                    so.order_date,
                    LAG(so.order_date) OVER (PARTITION BY c.id ORDER BY so.order_date) as prev_order_date
                FROM customers c
                JOIN sales_orders so ON c.id = so.customer_id
                WHERE c.deleted_at IS NULL AND so.cancelled = 0 AND so.delivered = 1
            ),
            CustomerIntervals AS (
                SELECT 
                    id,
                    name,
                    contact,
                    order_date,
                    prev_order_date,
                    CASE 
                        WHEN prev_order_date IS NOT NULL 
                        THEN DATEDIFF(order_date, prev_order_date)
                        ELSE NULL 
                    END as interval_days
                FROM CustomerOrderDates
                WHERE prev_order_date IS NOT NULL
            )
            SELECT 
                c.id,
                c.name,
                c.contact,
                COUNT(so.id) as total_orders,
                MIN(so.order_date) as first_order,
                MAX(so.order_date) as last_order,
                COALESCE(AVG(ci.interval_days), 0) as avg_interval_days,
                COALESCE(MIN(ci.interval_days), 0) as min_interval_days,
                COALESCE(MAX(ci.interval_days), 0) as max_interval_days,
                COALESCE(STDDEV(ci.interval_days), 0) as interval_stddev,
                DATEDIFF(NOW(), MAX(so.order_date)) as days_since_last_order,
                COALESCE(SUM(so.grand_total), 0) as total_spent,
                CASE 
                    WHEN COUNT(so.id) < 2 THEN 'New/One-time'
                    WHEN AVG(ci.interval_days) <= 7 THEN 'Weekly'
                    WHEN AVG(ci.interval_days) <= 30 THEN 'Monthly'
                    WHEN AVG(ci.interval_days) <= 90 THEN 'Quarterly'
                    ELSE 'Infrequent'
                END as frequency_category
            FROM customers c
            JOIN sales_orders so ON c.id = so.customer_id AND so.cancelled = 0 AND so.delivered = 1
            LEFT JOIN CustomerIntervals ci ON c.id = ci.id
            WHERE c.deleted_at IS NULL
            GROUP BY c.id, c.name, c.contact
            HAVING total_orders >= 1
            ORDER BY avg_interval_days ASC, total_orders DESC
        ");
        $stmt->execute();
        $intervalData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate summary statistics
        $totalCustomers = count($intervalData);
        $intervalsWithData = array_filter($intervalData, function($item) { 
            return $item['avg_interval_days'] > 0; 
        });
        $avgInterval = count($intervalsWithData) > 0 ? 
            array_sum(array_column($intervalsWithData, 'avg_interval_days')) / count($intervalsWithData) : 0;
    }
} catch (Exception $e) {
    error_log('Customer order intervals error: ' . $e->getMessage());
}
?>

<div class="page-content-wrapper">
    <div class="container mt-3">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="text-primary"><i class="fas fa-clock me-2"></i> Customer Order Intervals</h2>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-success btn-3d" onclick="exportData()">
                    <i class="fas fa-download me-1"></i> Export
                </button>
            </div>
        </div>

        <div class="alert alert-info max-width-700 mb-4">
            <strong>Customer Purchase Frequency Analysis.</strong> Analyzes customer purchase frequency by calculating average days between orders.<br>
            Only <strong>delivered orders</strong> are considered. Shows purchase patterns and identifies high-frequency vs occasional customers.
        </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Customers</h5>
                    <h3><?= number_format($totalCustomers) ?></h3>
                    <small>With order history</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Avg Order Interval</h5>
                    <h3><?= number_format($avgInterval, 1) ?> days</h3>
                    <small>Between orders</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Frequent Customers</h5>
                    <h3><?= count(array_filter($intervalData, function($item) { return $item['avg_interval_days'] > 0 && $item['avg_interval_days'] <= 30; })) ?></h3>
                    <small>≤30 days interval</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">At Risk Customers</h5>
                    <h3><?= count(array_filter($intervalData, function($item) { return $item['days_since_last_order'] > ($item['avg_interval_days'] * 2); })) ?></h3>
                    <small>Overdue for order</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Order Frequency Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="frequencyChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Interval vs Orders Correlation</h5>
                </div>
                <div class="card-body">
                    <canvas id="correlationChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card">
        <div class="card-header">
            <h5>Customer Order Interval Details</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="intervalsTable" class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Frequency</th>
                            <th>Total Orders</th>
                            <th>Avg Interval</th>
                            <th>Min/Max Interval</th>
                            <th>Last Order</th>
                            <th>Days Since</th>
                            <th>Total Spent</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($intervalData as $customer): ?>
                        <?php 
                            $frequencyClass = match($customer['frequency_category']) {
                                'Weekly' => 'success',
                                'Monthly' => 'primary',
                                'Quarterly' => 'info',
                                'Infrequent' => 'warning',
                                default => 'secondary'
                            };
                            
                            $isAtRisk = $customer['avg_interval_days'] > 0 && 
                                       $customer['days_since_last_order'] > ($customer['avg_interval_days'] * 2);
                            $statusClass = $isAtRisk ? 'danger' : 'success';
                            $statusText = $isAtRisk ? 'At Risk' : 'Active';
                        ?>
                        <tr>
                            <td>
                                <strong><?= h($customer['name']) ?></strong><br>
                                <small class="text-muted"><?= h($customer['contact']) ?></small>
                            </td>
                            <td><span class="badge bg-<?= $frequencyClass ?>"><?= h($customer['frequency_category']) ?></span></td>
                            <td><?= number_format($customer['total_orders']) ?></td>
                            <td>
                                <?php if ($customer['avg_interval_days'] > 0): ?>
                                    <?= number_format($customer['avg_interval_days'], 1) ?> days
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($customer['min_interval_days'] > 0): ?>
                                    <?= number_format($customer['min_interval_days']) ?> / <?= number_format($customer['max_interval_days']) ?>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><?= format_datetime($customer['last_order'], get_user_timezone(), 'M j, Y') ?></td>
                            <td><?= number_format($customer['days_since_last_order']) ?> days</td>
                            <td><?= format_currency($customer['total_spent']) ?></td>
                            <td><span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span></td>
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
    $('#intervalsTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[3, 'asc']],
        columnDefs: [
            { targets: [2, 3, 6, 7], className: 'text-end' }
        ]
    });

    initializeCharts();
});

function initializeCharts() {
    const intervalData = <?= json_encode($intervalData) ?>;
    
    // Frequency distribution chart
    const frequencyCounts = {};
    intervalData.forEach(customer => {
        frequencyCounts[customer.frequency_category] = (frequencyCounts[customer.frequency_category] || 0) + 1;
    });
    
    new Chart(document.getElementById('frequencyChart'), {
        type: 'doughnut',
        data: {
            labels: Object.keys(frequencyCounts),
            datasets: [{
                data: Object.values(frequencyCounts),
                backgroundColor: ['#28a745', '#007bff', '#17a2b8', '#ffc107', '#6c757d']
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
    
    // Interval vs Orders scatter plot
    const scatterData = intervalData
        .filter(customer => customer.avg_interval_days > 0)
        .map(customer => ({
            x: customer.avg_interval_days,
            y: customer.total_orders,
            label: customer.name
        }));
    
    new Chart(document.getElementById('correlationChart'), {
        type: 'scatter',
        data: {
            datasets: [{
                label: 'Customers',
                data: scatterData,
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
            }]
        },
        options: {
            responsive: true,
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Average Interval (Days)'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Total Orders'
                    },
                    beginAtZero: true
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            return context[0].raw.label;
                        },
                        label: function(context) {
                            return `Interval: ${context.parsed.x.toFixed(1)} days, Orders: ${context.parsed.y}`;
                        }
                    }
                }
            }
        }
    });
}

function exportData() {
    const table = document.getElementById('intervalsTable');
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
    downloadLink.download = 'customer_order_intervals.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.click();
}
</script>

</div> <!-- Close container -->
</div> <!-- Close page-content-wrapper -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>