<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Payables";
require_once __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

// Get filter parameters
$vendor_filter = $_GET['vendor'] ?? '';
$aging_filter = $_GET['aging'] ?? '';

// Get payables data
$payablesData = [];
$totalPayables = 0;
$overduePayables = 0;

try {
    if ($pdo) {
        // Build filters
        $whereConditions = ['p.deleted_at IS NULL'];
        $params = [];

        if ($vendor_filter) {
            $whereConditions[] = 'p.vendor_id = ?';
            $params[] = $vendor_filter;
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Get purchases payables
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.date as purchase_date,
                p.amount as purchase_amount,
                p.type as purchase_type,
                v.id as vendor_id,
                v.name as vendor_name,
                v.contact as vendor_phone,
                COALESCE(pp.total_paid, 0) as total_paid,
                (p.amount - COALESCE(pp.total_paid, 0)) as outstanding,
                DATEDIFF(NOW(), p.date) as days_outstanding,
                CASE 
                    WHEN DATEDIFF(NOW(), p.date) <= 30 THEN 'Current'
                    WHEN DATEDIFF(NOW(), p.date) <= 60 THEN '31-60 Days'
                    WHEN DATEDIFF(NOW(), p.date) <= 90 THEN '61-90 Days'
                    ELSE '90+ Days'
                END as aging_bucket,
                'Purchase' as type
            FROM purchases p
            JOIN vendors v ON p.vendor_id = v.id
            LEFT JOIN (
                SELECT purchase_id, SUM(amount) as total_paid
                FROM purchase_payments 
                WHERE deleted_at IS NULL 
                GROUP BY purchase_id
            ) pp ON p.id = pp.purchase_id
            WHERE $whereClause AND p.type = 'credit'
            HAVING outstanding > 0
        ");
        $stmt->execute($params);
        $purchasePayables = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get expenses payables if table exists
        $expensePayables = [];
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    e.id,
                    e.date as purchase_date,
                    e.amount as purchase_amount,
                    e.type as purchase_type,
                    v.id as vendor_id,
                    v.name as vendor_name,
                    v.contact as vendor_phone,
                    COALESCE(ep.total_paid, 0) as total_paid,
                    (e.amount - COALESCE(ep.total_paid, 0)) as outstanding,
                    DATEDIFF(NOW(), e.date) as days_outstanding,
                    CASE 
                        WHEN DATEDIFF(NOW(), e.date) <= 30 THEN 'Current'
                        WHEN DATEDIFF(NOW(), e.date) <= 60 THEN '31-60 Days'
                        WHEN DATEDIFF(NOW(), e.date) <= 90 THEN '61-90 Days'
                        ELSE '90+ Days'
                    END as aging_bucket,
                    'Expense' as type
                FROM expenses e
                JOIN vendors v ON e.vendor_id = v.id
                LEFT JOIN (
                    SELECT expense_id, SUM(amount) as total_paid
                    FROM expense_payments 
                    WHERE deleted_at IS NULL 
                    GROUP BY expense_id
                ) ep ON e.id = ep.expense_id
                WHERE e.deleted_at IS NULL AND e.type = 'credit'
                " . ($vendor_filter ? "AND e.vendor_id = ?" : "") . "
                HAVING outstanding > 0
            ");
            
            if ($vendor_filter) {
                $stmt->execute([$vendor_filter]);
            } else {
                $stmt->execute();
            }
            $expensePayables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Expenses table might not exist
        }

        // Combine payables
        $payablesData = array_merge($purchasePayables, $expensePayables);

        // Apply aging filter if specified
        if ($aging_filter) {
            $payablesData = array_filter($payablesData, function($item) use ($aging_filter) {
                return $item['aging_bucket'] === $aging_filter;
            });
        }

        // Sort by days outstanding (descending) then by outstanding amount (descending)
        usort($payablesData, function($a, $b) {
            if ($a['days_outstanding'] == $b['days_outstanding']) {
                return $b['outstanding'] <=> $a['outstanding'];
            }
            return $b['days_outstanding'] <=> $a['days_outstanding'];
        });

        // Calculate totals
        $totalPayables = array_sum(array_column($payablesData, 'outstanding'));
        $overduePayables = array_sum(array_column(
            array_filter($payablesData, function($item) { 
                return $item['days_outstanding'] > 30; 
            }), 
            'outstanding'
        ));

        // Get vendors for filter
        $stmt = $pdo->prepare("
            SELECT DISTINCT v.id, v.name 
            FROM vendors v 
            WHERE EXISTS (
                SELECT 1 FROM purchases p WHERE p.vendor_id = v.id AND p.deleted_at IS NULL
                UNION
                SELECT 1 FROM expenses e WHERE e.vendor_id = v.id AND e.deleted_at IS NULL
            )
            ORDER BY v.name
        ");
        $stmt->execute();
        $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log('Payables error: ' . $e->getMessage());
    $vendors = [];
}

// Calculate aging summary
$agingSummary = [
    'Current' => 0,
    '31-60 Days' => 0,
    '61-90 Days' => 0,
    '90+ Days' => 0
];

foreach ($payablesData as $item) {
    $agingSummary[$item['aging_bucket']] += $item['outstanding'];
}
?>

<div class="page-content-wrapper">
    <div class="container mt-3">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="text-primary"><i class="fas fa-money-bill-wave me-2"></i> Payables</h2>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-success btn-3d" onclick="exportData()">
                    <i class="fas fa-download me-1"></i> Export
                </button>
            </div>
        </div>

        <div class="alert alert-info max-width-700 mb-4">
            <strong>Accounts Payables Analysis.</strong> Outstanding amounts owed to vendors from purchases and expenses. Calculated as (Total Amount - Payments Made) with aging analysis based on invoice/expense dates.<br>
            Includes both <strong>purchase payables</strong> and <strong>expense payables</strong>.
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
                    <label class="form-label">Aging Filter</label>
                    <select name="aging" class="form-select">
                        <option value="">All Ages</option>
                        <option value="Current" <?= $aging_filter == 'Current' ? 'selected' : '' ?>>Current (≤30 days)</option>
                        <option value="31-60 Days" <?= $aging_filter == '31-60 Days' ? 'selected' : '' ?>>31-60 Days</option>
                        <option value="61-90 Days" <?= $aging_filter == '61-90 Days' ? 'selected' : '' ?>>61-90 Days</option>
                        <option value="90+ Days" <?= $aging_filter == '90+ Days' ? 'selected' : '' ?>>90+ Days</option>
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
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Payables</h5>
                    <h3><?= format_currency($totalPayables) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Overdue (>30 days)</h5>
                    <h3><?= format_currency($overduePayables) ?></h3>
                    <small><?= $totalPayables > 0 ? number_format(($overduePayables / $totalPayables) * 100, 1) : 0 ?>% of total</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Outstanding Items</h5>
                    <h3><?= count($payablesData) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Avg Outstanding</h5>
                    <h3><?= count($payablesData) > 0 ? format_currency($totalPayables / count($payablesData)) : format_currency(0) ?></h3>
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
                    <h5>Top 10 Vendors by Outstanding</h5>
                </div>
                <div class="card-body">
                    <canvas id="topVendorsChart"></canvas>
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
                            <th>Items Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agingSummary as $age => $amount): ?>
                        <?php 
                            $count = count(array_filter($payablesData, function($item) use ($age) { 
                                return $item['aging_bucket'] === $age; 
                            }));
                        ?>
                        <tr>
                            <td><strong><?= h($age) ?></strong></td>
                            <td><?= format_currency($amount) ?></td>
                            <td><?= $totalPayables > 0 ? number_format(($amount / $totalPayables) * 100, 1) : 0 ?>%</td>
                            <td><?= number_format($count) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Payables Details Table -->
    <div class="card">
        <div class="card-header">
            <h5>Payables Details</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="payablesTable" class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Vendor</th>
                            <th>Date</th>
                            <th>Total Amount</th>
                            <th>Paid</th>
                            <th>Outstanding</th>
                            <th>Days Outstanding</th>
                            <th>Age Group</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payablesData as $item): ?>
                        <?php 
                            $ageClass = match($item['aging_bucket']) {
                                'Current' => 'success',
                                '31-60 Days' => 'warning',
                                '61-90 Days' => 'danger',
                                '90+ Days' => 'dark'
                            };
                            
                            $typeClass = $item['type'] === 'Purchase' ? 'primary' : 'info';
                        ?>
                        <tr>
                            <td><span class="badge bg-<?= $typeClass ?>"><?= h($item['type']) ?></span></td>
                            <td>
                                <strong><?= h($item['vendor_name']) ?></strong><br>
                                <small class="text-muted"><?= h($item['vendor_phone']) ?></small>
                            </td>
                            <td><?= format_datetime($item['purchase_date'], get_user_timezone(), 'M j, Y') ?></td>
                            <td><?= format_currency($item['purchase_amount']) ?></td>
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
    $('#payablesTable').DataTable({
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
    const payablesData = <?= json_encode($payablesData) ?>;
    
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
    
    // Top vendors by outstanding amount
    const vendorTotals = {};
    payablesData.forEach(item => {
        if (!vendorTotals[item.vendor_name]) {
            vendorTotals[item.vendor_name] = 0;
        }
        vendorTotals[item.vendor_name] += parseFloat(item.outstanding);
    });
    
    const sortedVendors = Object.entries(vendorTotals)
        .sort(([,a], [,b]) => b - a)
        .slice(0, 10);
    
    new Chart(document.getElementById('topVendorsChart'), {
        type: 'bar',
        data: {
            labels: sortedVendors.map(([name]) => name.length > 15 ? name.substring(0, 15) + '...' : name),
            datasets: [{
                label: 'Outstanding Amount',
                data: sortedVendors.map(([, amount]) => amount),
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
    const table = document.getElementById('payablesTable');
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
    downloadLink.download = 'payables.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.click();
}
</script>

</div> <!-- Close container -->
</div> <!-- Close page-content-wrapper -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>