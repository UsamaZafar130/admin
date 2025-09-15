<?php
// Reusable Purchase Modal Component
// This can be included in any page that needs the Add Purchase modal functionality
?>

<!-- Purchase Modal (add/edit purchase) -->
<div class="modal fade" id="globalPurchaseModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="globalPurchaseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="globalPurchaseModalLabel">New Purchase</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="global-purchase-modal-body">
                <div class="text-center p-4">Loading form...</div>
            </div>
        </div>
    </div>
</div>

<script>
// Global function to show the purchase modal (reusable across pages)
// Only define if not already defined (to avoid conflicts)
if (typeof window.showPurchaseModal === 'undefined') {
    window.showPurchaseModal = function(purchaseId = null) {
        $('#globalPurchaseModalLabel').text(purchaseId ? 'Edit Purchase' : 'New Purchase');
        $('#global-purchase-modal-body').html('<div class="text-center p-4">Loading form...</div>');
        let url = '/entities/purchases/form.php';
        if(purchaseId) url += '?id=' + encodeURIComponent(purchaseId);

        const modal = document.getElementById('globalPurchaseModal');
        if (typeof window.UnifiedModals !== 'undefined') {
            window.UnifiedModals.show(modal);
        } else {
            // Fallback to Bootstrap modal if UnifiedModals not available
            const bootstrapModal = new bootstrap.Modal(modal);
            bootstrapModal.show();
        }

        $.get(url, function(html){
            $('#global-purchase-modal-body').html(html);
            // Setup form handlers if needed
            if(typeof window.initPurchaseFormJS === 'function') {
                window.initPurchaseFormJS(purchaseId ? { purchase_id: parseInt(purchaseId) } : null);
            }
        });
    };
}
</script>