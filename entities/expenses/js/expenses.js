// FrozoFun Admin - Expenses Module JS
// Handles modal dialogs, AJAX forms for expenses, vendor lookup/add, payments, and dynamic UI updates

window.ExpenseUI = {
    // Open Add Expense modal
    openAddModal: function() {
        UnifiedModals.loadAndShow('expense-modal', 'form.php', {
            onLoaded: (modal, bsModal) => {
                ExpenseUI._setupExpenseFormHandlers(modal);
            }
        });
    },

    // Open Edit Expense modal
    openEditModal: function(id) {
        UnifiedModals.loadAndShow('expense-modal', `form.php?id=${id}`, {
            onLoaded: (modal, bsModal) => {
                ExpenseUI._setupExpenseFormHandlers(modal);
            }
        });
    },

    // Open Expense Details modal
    openDetails: function(id) {
        UnifiedModals.loadAndShow('expense-details-modal', `details.php?id=${id}`);
    },

    // Open Payment modal for an expense (amountDue for limit)
    openPaymentModal: function(id, amountDue) {
        // Fetch a fresh CSRF token for the payment modal
        fetch('/entities/expenses/actions.php?action=get_csrf', {credentials: 'same-origin'})
          .then(r => r.json())
          .then(data => {
            let csrf = data.csrf_token || '';
            let html = `
            <div class="modal-header">
                <h5 class="modal-title">Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="expense-payment-form" class="entity-form" method="post" action="actions.php" autocomplete="off">
                    <input type="hidden" name="action" value="pay">
                    <input type="hidden" name="expense_id" value="${id}">
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
                        </select>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-primary">Pay</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                    <input type="hidden" name="csrf_token" value="${csrf}">
                </form>
            </div>
            `;
            
            const modal = document.getElementById('expense-payment-modal');
            const modalContent = modal.querySelector('.modal-content');
            if (modalContent) {
                modalContent.innerHTML = html;
            }
            
            const bsModal = UnifiedModals.show(modal);

            setTimeout(() => {
              let input = document.getElementById('pay_amount');
              if (input) {
                input.addEventListener('input', function() {
                  if (parseFloat(this.value) > parseFloat(amountDue)) {
                    this.value = amountDue;
                  }
                });
                input.focus();
                input.select();
              }
              
              // Set up form submission handler
              const form = document.getElementById('expense-payment-form');
              if (form && !form.onsubmit) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(form);
                    fetch('actions.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            UnifiedModals.hide(modal);
                            location.reload();
                        } else {
                            alert(data.error || 'Payment failed');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Network error occurred');
                    });
                });
              }
            }, 50);
          });
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
        UnifiedModals.show(modal);
    }
}

// AJAX delete logic
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form.ajax-delete-expense').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to delete this expense?')) return;
            var formData = new FormData(form);

            // --- FIX: Use form.getAttribute('action') instead of form.action or form['action'] ---
            // This ensures the correct URL is used, and avoids [object HTMLInputElement] bugs
            var actionUrl = form.getAttribute('action') || 'actions.php';

            var xhr = new XMLHttpRequest();
            xhr.open('POST', actionUrl, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onload = function() {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.success) {
                        location.reload();
                    } else {
                        showDeleteErrorModal(resp.message || "This expense can't be deleted because payment(s) exist—add an adjustment entry to fix errors.");
                    }
                } catch (e) {
                    showDeleteErrorModal("This expense can't be deleted because payment(s) exist—add an adjustment entry to fix errors.");
                }
            };
            xhr.onerror = function() {
                showDeleteErrorModal("This expense can't be deleted because payment(s) exist—add an adjustment entry to fix errors.");
            };
            xhr.send(formData);
        });
    });
});

// Set up form handlers (entity-specific functionality)  
window.ExpenseUI._setupExpenseFormHandlers = function(modal) {
    // No custom form submission handler - let UnifiedModals handle it
    // The form has method="post" action="actions.php" data-refresh-on-success="true"
};