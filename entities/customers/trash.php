<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Deleted Customers";
include __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

$stmt = $pdo->prepare("SELECT * FROM customers WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="entity-header">
    <h2>Deleted Customers (Trash)</h2>
    <a class="btn btn-secondary" href="list.php">← Back to Customers</a>
</div>
<div id="customers-trash-wrap">
    <table class="entity-table" id="customers-trash-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Contact</th>
                <th>Address</th>
                <th>City</th>
                <th>Deleted At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($customers as $c): ?>
            <tr data-customer-id="<?= $c['id'] ?>">
                <td class="customer-name" data-label="Name"><?= h($c['name']) ?></td>
                <td class="customer-contact" data-label="Contact"><?= h($c['contact']) ?></td>
                <td class="customer-address" data-label="Address"><?= h($c['house_no'] . ', ' . $c['area']) ?></td>
                <td class="customer-city" data-label="City"><?= h($c['city']) ?></td>
                <td data-label="Deleted At"><?= date('Y-m-d H:i', strtotime($c['deleted_at'])) ?></td>
                <td data-label="Actions">
                    <button class="btn-ico restore" title="Restore" onclick="CustomerUI.restoreCustomer(<?= $c['id'] ?>)"><i class="fa fa-undo"></i></button>
                    <button class="btn-ico danger" title="Delete Permanently" onclick="CustomerUI.deleteCustomerPermanent(<?= $c['id'] ?>)"><i class="fa fa-trash"></i></button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script src="/entities/customers/js/customers.js"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>