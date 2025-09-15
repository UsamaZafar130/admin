<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Customers";
require_once __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

// Fetch all customers (not trashed)
$stmt = $pdo->prepare("SELECT * FROM customers WHERE deleted_at IS NULL ORDER BY created_at DESC");
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

function whatsapp_link($contact, $customer_name, $reminder = false, $amount = 0, $invoice_token = null) {
    $num = preg_replace('/[^\d]/', '', get_contact_normalized(normalize_contact($contact)));
    if (strlen($num) === 10) $num = '92' . $num;
    if ($reminder) {
        $msg = "Assalam o Alaikum $customer_name,%0A%0A"
             . "I hope you and your loved ones are doing well.%0A%0A"
             . "This is a gentle reminder regarding your outstanding balance of ".urlencode(format_currency($amount, false)).".%0A";
        if ($invoice_token) {
            $invoice_url = "https://admin.frozofun.com/customer_invoice.php?token=" . urlencode($invoice_token);
            $msg .= "%0AView your invoice:%0A$invoice_url%0A";
        }
        $msg .= "If you have already made the payment, please ignore this message. Otherwise, we would appreciate it if you could arrange the payment at your earliest convenience.%0A%0A"
             . "Thank you so much for your continued trust and support.%0A%0ABest regards,%0A*".(isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'FrozoFun')."*";
    } else {
        $msg = "Assalam o Alaikum $customer_name,\nHope you are doing well. I would like to get in touch regarding our services. Kindly let me know if you have any queries. Thank you!";
        $msg = urlencode($msg);
    }
    return "https://wa.me/$num?text=$msg";
}

function get_unpaid_orders($customer_id, $pdo) {
    $stmt = $pdo->prepare("SELECT id, public_token, grand_total FROM sales_orders WHERE customer_id = ? AND paid = 0 AND cancelled = 0 ORDER BY order_date DESC");
    $stmt->execute([$customer_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Outstanding balances for Receivable summary ---
$outstanding_customers = [];
$total_receivable = 0;
$all_customers_stmt = $pdo->query("SELECT id, name, contact FROM customers WHERE deleted_at IS NULL");
while ($row = $all_customers_stmt->fetch(PDO::FETCH_ASSOC)) {
    $bal = get_customer_balance($row['id'], $pdo);
    if ($bal > 0) {
        $outstanding_customers[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'contact' => $row['contact'],
            'outstanding' => $bal
        ];
        $total_receivable += $bal;
    }
}
?>
<link rel="stylesheet" href="/assets/css/customers.css">

<div class="page-content-wrapper">
    <div class="container mt-3">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="text-primary"><i class="fa fa-users me-2"></i> Customers</h2>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-success btn-3d me-2" data-bs-toggle="modal" data-bs-target="#receivable-modal">
                    <i class="fa fa-chart-bar me-1"></i> Total Receivable
                </button>
                <button class="btn btn-primary btn-3d" onclick="showCustomerModal()">
                    <i class="fa fa-plus me-1"></i> Add Customer
                </button>
            </div>
        </div>
    <div class="alert alert-info max-width-700">
        <b>Manage your customers and outstanding balances.</b>
        Use <b>Add Customer</b> to register new customers, and <b>Trash</b> to view or restore deleted customers.<br>
        Click <b>Total Receivable</b> to see a summary of all outstanding amounts. All columns are searchable and sortable.
    </div>
    <div class="mb-3">
        <a class="btn btn-outline-danger btn-3d" href="trash.php" title="View Trash">
            <i class="fa fa-trash me-1"></i> Trash
        </a>
    </div>
    <div id="customers-list-wrap">
        <table class="entity-table table table-striped table-hover table-consistent" id="customers-table">
        <thead class="table-light">
            <tr>
                <th data-priority="1">Name</th>
                <th data-priority="4">Contact</th>
                <th data-priority="6">Address</th>
                <th data-priority="5">City</th>
                <th data-priority="2">Balance/Surplus</th>
                <th data-priority="3">Unpaid Invoices</th>
                <th data-priority="7">Created</th>
                <th class="actions-cell" data-priority="1">Actions</th>
            </tr>
        </thead>
            <tbody>
            <?php foreach ($customers as $c): 
                $balance = get_customer_balance($c['id'], $pdo);
                $balanceLabel = $balance > 0 ? 'Outstanding' : ($balance < 0 ? 'Surplus' : 'Settled');
                $balanceClass = $balance > 0 ? 'badge-consistent badge-danger' : ($balance < 0 ? 'badge-consistent badge-success' : 'badge-consistent badge-secondary');
                $unpaid_orders = get_unpaid_orders($c['id'], $pdo);
            ?>
                <tr 
                    data-customer-id="<?= $c['id'] ?>"
                    data-customer='<?= h(json_encode([
                        "id"=>$c['id'],
                        "name"=>$c['name'],
                        "contact"=>$c['contact'],
                        "contact_normalized"=>$c['contact_normalized'],
                        "house_no"=>$c['house_no'],
                        "area"=>$c['area'],
                        "city"=>$c['city'],
                        "location"=>$c['location'],
                        "created_at"=>$c['created_at'],
                        "balance"=>$balance
                    ])) ?>'>
                    <td class="customer-name" data-label="Name"><?= h($c['name']) ?></td>
                    <td class="customer-contact" data-label="Contact">
                        <a href="<?= whatsapp_link($c['contact'], $c['name']) ?>" target="_blank" class="whatsapp-link">
                            <i class="fab fa-whatsapp"></i> <?= h($c['contact']) ?>
                        </a>
                    </td>
                    <td class="customer-address" data-label="Address"><?= h($c['house_no'] . ', ' . $c['area']) ?></td>
                    <td class="customer-city" data-label="City"><?= h($c['city']) ?></td>
                    <td data-label="Balance/Surplus">
                        <span class="badge <?= $balanceClass ?> customer-balance">
                            <?= abs($balance) ?>
                            <?= $balance != 0 ? ('<small>' . $balanceLabel . '</small>') : '' ?>
                        </span>
                    </td>
                    <td data-label="Unpaid Invoices">
                        <?php if ($unpaid_orders): ?>
                            <?php foreach ($unpaid_orders as $uo): ?>
                                <div>
                                    <a href="/customer_invoice.php?token=<?= h($uo['public_token']) ?>" target="_blank" title="View Invoice">
                                        Invoice #<?= $uo['id'] ?> (<?= format_currency($uo['grand_total']) ?>)
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-muted">None</span>
                        <?php endif; ?>
                    </td>
                    <td class="customer-created" data-label="Created"><?= date('Y-m-d', strtotime($c['created_at'])) ?></td>
                    <td class="actions-cell" data-label="Actions">
                        <div class="btn-group btn-group-sm action-icons" role="group">
                            <button class="btn btn-outline-primary btn-3d" title="Details" onclick="CustomerUI.openDetails(<?= $c['id'] ?>)"><i class="fa fa-eye"></i></button>
                            <button class="btn btn-outline-warning btn-3d" title="Edit" onclick="showCustomerModal(<?= $c['id'] ?>)"><i class="fa fa-edit"></i></button>
                            <button class="btn btn-outline-danger btn-3d" title="Delete" onclick="CustomerUI.deleteCustomer(<?= $c['id'] ?>)"><i class="fa fa-trash"></i></button>
                            <?php if ($balance > 0): ?>
                                <button class="btn btn-outline-success btn-3d" title="Receive Payment" onclick="CustomerUI.openPaymentModal(<?= $c['id'] ?>)"><i class="fa fa-money"></i></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    </div>
</div>

<!-- Bootstrap Receivable Modal -->
<div class="modal fade" id="receivable-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="receivableModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="receivableModalLabel">Total Receivable</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="total-receivable">
                    Your Total Receivable is <span class="text-warning fw-bold"><?= format_currency($total_receivable) ?></span>
                </div>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Customer Name</th>
                            <th class="text-end">Outstanding Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($outstanding_customers as $oc): ?>
                        <tr>
                            <td>
                                <?php
                                    $unpaid_orders = get_unpaid_orders($oc['id'], $pdo);
                                    $invoice_token = $unpaid_orders && isset($unpaid_orders[0]['public_token']) ? $unpaid_orders[0]['public_token'] : null;
                                ?>
                                <a href="<?= whatsapp_link($oc['contact'], $oc['name'], true, $oc['outstanding'], $invoice_token) ?>" target="_blank" class="customer-wa-link">
                                    <?= h($oc['name']) ?>
                                </a>
                            </td>
                            <td class="amount"><?= format_currency($oc['outstanding']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($outstanding_customers)): ?>
                        <tr>
                            <td colspan="2" class="text-center text-muted">No outstanding receivables.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap Modals for customer details and payments only -->
<div class="modal fade" id="customer-details-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="customerDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<div class="modal fade" id="customer-payment-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="customerPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<script src="/entities/customers/js/customers.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable with Bootstrap 5 styling
    if ($('#customers-table').length && window.UnifiedTables) {
        UnifiedTables.init('#customers-table', 'customers');
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>