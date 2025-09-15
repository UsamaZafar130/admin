<?php
// Reusable Expense Modal Component
// This can be included in any page that needs the Add Expense modal functionality
?>

<!-- Expense Modal (add/edit expense) -->
<div class="modal fade" id="globalExpenseModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="globalExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="globalExpenseModalLabel">New Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="global-expense-modal-body">
                <div class="text-center p-4">Loading form...</div>
            </div>
        </div>
    </div>
</div>

<script>
// Global function to show the expense modal (reusable across pages)
// Only define if not already defined (to avoid conflicts)
if (typeof window.showExpenseModal === 'undefined') {
    window.showExpenseModal = function(expenseId = null) {
        $('#globalExpenseModalLabel').text(expenseId ? 'Edit Expense' : 'New Expense');
        $('#global-expense-modal-body').html('<div class="text-center p-4">Loading form...</div>');
        let url = '/entities/expenses/form.php';
        if(expenseId) url += '?id=' + encodeURIComponent(expenseId);

        const modal = document.getElementById('globalExpenseModal');
        if (typeof window.UnifiedModals !== 'undefined') {
            window.UnifiedModals.show(modal);
        } else {
            // Fallback to Bootstrap modal if UnifiedModals not available
            const bootstrapModal = new bootstrap.Modal(modal);
            bootstrapModal.show();
        }

        $.get(url, function(html){
            $('#global-expense-modal-body').html(html);
            // Setup form handlers if needed
            if(typeof window.initExpenseFormJS === 'function') {
                window.initExpenseFormJS(expenseId ? { expense_id: parseInt(expenseId) } : null);
            }
        });
    };
}
</script>