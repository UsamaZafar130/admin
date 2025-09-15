<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Top Selling Items";
require_once __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

// Get filter parameters
$period = $_GET['period'] ?? '30';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Build date filter
$date_condition = '';
$params = [];
if ($start_date && $end_date) {
    $date_condition = 'AND so.order_date BETWEEN ? AND ?';
    $params = [$start_date, $end_date];
} elseif ($period !== 'all') {
    $date_condition = 'AND so.order_date >= DATE_SUB(NOW(), INTERVAL ? DAY)';
    $params = [$period];
}

// Get top selling items data
$topItems = [];
$totalRevenue = 0;
$totalQuantity = 0;

try {
    if ($pdo) {
        // Get summary stats
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT oi.item_id) as unique_items,
                SUM(oi.qty) as total_qty,
                SUM(oi.total) as total_revenue
            FROM order_items oi
            JOIN sales_orders so ON oi.order_id = so.id
            WHERE so.cancelled = 0 $date_condition
        ");
        $stmt->execute($params);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $totalRevenue = $summary['total_revenue'] ?? 0;
        $totalQuantity = $summary['total_qty'] ?? 0;
        $uniqueItems = $summary['unique_items'] ?? 0;

        // Get top selling items
        $stmt = $pdo->prepare("
            SELECT 
                i.id,
                i.name,
                i.price_per_unit,
                SUM(oi.qty) as total_sold,
                SUM(oi.total) as revenue,
                AVG(oi.price_per_unit) as avg_price,
                COUNT(DISTINCT so.customer_id) as unique_customers,
                COUNT(DISTINCT oi.order_id) as order_count
            FROM order_items oi
            JOIN sales_orders so ON oi.order_id = so.id
            JOIN items i ON oi.item_id = i.id
            WHERE so.cancelled = 0 AND so.delivered = 1 $date_condition
            GROUP BY i.id, i.name, i.price_per_unit
            ORDER BY total_sold DESC
            LIMIT 20
        ");
        $stmt->execute($params);
        $topItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log('Top selling items error: ' . $e->getMessage());
}
?>

<div class="page-content-wrapper">
    <div class="container mt-3">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="text-primary"><i class="fas fa-chart-bar me-2"></i> Top Selling Items</h2>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-success btn-3d" onclick="exportData()">
                    <i class="fas fa-download me-1"></i> Export
                </button>
            </div>
        </div>

        <div class="alert alert-info max-width-700 mb-4">
            <strong>Top Selling Items Analysis.</strong> Ranks items by total quantity sold and revenue generated over the selected period.<br>
            Only includes <strong>delivered orders</strong> (status=1). Shows item performance, profit margins, and customer demand patterns.
        </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Period</label>
                    <select name="period" class="form-select">
                        <option value="7" <?= $period == '7' ? 'selected' : '' ?>>Last 7 days</option>
                        <option value="30" <?= $period == '30' ? 'selected' : '' ?>>Last 30 days</option>
                        <option value="90" <?= $period == '90' ? 'selected' : '' ?>>Last 90 days</option>
                        <option value="365" <?= $period == '365' ? 'selected' : '' ?>>Last year</option>
                        <option value="all" <?= $period == 'all' ? 'selected' : '' ?>>All time</option>
                        <option value="custom" <?= ($start_date && $end_date) ? 'selected' : '' ?>>Custom range</option>
                    </select>
                </div>
                <div class="col-md-3" id="start-date-col" style="display: <?= ($start_date && $end_date) ? 'block' : 'none' ?>">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-select" value="<?= h($start_date) ?>">
                </div>
                <div class="col-md-3" id="end-date-col" style="display: <?= ($start_date && $end_date) ? 'block' : 'none' ?>">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-select" value="<?= h($end_date) ?>">
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
                    <h5 class="card-title">Total Revenue</h5>
                    <h3><?= format_currency($totalRevenue) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Items Sold</h5>
                    <h3><?= number_format($totalQuantity) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Unique Items</h5>
                    <h3><?= number_format($uniqueItems ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Avg Revenue/Item</h5>
                    <h3><?= format_currency($totalRevenue / max(1, $uniqueItems ?? 1)) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Top 10 Items by Quantity</h5>
                </div>
                <div class="card-body">
                    <canvas id="quantityChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Top 10 Items by Revenue</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card">
        <div class="card-header">
            <h5>Top Selling Items Details</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="topItemsTable" class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty Sold</th>
                            <th>Revenue</th>
                            <th>Avg Price</th>
                            <th>Customers</th>
                            <th>Orders</th>
                            <th>Revenue %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topItems as $item): ?>
                        <tr>
                            <td><?= h($item['name']) ?></td>
                            <td><?= number_format($item['total_sold']) ?></td>
                            <td><?= format_currency($item['revenue']) ?></td>
                            <td><?= format_currency($item['avg_price']) ?></td>
                            <td><?= number_format($item['unique_customers']) ?></td>
                            <td><?= number_format($item['order_count']) ?></td>
                            <td><?= number_format(($item['revenue'] / max(1, $totalRevenue)) * 100, 1) ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize DataTable
$(document).ready(function() {
    $('#topItemsTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[1, 'desc']],
        columnDefs: [
            { targets: [1, 2, 3, 4, 5, 6], className: 'text-end' }
        ]
    });

    // Period filter change handler
    $('select[name="period"]').change(function() {
        if ($(this).val() === 'custom') {
            $('#start-date-col, #end-date-col').show();
        } else {
            $('#start-date-col, #end-date-col').hide();
        }
    });

    // Initialize charts
    initializeCharts();
});

function initializeCharts() {
    const items = <?= json_encode($topItems) ?>;
    const top10 = items.slice(0, 10);
    
    // Quantity Chart
    new Chart(document.getElementById('quantityChart'), {
        type: 'bar',
        data: {
            labels: top10.map(item => item.name.length > 20 ? item.name.substring(0, 20) + '...' : item.name),
            datasets: [{
                label: 'Quantity Sold',
                data: top10.map(item => item.total_sold),
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
                y: { beginAtZero: true }
            }
        }
    });
    
    // Revenue Chart  
    new Chart(document.getElementById('revenueChart'), {
        type: 'doughnut',
        data: {
            labels: top10.map(item => item.name.length > 15 ? item.name.substring(0, 15) + '...' : item.name),
            datasets: [{
                data: top10.map(item => item.revenue),
                backgroundColor: [
                    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                    '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { usePointStyle: true }
                }
            }
        }
    });
}

function exportData() {
    // Simple CSV export
    const table = document.getElementById('topItemsTable');
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
    downloadLink.download = 'top_selling_items.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.click();
}
</script>

</div> <!-- Close container -->
</div> <!-- Close page-content-wrapper -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>