<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Customer Trends";
require_once __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

// Get filter parameters
$period = $_GET['period'] ?? '90';
$customer_type = $_GET['customer_type'] ?? '';

// Get customer trends data
$customerStats = [];
$newCustomers = [];
$totalCustomers = 0;
$activeCustomers = 0;
$totalRevenue = 0;

try {
    if ($pdo) {
        // Get overall customer statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT c.id) as total_customers,
                COUNT(DISTINCT CASE WHEN so.order_date >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN c.id END) as active_customers,
                COALESCE(SUM(CASE WHEN so.order_date >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN so.grand_total END), 0) as total_revenue,
                COUNT(DISTINCT CASE WHEN c.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN c.id END) as new_customers
            FROM customers c
            LEFT JOIN sales_orders so ON c.id = so.customer_id AND so.cancelled = 0
            WHERE c.deleted_at IS NULL
        ");
        $stmt->execute([$period, $period, $period]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $totalCustomers = $stats['total_customers'] ?? 0;
        $activeCustomers = $stats['active_customers'] ?? 0;
        $totalRevenue = $stats['total_revenue'] ?? 0;
        $newCustomersCount = $stats['new_customers'] ?? 0;

        // Get new customers by date
        $stmt = $pdo->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as new_customers
            FROM customers 
            WHERE deleted_at IS NULL 
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY DATE(created_at) DESC
        ");
        $stmt->execute([$period]);
        $newCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get customer purchasing behavior
        $stmt = $pdo->prepare("
            SELECT 
                c.id,
                c.name,
                c.contact,
                c.created_at,
                COUNT(so.id) as order_count,
                COALESCE(SUM(so.grand_total), 0) as total_spent,
                COALESCE(AVG(so.grand_total), 0) as avg_order_value,
                MAX(so.order_date) as last_order_date,
                MIN(so.order_date) as first_order_date,
                DATEDIFF(NOW(), MAX(so.order_date)) as days_since_last_order,
                CASE 
                    WHEN COUNT(so.id) = 0 THEN 'No Orders'
                    WHEN COUNT(so.id) = 1 THEN 'One-time'
                    WHEN COUNT(so.id) BETWEEN 2 AND 5 THEN 'Occasional'
                    WHEN COUNT(so.id) BETWEEN 6 AND 15 THEN 'Regular'
                    ELSE 'VIP'
                END as customer_type
            FROM customers c
            LEFT JOIN sales_orders so ON c.id = so.customer_id AND so.cancelled = 0 AND so.delivered = 1
            WHERE c.deleted_at IS NULL
            GROUP BY c.id, c.name, c.contact, c.created_at
            ORDER BY total_spent DESC, order_count DESC
            LIMIT 100
        ");
        $stmt->execute();
        $customerStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log('Customer trends error: ' . $e->getMessage());
}
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="text-primary"><i class="fas fa-chart-line me-2"></i> Customer Trends</h2>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-success" onclick="exportData()">
                <i class="fas fa-download me-1"></i> Export
            </button>
        </div>
    </div>

    <div class="alert alert-info max-width-700 mb-4">
        <strong>Customer Behavior Trends Analysis.</strong> Analyzes customer behavior trends including new acquisitions, total spending, order frequency, and customer type classification.<br>
        Only <strong>delivered orders</strong> considered. Categorizes customers as One-time, Occasional, Regular, or VIP based on order count.
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Period</label>
                    <select name="period" class="form-select">
                        <option value="30" <?= $period == '30' ? 'selected' : '' ?>>Last 30 days</option>
                        <option value="90" <?= $period == '90' ? 'selected' : '' ?>>Last 90 days</option>
                        <option value="180" <?= $period == '180' ? 'selected' : '' ?>>Last 6 months</option>
                        <option value="365" <?= $period == '365' ? 'selected' : '' ?>>Last year</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Customer Type</label>
                    <select name="customer_type" class="form-select">
                        <option value="">All Types</option>
                        <option value="VIP" <?= $customer_type == 'VIP' ? 'selected' : '' ?>>VIP</option>
                        <option value="Regular" <?= $customer_type == 'Regular' ? 'selected' : '' ?>>Regular</option>
                        <option value="Occasional" <?= $customer_type == 'Occasional' ? 'selected' : '' ?>>Occasional</option>
                        <option value="One-time" <?= $customer_type == 'One-time' ? 'selected' : '' ?>>One-time</option>
                        <option value="No Orders" <?= $customer_type == 'No Orders' ? 'selected' : '' ?>>No Orders</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary form-control">Apply Filters</button>
                </div>
            </form>
        </div>
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
                    <h5 class="card-title">Active Customers</h5>
                    <h3><?= number_format($activeCustomers) ?></h3>
                    <small><?= $totalCustomers > 0 ? number_format(($activeCustomers / $totalCustomers) * 100, 1) : 0 ?>% of total</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">New Customers</h5>
                    <h3><?= number_format($newCustomersCount) ?></h3>
                    <small>Last <?= $period ?> days</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Revenue</h5>
                    <h3><?= format_currency($totalRevenue) ?></h3>
                    <small>From active customers</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>New Customer Acquisition</h5>
                </div>
                <div class="card-body">
                    <canvas id="acquisitionChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Customer Type Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="typeChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card">
        <div class="card-header">
            <h5>Customer Analysis Details</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="customersTable" class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Type</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Avg Order</th>
                            <th>Last Order</th>
                            <th>Days Since</th>
                            <th>Customer Since</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customerStats as $customer): ?>
                        <?php 
                            $typeClass = match($customer['customer_type']) {
                                'VIP' => 'success',
                                'Regular' => 'primary',
                                'Occasional' => 'info',
                                'One-time' => 'warning',
                                default => 'secondary'
                            };
                        ?>
                        <tr>
                            <td>
                                <strong><?= h($customer['name']) ?></strong><br>
                                <small class="text-muted"><?= h($customer['contact']) ?></small>
                            </td>
                            <td><span class="badge bg-<?= $typeClass ?>"><?= h($customer['customer_type']) ?></span></td>
                            <td><?= number_format($customer['order_count']) ?></td>
                            <td><?= format_currency($customer['total_spent']) ?></td>
                            <td><?= format_currency($customer['avg_order_value']) ?></td>
                            <td><?= $customer['last_order_date'] ? format_datetime($customer['last_order_date'], get_user_timezone(), 'M j, Y') : 'Never' ?></td>
                            <td>
                                <?php if ($customer['days_since_last_order']): ?>
                                    <?= number_format($customer['days_since_last_order']) ?> days
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><?= format_datetime($customer['created_at'], get_user_timezone(), 'M j, Y') ?></td>
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
    $('#customersTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[3, 'desc']],
        columnDefs: [
            { targets: [2, 3, 4, 6], className: 'text-end' }
        ]
    });

    initializeCharts();
});

function initializeCharts() {
    const newCustomers = <?= json_encode(array_reverse($newCustomers)) ?>;
    const customerStats = <?= json_encode($customerStats) ?>;
    
    // New customer acquisition chart
    new Chart(document.getElementById('acquisitionChart'), {
        type: 'line',
        data: {
            labels: newCustomers.map(data => data.date),
            datasets: [{
                label: 'New Customers',
                data: newCustomers.map(data => data.new_customers),
                borderColor: '#36A2EB',
                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                fill: true
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
    
    // Customer type distribution
    const typeCounts = {};
    customerStats.forEach(customer => {
        typeCounts[customer.customer_type] = (typeCounts[customer.customer_type] || 0) + 1;
    });
    
    new Chart(document.getElementById('typeChart'), {
        type: 'doughnut',
        data: {
            labels: Object.keys(typeCounts),
            datasets: [{
                data: Object.values(typeCounts),
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
}

function exportData() {
    const table = document.getElementById('customersTable');
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
    downloadLink.download = 'customer_trends.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.click();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>