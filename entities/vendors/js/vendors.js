// FrozoFun Admin - Vendors JS (with vendor payment FIFO for purchases and vendor advances)
// Requires: jQuery

window.VendorUI = {
    openAddModal: function () {
        window.UnifiedModals.loadAndShow('vendor-modal', 'form.php', {
            onLoaded: (modal, bsModal) => {
                VendorUI.enhanceForm(modal);
            }
        });
    },
    openEditModal: function (id, isDetails) {
        window.UnifiedModals.loadAndShow('vendor-modal', `form.php?id=${id}`, {
            onLoaded: (modal, bsModal) => {
                VendorUI.enhanceForm(modal);
            }
        });
    },
    openDetails: function (id) {
        window.UnifiedModals.loadAndShow('vendor-details-modal', `details.php?id=${id}`);
    },
    enhanceForm: function (form) {
        // Add contact normalization on blur (like CustomerUI)
        var contactInput = form.querySelector('input[name="contact"]');
        if (contactInput) {
            contactInput.addEventListener('blur', function () {
                this.value = VendorUI.normalizeContact(this.value);
            });
        }

        form.onsubmit = function (e) {
            e.preventDefault();
            var fd = new FormData(form);
            fd.append('action', form.dataset.editing == "1" ? 'edit' : 'add');
            $.ajax({
                url: 'actions.php',
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (resp) {
                    if (resp.success) {
                        const modal = form.closest('.modal');
                        if (modal) {
                            window.UnifiedModals.hide(modal);
                        }
                        location.reload();
                    } else {
                        showError(resp.error || "Failed to save vendor.");
                    }
                },
                error: function () {
                    showError("Network or server error. Please try again.");
                }
            });
        };
    },
    normalizeContact: function(raw) {
        let c = (raw || '').trim().replace(/[^\d\+]/g, '');
        if (/^0(\d{10})$/.test(c)) {
            c = '+92' + c.match(/^0(\d{10})$/)[1];
        }
        if (/^0092(\d{10})$/.test(c)) {
            c = '+92' + c.match(/^0092(\d{10})$/)[1];
        }
        let m = c.match(/^(\+?\d{2,3})(\d+)$/);
        if (m) {
            c = (m[1].startsWith('+') ? m[1] : ('+' + m[1])) + m[2];
        }
        return c.replace(/ /g, '');
    },
    deleteVendor: function (id) {
        showConfirm("Move this vendor to trash?", "Confirm Delete", function() {
            $.post('actions.php', {action: 'delete', id}, function (resp) {
                if (resp && resp.success) {
                    showSuccess("Vendor moved to trash successfully");
                    location.reload();
                } else {
                    showError((resp && resp.error) || "Failed to delete vendor.");
                }
            }, 'json').fail(function() {
                showError("Network error occurred while deleting vendor");
            });
        });
    },
    restoreVendor: function (id) {
        showConfirm("Restore this vendor?", "Confirm Restore", function() {
            $.post('actions.php', {action: 'restore', id}, function (resp) {
                if (resp && resp.success) {
                    showSuccess("Vendor restored successfully");
                    location.reload();
                } else {
                    showError((resp && resp.error) || "Failed to restore vendor.");
                }
            }, 'json').fail(function() {
                showError("Network error occurred while restoring vendor");
            });
        });
    },
    openPaymentModal: function(vendorId) {
        // Get outstanding and surplus info for this vendor
        $.get('actions.php', {action: 'get_vendor_payment_info', vendor_id: vendorId}, function(resp){
            let outstanding = resp.outstanding || 0;
            let surplus = resp.surplus || 0;
            let csrf = resp.csrf_token || '';
            let purchases = resp.purchases || [];
            let expenses = resp.expenses || [];

            // Single value logic for balance display
            let displayBalance = 0, displayType = '', displayClass = '';
            if (outstanding > 0) {
                displayBalance = outstanding;
                displayType = 'Outstanding';
                displayClass = 'badge-outstanding';
            } else if (surplus > 0) {
                displayBalance = surplus;
                displayType = 'Surplus';
                displayClass = 'badge-surplus';
            } else if (surplus < 0) {
                displayBalance = Math.abs(surplus);
                displayType = 'Outstanding';
                displayClass = 'badge-outstanding';
            } else {
                displayBalance = 0;
                displayType = 'Outstanding';
                displayClass = 'badge-settled';
            }

            // Build outstanding rows (FIFO: purchases, then expenses)
            let rows = '';
            purchases.forEach(p => {
                let unpaid = (parseFloat(p.amount) - parseFloat(p.paid));
                if (unpaid > 0) {
                    rows += `<tr>
                        <td>Purchase</td>
                        <td>${p.date}</td>
                        <td>${p.description || ''}</td>
                        <td>${parseFloat(p.amount).toFixed(2)}</td>
                        <td>${parseFloat(p.paid).toFixed(2)}</td>
                        <td>${unpaid.toFixed(2)}</td>
                    </tr>`;
                }
            });
            expenses.forEach(e => {
                let unpaid = (parseFloat(e.amount) - parseFloat(e.paid));
                if (unpaid > 0) {
                    rows += `<tr>
                        <td>Expense</td>
                        <td>${e.date}</td>
                        <td>${e.description || ''}</td>
                        <td>${parseFloat(e.amount).toFixed(2)}</td>
                        <td>${parseFloat(e.paid).toFixed(2)}</td>
                        <td>${unpaid.toFixed(2)}</td>
                    </tr>`;
                }
            });

            let html = `
            <div class="modal-header">
                <h5 class="modal-title">Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="vendor-payment-form" method="post" action="actions.php" autocomplete="off" data-refresh-on-success="true">
                    <input type="hidden" name="action" value="pay">
                    <input type="hidden" name="vendor_id" value="${vendorId}">
                    <div class="mb-3">
                        <strong>Balance:</strong>
                        <span class="vendor-balance badge ${displayClass}">${parseFloat(displayBalance).toFixed(2)} ${displayType}</span>
                    </div>
                    <div class="mb-3">
                        <label for="payment_amount" class="form-label"><strong>Payment Amount</strong> <span style="color:#c00">*</span></label>
                        <input type="number" name="payment_amount" id="payment_amount" step="0.01" required autofocus class="form-control" style="max-width:220px;">
                        <small class="form-text text-muted">Any amount allowed. Extra will be recorded as surplus/advance. <b>For negative value, description is required.</b></small>
                    </div>
                    <div class="mb-3">
                        <label for="payment_description" class="form-label"><strong>Description</strong> <span id="desc-required" style="color:#c00;display:none;">*</span></label>
                        <textarea name="payment_description" id="payment_description" rows="2" class="form-control" style="max-width:400px;"></textarea>
                        <small class="form-text text-muted">Required for negative adjustment.</small>
                    </div>
                    <div class="mb-3">
                        <strong>Outstanding Purchases & Expenses (FIFO):</strong>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Paid</th>
                                        <th>Unpaid</th>
                                    </tr>
                                </thead>
                                <tbody>${rows}</tbody>
                            </table>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-success">Record Payment</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                    <input type="hidden" name="csrf_token" value="${csrf}">
                </form>
                <div id="vendor-payment-msg" style="margin-top:14px;"></div>
            </div>
            `;
            
            const modal = document.getElementById('vendor-payment-modal');
            const modalContent = modal.querySelector('.modal-content');
            if (modalContent) {
                modalContent.innerHTML = html;
            }
            const bsModal = window.UnifiedModals.show(modal);

            setTimeout(function() {
                let input = document.getElementById('payment_amount');
                if (input) {
                    input.focus();
                    input.select();
                }
                // Show/hide description required asterisk
                $('#payment_amount').on('input', function() {
                    var val = parseFloat($(this).val());
                    if (val < 0) {
                        $('#desc-required').show();
                        $('#payment_description').attr('required', true);
                    } else {
                        $('#desc-required').hide();
                        $('#payment_description').attr('required', false);
                    }
                });
                // Only bind submit handler once per modal open
                $('#vendor-payment-form').off('submit').on('submit', function(e){
                    e.preventDefault();
                    let fd = new FormData(this);
                    $.ajax({
                        url: 'actions.php',
                        type: 'POST',
                        data: fd,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function (resp) {
                            if (resp.success) {
                                const modal = document.getElementById('vendor-payment-modal');
                                window.UnifiedModals.hide(modal);
                                location.reload();
                            } else {
                                let $msg = $('#vendor-payment-msg');
                                if ($msg.length) {
                                    $msg.html('<span style="color:red;">'+(resp.error||"Failed to record payment.")+'</span>');
                                } else {
                                    showError(resp.error || "Failed to record payment.");
                                }
                            }
                        },
                        error: function () {
                            let $msg = $('#vendor-payment-msg');
                            if ($msg.length) {
                                $msg.html('<span style="color:red;">Network/server error. Please try again.</span>');
                            } else {
                                showError("Network/server error. Please try again.");
                            }
                        }
                    });
                });
            }, 50);
        }, 'json');
    }
};

$(function () {
    // Modals should not close on backdrop click - only on close button or ESC
});