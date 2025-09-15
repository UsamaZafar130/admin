<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = "Vendors";
include __DIR__ . '/../../includes/header.php';

$pdo = $pdo ?? require __DIR__ . '/../../includes/db_connection.php';

// Fetch all vendors
$stmt = $pdo->prepare("SELECT * FROM vendors ORDER BY name");
$stmt->execute();
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<link rel="stylesheet" href="/assets/css/vendors.css">
<div class="page-content-wrapper">
    <div class="container mt-3">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="text-primary"><i class="fa fa-truck me-2"></i> Vendors</h2>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-primary btn-3d" data-bs-toggle="modal" data-bs-target="#vendor-modal" onclick="VendorUI.openAddModal()">
                    <i class="fa fa-plus me-1"></i> Add Vendor
                </button>
            </div>
        </div>
<div class="alert alert-info max-width-700 mb-4">
    <strong>Manage your vendors and payment records.</strong> Use <strong>Add Vendor</strong> to register new suppliers.<br>
    Track outstanding balances and record payments. All columns are searchable and sortable.
</div>

<div id="vendors-list-wrap">
    <table class="entity-table table table-striped table-hover table-consistent" id="vendors-table">
        <thead class="table-light">
            <tr>
                <th>Name</th>
                <th>Contact</th>
                <th>Address</th>
                <th>Location</th>
                <th>Balance</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($vendors as $v): 
            $balance = get_vendor_balance_details($v['id'], $pdo); 
            $is_disabled = !empty($v['deleted_at']);
            ?>
            <tr data-vendor-id="<?= $v['id'] ?>"<?= $is_disabled ? ' class="vendor-disabled-row"' : '' ?>>
                <td>
                    <a href="javascript:void(0)" class="vendor-name-link" data-vendor-id="<?= $v['id'] ?>">
                        <?= h($v['name']) ?>
                    </a>
                    <?php if ($is_disabled): ?>
                        <span class="badge badge-disabled">Disabled</span>
                    <?php endif; ?>
                </td>
                <td><?= h($v['contact']) ?></td>
                <td><?= h($v['address']) ?><?= $v['area'] ? ', ' . h($v['area']) : '' ?><?= $v['city'] ? ', ' . h($v['city']) : '' ?></td>
                <td><?= h($v['location']) ?></td>
                <td>
                    <?php
                        // Show negative surplus as Outstanding
                        if ($balance['surplus'] < 0) {
                            echo '<span class="badge badge-consistent badge-danger">'.format_currency(abs($balance['surplus'])).' Outstanding</span>';
                        } elseif ($balance['outstanding'] > 0) {
                            echo '<span class="badge badge-consistent badge-danger">'.format_currency($balance['outstanding']).' Outstanding</span>';
                        } elseif ($balance['surplus'] > 0) {
                            echo '<span class="badge badge-consistent badge-success">'.format_currency($balance['surplus']).' Surplus</span>';
                        } else {
                            echo '<span class="badge badge-consistent badge-secondary">Rs. 0.00 Outstanding</span>';
                        }
                    ?>
                </td>
                <td><?= date('Y-m-d', strtotime($v['created_at'])) ?></td>
                <td>
                    <div class="btn-group btn-group-sm action-icons" role="group">
                    <?php if (!$is_disabled): ?>
                        <button class="btn btn-outline-primary btn-3d" title="Details" data-vendor-id="<?= $v['id'] ?>" onclick="VendorUI.openDetails(<?= $v['id'] ?>)">
                            <i class="fa fa-eye"></i>
                        </button>
                        <button class="btn btn-outline-warning btn-3d" title="Edit" onclick="VendorUI.openEditModal(<?= $v['id'] ?>)">
                            <i class="fa fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-success btn-3d" title="Record Payment" onclick="VendorUI.openPaymentModal(<?= $v['id'] ?>, <?= $balance['outstanding'] ?>)">
                            <i class="fa fa-money-bill-wave"></i>
                        </button>
                        <button class="btn btn-outline-danger btn-3d" title="Disable" onclick="VendorUI.disableVendor(<?= $v['id'] ?>, <?= $balance['outstanding'] ?>, <?= $balance['surplus'] ?>)">
                            <i class="fa fa-ban"></i>
                        </button>
                    <?php else: ?>
                        <button class="btn btn-outline-info btn-3d" title="Enable" onclick="VendorUI.enableVendor(<?= $v['id'] ?>)">
                            <i class="fa fa-undo"></i>
                        </button>
                    <?php endif; ?>
                    </div>
                            <i class="fa fa-bar-chart"></i>
                        </button>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
</div>
<!-- Bootstrap Modals -->
<div class="modal fade" id="vendor-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="vendorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<div class="modal fade" id="vendor-details-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="vendorDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<div class="modal fade" id="vendor-payment-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="vendorPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<script src="js/vendors.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.vendor-name-link').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                VendorUI.openDetails(this.getAttribute('data-vendor-id'));
            });
        });
        document.querySelectorAll('button[title="Details"]').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                VendorUI.openDetails(this.getAttribute('data-vendor-id'));
            });
        });
        // DataTables initialization is handled in main.js only.
    });

    // Custom JS for disable/enable with outstanding/surplus check
    window.VendorUI = window.VendorUI || {};
    VendorUI.disableVendor = function(id, outstanding, surplus) {
        if (parseFloat(outstanding) > 0 || parseFloat(surplus) > 0) {
            showWarning("Cannot disable vendor: Outstanding or Surplus must be zero before disabling.");
            return;
        }
        showConfirm("Are you sure you want to disable this vendor? They will no longer be selectable in purchases/expenses.", "Confirm Disable", function() {
            $.post('actions.php', {action: 'disable', id: id}, function (resp) {
                if (resp && resp.success) {
                    showSuccess("Vendor disabled successfully");
                    location.reload();
                } else {
                    showError((resp && resp.error) || "Failed to disable vendor.");
                }
            }, 'json').fail(function() {
                showError("Network error occurred while disabling vendor");
            });
        });
    };
    VendorUI.enableVendor = function(id) {
        showConfirm("Enable this vendor? They will be selectable again.", "Confirm Enable", function() {
            $.post('actions.php', {action: 'enable', id: id}, function (resp) {
                if (resp && resp.success) {
                    showSuccess("Vendor enabled successfully");
                    location.reload();
                } else {
                    showError((resp && resp.error) || "Failed to enable vendor.");
                }
            }, 'json').fail(function() {
                showError("Network error occurred while enabling vendor");
            });
        });
    };

    // Initialize DataTable with Bootstrap 5 styling
    $(document).ready(function() {
        if ($('#vendors-table').length && window.UnifiedTables) {
            UnifiedTables.init('#vendors-table', 'vendors');
        }
    });
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>