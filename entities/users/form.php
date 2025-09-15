<?php
require_once __DIR__ . '/../../includes/auth_check.php';
$pageTitle = "User Form | " . SITE_NAME;
include __DIR__ . '/../../includes/header.php';

// For edit, get ?id=...
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
// TODO: Fetch user if $id > 0

?>
<div class="entity-form">
    <h1><?= $id ? "Edit User" : "Add User" ?></h1>
    <form id="userForm" method="post" action="actions.php">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div>
            <label>Username:</label>
            <input type="text" name="username" required>
        </div>
        <div>
            <label>Email:</label>
            <input type="email" name="email" required>
        </div>
        <div>
            <label>Role:</label>
            <select name="role" required>
                <option value="admin">Admin</option>
                <option value="manager">Manager</option>
                <option value="staff">Staff</option>
                <!-- TODO: Add more roles if needed -->
            </select>
        </div>
        <?php if (!$id): ?>
        <div>
            <label>Password:</label>
            <input type="password" name="password" required>
        </div>
        <?php endif; ?>
        <button type="submit" name="submit" value="<?= $id ? 'update' : 'create' ?>">
            <?= $id ? 'Update' : 'Create' ?>
        </button>
    </form>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>