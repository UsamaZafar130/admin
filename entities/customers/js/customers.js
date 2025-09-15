const CUSTOMER_BASE = '/entities/customers/';
const CustomerUI = {
    // Modal functions now use UnifiedModals
    openAddModal() {
        const modalContent = `
            <div class="modal-header">
                <h5 class="modal-title">Add Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">Loading...</div>
        `;
        CustomerUI._loadModalContent('customer-modal', CUSTOMER_BASE + 'form.php', modalContent);
    },
    openEditModal(id, fromDetails = false) {
        const modalContent = `
            <div class="modal-header">
                <h5 class="modal-title">Edit Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">Loading...</div>
        `;
        CustomerUI._loadModalContent('customer-modal', CUSTOMER_BASE + 'form.php?id=' + id, modalContent);
    },
    openDetails(id) {
        const modalContent = `
            <div class="modal-header">
                <h5 class="modal-title">Customer Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">Loading...</div>
        `;
        CustomerUI._loadModalContent('customer-details-modal', CUSTOMER_BASE + 'details.php?id=' + id, modalContent);
    },
    openPaymentModal(id) {
        const html = `<div class="modal-header">
            <h5 class="modal-title">Record Payment</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <form id="payment-form" class="entity-form">
                <input type="hidden" name="id" value="${id}">
                <div class="form-row mb-3">
                    <label class="form-label">Amount</label>
                    <input class="form-control" name="amount" type="number" min="1" step="any" required>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary">Record Payment</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>`;
        CustomerUI._showModalContent('customer-payment-modal', html);
    },
    _loadModalContent(modalId, url, placeholderContent) {
        // Use UnifiedModals.loadAndShow for loading content
        window.UnifiedModals.loadAndShow(modalId, url, {
            onLoaded: (modal, bsModal) => {
                CustomerUI._setupCustomerFormHandlers(modal);
            }
        });
    },
    _showModalContent(modalId, html) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        
        const modalContent = modal.querySelector('.modal-content');
        if (modalContent) {
            modalContent.innerHTML = html;
        }
        
        // Show using UnifiedModals
        window.UnifiedModals.show(modal);
        
        // Set up form handlers for payment modal
        if (modalId === 'customer-payment-modal') {
            const form = modal.querySelector('form');
            if (form) {
                form.onsubmit = CustomerUI._submitPaymentForm;
            }
        }
    },
    _setupCustomerFormHandlers(modal) {
        const form = modal.querySelector('form');
        if (form) {
            // Pakistani phone number normalization (entity-specific functionality)
            let phone = form.querySelector('input[name="contact"]');
            if (phone) {
                phone.addEventListener('blur', function() {
                    this.value = CustomerUI.normalizeContact(this.value);
                });
            }
        }
    },
    _refreshCustomerDetails(id) {
        fetch(CUSTOMER_BASE + 'details.php?id=' + id).then(r=>r.text()).then(html=>{
            const detailsModal = document.getElementById('customer-details-modal');
            if (detailsModal) {
                const modalContent = detailsModal.querySelector('.modal-content');
                if (modalContent) {
                    modalContent.innerHTML = html;
                }
            }
        });
    },
    _updateCustomerRow(customer) {
        const row = document.querySelector(`#customers-table tbody tr[data-customer-id='${customer.id}']`);
        if (!row) return;
        row.setAttribute('data-customer', JSON.stringify(customer));
        row.querySelector('.customer-name').textContent = customer.name;
        const contactTd = row.querySelector('.customer-contact');
        if (contactTd) {
            contactTd.innerHTML = CustomerUI._whatsappLink(customer.contact, customer.name);
        }
        row.querySelector('.customer-address').textContent = (customer.house_no || '') + ', ' + (customer.area || '');
        row.querySelector('.customer-city').textContent = customer.city;
        row.querySelector('.customer-created').textContent = (customer.created_at || '').slice(0, 10);
        if (typeof customer.balance !== 'undefined') {
            const badge = row.querySelector('.customer-balance');
            if (badge) {
                badge.textContent = Math.abs(customer.balance);
                badge.className = 'badge customer-balance ' + (
                    customer.balance > 0
                        ? 'badge-outstanding'
                        : (customer.balance < 0 ? 'badge-surplus' : 'badge-settled')
                );
            }
        }
    },
    _insertCustomerRow(customer) {
        const tbody = document.querySelector('#customers-table tbody');
        if (!tbody) return;
        // Build row HTML similar to PHP output (simplified, can be expanded)
        const tr = document.createElement('tr');
        tr.setAttribute('data-customer-id', customer.id);
        tr.setAttribute('data-customer', JSON.stringify(customer));
        tr.innerHTML = `
            <td class="customer-name" data-label="Name">${customer.name}</td>
            <td class="customer-contact" data-label="Contact">
                ${CustomerUI._whatsappLink(customer.contact, customer.name)}
            </td>
            <td class="customer-address" data-label="Address">${(customer.house_no || '') + ', ' + (customer.area || '')}</td>
            <td class="customer-city" data-label="City">${customer.city || ''}</td>
            <td data-label="Balance/Surplus">
                <span class="badge badge-settled customer-balance">0</span>
            </td>
            <td data-label="Unpaid Invoices">
                <span style="color:#aaa;font-size:0.98em;">None</span>
            </td>
            <td class="customer-created" data-label="Created">${(customer.created_at || '').slice(0, 10)}</td>
            <td data-label="Actions">
                <button class="btn-ico info" title="Details" onclick="CustomerUI.openDetails(${customer.id})"><i class="fa fa-eye"></i></button>
                <button class="btn-ico edit" title="Edit" onclick="CustomerUI.openEditModal(${customer.id})"><i class="fa fa-edit"></i></button>
                <button class="btn-ico danger" title="Delete" onclick="CustomerUI.deleteCustomer(${customer.id})"><i class="fa fa-trash"></i></button>
            </td>
        `;
        tbody.prepend(tr);
    },
    _whatsappLink(contact, name) {
        let c = CustomerUI.normalizeContact(contact);
        let num = c.replace(/[^\d]/g, '');
        if (num.length === 10) num = '92' + num;
        const msg = encodeURIComponent(`Assalam o Alaikum ${name},\nHope you are doing well. I would like to get in touch regarding our services. Kindly let me know if you have any queries. Thank you!`);
        return `<a href="https://wa.me/${num}?text=${msg}" target="_blank">${contact}</a>`;
    },
    _submitPaymentForm(e) {
        e.preventDefault();
        const form = e.target;
        const data = new FormData(form);
        data.append('action', 'payment');
        fetch('/api/customers.php', {
            method:'POST',
            body:data
        }).then(r=>r.json()).then(resp=>{
            if (resp.success) {
                const modal = form.closest('.modal');
                if (modal) {
                    modal.setAttribute('data-refresh-on-close', 'true');
                    window.UnifiedModals.hide(modal);
                }
            } else {
                alert(resp.error || 'Could not record payment!');
            }
        });
    },
    deleteCustomer(id) {
        if (!confirm('Are you sure you want to delete this customer?')) return;
        const data = new FormData();
        data.append('action', 'delete');
        data.append('id', id);
        fetch('/api/customers.php', {
            method:'POST',
            body:data
        }).then(r=>r.json()).then(resp=>{
            if (resp.success) {
                location.reload();
            } else {
                alert(resp.error || 'Could not delete customer!');
            }
        });
    },
    restoreCustomer(id) {
        if (!confirm('Restore this customer?')) return;
        const data = new FormData();
        data.append('action', 'restore');
        data.append('id', id);
        fetch('/api/customers.php', {
            method:'POST',
            body:data
        }).then(r=>r.json()).then(resp=>{
            if (resp.success) {
                location.reload();
            } else {
                alert(resp.error || 'Could not restore customer!');
            }
        });
    },
    deleteCustomerPermanent(id) {
        if (!confirm('Permanently delete this customer? This cannot be undone!')) return;
        const data = new FormData();
        data.append('action', 'delete_permanent');
        data.append('id', id);
        fetch('/api/customers.php', {
            method:'POST',
            body:data
        }).then(r=>r.json()).then(resp=>{
            if (resp.success) {
                location.reload();
            } else {
                alert(resp.error || 'Could not permanently delete!');
            }
        });
    },
    filterList(q) {
        q = (q||'').toLowerCase();
        document.querySelectorAll('#customers-table tbody tr').forEach(row=>{
            let show = false;
            row.querySelectorAll('td').forEach(td=>{
                if (td.textContent.toLowerCase().includes(q)) show = true;
            });
            if (show) {
                row.classList.remove('d-none-important');
            } else {
                row.classList.add('d-none-important');
            }
        });
    },
    // Pakistani contact normalization (entity-specific functionality preserved)
    normalizeContact(raw) {
        let c = raw.trim().replace(/[^\d\+]/g, '');
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
    }
};