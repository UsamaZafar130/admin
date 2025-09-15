<?php
require_once __DIR__ . '/../../includes/auth_check.php';
$pageTitle = "Users | " . SITE_NAME;
include __DIR__ . '/../../includes/header.php';
?>
<div class="entity-list">
    <h1>Users</h1>
    <div class="users-list-actions">
        <a href="form.php" class="btn btn-primary">Add New User</a>
        <a href="report.php" class="btn btn-secondary"><span class="icon">&#128200;</span> Report</a>
    </div>
    <!-- TODO: Table/List of users with roles, search/filter, etc. -->
    <div id="users-list"></div>
</div>
<script src="js/users.js"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>