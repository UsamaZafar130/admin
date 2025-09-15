/**
 * Unified Modal Handler for FrozoFun Admin
 */
window.UnifiedModals = {
    config: {
        backdrop: 'static',
        keyboard: true,
        focus: true
    },

    show: function(modalId, options = {}) {
        const modal = typeof modalId === 'string' ? document.getElementById(modalId) : modalId;
        if (!modal || !modal.isConnected) {
            console.error('Modal not found or not connected:', modalId);
            return null;
        }

        modal.setAttribute('data-bs-backdrop', 'static');
        modal.setAttribute('data-bs-keyboard', 'true');

        const config = Object.assign({}, this.config, options);
        if (config.rootElement == null) delete config.rootElement;

        const bsModal = new bootstrap.Modal(modal, config);
        bsModal.show();

        this._setupModalEvents(modal, bsModal);
        return bsModal;
    },

    hide: function(modalId) {
        const modal = typeof modalId === 'string' ? document.getElementById(modalId) : modalId;
        if (!modal) return;
        const bsModal = bootstrap.Modal.getInstance(modal);
        if (bsModal) bsModal.hide();
    },

    loadAndShow: function(modalId, url, options = {}) {
        const modal = document.getElementById(modalId);
        if (!modal) return console.error('Modal not found:', modalId);

        const modalContent = modal.querySelector('.modal-content');
        if (!modalContent) return console.error('Modal content not found in:', modalId);

        modalContent.innerHTML = `
            <div class="modal-header">
                <h5 class="modal-title">Loading...</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;

        fetch(url)
            .then(r => r.text())
            .then(html => {
                modalContent.innerHTML = html;
                const bsModal = this.show(modal, options);
                this._setupFormHandlers(modal);
                if (options.onLoaded) options.onLoaded(modal, bsModal);
            })
            .catch(err => {
                console.error('Error loading modal content:', err);
                modalContent.innerHTML = `
                    <div class="modal-header">
                        <h5 class="modal-title">Error</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            Failed to load content. Please try again.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                `;
                this.show(modal, options);
            });
    },

    _setupModalEvents: function(modal, bsModal) {
        modal.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') bsModal.hide();
        });

        modal.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
            btn.addEventListener('click', function() {
                bsModal.hide();
            });
        });

        modal.addEventListener('hidden.bs.modal', function() {
            const form = modal.querySelector('form');
            if (form) {
                form.reset();
                modal.querySelectorAll('.text-danger, .alert-danger').forEach(el => {
                    el.textContent = '';
                    el.classList.add('d-none-important');
                });
            }
        });
    },

    _setupFormHandlers: function(modal) {
        const forms = modal.querySelectorAll('form.entity-form');
        forms.forEach(form => {
            if (form.onsubmit || form.hasAttribute('data-custom-handler') || form.hasAttribute('data-entity-handler-set')) {
                return;
            }

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const action = form.getAttribute('action') || window.location.pathname.replace(/\/[^\/]*$/, '/actions.php');
                const formData = new FormData(form);
                if (!formData.has('action')) {
                    const editing = form.getAttribute('data-editing') === '1';
                    formData.append('action', editing ? 'edit' : 'add');
                }

                fetch(action, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        // NEW BEHAVIOR:
                        // 1) Close the entity modal immediately
                        UnifiedModals.hide(modal);

                        // 2) Show Success notification modal (no inline alert)
                        if (window.NotificationModal && typeof window.NotificationModal.success === 'function') {
                            window.NotificationModal.success(
                                data.message || 'Operation completed successfully!',
                                'Success',
                                function onOk() {
                                    if (typeof window.reloadLiveData === 'function') {
                                        try { window.reloadLiveData(); } catch (e) { console.error(e); }
                                    } else {
                                        document.dispatchEvent(new CustomEvent('app:reload-data'));
                                    }
                                }
                            );
                        }
                        return;
                    }

                    // Error: keep modal open and show error alert inline
                    let errorElement = modal.querySelector('.modal-error-message');
                    if (!errorElement) {
                        errorElement = document.createElement('div');
                        errorElement.className = 'alert alert-danger modal-error-message';
                        form.insertBefore(errorElement, form.firstChild);
                    }
                    errorElement.textContent = data.error || data.message || 'An error occurred';
                    errorElement.classList.remove('d-none-important');
                })
                .catch(err => {
                    console.error('Form submission error:', err);
                    alert('An error occurred while submitting the form');
                });
            });
        });
    },

    initializeAll: function() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.setAttribute('data-bs-backdrop', 'static');
            modal.setAttribute('data-bs-keyboard', 'true');

            modal.addEventListener('show.bs.modal', function() {
                setTimeout(() => {
                    const firstInput = modal.querySelector('input:not([type="hidden"]), textarea, select');
                    if (firstInput) firstInput.focus();
                }, 150);
            });
        });
    }
};

document.addEventListener('DOMContentLoaded', function() {
    UnifiedModals.initializeAll();
});

window.showModal = UnifiedModals.show;
window.hideModal = UnifiedModals.hide;
window.loadModal = UnifiedModals.loadAndShow;