<?php
// Reusable Order Modal Component
// This can be included in any page that needs the Add New Order modal functionality
?>

<!-- Order Modal (add/edit order) -->
<div class="modal fade" id="orderModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="orderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="orderModalLabel">New Sales Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="order-modal-body">
                <div class="text-center p-4">Loading form...</div>
            </div>
        </div>
    </div>
</div>

<script>
// Global function to show the order modal (reusable across pages)
// Only define if not already defined (to avoid conflicts with orders_list.js)
if (typeof window.showOrderModal === 'undefined') {
    window.showOrderModal = function(orderId = null) {
        $('#orderModalLabel').text(orderId ? 'Edit Sales Order' : 'New Sales Order');
        $('#order-modal-body').html('<div class="text-center p-4">Loading form...</div>');
        let url = '/entities/sales/new_order.php?inline=1';
        if(orderId) url += '&id=' + encodeURIComponent(orderId);

        const modal = document.getElementById('orderModal');
        if (typeof window.UnifiedModals !== 'undefined') {
            window.UnifiedModals.show(modal);
        } else {
            // Fallback to Bootstrap modal if UnifiedModals not available
            const bootstrapModal = new bootstrap.Modal(modal);
            bootstrapModal.show();
        }

        $.get(url, function(html){
            $('#order-modal-body').html(html);
            if(typeof window.initOrderFormJS === 'function') {
                window.initOrderFormJS(orderId ? { order_id: parseInt(orderId) } : null);
            }
        });
    };
}
</script>