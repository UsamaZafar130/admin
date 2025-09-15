// FrozoFun Admin - Purchases Module JS
// Handles modal dialogs, AJAX forms for purchases, vendor lookup/add, payments, and dynamic UI updates

window.PurchaseUI = {
    // Open Add Purchase modal using UnifiedModals
    openAddModal: function() {
        window.UnifiedModals.loadAndShow('purchase-modal', 'form.php', {
            onLoaded: (modal, bsModal) => {
                PurchaseUI._setupPurchaseFormHandlers(modal);
            }
        });
    },

    // Open Edit Purchase modal using UnifiedModals
    openEditModal: function(id) {
        window.UnifiedModals.loadAndShow('purchase-modal', `form.php?id=${id}`, {
            onLoaded: (modal, bsModal) => {
                PurchaseUI._setupPurchaseFormHandlers(modal);
            }
        });
    },

    // Open Purchase Details modal using UnifiedModals
    openDetails: function(id) {
        window.UnifiedModals.loadAndShow('purchase-details-modal', `details.php?id=${id}`);
    },

    // Open Payment modal for a purchase (amountDue for limit)
    openPaymentModal: function(id, amountDue) {
        fetch('/entities/purchases/actions.php?action=get_csrf', {credentials: 'same-origin'})
          .then(r => r.json())
          .then(data => {
            let csrf = data.csrf_token || '';
            let html = `
            <div class="modal-header">
                <h5 class="modal-title">Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="purchase-payment-form" class="entity-form" method="post" action="actions.php" autocomplete="off">
                    <input type="hidden" name="action" value="pay">
                    <input type="hidden" name="purchase_id" value="${id}">
                    <div class="mb-3">
                        <label for="pay_amount" class="form-label">Amount to Pay <span style="color:#c00">*</span></label>
                        <input type="number" name="pay_amount" id="pay_amount" class="form-control" min="1" max="${amountDue}" step="0.01" required autofocus>
                        <small class="form-text text-muted">Max: ${amountDue}</small>
                    </div>
                    <div class="mb-3">
                        <label for="route" class="form-label">Payment Method</label>
                        <select name="route" id="route" class="form-select" required>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                            <option value="advance">Advance/Surplus</option>
                        </select>
                    </div>
                    <div id="payment-error" class="text-danger small mb-2 d-none"></div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-primary">Pay</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                    <input type="hidden" name="csrf_token" value="${csrf}">
                </form>
            </div>
            `;
            
            const modal = document.getElementById('purchase-payment-modal');
            const modalContent = modal.querySelector('.modal-content');
            if (modalContent) {
                modalContent.innerHTML = html;
            }
            
            window.UnifiedModals.show(modal);

            setTimeout(() => {
              const input = document.getElementById('pay_amount');
              if (input) {
                input.addEventListener('input', function() {
                  const max = parseFloat(amountDue);
                  if (parseFloat(this.value) > max) this.value = max;
                });
                input.focus();
                input.select();
              }
              
              const form = document.getElementById('purchase-payment-form');
              if (form && !form.dataset.bound) {
                form.dataset.bound = "1";
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const errorBox = document.getElementById('payment-error');
                    if (errorBox) {
                        errorBox.textContent = '';
                        errorBox.classList.add('d-none');
                    }
                    const formData = new FormData(form);

                    fetch(form.getAttribute('action'), {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                    })
                    .then(async response => {
                        let data;
                        try {
                            data = await response.json();
                        } catch {
                            throw new Error('Unexpected server response.');
                        }
                        if (!data.success) {
                            throw new Error(data.message || 'Payment failed.');
                        }
                        // Update the table row inline
                        PurchaseUI._applyPaymentResult(id, data);
                        // Close modal
                        window.UnifiedModals.hide(modal);
                    })
                    .catch(err => {
                        if (errorBox) {
                            errorBox.textContent = err.message || 'Network error occurred';
                            errorBox.classList.remove('d-none');
                        } else {
                            alert(err.message || 'Network error occurred');
                        }
                    });
                });
              }
            }, 50);
          });
    },

    // Update table row after a successful payment (no reload)
    _applyPaymentResult: function(purchaseId, data) {
        const row = document.querySelector(`tr[data-purchase-id="${purchaseId}"]`);
        if (!row) return;

        // Parse original purchase data to get total amount
        let purchaseRaw = row.getAttribute('data-purchase');
        let purchaseObj;
        try {
            purchaseObj = JSON.parse(purchaseRaw);
        } catch {
            purchaseObj = {};
        }
        const total = parseFloat(purchaseObj.amount || 0);
        const newPaid = parseFloat(data.new_paid_total || 0);
        const status = data.status || (newPaid >= total ? 'Paid' : (newPaid > 0 ? 'Partial' : 'Unpaid'));

        // Map status to classes (mirror PHP)
        const classMap = {
            'Paid': 'badge-surplus',
            'Partial': 'badge-settled',
            'Unpaid': 'badge-outstanding'
        };
        const statusCell = row.querySelector('td[data-label="Status"] .badge');
        if (statusCell) {
            statusCell.className = 'badge ' + (classMap[status] || 'badge-outstanding');
            if (status === 'Paid') {
                statusCell.textContent = 'Paid';
            } else if (status === 'Partial') {
                statusCell.textContent = `Partial (${newPaid.toFixed(2)} paid)`;
            } else {
                statusCell.textContent = 'Unpaid';
            }
        }

        // Remove Pay button if now fully paid
        if (status === 'Paid') {
            const actionsCell = row.querySelector('td[data-label="Actions"]');
            if (actionsCell) {
                const payBtn = actionsCell.querySelector('button[title="Pay"]');
                if (payBtn) payBtn.remove();
            }
        } else {
            // If still partial, update the pay button's onclick with new remaining due
            const remaining = Math.max(0, total - newPaid);
            const actionsCell = row.querySelector('td[data-label="Actions"]');
            if (actionsCell) {
                const payBtn = actionsCell.querySelector('button[title="Pay"]');
                if (payBtn) {
                    payBtn.setAttribute('onclick', `PurchaseUI.openPaymentModal(${purchaseId}, ${remaining})`);
                }
            }
        }
    },

    // Utility: get current CSRF token from DOM
    csrfToken: function() {
        let inp = document.querySelector('input[name="csrf_token"]');
        return inp ? inp.value : '';
    }
};


// Vendor Add Modal for inline add (used in purchases form)
window.VendorUI = {
    openAddModal: function() {
        let modal = document.getElementById('purchase-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'purchase-modal';
            modal.className = 'modal fade';
            modal.setAttribute('tabindex', '-1');
            modal.innerHTML = '<div class="modal-dialog"><div class="modal-content"></div></div>';
            document.body.appendChild(modal);
        }
        const modalContent = modal.querySelector('.modal-content');
        if (!modalContent) return;

        let html = `
        <div class="modal-header">
            <h5 class="modal-title">Add Vendor</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <form id="vendor-add-form" class="entity-form" method="post" action="/entities/vendors/actions.php" autocomplete="off">
                <input type="hidden" name="action" value="add">
                <div class="mb-3">
                    <label for="vendor_name" class="form-label">Name <span style="color:#c00">*</span></label>
                    <input type="text" name="name" id="vendor_name" class="form-control" required autofocus>
                </div>
                <div class="mb-3">
                    <label for="vendor_contact" class="form-label">Contact</label>
                    <input type="text" name="contact" id="vendor_contact" class="form-control">
                </div>
                <div class="mb-3">
                    <label for="vendor_address" class="form-label">Address</label>
                    <input type="text" name="address" id="vendor_address" class="form-control">
                </div>
                <div class="mb-3">
                    <label for="vendor_area" class="form-label">Area</label>
                    <input type="text" name="area" id="vendor_area" class="form-control">
                </div>
                <div class="mb-3">
                    <label for="vendor_city" class="form-label">City</label>
                    <input type="text" name="city" id="vendor_city" class="form-control">
                </div>
                <div class="mb-3">
                    <label for="vendor_location" class="form-label">Location (Google Maps Link)</label>
                    <input type="text" name="location" id="vendor_location" class="form-control">
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary">Add Vendor</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
                <input type="hidden" name="csrf_token" value="${PurchaseUI.csrfToken()}">
            </form>
        </div>
        `;
        
        modalContent.innerHTML = html;
        window.UnifiedModals.show(modal);

        setTimeout(() => {
          let form = document.getElementById('vendor-add-form');
          if (form) {
            form.addEventListener('submit', function(e) {
              e.preventDefault();
              let fd = new FormData(form);
              const url = form.getAttribute('action');
              fetch(url, { method: 'POST', body: fd, credentials:'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
              })
              .then(r=>r.json())
              .then(resp => {
                  if(resp.success || resp.status === 'success') {
                    let name = form.querySelector('input[name="name"]').value;
                    let id = resp.id || resp.vendor_id;
                    let select = document.getElementById('vendor_id');
                    if (select && id) {
                      let option = document.createElement('option');
                      option.value = id;
                      option.textContent = name;
                      option.selected = true;
                      select.appendChild(option);
                      if (window.$ && window.$.fn.select2) {
                        window.$(select).trigger('change');
                      }
                    }
                    window.UnifiedModals.hide(modal);
                  } else {
                    alert(resp.error || resp.message || "Could not add vendor.");
                  }
              })
              .catch(() => {
                  alert("Network / server error adding vendor.");
              });
              let inp = form.querySelector('input,select,textarea');
              if (inp) { inp.focus(); if (inp.select) inp.select(); }
            });
          }
        }, 80);
    }
};

// --- Updated delete error modal using Bootstrap 5 ---
function showDeleteErrorModal(msg) {
    const modal = document.getElementById('delete-error-modal');
    const messageElement = document.getElementById('delete-error-message');
    if (messageElement) {
        messageElement.textContent = msg;
    }
    if (modal) {
        window.UnifiedModals.show(modal);
    }
}

// AJAX delete logic
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form.ajax-delete-purchase').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to delete this purchase?')) return;
            var formData = new FormData(form);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', form.getAttribute('action') || 'actions.php', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onload = function() {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.success) {
                        location.reload();
                    } else {
                        showDeleteErrorModal(resp.message || 'Could not delete.');
                    }
                } catch (e) {
                    showDeleteErrorModal('This purchase can\'t be deleted because payment(s) exist—add an adjustment entry to fix errors.');
                }
            };
            xhr.onerror = function() {
                showDeleteErrorModal('Request failed. Please try again.');
            };
            xhr.send(formData);
        });
    });
});

// Set up form handlers (entity-specific functionality)
window.PurchaseUI._setupPurchaseFormHandlers = function(modal) {
    // No custom form submission handler - let UnifiedModals handle it
};