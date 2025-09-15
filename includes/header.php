<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../settings/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($pageTitle)) $pageTitle = SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en" data-theme="default">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 Base CSS (replaced with Bootswatch themes) -->
    <link id="bootstrap-theme" href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/spacelab/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Main CSS (minimal overrides only) -->
    <link rel="icon" href="/assets/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="/assets/css/bootstrap-overrides.css">
    <link rel="stylesheet" href="/assets/css/inline-style-replacements.css">
    <link rel="stylesheet" href="/assets/css/list-pages-consistency.css">
    <link rel="stylesheet" href="/assets/css/sidebar.css">
    <link rel="stylesheet" href="/assets/css/dashboard-widgets.css">
    <link rel="stylesheet" href="/assets/css/modern-dashboard.css">
    <!-- DataTables CSS with Bootstrap 5 integration -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <!-- Tom Select CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
    <!-- JS Libraries: jQuery, Bootstrap, DataTables with Bootstrap, Tom Select -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <!-- Chart.js for analytics and reports -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <!-- SortableJS for drag and drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="/assets/js/unified-tables.js"></script>
    <script src="/assets/js/unified-modals.js"></script>
    <script src="/assets/js/notification-modal.js"></script>
    <script src="/assets/js/bootstrap-theme-manager.js"></script>
    <script src="/assets/js/sidebar.js"></script>
</head>
<body>
    <?php if (!empty($_SESSION['user_id'])): ?>
    <!-- Sidebar Navigation -->
    <div class="layout-container">
        <nav id="sidebar" class="sidebar bg-light border-end shadow-sm">
            <div class="sidebar-header p-3 border-bottom d-flex justify-content-start align-items-center">
                <a href="/index.php" class="d-flex align-items-center text-decoration-none">
                    <img src="/assets/img/logo.png" alt="FrozoFun Logo" class="logo-img" height="40">
                    <span class="ms-2 fs-5 fw-bold text-primary sidebar-title">FrozoFun</span>
                </a>
            </div>
            <div class="sidebar-content">
                <ul class="nav nav-pills nav-sidebar flex-column p-3" data-bs-parent="#sidebar">
                    <li class="nav-item mb-1">
                        <a href="/index.php" class="nav-link text-dark">
                            <i class="fas fa-tachometer-alt me-2"></i>
                            <span class="nav-text">Dashboard</span>
                        </a>
                    </li>
                    
                    <!-- A- Batches -->
                    <li class="nav-item mb-1">
                        <a href="/entities/batches/list.php" class="nav-link text-dark">
                            <i class="fas fa-layer-group me-2"></i>
                            <span class="nav-text">Batches</span>
                        </a>
                    </li>
                    
                    <!-- B- Sales Group -->
                    <li class="nav-item mb-1">
                        <a class="nav-link text-dark d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#salesSubmenu" role="button" aria-expanded="false" aria-controls="salesSubmenu">
                            <span>
                                <i class="fas fa-cash-register me-2"></i>
                                <span class="nav-text">Sales</span>
                            </span>
                            <i class="fas fa-chevron-down nav-chevron"></i>
                        </a>
                        <div class="collapse" id="salesSubmenu">
                            <ul class="nav nav-pills flex-column ms-3">
                                <li class="nav-item">
                                    <a class="nav-link text-dark small py-1" href="/entities/items/list.php">
                                        <i class="fas fa-cube me-2"></i> Items
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-dark small py-1" href="/entities/meals/list.php">
                                        <i class="fas fa-utensils me-2"></i> Meals
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-dark small py-1" href="/entities/customers/list.php">
                                        <i class="fas fa-users me-2"></i> Customers
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-dark small py-1" href="/entities/sales/orders_list.php">
                                        <i class="fas fa-shopping-bag me-2"></i> Orders
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    
                    <!-- C- Purchases Group -->
                    <li class="nav-item mb-1">
                        <a class="nav-link text-dark d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#purchasesSubmenu" role="button" aria-expanded="false" aria-controls="purchasesSubmenu">
                            <span>
                                <i class="fas fa-shopping-cart me-2"></i>
                                <span class="nav-text">Purchases</span>
                            </span>
                            <i class="fas fa-chevron-down nav-chevron"></i>
                        </a>
                        <div class="collapse" id="purchasesSubmenu">
                            <ul class="nav nav-pills flex-column ms-3">
                                <li class="nav-item">
                                    <a class="nav-link text-dark small py-1" href="/entities/expenses/list.php">
                                        <i class="fas fa-credit-card me-2"></i> Expenses
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-dark small py-1" href="/entities/purchases/list.php">
                                        <i class="fas fa-shopping-cart me-2"></i> Purchases
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-dark small py-1" href="/entities/vendors/list.php">
                                        <i class="fas fa-truck me-2"></i> Vendors
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    
                    <!-- D- Inventory -->
                    <li class="nav-item mb-1">
                        <a href="/entities/inventory/list.php" class="nav-link text-dark">
                            <i class="fas fa-warehouse me-2"></i>
                            <span class="nav-text">Inventory</span>
                        </a>
                    </li>
                    
                    <!-- E- Reports -->
                    <li class="nav-item mb-1">
                        <a class="nav-link text-dark d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#reportsSubmenu" role="button" aria-expanded="false" aria-controls="reportsSubmenu">
                            <span>
                                <i class="fas fa-chart-bar me-2"></i>
                                <span class="nav-text">Reports</span>
                            </span>
                            <i class="fas fa-chevron-down nav-chevron"></i>
                        </a>
                        <div class="collapse" id="reportsSubmenu">
                            <ul class="nav nav-pills flex-column ms-3">
                                <li class="nav-item">
                                    <h6 class="nav-link text-muted small mb-1">Items</h6>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-dark small py-1" href="/entities/items/top_selling.php">Top Selling Items</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-dark small py-1" href="/entities/items/sold_by_period.php">Items Sold by Period</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-dark small py-1" href="/entities/items/turnover.php">Inventory Turnover</a>
                                </li>
                                <li class="nav-item">
                                    <h6 class="nav-link text-muted small mb-1 mt-2">Customers</h6>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-dark small py-1" href="/entities/customers/trends.php">Customer Trends</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-dark small py-1" href="/entities/customers/intervals.php">Order Intervals</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-dark small py-1" href="/entities/customers/segmentation.php">Customer Segmentation</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-dark small py-1" href="/entities/customers/clv.php">Customer Lifetime Value</a>
                                </li>
                                <li class="nav-item">
                                    <h6 class="nav-link text-muted small mb-1 mt-2">Sales</h6>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-dark small py-1" href="/entities/sales/monthly.php">Monthly Sales</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-dark small py-1" href="/entities/sales/receivables.php">Receivables</a>
                                </li>
                                <li class="nav-item">
                                    <h6 class="nav-link text-muted small mb-1 mt-2">Purchases & Vendors</h6>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-dark small py-1" href="/entities/purchases/payables.php">Payables</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-dark small py-1" href="/entities/vendors/report.php">Vendor Reports</a>
                                </li>
                                <li class="nav-item">
                                    <h6 class="nav-link text-muted small mb-1 mt-2">General</h6>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-dark small py-1" href="/entities/reports/profit_loss.php">Profit & Loss</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-dark small py-1" href="/entities/reports/balance_sheet.php">Balance Sheet</a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    
                    <!-- F- Users -->
                    <li class="nav-item mb-1">
                        <a href="/entities/users/list.php" class="nav-link text-dark">
                            <i class="fas fa-user-shield me-2"></i>
                            <span class="nav-text">Users</span>
                        </a>
                    </li>
                </ul>
                <div class="sidebar-footer p-3 mt-auto border-top">
                    <!-- Sidebar Toggle Button (repositioned above settings) -->
                    <button id="sidebarToggle" class="btn btn-outline-secondary btn-sm w-100 mb-2 d-none d-lg-block sidebar-toggle-btn" type="button">
                        <i class="fas fa-chevron-left me-1"></i> <span class="toggle-text">Collapse</span>
                    </button>
                    <button id="settingsBtn" class="btn btn-outline-secondary btn-sm w-100 mb-2" data-bs-toggle="modal" data-bs-target="#settingsModal">
                        <i class="fas fa-cog me-1"></i> <span class="nav-text">Settings</span>
                    </button>
                    <a href="/logout.php" class="btn btn-outline-danger btn-sm w-100">
                        <i class="fas fa-sign-out-alt me-1"></i> <span class="nav-text">Logout</span>
                    </a>
                </div>
            </div>
        </nav>

        <!-- Mobile Sidebar Toggle Button -->
        <div class="d-lg-none position-fixed" style="top: 15px; left: 15px; z-index: 1050;">
            <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <!-- Mobile Sidebar Offcanvas -->
        <div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
            <div class="offcanvas-header border-bottom">
                <a href="/index.php" class="d-flex align-items-center text-decoration-none">
                    <img src="/assets/img/logo.png" alt="FrozoFun Logo" class="logo-img" height="30">
                    <span class="ms-2 fs-6 fw-bold text-primary">FrozoFun</span>
                </a>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <ul class="nav nav-pills nav-sidebar flex-column">
                    <li class="nav-item mb-1">
                        <a href="/index.php" class="nav-link text-dark">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                    </li>
                    
                    <!-- A- Batches -->
                    <li class="nav-item mb-1">
                        <a href="/entities/batches/list.php" class="nav-link text-dark">
                            <i class="fas fa-layer-group me-2"></i> Batches
                        </a>
                    </li>
                    
                    <!-- B- Sales Group -->
                    <li class="nav-item mb-1">
                        <a class="nav-link text-dark d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#mobileSalesSubmenu" role="button" aria-expanded="false" aria-controls="mobileSalesSubmenu">
                            <span><i class="fas fa-cash-register me-2"></i> Sales</span>
                            <i class="fas fa-chevron-down nav-chevron"></i>
                        </a>
                        <div class="collapse" id="mobileSalesSubmenu">
                            <ul class="nav nav-pills flex-column ms-3">
                                <li class="nav-item"><a class="nav-link text-dark small py-1" href="/entities/items/list.php"><i class="fas fa-cube me-2"></i> Items</a></li>
                                <li class="nav-item"><a class="nav-link text-dark small py-1" href="/entities/meals/list.php"><i class="fas fa-utensils me-2"></i> Meals</a></li>
                                <li class="nav-item"><a class="nav-link text-dark small py-1" href="/entities/customers/list.php"><i class="fas fa-users me-2"></i> Customers</a></li>
                                <li class="nav-item"><a class="nav-link text-dark small py-1" href="/entities/sales/orders_list.php"><i class="fas fa-shopping-bag me-2"></i> Orders</a></li>
                            </ul>
                        </div>
                    </li>
                    
                    <!-- C- Purchases Group -->
                    <li class="nav-item mb-1">
                        <a class="nav-link text-dark d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#mobilePurchasesSubmenu" role="button" aria-expanded="false" aria-controls="mobilePurchasesSubmenu">
                            <span><i class="fas fa-shopping-cart me-2"></i> Purchases</span>
                            <i class="fas fa-chevron-down nav-chevron"></i>
                        </a>
                        <div class="collapse" id="mobilePurchasesSubmenu">
                            <ul class="nav nav-pills flex-column ms-3">
                                <li class="nav-item"><a class="nav-link text-dark small py-1" href="/entities/expenses/list.php"><i class="fas fa-credit-card me-2"></i> Expenses</a></li>
                                <li class="nav-item"><a class="nav-link text-dark small py-1" href="/entities/purchases/list.php"><i class="fas fa-shopping-cart me-2"></i> Purchases</a></li>
                                <li class="nav-item"><a class="nav-link text-dark small py-1" href="/entities/vendors/list.php"><i class="fas fa-truck me-2"></i> Vendors</a></li>
                            </ul>
                        </div>
                    </li>
                    
                    <!-- D- Inventory -->
                    <li class="nav-item mb-1">
                        <a href="/entities/inventory/list.php" class="nav-link text-dark">
                            <i class="fas fa-warehouse me-2"></i> Inventory
                        </a>
                    </li>
                    
                    <!-- E- Reports -->
                    <li class="nav-item mb-1">
                        <a class="nav-link text-dark d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#mobileReportsSubmenu" role="button" aria-expanded="false" aria-controls="mobileReportsSubmenu">
                            <span><i class="fas fa-chart-bar me-2"></i> Reports</span>
                            <i class="fas fa-chevron-down nav-chevron"></i>
                        </a>
                        <div class="collapse" id="mobileReportsSubmenu">
                            <ul class="nav nav-pills flex-column ms-3">
                                <li class="nav-item"><a class="nav-link text-dark small py-1" href="/entities/items/top_selling.php">Top Selling Items</a></li>
                                <li class="nav-item"><a class="nav-link text-dark small py-1" href="/entities/items/sold_by_period.php">Items Sold by Period</a></li>
                                <li class="nav-item"><a class="nav-link text-dark small py-1" href="/entities/items/turnover.php">Inventory Turnover</a></li>
                                <li class="nav-item"><a class="nav-link text-dark small py-1" href="/entities/customers/trends.php">Customer Trends</a></li>
                                <li class="nav-item"><a class="nav-link text-dark small py-1" href="/entities/customers/intervals.php">Order Intervals</a></li>
                                <li class="nav-item"><a class="nav-link text-dark small py-1" href="/entities/customers/segmentation.php">Customer Segmentation</a></li>
                                <li class="nav-item"><a class="nav-link text-dark small py-1" href="/entities/customers/clv.php">Customer Lifetime Value</a></li>
                                <li class="nav-item"><a class="nav-link text-dark small py-1" href="/entities/sales/monthly.php">Monthly Sales</a></li>
                                <li class="nav-item"><a class="nav-link text-dark small py-1" href="/entities/sales/receivables.php">Receivables</a></li>
                                <li class="nav-item"><a class="nav-link text-dark small py-1" href="/entities/purchases/payables.php">Payables</a></li>
                                <li class="nav-item"><a class="nav-link text-dark small py-1" href="/entities/vendors/report.php">Vendor Reports</a></li>
                                <li class="nav-item"><a class="nav-link text-dark small py-1" href="/entities/reports/profit_loss.php">Profit & Loss</a></li>
                                <li class="nav-item"><a class="nav-link text-dark small py-1" href="/entities/reports/balance_sheet.php">Balance Sheet</a></li>
                            </ul>
                        </div>
                    </li>
                    
                    <!-- F- Users -->
                    <li class="nav-item mb-1">
                        <a href="/entities/users/list.php" class="nav-link text-dark">
                            <i class="fas fa-user-shield me-2"></i> Users
                        </a>
                    </li>
                </ul>
                <div class="mt-4">
                    <button class="btn btn-outline-secondary btn-sm w-100 mb-2" data-bs-toggle="modal" data-bs-target="#settingsModal">
                        <i class="fas fa-cog me-1"></i> Settings
                    </button>
                    <a href="/logout.php" class="btn btn-outline-danger btn-sm w-100">
                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <main class="main-content flex-grow-1">
    <?php else: ?>
        <!-- For non-logged in users, show minimal header -->
        <header class="bg-white border-bottom py-3 mb-4">
            <div class="container">
                <a href="/index.php" class="d-flex align-items-center text-decoration-none">
                    <img src="/assets/img/logo.png" alt="FrozoFun Logo" class="logo-img" height="40">
                    <span class="ms-2 fs-5 fw-bold text-primary">FrozoFun</span>
                </a>
            </div>
        </header>
        <main class="main-content container-fluid py-4">
    <?php endif; ?>
    
    <!-- Settings Modal -->
    <?php if (!empty($_SESSION['user_id'])): ?>
    <div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="settingsModalLabel">
                        <i class="fas fa-cog me-2"></i>Settings
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs" id="settingsTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">
                                <i class="fas fa-cog me-1"></i>General
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" type="button" role="tab" aria-controls="dashboard" aria-selected="false">
                                <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Tab content -->
                    <div class="tab-content mt-3" id="settingsTabContent">
                        <!-- General Settings Tab -->
                        <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                            <div class="mb-3">
                                <label for="modal-theme-selector" class="form-label">
                                    <i class="fas fa-palette me-2"></i>Theme
                                </label>
                                <select id="modal-theme-selector" class="form-select">
                                    <option value="cerulean">Cerulean</option>
                                    <option value="cosmo">Cosmo</option>
                                    <option value="cyborg">Cyborg</option>
                                    <option value="darkly">Darkly</option>
                                    <option value="flatly">Flatly</option>
                                    <option value="journal">Journal</option>
                                    <option value="litera">Litera</option>
                                    <option value="lumen">Lumen</option>
                                    <option value="lux">Lux</option>
                                    <option value="materia">Materia</option>
                                    <option value="minty">Minty</option>
                                    <option value="morph">Morph</option>
                                    <option value="pulse">Pulse</option>
                                    <option value="quartz">Quartz</option>
                                    <option value="sandstone">Sandstone</option>
                                    <option value="simplex">Simplex</option>
                                    <option value="sketchy">Sketchy</option>
                                    <option value="slate">Slate</option>
                                    <option value="solar">Solar</option>
                                    <option value="spacelab">Spacelab</option>
                                    <option value="superhero">Superhero</option>
                                    <option value="united">United</option>
                                    <option value="vapor">Vapor</option>
                                    <option value="yeti">Yeti</option>
                                    <option value="zephyr">Zephyr</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="modal-timezone-selector" class="form-label">
                                    <i class="fas fa-clock me-2"></i>Timezone
                                </label>
                                <select id="modal-timezone-selector" class="form-select">
                                    <option value="UTC">UTC</option>
                                    <option value="Asia/Karachi">Asia/Karachi</option>
                                    <option value="America/New_York">America/New_York</option>
                                    <option value="Europe/London">Europe/London</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Dashboard Settings Tab -->
                        <div class="tab-pane fade" id="dashboard" role="tabpanel" aria-labelledby="dashboard-tab">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-th-large me-2"></i>Visible Widgets
                                </label>
                                <p class="text-muted small">Select which widgets to display on your dashboard. You can also drag and drop to reorder them.</p>
                                <div id="widget-preferences">
                                    <p class="text-muted small mb-3">Select which cards to display on your dashboard. Individual cards can be reordered using the Edit Layout button.</p>
                                    
                                    <!-- Statistics Cards -->
                                    <div class="mb-3">
                                        <h6 class="text-primary mb-2"><i class="fas fa-chart-bar me-2"></i>Statistics Cards</h6>
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="widget-stat-items" data-widget="stat-items" checked>
                                                    <label class="form-check-label" for="widget-stat-items">
                                                        <i class="fas fa-cube me-1"></i>Items Count
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="widget-stat-customers" data-widget="stat-customers" checked>
                                                    <label class="form-check-label" for="widget-stat-customers">
                                                        <i class="fas fa-users me-1"></i>Customers Count
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="widget-stat-orders" data-widget="stat-orders" checked>
                                                    <label class="form-check-label" for="widget-stat-orders">
                                                        <i class="fas fa-shopping-bag me-1"></i>Orders Count
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="widget-stat-vendors" data-widget="stat-vendors" checked>
                                                    <label class="form-check-label" for="widget-stat-vendors">
                                                        <i class="fas fa-truck me-1"></i>Vendors Count
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Business Metrics -->
                                    <div class="mb-3">
                                        <h6 class="text-primary mb-2"><i class="fas fa-business-time me-2"></i>Business Metrics</h6>
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="widget-metric-orders-today" data-widget="metric-orders-today" checked>
                                                    <label class="form-check-label" for="widget-metric-orders-today">
                                                        <i class="fas fa-calendar-day me-1"></i>Orders Today
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="widget-metric-pending-orders" data-widget="metric-pending-orders" checked>
                                                    <label class="form-check-label" for="widget-metric-pending-orders">
                                                        <i class="fas fa-clock me-1"></i>Pending Orders
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="widget-metric-current-batch" data-widget="metric-current-batch" checked>
                                                    <label class="form-check-label" for="widget-metric-current-batch">
                                                        <i class="fas fa-layer-group me-1"></i>Current Batch
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Financial Metrics -->
                                    <div class="mb-3">
                                        <h6 class="text-primary mb-2"><i class="fas fa-money-bill-wave me-2"></i>Financial Metrics</h6>
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="widget-financial-receivables" data-widget="financial-receivables" checked>
                                                    <label class="form-check-label" for="widget-financial-receivables">
                                                        <i class="fas fa-arrow-up me-1"></i>Receivables
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="widget-financial-payables" data-widget="financial-payables" checked>
                                                    <label class="form-check-label" for="widget-financial-payables">
                                                        <i class="fas fa-arrow-down me-1"></i>Payables
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="widget-financial-stock-required" data-widget="financial-stock-required" checked>
                                                    <label class="form-check-label" for="widget-financial-stock-required">
                                                        <i class="fas fa-exclamation-triangle me-1"></i>Stock Required
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Business Modules -->
                                    <div class="mb-3">
                                        <h6 class="text-primary mb-2"><i class="fas fa-th me-2"></i>Business Modules</h6>
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="widget-module-items" data-widget="module-items" checked>
                                                    <label class="form-check-label" for="widget-module-items">
                                                        <i class="fas fa-box me-1"></i>Items
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="widget-module-customers" data-widget="module-customers" checked>
                                                    <label class="form-check-label" for="widget-module-customers">
                                                        <i class="fas fa-users me-1"></i>Customers
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="widget-module-sales" data-widget="module-sales" checked>
                                                    <label class="form-check-label" for="widget-module-sales">
                                                        <i class="fas fa-cash-register me-1"></i>Sales
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="widget-module-batches" data-widget="module-batches" checked>
                                                    <label class="form-check-label" for="widget-module-batches">
                                                        <i class="fas fa-layer-group me-1"></i>Batches
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="widget-module-purchases" data-widget="module-purchases" checked>
                                                    <label class="form-check-label" for="widget-module-purchases">
                                                        <i class="fas fa-shopping-cart me-1"></i>Purchases
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="widget-module-expenses" data-widget="module-expenses" checked>
                                                    <label class="form-check-label" for="widget-module-expenses">
                                                        <i class="fas fa-credit-card me-1"></i>Expenses
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="widget-module-vendors" data-widget="module-vendors" checked>
                                                    <label class="form-check-label" for="widget-module-vendors">
                                                        <i class="fas fa-truck me-1"></i>Vendors
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="widget-module-inventory" data-widget="module-inventory" checked>
                                                    <label class="form-check-label" for="widget-module-inventory">
                                                        <i class="fas fa-warehouse me-1"></i>Inventory
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="widget-module-users" data-widget="module-users" checked>
                                                    <label class="form-check-label" for="widget-module-users">
                                                        <i class="fas fa-user-shield me-1"></i>Users
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="widget-module-reports" data-widget="module-reports" checked>
                                                    <label class="form-check-label" for="widget-module-reports">
                                                        <i class="fas fa-chart-pie me-1"></i>Reports
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Analytics Dashboard -->
                                    <div class="mb-3">
                                        <h6 class="text-primary mb-2"><i class="fas fa-chart-line me-2"></i>Analytics Dashboard</h6>
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="widget-analytics-dashboard" data-widget="analytics-dashboard" checked>
                                                    <label class="form-check-label" for="widget-analytics-dashboard">
                                                        <i class="fas fa-chart-pie me-1"></i>Business Analytics
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveSettings">Save Settings</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
    // Dashboard preferences functionality
    $(document).ready(function() {
        // Load widget preferences when settings modal is shown
        $('#settingsModal').on('show.bs.modal', function() {
            loadDashboardPreferences();
        });
        
        // Update save settings handler to include dashboard preferences
        $('#saveSettings').off('click').on('click', function() {
            const theme = $('#modal-theme-selector').val();
            const timezone = $('#modal-timezone-selector').val();
            
            // Save theme and timezone (existing functionality)
            if (window.themeManager) {
                window.themeManager.setTheme(theme);
            }
            
            // Save dashboard preferences
            saveDashboardPreferences();
            
            $('#settingsModal').modal('hide');
        });
    });
    
    function loadDashboardPreferences() {
        $.ajax({
            url: '/api/dashboard_preferences.php',
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.success && data.preferences) {
                    // Update checkboxes based on saved preferences
                    // For individual cards, check if the card ID exists in preferences
                    $('#widget-preferences input[type="checkbox"]').each(function() {
                        const checkbox = $(this);
                        const widgetId = checkbox.data('widget');
                        
                        // Check if this specific card is saved in preferences
                        if (data.preferences[widgetId]) {
                            checkbox.prop('checked', data.preferences[widgetId].visible);
                        } else {
                            // For backwards compatibility, check old grouped preferences
                            let shouldBeChecked = true; // Default to checked
                            
                            // Map individual cards to their old group preferences
                            const groupMappings = {
                                'stat-items': 'stats-cards',
                                'stat-customers': 'stats-cards', 
                                'stat-orders': 'stats-cards',
                                'stat-vendors': 'stats-cards',
                                'metric-orders-today': 'business-metrics',
                                'metric-pending-orders': 'business-metrics',
                                'metric-current-batch': 'business-metrics',
                                'financial-receivables': 'financial-metrics',
                                'financial-payables': 'financial-metrics',
                                'financial-stock-required': 'financial-metrics',
                                'module-items': 'module-cards',
                                'module-customers': 'module-cards',
                                'module-sales': 'module-cards',
                                'module-batches': 'module-cards',
                                'module-purchases': 'module-cards',
                                'module-expenses': 'module-cards',
                                'module-vendors': 'module-cards',
                                'module-inventory': 'module-cards',
                                'module-users': 'module-cards',
                                'module-reports': 'module-cards'
                            };
                            
                            const groupId = groupMappings[widgetId];
                            if (groupId && data.preferences[groupId]) {
                                shouldBeChecked = data.preferences[groupId].visible;
                            }
                            
                            checkbox.prop('checked', shouldBeChecked);
                        }
                    });
                }
            },
            error: function(xhr, status, error) {
                console.warn('Could not load dashboard preferences:', error);
            }
        });
    }
    
    function saveDashboardPreferences() {
        const preferences = {};
        let order = 1;
        
        // Save individual card preferences
        $('#widget-preferences input[type="checkbox"]').each(function() {
            const checkbox = $(this);
            const widgetId = checkbox.data('widget');
            preferences[widgetId] = {
                visible: checkbox.prop('checked'),
                order: order++
            };
        });
        
        $.ajax({
            url: '/api/dashboard_preferences.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                preferences: preferences
            }),
            success: function(data) {
                if (data.success) {
                    // Reload the page to apply changes
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error saving dashboard preferences:', error);
                alert('Error saving dashboard preferences. Please try again.');
            }
        });
    }
    </script>
    
    <?php
    // Include floating shortcuts button and modals
    include_once __DIR__ . '/floating-shortcuts/floater.php';
    ?>