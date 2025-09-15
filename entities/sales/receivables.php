<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Receivables";
require_once __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

// Get filter parameters
$aging_filter = $_GET['aging'] ?? '';
$customer_filter = $_GET['customer'] ?? '';

// Get receivables data
$receivablesData = [];
$totalReceivables = 0;
$overdueReceivables = 0;

try {
    if ($pdo) {
        // Build filters - user specified: delivered=1 and paid=0/2 (0 or 2 means unpaid)
        $whereConditions = ['so.delivered = 1', 'so.cancelled = 0', '(so.paid = 0 OR so.paid = 2)'];
        $params = [];

        if ($customer_filter) {
            $whereConditions[] = 'so.customer_id = ?';
            $params[] = $customer_filter;
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Get receivables data with aging analysis
        $stmt = $pdo->prepare("
            SELECT 
                so.id as order_id,
                so.public_token,
                so.order_date,
                so.grand_total,
                c.id as customer_id,
                c.name as customer_name,
                c.contact as customer_contact,
                COALESCE(cp.customer_payments, 0) as customer_payments,
                COALESCE(op.order_payments, 0) as order_payments,
                (COALESCE(cp.customer_payments, 0) + COALESCE(op.order_payments, 0)) as total_paid,
                (so.grand_total - COALESCE(cp.customer_payments, 0) - COALESCE(op.order_payments, 0)) as outstanding,
                DATEDIFF(NOW(), so.order_date) as days_outstanding,
                CASE 
                    WHEN DATEDIFF(NOW(), so.order_date) <= 7 THEN 'Current'
                    ELSE 'Overdue'
                END as aging_bucket
            FROM sales_orders so
            JOIN customers c ON so.customer_id = c.id
            LEFT JOIN (
                SELECT customer_id, SUM(amount) as customer_payments
                FROM customer_payments 
                GROUP BY customer_id
            ) cp ON c.id = cp.customer_id
            LEFT JOIN (
                SELECT order_id, SUM(amount) as order_payments
                FROM order_payments 
                GROUP BY order_id
            ) op ON so.id = op.order_id
            WHERE $whereClause
            HAVING outstanding > 0
            ORDER BY days_outstanding DESC, outstanding DESC
        ");
        $stmt->execute($params);
        $receivablesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Apply aging filter if specified
        if ($aging_filter) {
            $receivablesData = array_filter($receivablesData, function($item) use ($aging_filter) {
                return $item['aging_bucket'] === $aging_filter;
            });
        }

        // Calculate totals
        $totalReceivables = array_sum(array_column($receivablesData, 'outstanding'));
        $overdueReceivables = array_sum(array_column(
            array_filter($receivablesData, function($item) { 
                return $item['days_outstanding'] > 7; 
            }), 
            'outstanding'
        ));

        // Get customers for filter
        $stmt = $pdo->prepare("
            SELECT DISTINCT c.id, c.name 
            FROM customers c 
            JOIN sales_orders so ON c.id = so.customer_id 
            WHERE so.delivered = 1 AND so.cancelled = 0 AND (so.paid = 0 OR so.paid = 2)
            ORDER BY c.name
        ");
        $stmt->execute();
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log('Receivables error: ' . $e->getMessage());
    $customers = [];
}

// Calculate aging summary
$agingSummary = [
    'Current' => 0,
    'Overdue' => 0
];

foreach ($receivablesData as $item) {
    $agingSummary[$item['aging_bucket']] += $item['outstanding'];
}
?>

<div class="page-content-wrapper">
    <div class="container mt-3">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="text-primary"><i class="fas fa-file-invoice-dollar me-2"></i> Receivables</h2>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-success btn-3d" onclick="exportData()">
                    <i class="fas fa-download me-1"></i> Export
                </button>
            </div>
        </div>

        <div class="alert alert-info max-width-700 mb-4">
            <strong>Accounts Receivables Analysis.</strong> Calculated from delivered orders (status=1) with unpaid status (paid=0 or 2), minus any customer payments and order-specific payments.<br>
            Orders <strong>>7 days</strong> are considered overdue.
        </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Aging Filter</label>
                    <select name="aging" class="form-select">
                        <option value="">All Ages</option>
                        <option value="Current" <?= $aging_filter == 'Current' ? 'selected' : '' ?>>Current (≤30 days)</option>
                        <option value="31-60 Days" <?= $aging_filter == '31-60 Days' ? 'selected' : '' ?>>31-60 Days</option>
                        <option value="61-90 Days" <?= $aging_filter == '61-90 Days' ? 'selected' : '' ?>>61-90 Days</option>
                        <option value="90+ Days" <?= $aging_filter == '90+ Days' ? 'selected' : '' ?>>90+ Days</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Customer</label>
                    <select name="customer" class="form-select">
                        <option value="">All Customers</option>
                        <?php foreach ($customers as $customer): ?>
                        <option value="<?= h($customer['id']) ?>" <?= $customer_filter == $customer['id'] ? 'selected' : '' ?>>
                            <?= h($customer['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary form-control">Apply</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Receivables</h5>
                    <h3><?= format_currency($totalReceivables) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Overdue (>30 days)</h5>
                    <h3><?= format_currency($overdueReceivables) ?></h3>
                    <small><?= $totalReceivables > 0 ? number_format(($overdueReceivables / $totalReceivables) * 100, 1) : 0 ?>% of total</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Outstanding Orders</h5>
                    <h3><?= count($receivablesData) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Avg Outstanding</h5>
                    <h3><?= count($receivablesData) > 0 ? format_currency($totalReceivables / count($receivablesData)) : format_currency(0) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Aging Analysis</h5>
                </div>
                <div class="card-body">
                    <canvas id="agingChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Top 10 Outstanding Customers</h5>
                </div>
                <div class="card-body">
                    <canvas id="topCustomersChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Aging Summary Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Aging Summary</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Age Group</th>
                            <th>Amount</th>
                            <th>% of Total</th>
                            <th>Orders Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agingSummary as $age => $amount): ?>
                        <?php 
                            $count = count(array_filter($receivablesData, function($item) use ($age) { 
                                return $item['aging_bucket'] === $age; 
                            }));
                        ?>
                        <tr>
                            <td><strong><?= h($age) ?></strong></td>
                            <td><?= format_currency($amount) ?></td>
                            <td><?= $totalReceivables > 0 ? number_format(($amount / $totalReceivables) * 100, 1) : 0 ?>%</td>
                            <td><?= number_format($count) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Receivables Details Table -->
    <div class="card">
        <div class="card-header">
            <h5>Receivables Details</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="receivablesTable" class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Order Date</th>
                            <th>Total Amount</th>
                            <th>Paid</th>
                            <th>Outstanding</th>
                            <th>Days Outstanding</th>
                            <th>Age Group</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($receivablesData as $item): ?>
                        <?php 
                            $ageClass = match($item['aging_bucket']) {
                                'Current' => 'success',
                                '31-60 Days' => 'warning',
                                '61-90 Days' => 'danger',
                                '90+ Days' => 'dark'
                            };
                        ?>
                        <tr>
                            <td><?= format_order_number($item['order_id']) ?></td>
                            <td>
                                <strong><?= h($item['customer_name']) ?></strong><br>
                                <small class="text-muted"><?= h($item['customer_contact']) ?></small>
                            </td>
                            <td><?= format_datetime($item['order_date'], get_user_timezone(), 'M j, Y') ?></td>
                            <td><?= format_currency($item['grand_total']) ?></td>
                            <td><?= format_currency($item['total_paid']) ?></td>
                            <td><strong><?= format_currency($item['outstanding']) ?></strong></td>
                            <td><?= number_format($item['days_outstanding']) ?> days</td>
                            <td><span class="badge bg-<?= $ageClass ?>"><?= h($item['aging_bucket']) ?></span></td>
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
    $('#receivablesTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[6, 'desc']],
        columnDefs: [
            { targets: [3, 4, 5, 6], className: 'text-end' }
        ]
    });

    initializeCharts();
});

function initializeCharts() {
    const agingSummary = <?= json_encode($agingSummary) ?>;
    const receivablesData = <?= json_encode($receivablesData) ?>;
    
    // Aging analysis chart
    new Chart(document.getElementById('agingChart'), {
        type: 'doughnut',
        data: {
            labels: Object.keys(agingSummary),
            datasets: [{
                data: Object.values(agingSummary),
                backgroundColor: ['#28a745', '#ffc107', '#dc3545', '#343a40']
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
    
    // Top customers by outstanding amount
    const customerTotals = {};
    receivablesData.forEach(item => {
        if (!customerTotals[item.customer_name]) {
            customerTotals[item.customer_name] = 0;
        }
        customerTotals[item.customer_name] += parseFloat(item.outstanding);
    });
    
    const sortedCustomers = Object.entries(customerTotals)
        .sort(([,a], [,b]) => b - a)
        .slice(0, 10);
    
    new Chart(document.getElementById('topCustomersChart'), {
        type: 'bar',
        data: {
            labels: sortedCustomers.map(([name]) => name.length > 15 ? name.substring(0, 15) + '...' : name),
            datasets: [{
                label: 'Outstanding Amount',
                data: sortedCustomers.map(([, amount]) => amount),
                backgroundColor: 'rgba(220, 53, 69, 0.8)',
                borderColor: 'rgba(220, 53, 69, 1)',
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
    const table = document.getElementById('receivablesTable');
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
    downloadLink.download = 'receivables.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.click();
}
</script>

</div> <!-- Close container -->
</div> <!-- Close page-content-wrapper -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>