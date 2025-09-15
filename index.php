<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/dashboard_stats.php';
$pageTitle = "FrozoFun Admin Dashboard";

// Load user dashboard preferences
$dashboardPrefs = [];
try {
    $pdo = null;
    @include __DIR__ . '/includes/db_connection.php';
    if ($pdo && !empty($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT widget_id, is_visible, sort_order FROM user_dashboard_prefs WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $prefs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($prefs as $pref) {
            $dashboardPrefs[$pref['widget_id']] = [
                'visible' => (bool)$pref['is_visible'],
                'order' => (int)$pref['sort_order']
            ];
        }
    }
} catch (Exception $e) {
    error_log('Error loading dashboard preferences: ' . $e->getMessage());
}

// Set defaults for widgets not in preferences
$defaultWidgets = [
    'stats-cards' => ['visible' => true, 'order' => 1],
    'business-metrics' => ['visible' => true, 'order' => 2],
    'financial-metrics' => ['visible' => true, 'order' => 3],
    'module-cards' => ['visible' => true, 'order' => 4],
    'analytics-dashboard' => ['visible' => true, 'order' => 5]
];

foreach ($defaultWidgets as $widgetId => $default) {
    if (!isset($dashboardPrefs[$widgetId])) {
        $dashboardPrefs[$widgetId] = $default;
    }
}

// Sort widgets by order
uasort($dashboardPrefs, function($a, $b) {
    return $a['order'] - $b['order'];
});

include __DIR__ . '/includes/header.php';

// Get dashboard statistics and activity
$stats = [];
$activity = [];
$stockRequirements = ['items' => [], 'batch_id' => null];
try {
    $pdo = null;
    @include __DIR__ . '/includes/db_connection.php';
    if ($pdo) {
        $stats = getDashboardStats($pdo);
        $activity = getRecentActivity($pdo);
        $stockRequirements = getCurrentBatchStockRequirements($pdo, 3);
    } else {
        throw new Exception('No database connection');
    }
} catch (Throwable $e) {
    $stats = [
        'items' => 0, 
        'customers' => 0, 
        'orders' => 0, 
        'vendors' => 0,
        'batches' => 0, 
        'purchases' => 0, 
        'expenses' => 0, 
        'users' => 0
    ];
    $activity = [
        'orders_today' => 0,
        'pending_orders' => 0, 
        'current_batch_total' => 0,
        'total_receivables' => 0,
        'total_payables' => 0
    ];
    error_log('Dashboard stats error: ' . $e->getMessage());
}

// Get upcoming batch for shortcut (not strictly required for summary button; kept)
$batchShortcutId = null;
try {
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT id FROM shipping_batches WHERE batch_date >= CURDATE() ORDER BY batch_date ASC, id ASC LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['id'])) {
            $batchShortcutId = (int)$row['id'];
        }
    }
} catch (Throwable $e) {
    $batchShortcutId = null;
}

// Original dashboard entity cards
$entities = [
    [
        'name' => 'Items',
        'desc' => 'Manage products, pricing, and categories.',
        'icon' => 'fa fa-box',
        'color' => 'accent1',
        'url' => '/entities/items/list.php'
    ],
    [
        'name' => 'Customers',
        'desc' => 'View, add, and manage your customers, balances, and orders.',
        'icon' => 'fa fa-users',
        'color' => 'accent2',
        'url' => '/entities/customers/list.php'
    ],
    [
        'name' => 'Sales',
        'desc' => 'Create and manage orders, shipping, and invoices.',
        'icon' => 'fa fa-cash-register',
        'color' => 'accent5',
        'url' => '/entities/sales/orders_list.php'
    ],
    [
        'name' => 'Batches',
        'desc' => 'Group orders, manage delivery, print docs & bulk operations.',
        'icon' => 'fa fa-layer-group',
        'color' => 'accent10',
        'url' => '/entities/batches/list.php'
    ],
    [
        'name' => 'Purchases',
        'desc' => 'Record and review purchases and expenses.',
        'icon' => 'fa fa-shopping-cart',
        'color' => 'accent4',
        'url' => '/entities/purchases/list.php'
    ],
    [
        'name' => 'Expenses',
        'desc' => 'Manage and track your business expenses.',
        'icon' => 'fa fa-credit-card',
        'color' => 'accent9',
        'url' => '/entities/expenses/list.php'
    ],
    [
        'name' => 'Vendors',
        'desc' => 'Track vendors, payments, and outstanding balances.',
        'icon' => 'fa fa-truck',
        'color' => 'accent3',
        'url' => '/entities/vendors/list.php'
    ],
    [
        'name' => 'Inventory',
        'desc' => 'Stock management, packing, and fulfilment.',
        'icon' => 'fa fa-warehouse',
        'color' => 'accent6',
        'url' => '/entities/inventory/list.php'
    ],
    [
        'name' => 'Users',
        'desc' => 'User management and permissions.',
        'icon' => 'fa fa-user-shield',
        'color' => 'accent7',
        'url' => '/entities/users/list.php'
    ],
    [
        'name' => 'Reports',
        'desc' => 'Business analytics and comprehensive reporting dashboard.',
        'icon' => 'fa fa-chart-line',
        'color' => 'accent8',
        'url' => '/entities/reports/index.php'
    ],
];
?>

<div class="container-fluid">
    <!-- Hero -->
    <div class="dashboard-hero animate-fade-in-up">
        <div class="row align-items-center">
            <div class="col-lg-8 col-md-12">
                <div class="hero-content">
                    <h1>FrozoFun Business Suite</h1>
                    <p class="lead">Transform your business operations with our comprehensive management platform. Monitor performance, track inventory, and grow your business with intelligent insights.</p>
                    <div class="hero-actions">
                        <button id="edit-layout-btn" class="btn btn-modern btn-outline-light">
                            <i class="fas fa-edit me-2"></i>Customize Dashboard
                        </button>
                        <a href="/entities/reports/index.php" class="btn btn-modern bg-glass">
                            <i class="fas fa-chart-line me-2"></i>View Reports
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-12 text-center animate-slide-in-right">
                <div class="hero-stats p-4">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="h3 fw-bold mb-1"><?= number_format($stats['orders']) ?></div>
                            <div class="small opacity-75">Total Orders</div>
                        </div>
                        <div class="col-6">
                            <div class="h3 fw-bold mb-1"><?= number_format($stats['customers']) ?></div>
                            <div class="small opacity-75">Customers</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tip -->
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <i class="fas fa-lightbulb me-2"></i>
        <strong>Dashboard Tips:</strong> Use "Customize Dashboard" to rearrange cards by dragging them. Access Settings to show/hide widget categories and personalize your workspace.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <div id="dashboard-rows">
        <?php
        function isCardVisible($cardId) {
            global $dashboardPrefs;
            if (isset($dashboardPrefs[$cardId])) {
                return $dashboardPrefs[$cardId]['visible'];
            }
            $groupMappings = [
                'stat-items' => 'stats-cards',
                'stat-customers' => 'stats-cards', 
                'stat-orders' => 'stats-cards',
                'stat-vendors' => 'stats-cards',
                'metric-orders-today' => 'business-metrics',
                'metric-pending-orders' => 'business-metrics',
                'metric-current-batch' => 'business-metrics',
                'metric-dailies' => 'business-metrics',
                'financial-receivables' => 'financial-metrics',
                'financial-payables' => 'financial-metrics',
                'financial-stock-required' => 'financial-metrics',
                'analytics-dashboard' => 'analytics-dashboard'
            ];
            if (strpos($cardId, 'module-') === 0) {
                $groupMappings[$cardId] = 'module-cards';
            }
            $groupId = $groupMappings[$cardId] ?? null;
            if ($groupId && isset($dashboardPrefs[$groupId])) {
                return $dashboardPrefs[$groupId]['visible'];
            }
            return true;
        }
        function createCardHtml($cardId, $content, $isVisible = true) {
            if ($isVisible) return $content;
            preg_match('/class="(col-[^"]*)"/', $content, $matches);
            $colClass = $matches[1] ?? 'col-xl-3 col-lg-4 col-md-6 col-sm-6';
            return '<div class="' . $colClass . ' mb-3"><div class="dashboard-card-placeholder" data-card-id="' . $cardId . '"></div></div>';
        }

        // Row 1
        $row1Cards = [
            'stat-items' => [
                'visible' => isCardVisible('stat-items'),
                'html' => '
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
                    <div class="dashboard-card h-100" data-card-id="stat-items">
                        <div class="card stat-card modern-card h-100">
                            <div class="card-body d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="icon-container-sm">
                                        <i class="fas fa-cube text-white"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold h4 mb-0 text-gradient">' . number_format($stats['items']) . '</div>
                                    <div class="text-muted small">Total Items</div>
                                </div>
                                <div class="drag-handle d-none"><i class="fas fa-grip-vertical"></i></div>
                            </div>
                        </div>
                    </div>
                </div>'
            ],
            'stat-customers' => [
                'visible' => isCardVisible('stat-customers'),
                'html' => '
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
                    <div class="dashboard-card h-100" data-card-id="stat-customers">
                        <div class="card stat-card modern-card h-100">
                            <div class="card-body d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="icon-container-sm icon-container-success">
                                        <i class="fas fa-users text-white"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold h4 mb-0 text-gradient">' . number_format($stats['customers']) . '</div>
                                    <div class="text-muted small">Total Customers</div>
                                </div>
                                <div class="drag-handle d-none"><i class="fas fa-grip-vertical"></i></div>
                            </div>
                        </div>
                    </div>
                </div>'
            ],
            'stat-orders' => [
                'visible' => isCardVisible('stat-orders'),
                'html' => '
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
                    <div class="dashboard-card h-100" data-card-id="stat-orders">
                        <div class="card stat-card modern-card h-100">
                            <div class="card-body d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="icon-container-sm icon-container-info">
                                        <i class="fas fa-shopping-cart text-white"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold h4 mb-0 text-gradient">' . number_format($stats['orders']) . '</div>
                                    <div class="text-muted small">Total Orders</div>
                                </div>
                                <div class="drag-handle d-none"><i class="fas fa-grip-vertical"></i></div>
                            </div>
                        </div>
                    </div>
                </div>'
            ],
            'stat-vendors' => [
                'visible' => isCardVisible('stat-vendors'),
                'html' => '
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
                    <div class="dashboard-card h-100" data-card-id="stat-vendors">
                        <div class="card stat-card modern-card h-100">
                            <div class="card-body d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="icon-container-sm icon-container-warning">
                                        <i class="fas fa-truck text-white"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold h4 mb-0 text-gradient">' . number_format($stats['vendors']) . '</div>
                                    <div class="text-muted small">Total Vendors</div>
                                </div>
                                <div class="drag-handle d-none"><i class="fas fa-grip-vertical"></i></div>
                            </div>
                        </div>
                    </div>
                </div>'
            ]
        ];

        // Row 2
        $batchLink = $stockRequirements['batch_id'] 
            ? "/entities/batches/batch.php?id=" . $stockRequirements['batch_id']
            : "/entities/batches/list.php";
        $batchText = $stockRequirements['batch_id'] ? "View Batch" : "View Batches";
        $currentBatchSummaryBtn = $stockRequirements['batch_id']
            ? '<button type="button" class="btn btn-modern btn-outline-secondary ms-2" id="btn-current-batch-summary" data-batch-id="' . (int)$stockRequirements['batch_id'] . '">
                    <i class="fas fa-list me-1"></i>Summary
               </button>'
            : '';

        $row2Cards = [
            'metric-orders-today' => [
                'visible' => isCardVisible('metric-orders-today'),
                'html' => '
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
                    <div class="dashboard-card h-100" data-card-id="metric-orders-today">
                        <div class="card metric-card modern-card h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <div class="icon-container-md icon-container-success">
                                        <i class="fas fa-calendar-day text-white h4 mb-0"></i>
                                    </div>
                                </div>
                                <h5 class="card-title">Orders Today</h5>
                                <p class="card-text display-6 fw-bold text-success">' . number_format($activity['orders_today']) . '</p>
                                <a href="/entities/sales/orders_list.php?filter=today" class="btn btn-modern btn-outline-success">View Today\'s Orders</a>
                                <div class="drag-handle d-none"><i class="fas fa-grip-vertical"></i></div>
                            </div>
                        </div>
                    </div>
                </div>'
            ],
            'metric-pending-orders' => [
                'visible' => isCardVisible('metric-pending-orders'),
                'html' => '
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
                    <div class="dashboard-card h-100" data-card-id="metric-pending-orders">
                        <div class="card metric-card modern-card h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <div class="icon-container-md icon-container-warning">
                                        <i class="fas fa-clock text-white h4 mb-0"></i>
                                    </div>
                                </div>
                                <h5 class="card-title">Pending Orders</h5>
                                <p class="card-text display-6 fw-bold text-warning">' . number_format($activity['pending_orders']) . '</p>
                                <a href="/entities/sales/orders_list.php?filter=pending" class="btn btn-modern btn-outline-warning">View Pending</a>
                                <div class="drag-handle d-none"><i class="fas fa-grip-vertical"></i></div>
                            </div>
                        </div>
                    </div>
                </div>'
            ],
            'metric-current-batch' => [
                'visible' => isCardVisible('metric-current-batch'),
                'html' => '
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
                    <div class="dashboard-card h-100" data-card-id="metric-current-batch">
                        <div class="card metric-card modern-card h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <div class="icon-container-md">
                                        <i class="fas fa-boxes text-white h4 mb-0"></i>
                                    </div>
                                </div>
                                <h5 class="card-title">Current Batch</h5>
                                <p class="card-text display-6 fw-bold text-primary">' . format_currency($activity['current_batch_total']) . '</p>
                                <div class="d-flex justify-content-center flex-wrap gap-2">
                                    <a href="' . $batchLink . '" class="btn btn-modern btn-outline-primary">' . $batchText . '</a>'
                                    . $currentBatchSummaryBtn .
                                '</div>
                                <div class="drag-handle d-none"><i class="fas fa-grip-vertical"></i></div>
                            </div>
                        </div>
                    </div>
                </div>'
            ],
            // New Dailies widget goes right after Current Batch widget
            'metric-dailies' => [
                'visible' => isCardVisible('metric-dailies'),
                'html' => '
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
                    <div class="dashboard-card h-100" data-card-id="metric-dailies">
                        <div class="card metric-card modern-card h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <div class="icon-container-md icon-container-primary">
                                        <i class="fas fa-calendar-check text-white h4 mb-0"></i>
                                    </div>
                                </div>
                                <h5 class="card-title">Dailies</h5>
                                <p class="text-muted small mb-2">Record today\'s entries and view cash position</p>
                                <div class="d-flex justify-content-center flex-wrap gap-2">
                                    <button type="button" class="btn btn-modern btn-outline-primary" data-bs-toggle="modal" data-bs-target="#dailyJournalModal">
                                        <i class="fas fa-book me-1"></i>Journal
                                    </button>
                                    <button type="button" class="btn btn-modern btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#dailyReportModal">
                                        <i class="fas fa-file-invoice-dollar me-1"></i>Report
                                    </button>
                                </div>
                                <div class="drag-handle d-none"><i class="fas fa-grip-vertical"></i></div>
                            </div>
                        </div>
                    </div>
                </div>'
            ]
        ];

        // Row 3
        $stockDisplay = count($stockRequirements['items']) > 0 
            ? '<p class="card-text display-6 fw-bold text-warning">' . count($stockRequirements['items']) . '</p>
               <button class="btn btn-outline-secondary btn-sm" id="view-stock-requirements">View All</button>'
            : '<p class="card-text display-6 fw-bold text-success">0</p>
               <span class="text-muted small">All items in stock!</span>';

        $row3Cards = [
            'financial-receivables' => [
                'visible' => isCardVisible('financial-receivables'),
                'html' => '
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
                    <div class="dashboard-card h-100" data-card-id="financial-receivables">
                        <div class="card metric-card modern-card h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <div class="icon-container-md icon-container-info">
                                        <i class="fas fa-money-bill-wave text-white h4 mb-0"></i>
                                    </div>
                                </div>
                                <h5 class="card-title">Total Receivables</h5>
                                <p class="card-text display-6 fw-bold text-info">' . format_currency($activity['total_receivables']) . '</p>
                                <a href="/entities/sales/orders_list.php?filter=unpaid" class="btn btn-modern btn-outline-info">View Unpaid</a>
                                <div class="drag-handle d-none"><i class="fas fa-grip-vertical"></i></div>
                            </div>
                        </div>
                    </div>
                </div>'
            ],
            'financial-payables' => [
                'visible' => isCardVisible('financial-payables'),
                'html' => '
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
                    <div class="dashboard-card h-100" data-card-id="financial-payables">
                        <div class="card metric-card modern-card h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <div class="icon-container-md icon-container-danger">
                                        <i class="fas fa-file-invoice-dollar text-white h4 mb-0"></i>
                                    </div>
                                </div>
                                <h5 class="card-title">Total Payables</h5>
                                <p class="card-text display-6 fw-bold text-danger">' . format_currency($activity['total_payables']) . '</p>
                                <a href="/entities/purchases/list.php" class="btn btn-modern btn-outline-danger">View Payables</a>
                                <div class="drag-handle d-none"><i class="fas fa-grip-vertical"></i></div>
                            </div>
                        </div>
                    </div>
                </div>'
            ],
            'financial-stock-required' => [
                'visible' => isCardVisible('financial-stock-required'),
                'html' => '
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
                    <div class="dashboard-card h-100" data-card-id="financial-stock-required">
                        <div class="card metric-card modern-card h-100">
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <div class="icon-container-md bg-secondary">
                                        <i class="fas fa-list-alt text-white h4 mb-0"></i>
                                    </div>
                                </div>
                                <h5 class="card-title text-center">Stock Required</h5>
                                <div class="text-center">
                                    ' . $stockDisplay . '
                                </div>
                                <div class="drag-handle d-none"><i class="fas fa-grip-vertical"></i></div>
                            </div>
                        </div>
                    </div>
                </div>'
            ],
            'empty-slot-2' => [
                'visible' => false,
                'html' => '<div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3"><div class="dashboard-card-placeholder"></div></div>'
            ]
        ];

        // Module Cards
        $moduleCards = [];
        foreach ($entities as $entity) {
            $cardId = 'module-' . strtolower(str_replace(' ', '-', $entity['name']));
            $moduleCards[$cardId] = [
                'visible' => isCardVisible($cardId),
                'html' => '
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
                    <div class="dashboard-card h-100" data-card-id="' . $cardId . '">
                        <a href="' . h($entity['url']) . '" class="text-decoration-none">
                            <div class="card module-card modern-card h-100">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="flex-shrink-0">
                                            <i class="' . h($entity['icon']) . ' h3 mb-0 text-primary"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h5 class="card-title mb-0">' . h($entity['name']) . '</h5>
                                        </div>'
                                        . ($entity['url'] === '/entities/reports/index.php' ? '<span class="badge bg-success rounded-pill">New</span>' : '') .
                                    '</div>
                                    <p class="card-text text-muted small flex-grow-1">' . h($entity['desc']) . '</p>
                                    <div class="drag-handle d-none"><i class="fas fa-grip-vertical"></i></div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>'
            ];
        }
        $moduleRows = array_chunk($moduleCards, 4, true);

        // Analytics row
        $analyticsRow = [
            'analytics-dashboard' => [
                'visible' => isCardVisible('analytics-dashboard'),
                'html' => '
                <div class="col-12 mb-4">
                    <div class="dashboard-card h-100" data-card-id="analytics-dashboard">
                        <div class="card analytics-card modern-card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chart-pie me-2 text-gradient"></i>Business Analytics Dashboard
                                    </h5>
                                    <div class="d-flex align-items-center">
                                        <a href="/entities/reports/index.php" class="btn btn-modern btn-outline-primary btn-sm me-2">
                                            <i class="fas fa-external-link-alt me-1"></i>All Reports
                                        </a>
                                        <div class="drag-handle d-none"><i class="fas fa-grip-vertical"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row" id="business-analytics-content">
                                    <div class="col-12 text-center py-4">
                                        <div class="loading-shimmer border-radius-modern p-4">
                                            <i class="fas fa-spinner fa-spin me-2 text-primary"></i>
                                            <span class="text-muted">Loading comprehensive analytics...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>'
            ]
        ];

        $allRows = [
            ['id' => 'stats-row', 'title' => 'Statistics', 'cards' => $row1Cards],
            ['id' => 'business-row', 'title' => 'Business Metrics', 'cards' => $row2Cards],
            ['id' => 'financial-row', 'title' => 'Financial Metrics', 'cards' => $row3Cards],
        ];
        foreach ($moduleRows as $index => $modRow) {
            $allRows[] = ['id' => 'modules-row-' . ($index + 1), 'title' => 'Business Modules ' . ($index + 1), 'cards' => $modRow];
        }
        $allRows[] = ['id' => 'analytics-row', 'title' => 'Analytics', 'cards' => $analyticsRow];

        foreach ($allRows as $row) {
            echo '<div class="dashboard-row mb-4" data-row-id="' . $row['id'] . '">';
            echo '<div class="row-drag-handle d-none align-items-center mb-2" style="display: none !important;">';
            echo '<i class="fas fa-grip-horizontal me-2"></i>';
            echo '<small class="text-muted">' . $row['title'] . ' Row</small>';
            echo '</div>';
            echo '<div class="row dashboard-row-content" data-row-content="' . $row['id'] . '">';
            foreach ($row['cards'] as $cardId => $card) {
                echo createCardHtml($cardId, $card['html'], $card['visible']);
            }
            echo '</div>';
            echo '</div>';
        }
        ?>
    </div>
</div>

<?php
include_once __DIR__ . '/includes/order_modal.php';

// Include the dailies modals (Journal + Report)
include_once __DIR__ . '/includes/dailies.php';
?>

<!-- ADDED FOR BATCH SUMMARY MODAL ON DASHBOARD -->
<div class="modal fade" id="dashboard-batch-summary-modal" tabindex="-1" aria-labelledby="dashboardBatchSummaryLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg"> <!-- Will be upgraded to xl by initBatchSummary -->
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="dashboardBatchSummaryLabel"><i class="fa fa-list me-2"></i>Batch Summary</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="dashboard-batch-summary-body">
        <div class="p-3 text-center text-muted">
          <i class="fa fa-info-circle me-1"></i> Summary will load here...
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Ensure batches.js is available so initBatchSummary exists -->
<script src="/entities/batches/js/batches.js"></script>
<script src="/entities/sales/js/order_form.js"></script>
<script src="/assets/js/dashboard.js"></script>

<script>
// ADDED: Dashboard Current Batch Summary Button Logic
(function(){
    const handler = function(e){
        e.preventDefault();
        const btn = document.getElementById('btn-current-batch-summary');
        if (!btn) return;
        const batchId = btn.getAttribute('data-batch-id');
        if (!batchId) {
            alert('No current batch found.');
            return;
        }
        const modalEl = document.getElementById('dashboard-batch-summary-modal');
        const body = document.getElementById('dashboard-batch-summary-body');
        if (!modalEl || !body) return;
        body.innerHTML = '<div class="p-3 text-center text-muted"><i class="fa fa-spinner fa-spin me-2"></i>Loading summary...</div>';
        // Show modal
        if (typeof bootstrap !== 'undefined') {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        } else if (window.UnifiedModals) {
            window.UnifiedModals.show(modalEl);
        } else {
            modalEl.style.display = 'block';
        }
        fetch('/entities/batches/batch_summary.php?batch_id=' + encodeURIComponent(batchId), {credentials:'same-origin'})
            .then(r => r.text())
            .then(html => {
                body.innerHTML = html;
                // Initialize the summary interactions
                if (typeof window.initBatchSummary === 'function') {
                    const root = body.querySelector('#batch-summary-root');
                    if (root) window.initBatchSummary(root);
                }
            })
            .catch(() => {
                body.innerHTML = '<div class="p-3 text-danger">Failed to load summary.</div>';
            });
    };

    document.addEventListener('click', function(ev){
        if (ev.target && ev.target.id === 'btn-current-batch-summary') {
            handler(ev);
        }
    });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>