<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/db_connection.php';

// Find the earliest upcoming batch (today or later)
$stmt = $pdo->prepare("SELECT * FROM shipping_batches WHERE batch_date >= CURDATE() ORDER BY batch_date ASC, id ASC LIMIT 1");
$stmt->execute();
$batch = $stmt->fetch(PDO::FETCH_ASSOC);

function glimpse_modal_header($batch) {
    ?>
    <div class="batch-summary-header">
        <span>
            <i class="fa fa-list-ul"></i>
            Stock Shortfall
        </span>
        <button class="batch-summary-close" data-bs-dismiss="modal" aria-label="Close">&times;</button>
    </div>
    <div class="batch-summary-badges">
        <span class="badge-summary badge-summary-batch"><b>Batch:</b> <?= htmlspecialchars($batch['batch_name']) ?></span>
        <span class="badge-summary badge-summary-date"><?= htmlspecialchars($batch['batch_date']) ?></span>
    </div>
    <?php
}

if (!$batch) {
    ?>
    <div class="batch-summary-modal transparent-bg">
        <div class="batch-summary-header">
            <span>
                <i class="fa fa-list-ul"></i>
                Stock Shortfall
            </span>
            <button class="batch-summary-close" data-bs-dismiss="modal" aria-label="Close">&times;</button>
        </div>
        <div class="text-muted-custom">
            No upcoming batch found.
        </div>
    </div>
    <?php
    exit;
}

// Get undelivered, not-cancelled orders in this batch
$stmt = $pdo->prepare("
    SELECT so.id
    FROM shipping_batch_orders cbo
    JOIN sales_orders so ON cbo.order_id=so.id
    WHERE cbo.batch_id=? AND so.delivered=0 AND so.cancelled=0
");
$stmt->execute([$batch['id']]);
$order_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!$order_ids) {
    ?>
    <div class="batch-summary-modal transparent-bg">
        <?php glimpse_modal_header($batch); ?>
        <div class="text-muted-custom">
            No undelivered, not-cancelled orders in the next batch.<br>
            Batch: <b><?= htmlspecialchars($batch['batch_name']) ?></b>
        </div>
    </div>
    <?php
    exit;
}

// Get item requirements for these orders
$in  = str_repeat('?,', count($order_ids) - 1) . '?';
$stmt = $pdo->prepare("SELECT oi.item_id, i.name, SUM(oi.qty) as total_qty
    FROM order_items oi
    JOIN items i ON oi.item_id = i.id
    WHERE oi.order_id IN ($in)
    GROUP BY oi.item_id, i.name
    ORDER BY i.name");
$stmt->execute($order_ids);
$req_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get manufactured stock for these items
$item_ids = array_column($req_items, 'item_id');
$in_items = $item_ids ? (str_repeat('?,', count($item_ids) - 1) . '?') : '';
$stocked = [];
if ($item_ids) {
    $st2 = $pdo->prepare("
        SELECT item_id, SUM(qty) AS total_stock
        FROM inventory_ledger
        WHERE item_id IN ($in_items)
        GROUP BY item_id
    ");
    $st2->execute($item_ids);
    foreach ($st2->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $stocked[$row['item_id']] = intval($row['total_stock']);
    }
}

$glimpse = [];
foreach ($req_items as $it) {
    $manufactured = $stocked[$it['item_id']] ?? 0;
    $more_required = (int)$it['total_qty'] - $manufactured;
    if ($more_required > 0) {
        $glimpse[] = [
            'name' => $it['name'],
            'more_required' => $more_required
        ];
    }
}
?>
<div class="batch-summary-modal transparent-bg">
    <?php glimpse_modal_header($batch); ?>
    <div class="batch-summary-table-wrap no-scrollbar">
    <?php if (count($glimpse) === 0): ?>
        <div class="text-success-custom">
            All items are fully manufactured for this batch.<br>No further stock required!
        </div>
    <?php else: ?>
        <table class="batch-summary-table">
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>More Required</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($glimpse as $row): ?>
                <tr>
                    <td class="glimpse-item"><?= htmlspecialchars($row['name']) ?></td>
                    <td class="glimpse-more"><?= $row['more_required'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>
</div>
<style>
/* Transparent modal background */
.batch-summary-modal.transparent-bg {
    background: transparent !important;
    box-shadow: none !important;
    border-radius: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
}

/* Rest of the modal and table styling remains the same */
.batch-summary-header {
    background: #243c5a;
    color: #fff;
    font-size: 1.22rem;
    font-weight: 600;
    padding: 20px 28px 14px 28px;
    border-top-left-radius: 20px;
    border-top-right-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    letter-spacing: 0.3px;
}
.batch-summary-header .fa {
    font-size: 1.18em;
    margin-right: 9px;
}
.batch-summary-close {
    background: #fff;
    border: none;
    color: #243c5a;
    font-size: 1.65rem;
    font-weight: bold;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    line-height: 35px;
    text-align: center;
    transition: background 0.18s, color 0.18s;
    cursor: pointer;
    margin-left: 18px;
    box-shadow: 0 1px 5px #243c5a13;
}
.batch-summary-close:hover, .batch-summary-close:focus {
    background: #e74c3c;
    color: #fff;
    outline: none;
}
.batch-summary-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 12px 14px;
    padding: 22px 28px 12px 28px;
    align-items: center;
}
.badge-summary {
    font-size: 15px !important;
    padding: 7px 17px !important;
    min-width: 110px;
    margin-bottom: 5px;
    border-radius: 13px;
    font-weight: 600;
    color: #fff;
    display: inline-block;
    box-shadow: 0 2px 8px rgba(52,73,94,0.09);
    border: none;
    letter-spacing: 0.2px;
}
.badge-summary-batch { background: #34495e; }
.badge-summary-date { background: #3498db; }
.batch-summary-table-wrap {
    width: 100%;
    overflow-x: auto;
    padding: 12px 18px 0 18px;
    max-height: 48vh;
}
.batch-summary-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0;
    font-size: clamp(0.98em, 2vw, 1.13em);
}
.batch-summary-table th {
    background: #f1f6fd;
    color: #1877F2;
    font-size: 1.06em;
    font-weight: 700;
    padding: 8px 10px;
    border-bottom: 2px solid #eaf0fa;
    text-align: left;
    white-space: nowrap;
}
.batch-summary-table td {
    background: #fff;
    padding: 7px 10px;
    font-size: inherit;
    font-weight: 500;
    white-space: nowrap;
    border-bottom: 1px solid #f2f7fb;
    vertical-align: middle;
}
.batch-summary-table tr:last-child td {
    border-bottom: none;
}
.glimpse-item {
    color:#1877F2;
    font-weight:600;
}
.glimpse-more {
    color:#c0392b;
    font-weight:700;
}
.no-scrollbar {
    scrollbar-width: none !important;
    -ms-overflow-style: none !important;
}
.no-scrollbar::-webkit-scrollbar {
    display: none !important;
}
@media (max-width: 650px) {
    .batch-summary-header, .batch-summary-badges {
        padding-left: 6vw;
        padding-right: 6vw;
    }
    .batch-summary-table-wrap {
        padding-left: 1vw;
        padding-right: 1vw;
    }
}
@media (max-width: 420px) {
    .batch-summary-header {
        font-size: 1.07rem;
        padding: 13px 6vw 10px 6vw;
        border-radius: 10px 10px 0 0;
    }
    .batch-summary-badges {
        padding: 13px 6vw 7px 6vw;
        gap: 10px 7px;
    }
}
</style>