<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Expenses";
include __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

/*
 * Date Filter Logic (same pattern as purchases)
 * Default: current month (first day → today)
 * Supports quick month dropdown (last 12 months) and manual from/to dates.
 */

$today = new DateTime('today');
$defaultFrom = (clone $today)->modify('first day of this month')->format('Y-m-d');
$defaultTo   = $today->format('Y-m-d');

$selectedMonth = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : '';
if ($selectedMonth) {
    $monthStart = DateTime::createFromFormat('Y-m-d', $selectedMonth . '-01');
    if ($monthStart) {
        $from_date = $monthStart->format('Y-m-d');
        $to_date = $monthStart->modify('last day of this month')->format('Y-m-d');
    }
}

// Manual overrides
if (isset($_GET['from_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from_date'])) {
    $from_date = $_GET['from_date'];
}
if (isset($_GET['to_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to_date'])) {
    $to_date = $_GET['to_date'];
}

// Fallback to defaults
$from_date = $from_date ?? $defaultFrom;
$to_date   = $to_date ?? $defaultTo;

// Ensure order
if ($from_date > $to_date) {
    $tmp = $from_date;
    $from_date = $to_date;
    $to_date = $tmp;
}

// Build last 12 month options (including current)
$monthOptions = [];
$cursor = new DateTime('first day of this month');
for ($i=0; $i<12; $i++) {
    $monthOptions[] = $cursor->format('Y-m');
    $cursor->modify('-1 month');
}

// Fetch expenses in date range
$stmt = $pdo->prepare("
    SELECT e.*, v.name AS vendor_name,
        (SELECT COUNT(*) FROM expense_payments ep WHERE ep.expense_id = e.id AND ep.deleted_at IS NULL) AS payment_count
    FROM expenses e
    LEFT JOIN vendors v ON e.vendor_id = v.id
    WHERE e.deleted_at IS NULL
      AND e.date >= :from_date
      AND e.date <= :to_date
    ORDER BY e.date DESC, e.created_at DESC
");
$stmt->execute([
    ':from_date' => $from_date,
    ':to_date'   => $to_date
]);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Paid sums
$expense_ids = array_column($expenses, 'id');
$amount_paid = [];
if ($expense_ids) {
    $in = implode(',', array_fill(0, count($expense_ids), '?'));
    $payStmt = $pdo->prepare("
        SELECT expense_id, SUM(amount) AS paid 
        FROM expense_payments 
        WHERE expense_id IN ($in) 
          AND deleted_at IS NULL 
        GROUP BY expense_id
    ");
    $payStmt->execute($expense_ids);
    foreach ($payStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $amount_paid[$row['expense_id']] = $row['paid'];
    }
}

// Applied advances
$advances_by_expense = [];
if ($expense_ids) {
    $in = implode(',', array_fill(0, count($expense_ids), '?'));
    $advStmt = $pdo->prepare("
        SELECT id, amount, applied_to_expense_id 
        FROM vendor_advances 
        WHERE applied_to_expense_id IN ($in)
    ");
    $advStmt->execute($expense_ids);
    foreach ($advStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $eid = $row['applied_to_expense_id'];
        if (!isset($advances_by_expense[$eid])) $advances_by_expense[$eid] = [];
        $advances_by_expense[$eid][] = $row;
    }
}
?>

<div class="page-content-wrapper">
    <div class="container mt-3">
        <div class="row mb-2">
            <div class="col-md-7">
                <h2 class="text-primary"><i class="fa fa-money-bill me-2"></i> Expenses</h2>
            </div>
            <div class="col-md-5 text-md-end mt-2 mt-md-0">
                <button class="btn btn-primary btn-3d" onclick="showExpenseModal()">
                    <i class="fa fa-plus me-1"></i> Add Expense
                </button>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card mb-3">
            <div class="card-body py-3">
                <form id="expenses-filter-form" class="row gy-2 gx-2 align-items-end" method="get" action="">
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
                            Rows: <?= count($expenses) ?>
                        </span>
                    </div>
                </form>
            </div>
        </div>

        <div class="alert alert-info max-width-700 mb-4">
            <strong>Track and manage business expenses.</strong> Use <strong>Add Expense</strong> to record vendor expenses and payments.<br>
            Monitor payment status and outstanding amounts. Use <strong>Trash</strong> to view deleted expenses.
        </div>

        <div class="mb-3 header-buttons-secondary">
            <a class="btn btn-outline-danger btn-3d" href="trash.php" title="View Trash">
                <i class="fa fa-trash me-1"></i> Trash
            </a>
        </div>

        <div id="expenses-list-wrap">
            <table class="entity-table table table-striped table-hover table-consistent" id="expenses-table">
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
                <?php foreach ($expenses as $e):
                    $paid = isset($amount_paid[$e['id']]) ? floatval($amount_paid[$e['id']]) : 0;
                    $status = ($paid >= floatval($e['amount'])) ? 'Paid' : ($paid > 0 ? 'Partial' : 'Unpaid');
                    $badgeClass = $status === 'Paid' ? 'badge-surplus' : ($status === 'Partial' ? 'badge-settled' : 'badge-outstanding');
                    $adv_list = $advances_by_expense[$e['id']] ?? [];
                ?>
                    <tr data-expense-id="<?= $e['id'] ?>"
                        data-expense='<?= h(json_encode($e)) ?>'
                        data-payment-count="<?= $e['payment_count'] ?>">
                        <td data-label="Date"><?= h(date('Y-m-d', strtotime($e['date']))) ?></td>
                        <td data-label="Vendor"><?= h($e['vendor_name']) ?></td>
                        <td data-label="Type">
                          <span class="badge <?= $e['type']=='credit'?'badge-outstanding':'badge-surplus' ?>">
                            <?= ucfirst($e['type']) ?>
                          </span>
                        </td>
                        <td data-label="Amount"><?= format_currency($e['amount']) ?></td>
                        <td data-label="Description"><?= h($e['description']) ?></td>
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
                                <button class="btn btn-outline-primary btn-3d" title="Details" onclick="ExpenseUI.openDetails(<?= $e['id'] ?>)">
                                    <i class="fa fa-eye"></i>
                                </button>
                                <?php if($status !== 'Paid'): ?>
                                    <button class="btn btn-outline-warning btn-3d" title="Edit" onclick="showExpenseModal(<?= $e['id'] ?>)">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                <?php endif; ?>
                                <form method="post" action="actions.php" class="ajax-delete-expense" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <button class="btn btn-outline-danger btn-3d" title="Delete" type="submit">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                                <?php if($status !== 'Paid'): ?>
                                    <button class="btn btn-outline-success btn-3d" title="Pay" onclick="ExpenseUI.openPaymentModal(<?= $e['id'] ?>, <?= $e['amount']-$paid ?>)">
                                        <i class="fa fa-money-bill-wave"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($expenses)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No expenses found for the selected date range.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Error Modal -->
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
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<div class="modal fade" id="expense-details-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="expenseDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content"></div>
    </div>
</div>

<div class="modal fade" id="expense-payment-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="expensePaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content"></div>
    </div>
</div>

<script src="js/expenses.js"></script>
<script>
$(document).ready(function() {
    if ($('#expenses-table').length && window.UnifiedTables) {
        UnifiedTables.init('#expenses-table', 'expenses');
    }

    // Quick month change autofills range and submits
    $('#month').on('change', function() {
        const val = this.value;
        if (!val) return;
        const parts = val.split('-');
        if (parts.length === 2) {
            const year = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10);
            if (year && month) {
                const firstDay = new Date(year, month - 1, 1);
                const lastDay  = new Date(year, month, 0);
                const fd = firstDay.toISOString().slice(0,10);
                const ld = lastDay.toISOString().slice(0,10);
                $('#from_date').val(fd);
                $('#to_date').val(ld);
                $('#expenses-filter-form')[0].submit();
            }
        }
    });

    // Clearing quick month when manual dates change
    $('#from_date, #to_date').on('change', function() {
        $('#month').val('');
    });
});
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>