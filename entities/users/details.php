<?php
require_once __DIR__ . '/../../includes/auth_check.php';
$pageTitle = "User Details | " . SITE_NAME;
include __DIR__ . '/../../includes/header.php';

// Get user ID and fetch details
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
// TODO: Fetch user by $id

?>
<div class="entity-details">
    <h1>User Details</h1>
    <!-- TODO: Show user details here -->
    <p>User ID: <?= $id ?></p>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>