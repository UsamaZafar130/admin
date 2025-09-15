<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Items Sold by Period";
require_once __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

// Get filter parameters
$period = $_GET['period'] ?? 'monthly';
$year = $_GET['year'] ?? date('Y');
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Get sales data by period
$salesData = [];
$totalSales = 0;
$totalRevenue = 0;

try {
    if ($pdo) {
        // Build period-based query
        $dateFormat = '';
        $groupBy = '';
        $orderBy = '';
        
        if ($period === 'daily') {
            $dateFormat = 'DATE(so.order_date)';
            $groupBy = 'DATE(so.order_date)';
            $orderBy = 'DATE(so.order_date) DESC';
        } elseif ($period === 'weekly') {
            $dateFormat = 'YEARWEEK(so.order_date, 1)';
            $groupBy = 'YEARWEEK(so.order_date, 1)';
            $orderBy = 'YEARWEEK(so.order_date, 1) DESC';
        } elseif ($period === 'monthly') {
            $dateFormat = 'DATE_FORMAT(so.order_date, "%Y-%m")';
            $groupBy = 'YEAR(so.order_date), MONTH(so.order_date)';
            $orderBy = 'YEAR(so.order_date) DESC, MONTH(so.order_date) DESC';
        } else { // yearly
            $dateFormat = 'YEAR(so.order_date)';
            $groupBy = 'YEAR(so.order_date)';
            $orderBy = 'YEAR(so.order_date) DESC';
        }

        // Date filter condition
        $dateCondition = '';
        $params = [];
        if ($start_date && $end_date) {
            $dateCondition = 'AND so.order_date BETWEEN ? AND ?';
            $params = [$start_date, $end_date];
        } elseif ($year && $period !== 'yearly') {
            $dateCondition = 'AND YEAR(so.order_date) = ?';
            $params = [$year];
        }

        // Get period-based sales data
        $stmt = $pdo->prepare("
            SELECT 
                $dateFormat as period,
                COUNT(DISTINCT oi.item_id) as unique_items,
                SUM(oi.qty) as total_qty,
                SUM(oi.total) as total_revenue,
                COUNT(DISTINCT so.id) as order_count,
                COUNT(DISTINCT so.customer_id) as unique_customers
            FROM order_items oi
            JOIN sales_orders so ON oi.order_id = so.id
            WHERE so.cancelled = 0 AND so.delivered = 1 $dateCondition
            GROUP BY $groupBy
            ORDER BY $orderBy
            LIMIT 50
        ");
        $stmt->execute($params);
        $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate totals
        foreach ($salesData as $data) {
            $totalSales += $data['total_qty'];
            $totalRevenue += $data['total_revenue'];
        }
    }
} catch (Exception $e) {
    error_log('Items sold by period error: ' . $e->getMessage());
}
?>

<div class="page-content-wrapper">
    <div class="container mt-3">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="text-primary"><i class="fas fa-calendar-alt me-2"></i> Items Sold by Period</h2>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-success btn-3d" onclick="exportData()">
                    <i class="fas fa-download me-1"></i> Export
                </button>
            </div>
        </div>

        <div class="alert alert-info max-width-700 mb-4">
            <strong>Period-wise Item Sales Analysis.</strong> Analysis of item sales across different time periods (daily, weekly, monthly, yearly).<br>
            Only includes <strong>delivered orders</strong> (status=1). Shows quantity sold and revenue generated for each period.
        </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Period</label>
                    <select name="period" class="form-select">
                        <option value="daily" <?= $period == 'daily' ? 'selected' : '' ?>>Daily</option>
                        <option value="weekly" <?= $period == 'weekly' ? 'selected' : '' ?>>Weekly</option>
                        <option value="monthly" <?= $period == 'monthly' ? 'selected' : '' ?>>Monthly</option>
                        <option value="yearly" <?= $period == 'yearly' ? 'selected' : '' ?>>Yearly</option>
                    </select>
                </div>
                <div class="col-md-2" id="year-col" style="display: <?= $period !== 'yearly' ? 'block' : 'none' ?>">
                    <label class="form-label">Year</label>
                    <select name="year" class="form-select">
                        <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                        <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= h($start_date) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= h($end_date) ?>">
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
                    <h5 class="card-title">Total Periods</h5>
                    <h3><?= count($salesData) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Revenue</h5>
                    <h3><?= format_currency($totalRevenue) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Items Sold</h5>
                    <h3><?= number_format($totalSales) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Avg/Period</h5>
                    <h3><?= count($salesData) > 0 ? format_currency($totalRevenue / count($salesData)) : format_currency(0) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Sales Trend - Items Sold & Revenue</h5>
                </div>
                <div class="card-body">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card">
        <div class="card-header">
            <h5>Period-wise Sales Details</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="salesTable" class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Items Sold</th>
                            <th>Revenue</th>
                            <th>Unique Items</th>
                            <th>Orders</th>
                            <th>Customers</th>
                            <th>Avg Order Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($salesData as $data): ?>
                        <tr>
                            <td><?= h($data['period']) ?></td>
                            <td><?= number_format($data['total_qty']) ?></td>
                            <td><?= format_currency($data['total_revenue']) ?></td>
                            <td><?= number_format($data['unique_items']) ?></td>
                            <td><?= number_format($data['order_count']) ?></td>
                            <td><?= number_format($data['unique_customers']) ?></td>
                            <td><?= format_currency($data['order_count'] > 0 ? $data['total_revenue'] / $data['order_count'] : 0) ?></td>
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
    $('#salesTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[0, 'desc']],
        columnDefs: [
            { targets: [1, 2, 3, 4, 5, 6], className: 'text-end' }
        ]
    });

    // Period filter change handler
    $('select[name="period"]').change(function() {
        if ($(this).val() === 'yearly') {
            $('#year-col').hide();
        } else {
            $('#year-col').show();
        }
    });

    initializeTrendChart();
});

function initializeTrendChart() {
    const salesData = <?= json_encode(array_reverse($salesData)) ?>;
    
    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: salesData.map(data => data.period),
            datasets: [{
                label: 'Items Sold',
                data: salesData.map(data => data.total_qty),
                borderColor: '#36A2EB',
                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                yAxisID: 'y'
            }, {
                label: 'Revenue (Rs.)',
                data: salesData.map(data => data.total_revenue),
                borderColor: '#FF6384',
                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: '<?= ucfirst($period) ?> Period'
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Items Sold'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Revenue (Rs.)'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });
}

function exportData() {
    const table = document.getElementById('salesTable');
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
    downloadLink.download = 'items_sold_by_period.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.click();
}
</script>

</div> <!-- Close container -->
</div> <!-- Close page-content-wrapper -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>