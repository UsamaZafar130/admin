<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Business Reports & Analytics";
require_once __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

// Get basic analytics data for the overview cards
$analyticsData = [];
try {
    if ($pdo) {
        // Top selling item this month
        $stmt = $pdo->query("
            SELECT i.name, SUM(oi.qty) as total_sold
            FROM order_items oi
            JOIN items i ON oi.item_id = i.id
            JOIN sales_orders so ON oi.order_id = so.id
            WHERE so.cancelled = 0 AND DATE_FORMAT(so.order_date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
            GROUP BY oi.item_id, i.name
            ORDER BY total_sold DESC
            LIMIT 1
        ");
        $topItem = $stmt->fetch(PDO::FETCH_ASSOC);
        $analyticsData['top_item'] = $topItem ? $topItem['name'] : 'N/A';
        $analyticsData['top_item_qty'] = $topItem ? $topItem['total_sold'] : 0;

        // Monthly revenue
        $stmt = $pdo->query("
            SELECT SUM(grand_total) as monthly_revenue
            FROM sales_orders 
            WHERE cancelled = 0 AND DATE_FORMAT(order_date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $analyticsData['monthly_revenue'] = $result['monthly_revenue'] ?? 0;

        // Monthly profit (simplified: revenue - paid purchases this month)
        $stmt = $pdo->query("
            SELECT SUM(amount) as monthly_purchases
            FROM purchase_payments 
            WHERE deleted_at IS NULL AND DATE_FORMAT(paid_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $monthlyPurchases = $result['monthly_purchases'] ?? 0;
        $analyticsData['monthly_profit'] = $analyticsData['monthly_revenue'] - $monthlyPurchases;

        // Total customers this month
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT customer_id) as monthly_customers
            FROM sales_orders 
            WHERE cancelled = 0 AND DATE_FORMAT(order_date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $analyticsData['monthly_customers'] = $result['monthly_customers'] ?? 0;
    }
} catch (Exception $e) {
    error_log('Reports overview error: ' . $e->getMessage());
    $analyticsData = [
        'top_item' => 'N/A',
        'top_item_qty' => 0,
        'monthly_revenue' => 0,
        'monthly_profit' => 0,
        'monthly_customers' => 0
    ];
}

// Reports categories with their available reports
$reportCategories = [
    'Items Analytics' => [
        [
            'name' => 'Top Selling Items',
            'desc' => 'Revenue analysis with quantity tracking and customer insights',
            'url' => '/entities/items/top_selling.php',
            'icon' => 'fa-chart-bar'
        ],
        [
            'name' => 'Items Sold by Period',
            'desc' => 'Temporal sales analysis with daily/weekly/monthly/yearly views',
            'url' => '/entities/items/sold_by_period.php',
            'icon' => 'fa-calendar-alt'
        ],
        [
            'name' => 'Inventory Turnover',
            'desc' => 'Fast/slow moving inventory analysis with turnover ratios',
            'url' => '/entities/items/turnover.php',
            'icon' => 'fa-sync-alt'
        ]
    ],
    'Customer Analytics' => [
        [
            'name' => 'Customer Trends',
            'desc' => 'Comprehensive customer behavior and acquisition analysis',
            'url' => '/entities/customers/trends.php',
            'icon' => 'fa-users'
        ],
        [
            'name' => 'Customer Order Intervals',
            'desc' => 'Purchase frequency and timing pattern analysis',
            'url' => '/entities/customers/intervals.php',
            'icon' => 'fa-clock'
        ],
        [
            'name' => 'Customer Segmentation',
            'desc' => 'Advanced RFM (Recency, Frequency, Monetary) analysis',
            'url' => '/entities/customers/segmentation.php',
            'icon' => 'fa-layer-group'
        ],
        [
            'name' => 'Customer Lifetime Value',
            'desc' => 'Predictive CLV modeling with tier classification',
            'url' => '/entities/customers/clv.php',
            'icon' => 'fa-gem'
        ]
    ],
    'Sales Analytics' => [
        [
            'name' => 'Monthly Sales',
            'desc' => 'Year-over-year comparisons with delivery performance tracking',
            'url' => '/entities/sales/monthly.php',
            'icon' => 'fa-chart-line'
        ],
        [
            'name' => 'Receivables',
            'desc' => 'Aging analysis with overdue tracking and customer breakdown',
            'url' => '/entities/sales/receivables.php',
            'icon' => 'fa-money-bill-wave'
        ]
    ],
    'Purchase & Vendor Analytics' => [
        [
            'name' => 'Payables',
            'desc' => 'Vendor payables with aging analysis for both purchases & expenses',
            'url' => '/entities/purchases/payables.php',
            'icon' => 'fa-file-invoice-dollar'
        ],
        [
            'name' => 'Vendor Reports',
            'desc' => 'Complete vendor performance analysis with status tracking',
            'url' => '/entities/vendors/report.php',
            'icon' => 'fa-truck'
        ]
    ],
    'Financial Analytics' => [
        [
            'name' => 'Profit & Loss',
            'desc' => 'Comprehensive P&L with revenue breakdown and margin analysis',
            'url' => '/entities/reports/profit_loss.php',
            'icon' => 'fa-chart-pie'
        ],
        [
            'name' => 'Balance Sheet',
            'desc' => 'Financial position showing assets, liabilities, and net worth',
            'url' => '/entities/reports/balance_sheet.php',
            'icon' => 'fa-balance-scale'
        ]
    ]
];
?>

<div class="page-content-wrapper">
    <div class="container mt-3">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="text-primary"><i class="fa fa-chart-pie me-2"></i> Business Reports & Analytics</h2>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-outline-primary btn-3d" onclick="window.print()">
                    <i class="fas fa-print me-1"></i>Print Overview
                </button>
            </div>
        </div>
        
        <div class="alert alert-info max-width-700 mb-4">
            <strong>Comprehensive business intelligence and reporting dashboard.</strong><br>
            Access detailed analytics across items, customers, sales, purchases, and financial reports.<br>
            All reports include filtering, export functionality, and interactive visualizations.
        </div>

    <!-- Analytics Overview Cards -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-success bg-gradient rounded-circle d-flex align-items-center justify-content-center icon-container-sm">
                            <i class="fas fa-trophy text-white"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="fw-bold h6 mb-0 text-truncate" title="<?= h($analyticsData['top_item']) ?>">
                            <?= h($analyticsData['top_item']) ?>
                        </div>
                        <div class="text-muted small">Top Item (<?= number_format($analyticsData['top_item_qty']) ?> sold)</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-primary bg-gradient rounded-circle d-flex align-items-center justify-content-center icon-container-sm">
                            <i class="fas fa-dollar-sign text-white"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="fw-bold h5 mb-0"><?= format_currency($analyticsData['monthly_revenue']) ?></div>
                        <div class="text-muted small">Monthly Revenue</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-<?= $analyticsData['monthly_profit'] >= 0 ? 'success' : 'danger' ?> bg-gradient rounded-circle d-flex align-items-center justify-content-center icon-container-sm">
                            <i class="fas fa-chart-line text-white"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="fw-bold h5 mb-0 text-<?= $analyticsData['monthly_profit'] >= 0 ? 'success' : 'danger' ?>">
                            <?= format_currency($analyticsData['monthly_profit']) ?>
                        </div>
                        <div class="text-muted small">Monthly Profit</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-info bg-gradient rounded-circle d-flex align-items-center justify-content-center icon-container-sm">
                            <i class="fas fa-users text-white"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="fw-bold h5 mb-0"><?= number_format($analyticsData['monthly_customers']) ?></div>
                        <div class="text-muted small">Active Customers</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Access Chart -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-area me-2"></i>Quick Analytics Overview
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="quickOverviewChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Reports Categories -->
    <?php foreach ($reportCategories as $categoryName => $reports): ?>
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="text-secondary mb-3">
                <i class="fas fa-folder-open me-2"></i><?= h($categoryName) ?>
            </h4>
        </div>
        <?php foreach ($reports as $report): ?>
        <div class="col-lg-4 col-md-6 mb-3">
            <a href="<?= h($report['url']) ?>" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 report-card">
                    <div class="card-body">
                        <div class="d-flex align-items-start mb-3">
                            <div class="flex-shrink-0">
                                <i class="fas <?= h($report['icon']) ?> h4 mb-0 text-primary"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="card-title mb-1"><?= h($report['name']) ?></h6>
                                <p class="card-text text-muted small mb-0"><?= h($report['desc']) ?></p>
                            </div>
                            <div class="flex-shrink-0">
                                <i class="fas fa-external-link-alt text-muted small"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    </div> <!-- Close container -->
</div> <!-- Close page-content-wrapper -->

<style>
.report-card {
    transition: all 0.3s ease;
}

.report-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

.icon-container-sm {
    width: 40px;
    height: 40px;
}
</style>

<script>
// Quick overview chart
$(document).ready(function() {
    try {
        const ctx = document.getElementById('quickOverviewChart').getContext('2d');
        
        // Get last 7 days data (simplified for demo)
        const labels = [];
        const revenueData = [];
        const today = new Date();
        
        for(let i = 6; i >= 0; i--) {
            const date = new Date(today);
            date.setDate(date.getDate() - i);
            labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
            // Simulate data - in real implementation, this would come from PHP/AJAX
            revenueData.push(Math.floor(Math.random() * 5000) + 1000);
        }
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Daily Revenue',
                    data: revenueData,
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    } catch (error) {
        console.error('Chart initialization error:', error);
        document.getElementById('quickOverviewChart').style.display = 'none';
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>