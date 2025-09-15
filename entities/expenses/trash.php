<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Trash - Expenses";
include __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

$stmt = $pdo->prepare("
    SELECT e.*, v.name AS vendor_name
    FROM expenses e
    LEFT JOIN vendors v ON e.vendor_id = v.id
    WHERE e.deleted_at IS NOT NULL
    ORDER BY e.deleted_at DESC
");
$stmt->execute();
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="entity-header">
    <h2>Trashed Expenses</h2>
    <a class="btn btn-primary" href="list.php">← Back to Expenses</a>
</div>
<div id="expenses-trash-wrap">
    <table class="entity-table" id="expenses-trash-table">
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
        <?php foreach ($expenses as $e): ?>
            <tr>
                <td><?= h(date('Y-m-d', strtotime($e['date']))) ?></td>
                <td><?= h($e['vendor_name']) ?: 'None (Admin Expense)' ?></td>
                <td><?= ucfirst($e['type']) ?></td>
                <td><?= number_format($e['amount'],2) ?></td>
                <td><?= h($e['description']) ?></td>
                <td><?= h($e['deleted_at']) ?></td>
                <td>
                    <form method="post" action="actions.php" style="display:inline;">
                        <input type="hidden" name="action" value="restore">
                        <input type="hidden" name="id" value="<?= $e['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <button class="btn btn-primary" type="submit" onclick="return confirm('Restore this expense?')">Restore</button>
                    </form>
                    <form method="post" action="actions.php" style="display:inline;">
                        <input type="hidden" name="action" value="delete_permanent">
                        <input type="hidden" name="id" value="<?= $e['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <button class="btn btn-danger" type="submit" onclick="return confirm('Permanently delete this expense? This cannot be undone.')">Delete Permanently</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>