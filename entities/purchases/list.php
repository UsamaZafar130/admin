<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Purchases";
include __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

/*
 * Date Filter Logic
 * Default: current month (from first day of month 00:00:00 to today 23:59:59)
 * Supports:
 *   - Explicit from_date & to_date (YYYY-MM-DD)
 *   - Quick month selector (?month=YYYY-MM)
 */

$today = new DateTime('today');
$defaultFrom = (clone $today)->modify('first day of this month')->format('Y-m-d');
$defaultTo   = $today->format('Y-m-d');

$selectedMonth = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : '';
if ($selectedMonth) {
    // If a month is selected, override from/to unless explicit manual range was given.
    $monthStart = DateTime::createFromFormat('Y-m-d', $selectedMonth . '-01');
    if ($monthStart) {
        $from_date = $monthStart->format('Y-m-d');
        // End of month
        $to_date = $monthStart->modify('last day of this month')->format('Y-m-d');
    }
}

// Manual range has priority over month if provided
if (isset($_GET['from_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from_date'])) {
    $from_date = $_GET['from_date'];
}
if (isset($_GET['to_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to_date'])) {
    $to_date = $_GET['to_date'];
}

// Final fallback to defaults
$from_date = $from_date ?? $defaultFrom;
$to_date   = $to_date ?? $defaultTo;

// Ensure logical order
if ($from_date > $to_date) {
    $tmp = $from_date;
    $from_date = $to_date;
    $to_date = $tmp;
}

// Build month options (last 12 months including current)
$monthOptions = [];
$cursor = new DateTime('first day of this month');
for ($i=0; $i<12; $i++) {
    $monthOptions[] = $cursor->format('Y-m');
    $cursor->modify('-1 month');
}

// Fetch purchases within date range (inclusive)
$stmt = $pdo->prepare("
    SELECT p.*, v.name AS vendor_name,
        (SELECT COUNT(*) FROM purchase_payments pp WHERE pp.purchase_id = p.id AND pp.deleted_at IS NULL) AS payment_count
    FROM purchases p
    LEFT JOIN vendors v ON p.vendor_id = v.id
    WHERE p.deleted_at IS NULL
      AND p.date >= :from_date
      AND p.date <= :to_date
    ORDER BY p.date DESC, p.created_at DESC
");
$stmt->execute([
    ':from_date' => $from_date,
    ':to_date' => $to_date
]);
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate paid/unpaid status and amount paid for each (ids from filtered set)
$purchase_ids = array_column($purchases, 'id');
$amount_paid = [];
if ($purchase_ids) {
    $in = implode(',', array_fill(0, count($purchase_ids), '?'));
    $payStmt = $pdo->prepare("
        SELECT purchase_id, SUM(amount) AS paid 
        FROM purchase_payments 
        WHERE purchase_id IN ($in) 
          AND deleted_at IS NULL 
        GROUP BY purchase_id
    ");
    $payStmt->execute($purchase_ids);
    foreach ($payStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $amount_paid[$row['purchase_id']] = $row['paid'];
    }
}

// Fetch applied advances (only those linked to currently visible purchases)
$advances_by_purchase = [];
if ($purchase_ids) {
    $in = implode(',', array_fill(0, count($purchase_ids), '?'));
    $advStmt = $pdo->prepare("
        SELECT id, amount, applied_to_purchase_id 
        FROM vendor_advances 
        WHERE applied_to_purchase_id IN ($in)
    ");
    $advStmt->execute($purchase_ids);
    foreach ($advStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $pid = $row['applied_to_purchase_id'];
        if (!isset($advances_by_purchase[$pid])) $advances_by_purchase[$pid] = [];
        $advances_by_purchase[$pid][] = $row;
    }
}
?>

<div class="page-content-wrapper">
    <div class="container mt-3">
        <div class="row mb-2">
            <div class="col-md-7">
                <h2 class="text-primary"><i class="fa fa-shopping-cart me-2"></i> Purchases</h2>
            </div>
            <div class="col-md-5 text-md-end mt-2 mt-md-0">
                <button class="btn btn-primary btn-3d" onclick="showPurchaseModal()">
                    <i class="fa fa-plus me-1"></i> Add Purchase
                </button>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card mb-3">
            <div class="card-body py-3">
                <form id="purchases-filter-form" class="row gy-2 gx-2 align-items-end" method="get" action="">
                    <div class="col-auto">
                        <label for="month" class="form-label mb-0 small text-muted">Quick Month</label>
                        <select name="month" id="month" class="form-select form-select-sm">
                            <option value="">-- Custom / Current Range --</option>
                            <?php foreach ($monthOptions as $m): ?>
                                <option value="<?= h($m) ?>" <?= ($selectedMonth === $m ? 'selected' : '') ?>>
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
                        <a href="list.php" class="btn btn-sm btn-secondary">
                            <i class="fa fa-undo me-1"></i> Reset
                        </a>
                    </div>
                    <div class="col-auto ms-auto">
                        <span class="badge bg-info text-dark">
                            Showing: <?= h($from_date) ?> → <?= h($to_date) ?>
                        </span>
                        <span class="badge bg-secondary">
                            Rows: <?= count($purchases) ?>
                        </span>
                    </div>
                </form>
            </div>
        </div>

        <div class="alert alert-info max-width-700 mb-4">
            <strong>Manage vendor purchases and payments.</strong> Use <strong>Add Purchase</strong> to record purchases from vendors.<br>
            Track payment status and apply vendor advances. Use <strong>Trash</strong> to view deleted purchases.
        </div>

        <div class="mb-3 header-buttons-secondary">
            <a class="btn btn-outline-danger btn-3d" href="trash.php" title="View Trash">
                <i class="fa fa-trash me-1"></i> Trash
            </a>
        </div>

        <div id="purchases-list-wrap">
            <table class="entity-table table table-striped table-hover table-consistent" id="purchases-table">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Vendor</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Description</th>
                        <th>Advance Applied</th>
                        <th>Status</th>
                        <th style="width:140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($purchases as $p):
                    $paid = isset($amount_paid[$p['id']]) ? floatval($amount_paid[$p['id']]) : 0;
                    $status = ($paid >= floatval($p['amount'])) ? 'Paid' : ($paid > 0 ? 'Partial' : 'Unpaid');
                    $badgeClass = $status === 'Paid' ? 'badge-surplus' : ($status === 'Partial' ? 'badge-settled' : 'badge-outstanding');
                    $adv_list = isset($advances_by_purchase[$p['id']]) ? $advances_by_purchase[$p['id']] : [];
                ?>
                    <tr data-purchase-id="<?= $p['id'] ?>"
                        data-purchase='<?= h(json_encode($p)) ?>'
                        data-payment-count="<?= $p['payment_count'] ?>">
                        <td data-label="Date"><?= h(date('Y-m-d', strtotime($p['date']))) ?></td>
                        <td data-label="Vendor"><?= h($p['vendor_name']) ?></td>
                        <td data-label="Type">
                          <span class="badge <?= $p['type']=='credit'?'badge-outstanding':'badge-surplus' ?>">
                            <?= ucfirst($p['type']) ?>
                          </span>
                        </td>
                        <td data-label="Amount"><?= format_currency($p['amount']) ?></td>
                        <td data-label="Description"><?= h($p['description']) ?></td>
                        <td data-label="Advance Applied">
                          <?php if ($adv_list): ?>
                            <span class="badge badge-surplus">
                                <?= implode(", ", array_map(function($a){ return format_currency($a['amount']); }, $adv_list)) ?>
                            </span>
                          <?php else: ?>
                            <span class="badge badge-settled">-</span>
                          <?php endif; ?>
                        </td>
                        <td data-label="Status">
                            <span class="badge <?= $badgeClass ?>">
                                <?= h($status) ?><?= $status !== 'Paid' && $paid > 0 ? " (" . number_format($paid,2) . " paid)" : "" ?>
                            </span>
                        </td>
                        <td data-label="Actions">
                            <div class="btn-group btn-group-sm action-icons" role="group">
                                <button class="btn btn-outline-primary btn-3d" title="Details" onclick="PurchaseUI.openDetails(<?= $p['id'] ?>)">
                                    <i class="fa fa-eye"></i>
                                </button>
                                <button class="btn btn-outline-warning btn-3d" title="Edit" onclick="showPurchaseModal(<?= $p['id'] ?>)">
                                    <i class="fa fa-edit"></i>
                                </button>
                                <form method="post" action="actions.php" class="ajax-delete-purchase d-inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <button class="btn btn-outline-danger btn-3d" title="Delete" type="submit">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                                <?php if($status !== 'Paid'): ?>
                                    <button class="btn btn-outline-success btn-3d" title="Pay" onclick="PurchaseUI.openPaymentModal(<?= $p['id'] ?>, <?= $p['amount']-$paid ?>)">
                                        <i class="fa fa-money-bill-wave"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($purchases)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No purchases found for the selected date range.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delete Error Modal -->
<div class="modal fade" id="delete-error-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="deleteErrorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteErrorModalLabel">Error</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="delete-error-message" class="fs-5 text-danger"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Modals used by PurchaseUI -->
<div class="modal fade" id="purchase-details-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="purchaseDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content"></div>
    </div>
</div>

<div class="modal fade" id="purchase-payment-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="purchasePaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content"></div>
    </div>
</div>

<script src="js/purchases.js"></script>
<script>
$(document).ready(function() {
    if ($('#purchases-table').length && window.UnifiedTables) {
        UnifiedTables.init('#purchases-table', 'purchases');
    }

    // When month dropdown changes, auto-fill from/to and submit
    $('#month').on('change', function() {
        const val = this.value;
        if (!val) return; // user might want custom manual dates
        // Derive month boundaries client-side for UX (server still validates)
        const parts = val.split('-');
        if (parts.length === 2) {
            const year = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10);
            if (year && month) {
                const firstDay = new Date(year, month - 1, 1);
                const lastDay = new Date(year, month, 0);
                const fd = firstDay.toISOString().slice(0,10);
                const ld = lastDay.toISOString().slice(0,10);
                $('#from_date').val(fd);
                $('#to_date').val(ld);
                $('#purchases-filter-form')[0].submit();
            }
        }
    });

    // If manual dates changed, clear month selection (so user knows it's a custom range)
    $('#from_date, #to_date').on('change', function() {
        $('#month').val('');
    });
});
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>