<?php
// Reusable Customer Modal Component
// This can be included in any page that needs the Add Customer modal functionality
?>

<!-- Customer Modal (add/edit customer) -->
<div class="modal fade" id="globalCustomerModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="globalCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="globalCustomerModalLabel">New Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="global-customer-modal-body">
                <div class="text-center p-4">Loading form...</div>
            </div>
        </div>
    </div>
</div>

<script>
// Global function to show the customer modal (reusable across pages)
// Only define if not already defined (to avoid conflicts)
if (typeof window.showCustomerModal === 'undefined') {
    window.showCustomerModal = function(customerId = null) {
        $('#globalCustomerModalLabel').text(customerId ? 'Edit Customer' : 'New Customer');
        $('#global-customer-modal-body').html('<div class="text-center p-4">Loading form...</div>');
        let url = '/entities/customers/form.php';
        if(customerId) url += '?id=' + encodeURIComponent(customerId);

        const modal = document.getElementById('globalCustomerModal');
        if (typeof window.UnifiedModals !== 'undefined') {
            window.UnifiedModals.show(modal);
        } else {
            // Fallback to Bootstrap modal if UnifiedModals not available
            const bootstrapModal = new bootstrap.Modal(modal);
            bootstrapModal.show();
        }

        $.get(url, function(html){
            $('#global-customer-modal-body').html(html);
            
            // Ensure AJAX form handling is set up after content is loaded
            const form = modal.querySelector('form.entity-form');
            if (form && typeof window.UnifiedModals !== 'undefined') {
                // Force setup of form handlers after content is loaded
                window.UnifiedModals._setupFormHandlers(modal);
            }
            
            // Setup form handlers if needed
            if(typeof window.initCustomerFormJS === 'function') {
                window.initCustomerFormJS(customerId ? { customer_id: parseInt(customerId) } : null);
            }
        });
    };
}
</script>