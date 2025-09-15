<?php
require_once __DIR__.'/../../includes/auth_check.php';
require_once __DIR__.'/../../includes/db_connection.php';
require_once __DIR__.'/../../includes/functions.php';

if (!function_exists('h')) {
    function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

/**
 * AJAX FRAGMENT: simplified "items required" style for an order
 * GET: batch_summary.php?order_items=1&order_id=#
 */
if (isset($_GET['order_items']) && isset($_GET['order_id'])) {
    header('Content-Type: text/html; charset=UTF-8');
    $order_id = (int)$_GET['order_id'];
    if ($order_id <= 0) {
        echo '<div class="p-3 text-danger">Invalid order.</div>';
        exit;
    }

    // Fetch items
    $stmt = $pdo->prepare("
        SELECT oi.qty, oi.pack_size, it.name AS item_name
        FROM order_items oi
        LEFT JOIN items it ON oi.item_id = it.id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch meals
    $stmt = $pdo->prepare("
        SELECT om.qty, m.name AS meal_name
        FROM order_meals om
        LEFT JOIN meals m ON om.meal_id = m.id
        WHERE om.order_id = ?
        ORDER BY om.id ASC
    ");
    $stmt->execute([$order_id]);
    $meals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $lines = [];

    // Build item lines like: Name pack_size x packs_required
    foreach ($items as $it) {
        $packSize = (float)$it['pack_size'];
        if ($packSize <= 0) $packSize = 1;
        $qty = (float)$it['qty'];
        $packsRequired = ($packSize > 0) ? floor($qty / $packSize) : 0;
        if ($packsRequired < 1) $packsRequired = 1; // mimic original modal behavior fallback

        // Format pack size without trailing zeros if possible
        $packSizeDisplay = (fmod($packSize, 1) === 0.0) ? (int)$packSize : rtrim(rtrim(number_format($packSize, 2), '0'), '.');

        $lines[] = h($it['item_name']) . ' ' . $packSizeDisplay . ' x ' . $packsRequired;
    }

    // Build meal lines: MealName qty x 1
    foreach ($meals as $m) {
        $qty = (float)$m['qty'];
        $qtyDisplay = (fmod($qty, 1) === 0.0) ? (int)$qty : rtrim(rtrim(number_format($qty, 2), '0'), '.');
        $lines[] = h($m['meal_name']) . ' ' . $qtyDisplay . ' x 1';
    }

    if (empty($lines)) {
        echo '<div class="p-3 text-muted">No line items for this order.</div>';
        exit;
    }

    // Output styled list
    ?>
    <div class="p-3">
        <div class="fw-bold small text-uppercase text-secondary mb-2">Items Required</div>
        <div class="d-flex flex-wrap gap-2 item-pills-wrapper">
            <?php foreach ($lines as $ln): ?>
                <span class="badge bg-light border text-dark fw-normal" style="font-size:.75rem; padding:.45rem .6rem; border-radius:.65rem;">
                    <?= $ln ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
    <style>
        /* Scoped styling for pills (only inside this fragment) */
        .item-pills-wrapper::-webkit-scrollbar { height: 6px; }
        .item-pills-wrapper::-webkit-scrollbar-track { background: #f1f1f1; }
        .item-pills-wrapper::-webkit-scrollbar-thumb { background:#c7c7c7; border-radius: 3px; }
        .item-pills-wrapper::-webkit-scrollbar-thumb:hover { background:#b0b0b0; }
    </style>
    <?php
    exit;
}

/**
 * MAIN SUMMARY
 */
$batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
if ($batch_id <= 0) {
    echo '<div class="alert alert-danger m-0">Batch not found.</div>';
    exit;
}

/**
 * Include pack_no & order by pack_no (NULLs first), then pack_no asc, then order id
 */
$stmt = $pdo->prepare("
    SELECT 
        so.id,
        so.grand_total,
        so.delivered,
        c.name AS customer_name,
        sbo.pack_no,
        pay.total_paid,
        pay.last_method AS payment_method
    FROM shipping_batch_orders sbo
    JOIN sales_orders so ON sbo.order_id = so.id
    LEFT JOIN customers c ON so.customer_id = c.id
    LEFT JOIN (
        SELECT 
            op.order_id,
            SUM(op.amount) AS total_paid,
            SUBSTRING_INDEX(
                GROUP_CONCAT(op.payment_method ORDER BY op.paid_at DESC SEPARATOR ','),
                ',', 1
            ) AS last_method
        FROM order_payments op
        GROUP BY op.order_id
    ) pay ON pay.order_id = so.id
    WHERE sbo.batch_id = ?
    ORDER BY 
        (sbo.pack_no IS NULL) DESC,
        sbo.pack_no ASC,
        so.id ASC
");
$stmt->execute([$batch_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_batch_amount        = 0.0;
$total_received            = 0.0;
$total_bank                = 0.0;
$total_cash                = 0.0;
$total_pending_orders      = 0;
$total_payments_received   = 0.0;

foreach ($orders as $o) {
    $grand   = (float)$o['grand_total'];
    $paidAmt = (float)($o['total_paid'] ?? 0);
    $method  = $o['payment_method'];

    $total_batch_amount      += $grand;
    $total_payments_received += $paidAmt;

    if ($paidAmt > 0) {
        $total_received += $paidAmt;
        if ($method === 'bank') $total_bank += $paidAmt; else $total_cash += $paidAmt;
    }
    if ($paidAmt < $grand) $total_pending_orders++;
}
$total_receivable = $total_batch_amount - $total_payments_received;
?>
<style>
#batch-summary-root { font-size:.92rem; }
.batch-summary-stats { display:flex; flex-wrap:wrap; gap:.75rem; margin-bottom:1rem; }
.batch-summary-tile {
    background: var(--bs-light,#f8f9fa);
    border:1px solid var(--bs-border-color,#dee2e6);
    border-radius:.5rem;
    padding:.65rem .85rem;
    min-width:170px;
    display:flex;
    flex-direction:column;
}
.batch-summary-tile .label {
    font-size:.68rem; letter-spacing:.05em; text-transform:uppercase;
    font-weight:600; color: var(--bs-secondary,#6c757d); margin-bottom:.2rem;
}
.batch-summary-tile .value { font-weight:600; font-size:.95rem; white-space:nowrap; }

.batch-summary-tile.total      { background:#eef6ff; border-color:#b6dcff; }
.batch-summary-tile.received   { background:#e6f9f1; border-color:#b5e8d5; }
.batch-summary-tile.bank       { background:#e8f3ff; border-color:#b9dafc; }
.batch-summary-tile.cash       { background:#fff9e6; border-color:#f5e3a6; }
.batch-summary-tile.pending    { background:#fff2ef; border-color:#f7c7bc; }
.batch-summary-tile.receivable { background:#fdefff; border-color:#e4b9ed; }

.batch-summary-controls { display:flex; flex-wrap:wrap; gap:1rem; align-items:center; margin-bottom:.75rem; }
.batch-summary-controls .form-check { margin:0; }

.batch-summary-table-wrap {
    max-height:55vh; overflow:auto;
    border:1px solid var(--bs-border-color,#dee2e6);
    border-radius:.5rem;
}

.order-row { cursor:pointer; transition:background-color .12s ease; }
.order-row:hover { background:rgba(0,123,255,0.08); }
.order-row.active { background:rgba(13,110,253,0.15); }
.details-row td { background:#fcfcfc; border-top:1px dashed #d9d9d9; padding:0 !important; }

.order-status-badge {
    display:inline-block; padding:.25rem .55rem; font-size:.65rem;
    font-weight:600; text-transform:uppercase; border-radius:.35rem;
    letter-spacing:.05em;
}
.order-status-paid { background:#d1f7e2; color:#0b6b36; }
.order-status-partial { background:#fff3cd; color:#775d00; }
.order-status-unpaid { background:#fde2e1; color:#842029; }

.order-icons i { font-size:1.05rem; vertical-align:middle; }
.order-details-loading { padding:1.25rem; text-align:center; font-size:.85rem; color:#666; }

@media (max-width: 768px) {
    .batch-summary-tile { min-width:48%; flex:1 1 calc(50% - .75rem); }
    .batch-summary-controls { gap:.75rem; }
}
</style>

<div id="batch-summary-root" data-batch-id="<?= (int)$batch_id ?>">
    <div class="batch-summary-stats">
        <div class="batch-summary-tile total">
            <div class="label">Total Batch Amount</div>
            <div class="value"><?= format_currency($total_batch_amount) ?></div>
        </div>
        <div class="batch-summary-tile received">
            <div class="label">Total Received</div>
            <div class="value"><?= format_currency($total_received) ?></div>
        </div>
        <div class="batch-summary-tile bank">
            <div class="label">In Bank</div>
            <div class="value"><?= format_currency($total_bank) ?></div>
        </div>
        <div class="batch-summary-tile cash">
            <div class="label">In Cash</div>
            <div class="value"><?= format_currency($total_cash) ?></div>
        </div>
        <div class="batch-summary-tile pending">
            <div class="label">Pending Orders</div>
            <div class="value"><?= h($total_pending_orders) ?></div>
        </div>
        <div class="batch-summary-tile receivable">
            <div class="label">Receivable</div>
            <div class="value"><?= format_currency($total_receivable) ?></div>
        </div>
    </div>

    <div class="batch-summary-controls">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="toggle-paid" checked>
            <label class="form-check-label" for="toggle-paid">Show Paid</label>
        </div>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="toggle-partial" checked>
            <label class="form-check-label" for="toggle-partial">Show Partial</label>
        </div>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="toggle-unpaid" checked>
            <label class="form-check-label" for="toggle-unpaid">Show Unpaid</label>
        </div>
        <small class="text-muted ms-auto">Click a row to view details</small>
    </div>

    <div class="batch-summary-table-wrap">
        <table class="table table-bordered table-striped table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="min-width:90px;">Pack #</th>
                    <th style="min-width:105px;">Order #</th>
                    <th>Customer</th>
                    <th class="text-end">Grand Total</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Receivable</th>
                    <th>Status</th>
                    <th style="width:90px;">Flags</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($orders): ?>
                <?php foreach ($orders as $order):
                    $grand      = (float)$order['grand_total'];
                    $paidAmt    = (float)($order['total_paid'] ?? 0);
                    $receivable = $grand - $paidAmt;
                    $packNo     = $order['pack_no'];

                    if ($grand > 0 && $paidAmt >= $grand) {
                        $statusKey   = 'paid';
                        $statusBadge = '<span class="order-status-badge order-status-paid">Paid</span>';
                    } elseif ($paidAmt > 0 && $paidAmt < $grand) {
                        $statusKey   = 'partial';
                        $statusBadge = '<span class="order-status-badge order-status-partial">Partial</span>';
                    } else {
                        $statusKey   = 'unpaid';
                        $statusBadge = '<span class="order-status-badge order-status-unpaid">Unpaid</span>';
                    }

                    if ($paidAmt > 0) {
                        if ($order['payment_method'] === 'bank') {
                            $paidIcon = '<i class="fa fa-university text-info" title="Paid by Bank"></i>';
                        } else {
                            $paidIcon = '<i class="fa fa-money-bill text-success" title="Paid"></i>';
                        }
                    } else {
                        $paidIcon = '<i class="fa fa-exclamation-circle text-warning" title="Payment Pending"></i>';
                    }

                    $deliveredIcon = $order['delivered']
                        ? '<i class="fa fa-check-circle text-success" title="Delivered"></i>'
                        : '<i class="fa fa-times-circle text-danger" title="Not Delivered"></i>';
                ?>
                    <tr class="order-row"
                        data-order-id="<?= (int)$order['id'] ?>"
                        data-status="<?= h($statusKey) ?>"
                        data-pack="<?= $packNo === null ? '' : (int)$packNo ?>">
                        <td><?= $packNo === null ? '' : h($packNo) ?></td>
                        <td><?= h(format_order_number($order['id'])) ?></td>
                        <td><?= h($order['customer_name']) ?></td>
                        <td class="text-end"><?= format_currency($grand) ?></td>
                        <td class="text-end"><?= format_currency($paidAmt) ?></td>
                        <td class="text-end <?= $receivable > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>">
                            <?= format_currency($receivable) ?>
                        </td>
                        <td><?= $statusBadge ?></td>
                        <td class="text-center order-icons">
                            <?= $deliveredIcon ?>&nbsp;<?= $paidIcon ?>
                        </td>
                    </tr>
                    <tr class="details-row d-none" data-details-for="<?= (int)$order['id'] ?>">
                        <td colspan="8"><div class="order-details-loading">Loading details...</div></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No orders in this batch.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>