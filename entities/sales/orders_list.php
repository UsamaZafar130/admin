<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Sales Orders";

// Date filter logic (unified)
$today = new DateTime('today');
$defaultFrom = (clone $today)->modify('first day of this month')->format('Y-m-d');
$defaultTo   = $today->format('Y-m-d');

$selectedMonth = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : '';

if ($selectedMonth) {
    $monthStart = DateTime::createFromFormat('Y-m-d', $selectedMonth . '-01');
    if ($monthStart) {
        $from_date = $monthStart->format('Y-m-d');
        $to_date   = $monthStart->modify('last day of this month')->format('Y-m-d');
    }
}
if (isset($_GET['from_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from_date'])) {
    $from_date = $_GET['from_date'];
}
if (isset($_GET['to_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to_date'])) {
    $to_date = $_GET['to_date'];
}
$from_date = $from_date ?? $defaultFrom;
$to_date   = $to_date ?? $defaultTo;
if ($from_date > $to_date) {
    $tmp = $from_date;
    $from_date = $to_date;
    $to_date = $tmp;
}

// Build last 12 months list
$monthOptions = [];
$cursor = new DateTime('first day of this month');
for ($i=0; $i<12; $i++) {
    $monthOptions[] = $cursor->format('Y-m');
    $cursor->modify('-1 month');
}

require_once __DIR__ . '/../../includes/header.php';
?>
<link href="css/sales.css" rel="stylesheet">

<div class="orders-content">
    <div class="container mt-3">
        <div class="row mb-2">
            <div class="col-md-7">
                <h2 class="text-primary">
                    <i class="fa fa-cash-register me-2"></i>
                    Sales Orders
                </h2>
            </div>
            <div class="col-md-5 text-md-end mt-2 mt-md-0">
                <a href="orders_summary.php" class="btn btn-success btn-3d me-2" title="Orders Summary">
                    <i class="fa fa-chart-bar me-1"></i> Summary
                </a>
                <button type="button" class="btn btn-primary btn-3d" id="open-order-modal" title="Create New Order">
                    <i class="fa fa-plus me-1"></i> Add Order
                </button>
            </div>
        </div>

        <!-- Date Filter Bar -->
        <div class="card mb-3">
            <div class="card-body py-3">
                <form id="orders-date-filter-form" class="row gy-2 gx-2 align-items-end" method="get" action="">
                    <div class="col-auto">
                        <label for="month" class="form-label mb-0 small text-muted">Quick Month</label>
                        <select name="month" id="month" class="form-select form-select-sm">
                            <option value="">-- Custom / Current Range --</option>
                            <?php foreach ($monthOptions as $m): ?>
                                <option value="<?= h($m) ?>" <?= $selectedMonth === $m ? 'selected' : '' ?>>
                                    <?= h(date('M Y', strtotime($m . '-01'))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label for="from_date" class="form-label mb-0 small text-muted">From</label>
                        <input type="date" class="form-control form-control-sm" id="from_date" name="from_date" value="<?= h($from_date) ?>">
                    </div>
                    <div class="col-auto">
                        <label for="to_date" class="form-label mb-0 small text-muted">To</label>
                        <input type="date" class="form-control form-control-sm" id="to_date" name="to_date" value="<?= h($to_date) ?>">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fa fa-filter me-1"></i> Apply
                        </button>
                        <a href="orders_list.php" class="btn btn-sm btn-secondary">
                            <i class="fa fa-undo me-1"></i> Reset
                        </a>
                    </div>
                    <div class="col-auto ms-auto">
                        <span class="badge bg-info text-dark">
                            Showing: <?= h($from_date) ?> → <?= h($to_date) ?>
                        </span>
                    </div>
                </form>
            </div>
        </div>

        <div class="alert alert-info max-width-800 mb-4">
            <strong>Manage all your sales orders efficiently.</strong><br>
            By default you are viewing <strong>this month's orders</strong> based on order date.<br>
            Use the date filter above to narrow results. Use <strong>Add Order</strong> to create orders, or <strong>Summary</strong> for analytics.
        </div>

        <div class="orders-filter-action-row">
            <select id="orders-status-filter" class="orders-filter-select">
                <option value="">All (Status)</option>
                <option value="undelivered">Undelivered</option>
                <option value="delivered">Delivered</option>
                <option value="paid">Paid</option>
                <option value="partial_paid">Partial Paid</option>
                <option value="unpaid">Unpaid</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <a href="#" class="orders-shipping-docs-btn" id="shipping-docs-btn" title="Shipping Docs">
                <i class="fa fa-file-lines"></i>
            </a>
        </div>

        <div class="table-responsive">
            <table class="entity-table table table-striped table-hover table-consistent" id="orders-table">
                <thead class="table-light">
                    <tr>
                        <th class="no-sort" data-priority="9"><input type="checkbox" id="select-all-orders"></th>
                        <th data-priority="2">Order #</th>
                        <th data-priority="1">Customer</th>
                        <th data-priority="3">Grand Total</th>
                        <th data-priority="4">Status</th>
                        <th data-priority="5">Amount</th>
                        <th data-priority="6">Discount</th>
                        <th data-priority="7">Delivery Charges</th>
                        <th data-priority="8">Paid</th>
                        <th class="no-sort">
                            <div class="orders-bulk-row-inside-table">
                                <button type="button" class="orders-bulk-btn" id="bulk-delivered-btn" title="Mark Delivered"><i class="fa fa-truck"></i></button>
                                <button type="button" class="orders-bulk-btn" id="bulk-paid-btn" title="Mark Paid"><i class="fa fa-check-circle"></i></button>
                                <button type="button" class="orders-bulk-btn" id="bulk-cancel-btn" title="Cancel"><i class="fa fa-ban"></i></button>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <!-- JS fills this -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Order Modal -->
<div class="modal fade" id="orderModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="orderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="orderModalLabel">New Sales Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="order-modal-body">
                <div class="text-center p-4">Loading form...</div>
            </div>
        </div>
    </div>
</div>

<!-- Payment / Delivery related modals (kept as-is) -->
<div class="modal fade" id="paymentOnDeliveryAfterDeliveryModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="paymentOnDeliveryAfterDeliveryModalLabel" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content"><div class="modal-header">
    <h5 class="modal-title" id="paymentOnDeliveryAfterDeliveryModalLabel">Payment on Delivery</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
  </div><div class="modal-body" id="payment-on-delivery-after-delivery-body"></div></div></div>
</div>

<div class="modal fade" id="paymentOnDeliveryModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="paymentOnDeliveryModalLabel" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content"><div class="modal-header">
    <h5 class="modal-title" id="paymentOnDeliveryModalLabel">Payment on Delivery</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
  </div><div class="modal-body" id="payment-on-delivery-body"></div></div></div>
</div>

<div class="modal fade" id="markPaymentModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="markPaymentModalLabel" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content"><div class="modal-header">
    <h5 class="modal-title" id="markPaymentModalLabel">Mark Payment for Order</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
  </div><div class="modal-body" id="mark-payment-modal-body"></div></div></div>
</div>

<div class="modal fade" id="bulkDeliveryResultModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="bulkDeliveryResultModalLabel" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content"><div class="modal-header">
        <h5 class="modal-title" id="bulkDeliveryResultModalLabel">Bulk Delivery Result</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div><div class="modal-body"></div></div></div>
</div>

<script>
// Pass date range to JS for API query
window.ordersFromDate = "<?= h($from_date) ?>";
window.ordersToDate   = "<?= h($to_date) ?>";
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<script src="js/orders_list.js"></script>
<script src="js/order_form.js"></script>
<script>
$(function() {
    if (window.location.search.indexOf('open_order_modal=1') !== -1) {
        setTimeout(function() { $('#open-order-modal').trigger('click'); }, 300);
    }

    $('#shipping-docs-btn').on('click', function(e) {
        e.preventDefault();
        let ids = $('.order-select-box:checked').map(function(){ return $(this).val(); }).get();
        if (ids.length === 0) {
            alert('Please select at least one order to generate shipping docs.');
            return;
        }
        window.open('shipping_docs.php?ids=' + encodeURIComponent(ids.join(',')), '_blank');
    });

    // Month quick selector auto-fill
    $('#month').on('change', function() {
        const val = this.value;
        if (!val) return;
        const parts = val.split('-');
        if (parts.length === 2) {
            const y = parseInt(parts[0],10), m = parseInt(parts[1],10);
            if (y && m) {
                const firstDay = new Date(y, m-1, 1);
                const lastDay  = new Date(y, m, 0);
                $('#from_date').val(firstDay.toISOString().slice(0,10));
                $('#to_date').val(lastDay.toISOString().slice(0,10));
                $('#orders-date-filter-form')[0].submit();
            }
        }
    });
    $('#from_date,#to_date').on('change', function() { $('#month').val(''); });
});
</script>