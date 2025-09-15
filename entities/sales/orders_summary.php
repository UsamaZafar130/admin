<?php
$pageTitle = "Undelivered Orders Summary";
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';
$pdo = $GLOBALS['pdo'] ?? require __DIR__ . '/../../includes/db_connection.php';

// Fetch undelivered orders with customer name and grand total
$sql = "SELECT so.id, so.order_date, so.customer_id, so.grand_total, so.delivery_charges, c.name AS customer_name
        FROM sales_orders so
        LEFT JOIN customers c ON so.customer_id = c.id
        WHERE so.delivered = 0 AND so.cancelled = 0
        ORDER BY so.order_date DESC";
$stmt = $pdo->query($sql);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all items for these orders in one query for efficiency
$orderIds = array_column($orders, 'id');
$itemsByOrder = [];
if ($orderIds) {
    $in = implode(',', array_map('intval', $orderIds));
    $itemSql = "SELECT oi.order_id, i.name AS item_name, oi.qty, oi.pack_size 
                FROM order_items oi 
                LEFT JOIN items i ON oi.item_id = i.id
                WHERE oi.order_id IN ($in)
                ORDER BY oi.order_id, oi.id";
    $itemStmt = $pdo->query($itemSql);
    foreach ($itemStmt as $row) {
        $itemsByOrder[$row['order_id']][] = $row;
    }
}

// Calculate totals
$totalGrand = 0;
$totalDelivery = 0;
foreach ($orders as $o) {
    $totalGrand += floatval($o['grand_total']);
    $totalDelivery += floatval($o['delivery_charges']);
}

// Helper function to format item packs as required
function format_item_packs($name, $qty, $pack_size) {
    $result = [];
    $qty = (int)$qty;
    $pack_size = (int)$pack_size;
    $name = htmlspecialchars($name);

    if ($pack_size > 0 && $qty > 0) {
        $full_packs = intdiv($qty, $pack_size);
        $remainder = $qty % $pack_size;

        if ($full_packs > 0) {
            $result[] = "{$name} {$pack_size} x {$full_packs}";
        }
        if ($remainder > 0) {
            $result[] = "{$name} {$remainder} x 1";
        }
    } elseif ($qty > 0) {
        $result[] = "{$name} {$qty} x 1";
    }
    return $result;
}
?>

<style>
.entity-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 18px;
    margin-top: 18px;
    flex-wrap: wrap;
}
.entity-header h2 {
    margin: 0;
    font-weight: 700;
    color: #0070e0;
    font-size: 2rem;
    letter-spacing: -.5px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.entity-header .entity-back-btn {
    display: inline-flex;
    align-items: center;
    background: none;
    border: none;
    color: #007bff;
    font-size: 1.4rem;
    font-weight: 500;
    cursor: pointer;
    padding: 8px 10px;
    text-decoration: none;
    transition: color 0.15s;
    gap: 7px;
}
.entity-header .entity-back-btn:hover, .entity-header .entity-back-btn:focus {
    color: #0056b3;
    text-decoration: none;
}
.entity-header .summary-totals-inline {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
    font-size: 1.08em;
    font-weight: 600;
    margin-left: 18px;
}
.entity-header .summary-totals-inline .summary-totals-label {
    color: #1869e3;
    font-size: 1em;
    font-weight: 700;
    margin-right: 3px;
}
.entity-header .summary-totals-inline .summary-totals-amount {
    color: #007bff;
    font-size: 1.13em;
    font-weight: 700;
    letter-spacing: 0.5px;
}
@media (max-width: 900px) {
    .entity-header {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    .entity-header h2 {
        justify-content: center;
        font-size: 1.3rem;
    }
    .entity-header .summary-totals-inline {
        align-items: flex-start;
        margin: 10px 0 0 0;
        font-size: 1em;
    }
}
.orders-table .badge {
    font-size: 0.98rem;
    font-weight: 600;
    padding: 4px 9px;
    border-radius: 7px;
    letter-spacing: .02em;
    color: #333;
}
.top-row {
    margin-bottom: 14px;
    gap: 18px;
}
@media (max-width: 600px) {
    .top-row {
        flex-direction: column;
        align-items: stretch;
        gap: 5px;
    }
}
</style>
<div class="orders-content">
    <div class="container mt-3">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="text-primary"><i class="fa fa-chart-bar me-2"></i> Undelivered Orders Summary</h2>
            </div>
            <div class="col-md-4 text-end">
                <a href="orders_list.php" class="btn btn-success btn-3d me-2" title="Back to Orders">
                    <i class="fa fa-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <div class="alert alert-info mb-4 page-info-alert">
            <strong>This page shows all undelivered orders with quick totals and item breakdowns.</strong><br>
            Batch Total Amount: <strong><?= format_currency($totalGrand) ?></strong> | 
            Total Delivery Charges: <strong><?= format_currency($totalDelivery) ?></strong>
        </div>
        <div class="table-responsive">
            <table class="entity-table table table-striped table-hover table-consistent" id="orders-table" style="width:100%">
                <thead>
                <tr>
                    <th data-priority="1">Order #</th>
                    <th data-priority="2">Date</th>
                    <th data-priority="3">Customer Name</th>
                    <th data-priority="4">Items</th>
                    <th data-priority="5">Grand Total</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td data-label="Order #"><?= format_order_number($order['id']) ?></td>
                    <td data-label="Date"><?= format_datetime($order['order_date'], get_user_timezone(), 'Y-m-d') ?></td>
                    <td data-label="Customer Name"><?= htmlspecialchars($order['customer_name']) ?></td>
                    <td data-label="Items" style="white-space:normal;max-width:420px;">
                        <?php
                        if (!empty($itemsByOrder[$order['id']])) {
                            $itemStrings = [];
                            foreach ($itemsByOrder[$order['id']] as $item) {
                                $packs = format_item_packs($item['item_name'], $item['qty'], $item['pack_size']);
                                foreach ($packs as $packStr) {
                                    $itemStrings[] = $packStr;
                                }
                            }
                            echo implode(', ', $itemStrings);
                        } else {
                            echo '<span style="color:#aaa">No items</span>';
                        }
                        ?>
                    </td>
                    <td data-label="Grand Total" style="font-weight:600;color:#007bff;">
                        <?= format_currency($order['grand_total']) ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<script>
$(function() {
    $('#orders-table').DataTable({
        responsive: true,
        paging: true,
        searching: true,
        info: true,
        order: [[0, 'desc']],
        dom: '<"top-row d-flex align-items-center justify-content-between"lfi>rtp',
        columnDefs: [
            { responsivePriority: 1, targets: 0 }, // Order #
            { responsivePriority: 2, targets: 1 }, // Date
            { responsivePriority: 3, targets: 2 }, // Customer Name
            { responsivePriority: 4, targets: 3 }, // Items
            { responsivePriority: 5, targets: 4 }  // Grand Total
        ],
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ orders",
            info: "Showing _START_ to _END_ of _TOTAL_ orders",
            infoEmpty: "No orders found",
            zeroRecords: "No matching orders found"
        }
    });
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>