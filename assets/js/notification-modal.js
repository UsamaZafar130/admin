/**
 * Universal Notification Modal System for FrozoFun Admin
 * 
 * Provides site-wide modal notifications to replace JavaScript alerts
 * Uses Bootstrap modals with consistent styling and behavior
 */

window.NotificationModal = {
    
    /**
     * Initialize the notification modal (should be called once per page)
     */
    init: function() {
        // Check if modal already exists
        if (document.getElementById('notificationModal')) {
            return;
        }

        // Create the modal HTML
        const modalHTML = `
            <div class="modal fade" id="notificationModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="notificationModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header" id="notificationModalHeader">
                            <h5 class="modal-title" id="notificationModalLabel">
                                <i id="notificationModalIcon" class="fa fa-info-circle me-2"></i>
                                <span id="notificationModalTitle">Notification</span>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="notificationModalBody">
                            <p id="notificationModalMessage">Message will appear here</p>
                        </div>
                        <div class="modal-footer" id="notificationModalFooter">
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal" id="notificationModalOkBtn">OK</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    },

    /**
     * Show a success notification
     * @param {string} message - The message to display
     * @param {string} title - Optional title (default: "Success")
     * @param {Function} callback - Optional callback when modal is closed
     */
    success: function(message, title = 'Success', callback = null) {
        this._showModal(message, title, 'success', callback);
    },

    /**
     * Show an error notification
     * @param {string} message - The message to display
     * @param {string} title - Optional title (default: "Error")
     * @param {Function} callback - Optional callback when modal is closed
     */
    error: function(message, title = 'Error', callback = null) {
        this._showModal(message, title, 'error', callback);
    },

    /**
     * Show a warning notification
     * @param {string} message - The message to display
     * @param {string} title - Optional title (default: "Warning")
     * @param {Function} callback - Optional callback when modal is closed
     */
    warning: function(message, title = 'Warning', callback = null) {
        this._showModal(message, title, 'warning', callback);
    },

    /**
     * Show an info notification
     * @param {string} message - The message to display
     * @param {string} title - Optional title (default: "Information")
     * @param {Function} callback - Optional callback when modal is closed
     */
    info: function(message, title = 'Information', callback = null) {
        this._showModal(message, title, 'info', callback);
    },

    /**
     * Show a confirmation dialog with Yes/No buttons
     * @param {string} message - The message to display
     * @param {string} title - Optional title (default: "Confirm")
     * @param {Function} onConfirm - Callback when user clicks Yes
     * @param {Function} onCancel - Optional callback when user clicks No
     */
    confirm: function(message, title = 'Confirm', onConfirm = null, onCancel = null) {
        this.init();
        
        const modal = document.getElementById('notificationModal');
        const modalHeader = document.getElementById('notificationModalHeader');
        const modalTitle = document.getElementById('notificationModalTitle');
        const modalIcon = document.getElementById('notificationModalIcon');
        const modalMessage = document.getElementById('notificationModalMessage');
        const modalFooter = document.getElementById('notificationModalFooter');

        // Set header style and icon for confirmation
        modalHeader.className = 'modal-header bg-warning text-dark';
        modalIcon.className = 'fa fa-question-circle me-2';
        modalTitle.textContent = title;
        modalMessage.textContent = message;

        // Create Yes/No buttons
        modalFooter.innerHTML = `
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="notificationModalNoBtn">No</button>
            <button type="button" class="btn btn-warning" id="notificationModalYesBtn">Yes</button>
        `;

        // Set up event handlers
        const yesBtn = document.getElementById('notificationModalYesBtn');
        const noBtn = document.getElementById('notificationModalNoBtn');

        yesBtn.onclick = function() {
            if (onConfirm) onConfirm();
            bootstrap.Modal.getInstance(modal).hide();
        };

        noBtn.onclick = function() {
            if (onCancel) onCancel();
            bootstrap.Modal.getInstance(modal).hide();
        };

        // Show modal
        const bsModal = new bootstrap.Modal(modal, {
            backdrop: 'static',
            keyboard: true
        });
        bsModal.show();
    },

    /**
     * Internal method to show modal with specific type
     * @private
     */
    _showModal: function(message, title, type, callback) {
        this.init();
        
        const modal = document.getElementById('notificationModal');
        const modalHeader = document.getElementById('notificationModalHeader');
        const modalTitle = document.getElementById('notificationModalTitle');
        const modalIcon = document.getElementById('notificationModalIcon');
        const modalMessage = document.getElementById('notificationModalMessage');
        const modalFooter = document.getElementById('notificationModalFooter');
        const okBtn = document.getElementById('notificationModalOkBtn');

        // Configure based on type
        let headerClass, iconClass, btnClass;
        
        switch (type) {
            case 'success':
                headerClass = 'modal-header bg-success text-white';
                iconClass = 'fa fa-check-circle me-2';
                btnClass = 'btn btn-success';
                break;
            case 'error':
                headerClass = 'modal-header bg-danger text-white';
                iconClass = 'fa fa-exclamation-circle me-2';
                btnClass = 'btn btn-danger';
                break;
            case 'warning':
                headerClass = 'modal-header bg-warning text-dark';
                iconClass = 'fa fa-exclamation-triangle me-2';
                btnClass = 'btn btn-warning';
                break;
            case 'info':
            default:
                headerClass = 'modal-header bg-info text-white';
                iconClass = 'fa fa-info-circle me-2';
                btnClass = 'btn btn-info';
                break;
        }

        // Apply styling
        modalHeader.className = headerClass;
        modalIcon.className = iconClass;
        modalTitle.textContent = title;
        modalMessage.textContent = message;

        // Reset footer to single OK button
        modalFooter.innerHTML = `
            <button type="button" class="${btnClass}" data-bs-dismiss="modal" id="notificationModalOkBtn">OK</button>
        `;

        // Set up callback if provided
        if (callback) {
            const newOkBtn = document.getElementById('notificationModalOkBtn');
            newOkBtn.onclick = function() {
                callback();
                bootstrap.Modal.getInstance(modal).hide();
            };
        }

        // Show modal
        const bsModal = new bootstrap.Modal(modal, {
            backdrop: 'static',
            keyboard: true
        });
        bsModal.show();
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    NotificationModal.init();
});

// Make it globally available with shorter aliases
window.showSuccess = NotificationModal.success.bind(NotificationModal);
window.showError = NotificationModal.error.bind(NotificationModal);
window.showWarning = NotificationModal.warning.bind(NotificationModal);
window.showInfo = NotificationModal.info.bind(NotificationModal);
window.showConfirm = NotificationModal.confirm.bind(NotificationModal);