<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Trash - Purchases";
include __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

$stmt = $pdo->prepare("
    SELECT p.*, v.name AS vendor_name
    FROM purchases p
    LEFT JOIN vendors v ON p.vendor_id = v.id
    WHERE p.deleted_at IS NOT NULL
    ORDER BY p.deleted_at DESC
");
$stmt->execute();
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="entity-header">
    <h2>Trashed Purchases</h2>
    <a class="btn btn-primary" href="list.php">← Back to Purchases</a>
</div>
<div id="purchases-trash-wrap">
    <table class="entity-table" id="purchases-trash-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Vendor</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Description</th>
                <th>Deleted At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($purchases as $p): ?>
            <tr>
                <td><?= h(date('Y-m-d', strtotime($p['date']))) ?></td>
                <td><?= h($p['vendor_name']) ?></td>
                <td><?= ucfirst($p['type']) ?></td>
                <td><?= number_format($p['amount'],2) ?></td>
                <td><?= h($p['description']) ?></td>
                <td><?= h($p['deleted_at']) ?></td>
                <td>
                    <form method="post" action="actions.php" style="display:inline;">
                        <input type="hidden" name="action" value="restore">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <button class="btn btn-primary" type="submit" onclick="return confirm('Restore this purchase?')">Restore</button>
                    </form>
                    <form method="post" action="actions.php" style="display:inline;">
                        <input type="hidden" name="action" value="delete_permanent">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <button class="btn btn-danger" type="submit" onclick="return confirm('Permanently delete this purchase? This cannot be undone.')">Delete Permanently</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>