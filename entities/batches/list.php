<?php
require_once __DIR__.'/../../includes/auth_check.php';
require_once __DIR__.'/../../includes/db_connection.php';
require_once __DIR__.'/../../includes/functions.php'; // for h() if available

// AJAX endpoint for Move Order modal: return eligible batches as JSON (leave untouched)
if (isset($_GET['ajax']) && $_GET['ajax'] == 1 && isset($_GET['eligible'])) {
    $stmt = $pdo->query("SELECT id, batch_name, status FROM shipping_batches WHERE status != 2 ORDER BY id DESC");
    $eligible = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($eligible);
    exit;
}

$pageTitle = "Batches";
include_once __DIR__.'/../../includes/header.php';

/*
 * Date Filter Logic
 * Default: current month (first day of month -> today)
 * Supports:
 *   - Quick month selector (?month=YYYY-MM)
 *   - Manual from_date / to_date (YYYY-MM-DD)
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

// Manual overrides have priority
if (isset($_GET['from_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from_date'])) {
    $from_date = $_GET['from_date'];
}
if (isset($_GET['to_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to_date'])) {
    $to_date = $_GET['to_date'];
}

// Fallback to defaults
$from_date = $from_date ?? $defaultFrom;
$to_date   = $to_date ?? $defaultTo;

// Ensure logical order
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

// Fetch batches within filtered date range (batch_date column)
$stmt = $pdo->prepare("
    SELECT * 
    FROM shipping_batches
    WHERE batch_date >= :from_date
      AND batch_date <= :to_date
    ORDER BY batch_date DESC, id DESC
");
$stmt->execute([
    ':from_date' => $from_date,
    ':to_date'   => $to_date
]);
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper escape
if (!function_exists('h')) {
    function h($v){ return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}
?>
<link rel="stylesheet" href="/assets/css/batches.css">
<div class="page-content-wrapper">
    <div class="container mt-3">
        <div class="row mb-2">
            <div class="col-md-7">
                <h2 class="text-primary"><i class="fa fa-layer-group me-2"></i> Batches</h2>
            </div>
            <div class="col-md-5 text-md-end mt-2 mt-md-0">
                <button class="btn btn-primary btn-3d" id="btn-create-batch">
                    <i class="fa fa-plus me-1"></i> New Batch
                </button>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card mb-3">
            <div class="card-body py-3">
                <form id="batches-filter-form" class="row gy-2 gx-2 align-items-end" method="get" action="">
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
                        <a href="list.php" class="btn btn-sm btn-secondary">
                            <i class="fa fa-undo me-1"></i> Reset
                        </a>
                    </div>
                    <div class="col-auto ms-auto">
                        <span class="badge bg-info text-dark">
                            Showing: <?= h($from_date) ?> → <?= h($to_date) ?>
                        </span>
                        <span class="badge bg-secondary">
                            Rows: <?= count($batches) ?>
                        </span>
                    </div>
                </form>
            </div>
        </div>

        <div class="alert alert-info max-width-700 mb-4">
            <strong>Manage your shipping batches.</strong> Use <strong>New Batch</strong> to create shipping batches for orders.<br>
            Track batch status from pending to delivered. All columns are searchable and sortable.
        </div>

        <table class="entity-table table table-striped table-hover table-consistent" id="batches-table">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Batch Name</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Orders</th>
                    <th>Created</th>
                    <th style="width:90px;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($batches)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No batches found for the selected date range.</td></tr>
            <?php endif; ?>
            <?php foreach($batches as $batch): ?>
                <tr data-batch-id="<?= (int)$batch['id'] ?>">
                    <td><?= (int)$batch['id'] ?></td>
                    <td><?= h($batch['batch_name']) ?></td>
                    <td><?= h($batch['batch_date']) ?></td>
                    <td>
                        <?php
                        $status = (int)$batch['status'];
                        if ($status === 0) {
                            echo '<span class="badge-status badge-secondary">Pending</span>';
                        } elseif ($status === 1) {
                            echo '<span class="badge-status badge-warning">In Process</span>';
                        } elseif ($status === 2) {
                            echo '<span class="badge-status badge-success">Delivered</span>';
                        } else {
                            echo '<span class="badge-status badge-secondary">Unknown</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <i class="fa fa-box"></i>
                    </td>
                    <td><?= h($batch['created_at']) ?></td>
                    <td>
                        <div class="btn-group btn-group-sm action-icons" role="group">
                            <a href="batch.php?id=<?= (int)$batch['id'] ?>" class="btn btn-outline-primary btn-3d" target="_blank" title="Details">
                                <i class="fa fa-eye"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Batch Modal (Bootstrap Modal) -->
<div class="modal fade" id="createBatchModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="createBatchModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createBatchModalLabel">
                    <i class="fa fa-layer-group me-2"></i>Create New Batch
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="create-batch-form" method="post" action="create.php">
                    <div class="mb-3">
                        <label for="batch_name" class="form-label">
                            Batch Name
                            <span class="text-muted fw-normal">(leave blank for auto)</span>
                        </label>
                        <input type="text" name="batch_name" id="batch_name" class="form-control" placeholder="Batch YYYY-MM-DD #100XXX">
                    </div>
                    <div class="mb-3">
                        <label for="batch_date" class="form-label">Batch Date</label>
                        <input type="date" name="batch_date" id="batch_date" class="form-control" required value="<?= h($defaultTo) ?>">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Create</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                    <!-- CSRF token (add if your create.php expects it) -->
                    <?php if (function_exists('csrf_token')): ?>
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="js/batches.js"></script>
<script>
$(function() {
    if (window.UnifiedTables) {
        window.UnifiedTables.init('#batches-table', 'batches');
    }

    // Create batch modal logic - use UnifiedModals
    $('#btn-create-batch').on('click', function(){
        window.UnifiedModals.show('createBatchModal');
    });

    // Quick month selector: when changed, auto-calc month boundaries and submit
    $('#month').on('change', function(){
        const val = this.value;
        if (!val) return; // user might want custom manual range
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
                $('#batches-filter-form')[0].submit();
            }
        }
    });

    // Manual date change clears month selector
    $('#from_date, #to_date').on('change', function() {
        $('#month').val('');
    });
});
</script>
<?php include_once __DIR__.'/../../includes/footer.php'; ?>