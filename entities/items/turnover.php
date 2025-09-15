<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Inventory Turnover";
require_once __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

// Get filter parameters
$period = $_GET['period'] ?? '90';
$category = $_GET['category'] ?? '';

// Get inventory turnover data
$turnoverData = [];
$avgTurnover = 0;
$totalItems = 0;

try {
    if ($pdo) {
        // Build category filter
        $categoryCondition = '';
        $params = [$period];
        if ($category) {
            $categoryCondition = 'AND i.category_id = ?';
            $params[] = $category;
        }

        // Get inventory turnover calculation
        $stmt = $pdo->prepare("
            SELECT 
                i.id,
                i.name,
                i.price_per_unit,
                COALESCE(inventory.current_stock, 0) as current_stock,
                COALESCE(sales.total_sold, 0) as total_sold,
                COALESCE(sales.total_revenue, 0) as total_revenue,
                CASE 
                    WHEN COALESCE(inventory.current_stock, 0) > 0 AND sales.total_sold > 0 
                    THEN ROUND(sales.total_sold / ((COALESCE(inventory.current_stock, 0) + COALESCE(sales.total_sold, 0)) / 2), 2)
                    ELSE 0 
                END as turnover_ratio,
                CASE 
                    WHEN sales.total_sold > 0 
                    THEN ROUND(? / (sales.total_sold / ?), 0)
                    ELSE 0 
                END as days_to_sell_inventory
            FROM items i
            LEFT JOIN (
                SELECT 
                    item_id,
                    SUM(CASE 
                        WHEN change_type IN ('add', 'manufacture') THEN qty 
                        WHEN change_type IN ('remove', 'delivery') THEN -qty 
                        ELSE 0 
                    END) as current_stock
                FROM inventory_ledger
                GROUP BY item_id
            ) inventory ON i.id = inventory.item_id
            LEFT JOIN (
                SELECT 
                    oi.item_id,
                    SUM(oi.qty) as total_sold,
                    SUM(oi.total) as total_revenue
                FROM order_items oi
                JOIN sales_orders so ON oi.order_id = so.id
                WHERE so.cancelled = 0 AND so.delivered = 1 
                AND so.order_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY oi.item_id
            ) sales ON i.id = sales.item_id
            WHERE i.deleted_at IS NULL $categoryCondition
            ORDER BY turnover_ratio DESC, total_sold DESC
        ");
        
        // Add period twice for days calculation, then once for sales filter
        $fullParams = [$period, $period, $period];
        if ($category) {
            $fullParams[] = $category;
        }
        
        $stmt->execute($fullParams);
        $turnoverData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate averages
        $totalItems = count($turnoverData);
        $avgTurnover = $totalItems > 0 ? array_sum(array_column($turnoverData, 'turnover_ratio')) / $totalItems : 0;

        // Get categories for filter
        $stmt = $pdo->prepare("SELECT id as category_id, name as category_name FROM categories ORDER BY name");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log('Inventory turnover error: ' . $e->getMessage());
    $categories = [];
}
?>

<div class="page-content-wrapper">
    <div class="container mt-3">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="text-primary"><i class="fas fa-sync-alt me-2"></i> Inventory Turnover</h2>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-success btn-3d" onclick="exportData()">
                    <i class="fas fa-download me-1"></i> Export
                </button>
            </div>
        </div>

        <div class="alert alert-info max-width-700 mb-4">
            <strong>Inventory Turnover Analysis.</strong> Shows how quickly items sell. Calculated as (Total Units Sold / Average Stock on Hand) over the selected period.<br>
            Only <strong>delivered orders</strong> counted. Higher ratios indicate fast-moving items.
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
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= h($cat['category_id']) ?>" <?= $category == $cat['category_id'] ? 'selected' : '' ?>>
                            <?= h($cat['category_name']) ?>
                        </option>
                        <?php endforeach; ?>
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
                    <h5 class="card-title">Total Items</h5>
                    <h3><?= number_format($totalItems) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Avg Turnover Ratio</h5>
                    <h3><?= number_format($avgTurnover, 2) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Fast Moving Items</h5>
                    <h3><?= count(array_filter($turnoverData, function($item) { return $item['turnover_ratio'] > 2; })) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Slow Moving Items</h5>
                    <h3><?= count(array_filter($turnoverData, function($item) { return $item['turnover_ratio'] > 0 && $item['turnover_ratio'] < 1; })) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Turnover Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="turnoverChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Top 10 Fast Moving Items</h5>
                </div>
                <div class="card-body">
                    <canvas id="fastMovingChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card">
        <div class="card-header">
            <h5>Inventory Turnover Details</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="turnoverTable" class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Current Stock</th>
                            <th>Sold (Period)</th>
                            <th>Turnover Ratio</th>
                            <th>Days to Sell</th>
                            <th>Revenue</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($turnoverData as $item): ?>
                        <?php 
                            $status = 'Dead Stock';
                            $statusClass = 'danger';
                            if ($item['turnover_ratio'] > 3) {
                                $status = 'Fast Moving';
                                $statusClass = 'success';
                            } elseif ($item['turnover_ratio'] > 1) {
                                $status = 'Medium Moving';
                                $statusClass = 'warning';
                            } elseif ($item['turnover_ratio'] > 0) {
                                $status = 'Slow Moving';
                                $statusClass = 'info';
                            }
                        ?>
                        <tr>
                            <td><?= h($item['name']) ?></td>
                            <td><?= number_format($item['current_stock']) ?></td>
                            <td><?= number_format($item['total_sold']) ?></td>
                            <td><?= number_format($item['turnover_ratio'], 2) ?></td>
                            <td><?= $item['days_to_sell_inventory'] > 0 ? number_format($item['days_to_sell_inventory']) . ' days' : 'N/A' ?></td>
                            <td><?= format_currency($item['total_revenue']) ?></td>
                            <td><span class="badge bg-<?= $statusClass ?>"><?= $status ?></span></td>
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
    $('#turnoverTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[3, 'desc']],
        columnDefs: [
            { targets: [1, 2, 3, 4, 5], className: 'text-end' }
        ]
    });

    initializeCharts();
});

function initializeCharts() {
    const turnoverData = <?= json_encode($turnoverData) ?>;
    
    // Turnover distribution chart
    const fastMoving = turnoverData.filter(item => item.turnover_ratio > 3).length;
    const mediumMoving = turnoverData.filter(item => item.turnover_ratio > 1 && item.turnover_ratio <= 3).length;
    const slowMoving = turnoverData.filter(item => item.turnover_ratio > 0 && item.turnover_ratio <= 1).length;
    const deadStock = turnoverData.filter(item => item.turnover_ratio == 0).length;
    
    new Chart(document.getElementById('turnoverChart'), {
        type: 'doughnut',
        data: {
            labels: ['Fast Moving', 'Medium Moving', 'Slow Moving', 'Dead Stock'],
            datasets: [{
                data: [fastMoving, mediumMoving, slowMoving, deadStock],
                backgroundColor: ['#28a745', '#ffc107', '#17a2b8', '#dc3545']
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
    
    // Fast moving items chart
    const top10FastMoving = turnoverData
        .filter(item => item.turnover_ratio > 0)
        .slice(0, 10);
    
    new Chart(document.getElementById('fastMovingChart'), {
        type: 'bar',
        data: {
            labels: top10FastMoving.map(item => item.name.length > 15 ? item.name.substring(0, 15) + '...' : item.name),
            datasets: [{
                label: 'Turnover Ratio',
                data: top10FastMoving.map(item => item.turnover_ratio),
                backgroundColor: 'rgba(40, 167, 69, 0.8)',
                borderColor: 'rgba(40, 167, 69, 1)',
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
}

function exportData() {
    const table = document.getElementById('turnoverTable');
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
    downloadLink.download = 'inventory_turnover.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.click();
}
</script>

</div> <!-- Close container -->
</div> <!-- Close page-content-wrapper -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>