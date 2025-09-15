<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Vendor Reports";
require_once __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

// Get filter parameters
$vendor_filter = $_GET['vendor'] ?? '';
$period = $_GET['period'] ?? '90';

// Get vendor data
$vendorData = [];
$totalPayables = 0;
$totalPurchases = 0;

try {
    if ($pdo) {
        // Build filters
        $whereConditions = [];
        $params = [];
        
        if ($vendor_filter) {
            $whereConditions[] = 'v.id = ?';
            $params[] = $vendor_filter;
        }
        
        if ($period !== 'all') {
            $whereConditions[] = '(p.date >= DATE_SUB(NOW(), INTERVAL ? DAY) OR e.date >= DATE_SUB(NOW(), INTERVAL ? DAY))';
            $params[] = $period;
            $params[] = $period;
        }
        
        $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Get comprehensive vendor analysis
        $stmt = $pdo->prepare("
            SELECT 
                v.id,
                v.name,
                v.contact,
                v.address,
                v.created_at,
                COALESCE(purchase_stats.total_purchases, 0) as total_purchases,
                COALESCE(purchase_stats.purchase_count, 0) as purchase_count,
                COALESCE(purchase_stats.avg_purchase, 0) as avg_purchase,
                COALESCE(purchase_stats.last_purchase, NULL) as last_purchase,
                COALESCE(expense_stats.total_expenses, 0) as total_expenses,
                COALESCE(expense_stats.expense_count, 0) as expense_count,
                COALESCE(payments.total_paid, 0) as total_paid,
                COALESCE(advances.total_advances, 0) as total_advances,
                (COALESCE(purchase_stats.total_purchases, 0) + COALESCE(expense_stats.total_expenses, 0) - COALESCE(payments.total_paid, 0)) as outstanding_balance,
                CASE 
                    WHEN COALESCE(purchase_stats.purchase_count, 0) + COALESCE(expense_stats.expense_count, 0) = 0 THEN 'Inactive'
                    WHEN COALESCE(purchase_stats.last_purchase, expense_stats.last_expense) >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'Active'
                    WHEN COALESCE(purchase_stats.last_purchase, expense_stats.last_expense) >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 'Moderate'
                    ELSE 'Inactive'
                END as status
            FROM vendors v
            LEFT JOIN (
                SELECT 
                    vendor_id,
                    SUM(amount) as total_purchases,
                    COUNT(*) as purchase_count,
                    AVG(amount) as avg_purchase,
                    MAX(date) as last_purchase
                FROM purchases 
                WHERE deleted_at IS NULL
                " . ($period !== 'all' ? "AND date >= DATE_SUB(NOW(), INTERVAL $period DAY)" : "") . "
                GROUP BY vendor_id
            ) purchase_stats ON v.id = purchase_stats.vendor_id
            LEFT JOIN (
                SELECT 
                    vendor_id,
                    SUM(amount) as total_expenses,
                    COUNT(*) as expense_count,
                    MAX(date) as last_expense
                FROM expenses 
                WHERE deleted_at IS NULL
                " . ($period !== 'all' ? "AND date >= DATE_SUB(NOW(), INTERVAL $period DAY)" : "") . "
                GROUP BY vendor_id
            ) expense_stats ON v.id = expense_stats.vendor_id
            LEFT JOIN (
                SELECT 
                    vendor_id,
                    SUM(amount) as total_paid
                FROM (
                    SELECT vendor_id, SUM(amount) as amount FROM purchase_payments pp
                    JOIN purchases p ON pp.purchase_id = p.id
                    WHERE pp.deleted_at IS NULL
                    GROUP BY vendor_id
                    UNION ALL
                    SELECT vendor_id, SUM(amount) as amount FROM expense_payments ep
                    JOIN expenses e ON ep.expense_id = e.id
                    WHERE ep.deleted_at IS NULL
                    GROUP BY vendor_id
                ) combined_payments
                GROUP BY vendor_id
            ) payments ON v.id = payments.vendor_id
            LEFT JOIN (
                SELECT vendor_id, SUM(amount) as total_advances
                FROM vendor_advances
                GROUP BY vendor_id
            ) advances ON v.id = advances.vendor_id
            $whereClause
            ORDER BY (COALESCE(purchase_stats.total_purchases, 0) + COALESCE(expense_stats.total_expenses, 0)) DESC
        ");
        $stmt->execute($params);
        $vendorData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate totals
        $totalPurchases = array_sum(array_column($vendorData, 'total_purchases'));
        $totalPayables = array_sum(array_column($vendorData, 'outstanding_balance'));

        // Get vendors for filter
        $stmt = $pdo->prepare("SELECT id, name FROM vendors ORDER BY name");
        $stmt->execute();
        $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log('Vendor reports error: ' . $e->getMessage());
    $vendors = [];
}

// Calculate status distribution
$statusStats = ['Active' => 0, 'Moderate' => 0, 'Inactive' => 0];
foreach ($vendorData as $vendor) {
    $statusStats[$vendor['status']]++;
}
?>

<div class="page-content-wrapper">
    <div class="container mt-3">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="text-primary"><i class="fas fa-truck me-2"></i> Vendor Reports</h2>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-success btn-3d" onclick="exportData()">
                    <i class="fas fa-download me-1"></i> Export
                </button>
            </div>
        </div>

        <div class="alert alert-info max-width-700 mb-4">
            <strong>Vendor Performance Analysis.</strong> Comprehensive vendor performance analysis including total purchases, expenses, payments, and outstanding balances.<br>
            Vendor status determined by activity level: <strong>Active</strong> (recent transactions), <strong>Moderate</strong> (some activity), <strong>Inactive</strong> (no recent activity).
        </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Vendor</label>
                    <select name="vendor" class="form-select">
                        <option value="">All Vendors</option>
                        <?php foreach ($vendors as $vendor): ?>
                        <option value="<?= h($vendor['id']) ?>" <?= $vendor_filter == $vendor['id'] ? 'selected' : '' ?>>
                            <?= h($vendor['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Period</label>
                    <select name="period" class="form-select">
                        <option value="30" <?= $period == '30' ? 'selected' : '' ?>>Last 30 days</option>
                        <option value="90" <?= $period == '90' ? 'selected' : '' ?>>Last 90 days</option>
                        <option value="180" <?= $period == '180' ? 'selected' : '' ?>>Last 6 months</option>
                        <option value="365" <?= $period == '365' ? 'selected' : '' ?>>Last year</option>
                        <option value="all" <?= $period == 'all' ? 'selected' : '' ?>>All time</option>
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
                    <h5 class="card-title">Total Vendors</h5>
                    <h3><?= count($vendorData) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Purchases</h5>
                    <h3><?= format_currency($totalPurchases) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title">Outstanding Payables</h5>
                    <h3><?= format_currency($totalPayables) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Active Vendors</h5>
                    <h3><?= $statusStats['Active'] ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Vendor Status Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Top 10 Vendors by Purchase Volume</h5>
                </div>
                <div class="card-body">
                    <canvas id="topVendorsChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card">
        <div class="card-header">
            <h5>Vendor Analysis Details</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="vendorTable" class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Vendor</th>
                            <th>Status</th>
                            <th>Total Purchases</th>
                            <th>Purchase Count</th>
                            <th>Avg Purchase</th>
                            <th>Total Paid</th>
                            <th>Outstanding</th>
                            <th>Last Purchase</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vendorData as $vendor): ?>
                        <?php 
                            $statusClass = match($vendor['status']) {
                                'Active' => 'success',
                                'Moderate' => 'warning',
                                'Inactive' => 'secondary'
                            };
                        ?>
                        <tr>
                            <td>
                                <strong><?= h($vendor['name']) ?></strong><br>
                                <small class="text-muted"><?= h($vendor['contact']) ?></small>
                            </td>
                            <td><span class="badge bg-<?= $statusClass ?>"><?= h($vendor['status']) ?></span></td>
                            <td><?= format_currency($vendor['total_purchases'] + $vendor['total_expenses']) ?></td>
                            <td><?= number_format($vendor['purchase_count'] + $vendor['expense_count']) ?></td>
                            <td><?= ($vendor['purchase_count'] + $vendor['expense_count']) > 0 ? format_currency(($vendor['total_purchases'] + $vendor['total_expenses']) / ($vendor['purchase_count'] + $vendor['expense_count'])) : format_currency(0) ?></td>
                            <td><?= format_currency($vendor['total_paid']) ?></td>
                            <td class="<?= $vendor['outstanding_balance'] > 0 ? 'text-danger' : 'text-success' ?>">
                                <strong><?= format_currency($vendor['outstanding_balance']) ?></strong>
                            </td>
                            <td><?= $vendor['last_purchase'] ? format_datetime($vendor['last_purchase'], get_user_timezone(), 'M j, Y') : 'Never' ?></td>
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
    $('#vendorTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[2, 'desc']],
        columnDefs: [
            { targets: [2, 3, 4, 5, 6], className: 'text-end' }
        ]
    });

    initializeCharts();
});

function initializeCharts() {
    const statusStats = <?= json_encode($statusStats) ?>;
    const vendorData = <?= json_encode($vendorData) ?>;
    
    // Status distribution chart
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: Object.keys(statusStats),
            datasets: [{
                data: Object.values(statusStats),
                backgroundColor: ['#28a745', '#ffc107', '#6c757d']
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
    
    // Top vendors chart
    const top10Vendors = vendorData
        .sort((a, b) => (b.total_purchases + b.total_expenses) - (a.total_purchases + a.total_expenses))
        .slice(0, 10);
    
    new Chart(document.getElementById('topVendorsChart'), {
        type: 'bar',
        data: {
            labels: top10Vendors.map(vendor => vendor.name.length > 15 ? vendor.name.substring(0, 15) + '...' : vendor.name),
            datasets: [{
                label: 'Purchase Volume',
                data: top10Vendors.map(vendor => vendor.total_purchases + vendor.total_expenses),
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
    const table = document.getElementById('vendorTable');
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
    downloadLink.download = 'vendor_reports.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.click();
}
</script>

</div> <!-- Close container -->
</div> <!-- Close page-content-wrapper -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>