<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Monthly Sales";
require_once __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

// Get filter parameters
$year = $_GET['year'] ?? date('Y');
$compare_year = $_GET['compare_year'] ?? (date('Y') - 1);

// Get monthly sales data
$monthlyData = [];
$compareData = [];
$totalSales = 0;
$totalOrders = 0;

try {
    if ($pdo) {
        // Get current year monthly data
        $stmt = $pdo->prepare("
            SELECT 
                MONTH(order_date) as month,
                MONTHNAME(order_date) as month_name,
                COUNT(*) as order_count,
                SUM(grand_total) as total_sales,
                AVG(grand_total) as avg_order_value,
                COUNT(DISTINCT customer_id) as unique_customers,
                SUM(CASE WHEN delivered = 1 THEN grand_total ELSE 0 END) as delivered_sales,
                COUNT(CASE WHEN delivered = 1 THEN 1 END) as delivered_orders
            FROM sales_orders 
            WHERE YEAR(order_date) = ? AND cancelled = 0 AND delivered = 1
            GROUP BY MONTH(order_date), MONTHNAME(order_date)
            ORDER BY MONTH(order_date)
        ");
        $stmt->execute([$year]);
        $currentYearData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organize by month number
        foreach ($currentYearData as $data) {
            $monthlyData[$data['month']] = $data;
        }

        // Get comparison year data
        $stmt->execute([$compare_year]);
        $compareYearData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($compareYearData as $data) {
            $compareData[$data['month']] = $data;
        }

        // Calculate totals for current year
        $totalSales = array_sum(array_column($currentYearData, 'total_sales'));
        $totalOrders = array_sum(array_column($currentYearData, 'order_count'));
    }
} catch (Exception $e) {
    error_log('Monthly sales error: ' . $e->getMessage());
}

// Month names for display
$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
?>

<div class="page-content-wrapper">
    <div class="container mt-3">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="text-primary"><i class="fas fa-calendar-check me-2"></i> Monthly Sales</h2>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-success btn-3d" onclick="exportData()">
                    <i class="fas fa-download me-1"></i> Export
                </button>
            </div>
        </div>

        <div class="alert alert-info max-width-700 mb-4">
            <strong>Monthly Sales Analysis.</strong> Monthly sales analysis with year-over-year comparison. Shows revenue, order count, and delivery performance by month.<br>
            Only <strong>delivered orders</strong> (status=1) counted in calculations.
        </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Year</label>
                    <select name="year" class="form-select">
                        <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                        <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Compare with</label>
                    <select name="compare_year" class="form-select">
                        <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                        <option value="<?= $y ?>" <?= $compare_year == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
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
                    <h5 class="card-title">Total Sales <?= $year ?></h5>
                    <h3><?= format_currency($totalSales) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Orders</h5>
                    <h3><?= number_format($totalOrders) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Avg Order Value</h5>
                    <h3><?= $totalOrders > 0 ? format_currency($totalSales / $totalOrders) : format_currency(0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Monthly Average</h5>
                    <h3><?= format_currency($totalSales / 12) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Monthly Sales Comparison: <?= $year ?> vs <?= $compare_year ?></h5>
                </div>
                <div class="card-body">
                    <canvas id="monthlySalesChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Orders vs Sales Trend <?= $year ?></h5>
                </div>
                <div class="card-body">
                    <canvas id="ordersSalesChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Delivery Performance <?= $year ?></h5>
                </div>
                <div class="card-body">
                    <canvas id="deliveryChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card">
        <div class="card-header">
            <h5>Monthly Sales Details</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="monthlySalesTable" class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th><?= $year ?> Sales</th>
                            <th><?= $year ?> Orders</th>
                            <th><?= $compare_year ?> Sales</th>
                            <th><?= $compare_year ?> Orders</th>
                            <th>Sales Growth</th>
                            <th>Orders Growth</th>
                            <th>Avg Order Value</th>
                            <th>Customers</th>
                            <th>Delivery Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($month = 1; $month <= 12; $month++): ?>
                        <?php 
                            $current = $monthlyData[$month] ?? null;
                            $compare = $compareData[$month] ?? null;
                            
                            $currentSales = $current ? $current['total_sales'] : 0;
                            $currentOrders = $current ? $current['order_count'] : 0;
                            $compareSales = $compare ? $compare['total_sales'] : 0;
                            $compareOrders = $compare ? $compare['order_count'] : 0;
                            
                            $salesGrowth = $compareSales > 0 ? (($currentSales - $compareSales) / $compareSales) * 100 : 0;
                            $ordersGrowth = $compareOrders > 0 ? (($currentOrders - $compareOrders) / $compareOrders) * 100 : 0;
                            
                            $deliveryRate = $current && $current['order_count'] > 0 ? 
                                ($current['delivered_orders'] / $current['order_count']) * 100 : 0;
                        ?>
                        <tr>
                            <td><strong><?= $monthNames[$month] ?></strong></td>
                            <td><?= format_currency($currentSales) ?></td>
                            <td><?= number_format($currentOrders) ?></td>
                            <td class="text-muted"><?= format_currency($compareSales) ?></td>
                            <td class="text-muted"><?= number_format($compareOrders) ?></td>
                            <td>
                                <?php if ($salesGrowth > 0): ?>
                                    <span class="text-success">+<?= number_format($salesGrowth, 1) ?>%</span>
                                <?php elseif ($salesGrowth < 0): ?>
                                    <span class="text-danger"><?= number_format($salesGrowth, 1) ?>%</span>
                                <?php else: ?>
                                    <span class="text-muted">0%</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($ordersGrowth > 0): ?>
                                    <span class="text-success">+<?= number_format($ordersGrowth, 1) ?>%</span>
                                <?php elseif ($ordersGrowth < 0): ?>
                                    <span class="text-danger"><?= number_format($ordersGrowth, 1) ?>%</span>
                                <?php else: ?>
                                    <span class="text-muted">0%</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $current ? format_currency($current['avg_order_value']) : format_currency(0) ?></td>
                            <td><?= $current ? number_format($current['unique_customers']) : 0 ?></td>
                            <td><?= number_format($deliveryRate, 1) ?>%</td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#monthlySalesTable').DataTable({
        responsive: true,
        pageLength: 12,
        paging: false,
        columnDefs: [
            { targets: [1, 2, 3, 4, 7, 8, 9], className: 'text-end' }
        ]
    });

    initializeCharts();
});

function initializeCharts() {
    const monthlyData = <?= json_encode($monthlyData) ?>;
    const compareData = <?= json_encode($compareData) ?>;
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    
    // Prepare data arrays
    const currentSales = [];
    const compareSales = [];
    const currentOrders = [];
    const deliveryRates = [];
    
    for (let month = 1; month <= 12; month++) {
        currentSales.push(monthlyData[month] ? monthlyData[month].total_sales : 0);
        compareSales.push(compareData[month] ? compareData[month].total_sales : 0);
        currentOrders.push(monthlyData[month] ? monthlyData[month].order_count : 0);
        
        const deliveryRate = monthlyData[month] && monthlyData[month].order_count > 0 ? 
            (monthlyData[month].delivered_orders / monthlyData[month].order_count) * 100 : 0;
        deliveryRates.push(deliveryRate);
    }
    
    // Monthly sales comparison chart
    new Chart(document.getElementById('monthlySalesChart'), {
        type: 'line',
        data: {
            labels: monthNames,
            datasets: [{
                label: '<?= $year ?> Sales',
                data: currentSales,
                borderColor: '#36A2EB',
                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                fill: false
            }, {
                label: '<?= $compare_year ?> Sales',
                data: compareSales,
                borderColor: '#FF6384',
                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                fill: false
            }]
        },
        options: {
            responsive: true,
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
    
    // Orders vs Sales dual axis chart
    new Chart(document.getElementById('ordersSalesChart'), {
        type: 'bar',
        data: {
            labels: monthNames,
            datasets: [{
                label: 'Sales (Rs.)',
                data: currentSales,
                backgroundColor: 'rgba(54, 162, 235, 0.8)',
                yAxisID: 'y'
            }, {
                label: 'Orders',
                data: currentOrders,
                type: 'line',
                borderColor: '#FF6384',
                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    type: 'linear',
                    position: 'left',
                    title: { display: true, text: 'Sales (Rs.)' }
                },
                y1: {
                    type: 'linear',
                    position: 'right',
                    title: { display: true, text: 'Orders' },
                    grid: { drawOnChartArea: false }
                }
            }
        }
    });
    
    // Delivery performance chart
    new Chart(document.getElementById('deliveryChart'), {
        type: 'bar',
        data: {
            labels: monthNames,
            datasets: [{
                label: 'Delivery Rate (%)',
                data: deliveryRates,
                backgroundColor: deliveryRates.map(rate => rate >= 90 ? '#28a745' : rate >= 70 ? '#ffc107' : '#dc3545'),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { 
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            }
        }
    });
}

function exportData() {
    const table = document.getElementById('monthlySalesTable');
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
    downloadLink.download = 'monthly_sales.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.click();
}
</script>

</div> <!-- Close container -->
</div> <!-- Close page-content-wrapper -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>