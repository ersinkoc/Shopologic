<?php $this->layout('layouts/main'); ?>

<?php $this->section('title', ($title ?? 'My Addresses') . ' - Shopologic'); ?>

<?php $this->section('content'); ?>
<div class="account-page">
    <div class="container">
        <div class="account-header">
            <h1>Address Book</h1>
            <p>Manage your billing and shipping addresses</p>
        </div>

        <div class="account-content">
            <!-- Account Navigation -->
            <div class="account-sidebar">
                <div class="account-nav">
                    <h3>My Account</h3>
                    <ul class="nav-menu">
                        <li><a href="<?php echo $account_url; ?>" class="nav-link">
                            <i class="icon-dashboard"></i>
                            Dashboard
                        </a></li>
                        <li><a href="/account/profile" class="nav-link">
                            <i class="icon-user"></i>
                            Profile Settings
                        </a></li>
                        <li><a href="/account/orders" class="nav-link">
                            <i class="icon-orders"></i>
                            Order History
                        </a></li>
                        <li><a href="/account/addresses" class="nav-link active">
                            <i class="icon-location"></i>
                            Address Book
                        </a></li>
                        <li><a href="/account/wishlist" class="nav-link">
                            <i class="icon-heart"></i>
                            Wishlist
                        </a></li>
                        <li><a href="/account/reviews" class="nav-link">
                            <i class="icon-star"></i>
                            Reviews
                        </a></li>
                        <li><a href="/auth/logout" class="nav-link logout">
                            <i class="icon-logout"></i>
                            Sign Out
                        </a></li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="account-main">
                <?php if (isset($_GET['added']) && $_GET['added'] === '1'): ?>
                    <div class="alert alert-success">
                        <strong>Success!</strong> Address has been added successfully.
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-error">
                        <?php echo $this->e($_GET['error']); ?>
                    </div>
                <?php endif; ?>

                <!-- Add New Address Button -->
                <div class="addresses-header">
                    <h2>Your Addresses</h2>
                    <button type="button" class="btn btn-primary" id="add-address-btn">
                        <i class="icon-plus"></i>
                        Add New Address
                    </button>
                </div>

                <!-- Addresses Grid -->
                <?php if (!empty($addresses)): ?>
                    <div class="addresses-grid">
                        <?php foreach ($addresses as $address): ?>
                            <div class="address-card <?php echo $address['is_default'] ? 'default' : ''; ?>">
                                <div class="address-header">
                                    <div class="address-type">
                                        <i class="icon-<?php echo $address['type'] === 'billing' ? 'credit-card' : ($address['type'] === 'shipping' ? 'truck' : 'location'); ?>"></i>
                                        <span><?php echo ucfirst($this->e($address['type'])); ?> Address</span>
                                    </div>
                                    <?php if ($address['is_default']): ?>
                                        <span class="default-badge">Default</span>
                                    <?php endif; ?>
                                </div>

                                <div class="address-content">
                                    <div class="address-name">
                                        <?php echo $this->e($address['first_name'] . ' ' . $address['last_name']); ?>
                                    </div>
                                    <?php if (!empty($address['company'])): ?>
                                        <div class="address-company">
                                            <?php echo $this->e($address['company']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="address-street">
                                        <?php echo $this->e($address['address_1']); ?>
                                        <?php if (!empty($address['address_2'])): ?>
                                            <br><?php echo $this->e($address['address_2']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="address-city">
                                        <?php echo $this->e($address['city'] . ', ' . $address['state'] . ' ' . $address['postcode']); ?>
                                    </div>
                                    <div class="address-country">
                                        <?php echo $this->e($address['country']); ?>
                                    </div>
                                </div>

                                <div class="address-actions">
                                    <button type="button" class="btn btn-sm btn-outline-primary edit-address" 
                                            data-address-id="<?php echo $address['id']; ?>">
                                        Edit
                                    </button>
                                    <?php if (!$address['is_default']): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary set-default" 
                                                data-address-id="<?php echo $address['id']; ?>"
                                                data-address-type="<?php echo $this->e($address['type']); ?>">
                                            Set Default
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-address" 
                                                data-address-id="<?php echo $address['id']; ?>">
                                            Delete
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-addresses">
                        <div class="empty-icon">
                            <i class="icon-location"></i>
                        </div>
                        <h3>No addresses saved</h3>
                        <p>Add your first address to make checkout faster and easier.</p>
                        <button type="button" class="btn btn-primary" id="add-first-address-btn">
                            Add Your First Address
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Address Modal -->
<div id="address-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-title">Add New Address</h3>
            <button type="button" class="modal-close" id="close-modal">&times;</button>
        </div>

        <form id="address-form" action="<?php echo $add_address_url; ?>" method="post">
            <?php echo $this->csrf_field(); ?>
            <input type="hidden" id="address-id" name="address_id" value="">

            <div class="modal-body">
                <div class="form-group">
                    <label for="address_type">Address Type *</label>
                    <select id="address_type" name="type" class="form-control" required>
                        <option value="billing">Billing Address</option>
                        <option value="shipping">Shipping Address</option>
                        <option value="both">Both Billing & Shipping</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="address_first_name">First Name *</label>
                        <input type="text" 
                               id="address_first_name" 
                               name="first_name" 
                               class="form-control" 
                               required>
                    </div>
                    <div class="form-group">
                        <label for="address_last_name">Last Name *</label>
                        <input type="text" 
                               id="address_last_name" 
                               name="last_name" 
                               class="form-control" 
                               required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="address_company">Company (optional)</label>
                    <input type="text" 
                           id="address_company" 
                           name="company" 
                           class="form-control">
                </div>

                <div class="form-group">
                    <label for="address_address_1">Street Address *</label>
                    <input type="text" 
                           id="address_address_1" 
                           name="address_1" 
                           class="form-control" 
                           placeholder="Street address"
                           required>
                </div>

                <div class="form-group">
                    <input type="text" 
                           id="address_address_2" 
                           name="address_2" 
                           class="form-control" 
                           placeholder="Apartment, suite, etc. (optional)">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="address_city">City *</label>
                        <input type="text" 
                               id="address_city" 
                               name="city" 
                               class="form-control" 
                               required>
                    </div>
                    <div class="form-group">
                        <label for="address_state">State/Province</label>
                        <input type="text" 
                               id="address_state" 
                               name="state" 
                               class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="address_postcode">Postal Code *</label>
                        <input type="text" 
                               id="address_postcode" 
                               name="postcode" 
                               class="form-control" 
                               required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="address_country">Country *</label>
                    <select id="address_country" name="country" class="form-control" required>
                        <?php foreach ($countries as $code => $name): ?>
                            <option value="<?php echo $this->e($code); ?>"
                                    <?php echo $code === 'US' ? 'selected' : ''; ?>>
                                <?php echo $this->e($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="is_default" name="is_default" value="1">
                        <span class="checkmark"></span>
                        Set as default address
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancel-address">Cancel</button>
                <button type="submit" class="btn btn-primary" id="save-address-btn">
                    <span class="btn-text">Save Address</span>
                    <span class="btn-loading" style="display: none;">Saving...</span>
                </button>
            </div>
        </form>
    </div>
</div>

<?php $this->do_action('account.addresses.after_content', $user); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('address-modal');
    const modalTitle = document.getElementById('modal-title');
    const addressForm = document.getElementById('address-form');
    const addAddressBtn = document.getElementById('add-address-btn');
    const addFirstAddressBtn = document.getElementById('add-first-address-btn');
    const closeModal = document.getElementById('close-modal');
    const cancelAddress = document.getElementById('cancel-address');
    const saveAddressBtn = document.getElementById('save-address-btn');
    
    // Add address buttons
    if (addAddressBtn) {
        addAddressBtn.addEventListener('click', function() {
            openAddressModal();
        });
    }
    
    if (addFirstAddressBtn) {
        addFirstAddressBtn.addEventListener('click', function() {
            openAddressModal();
        });
    }
    
    // Edit address buttons
    document.querySelectorAll('.edit-address').forEach(btn => {
        btn.addEventListener('click', function() {
            const addressId = this.dataset.addressId;
            openAddressModal(addressId);
        });
    });
    
    // Set default buttons
    document.querySelectorAll('.set-default').forEach(btn => {
        btn.addEventListener('click', function() {
            const addressId = this.dataset.addressId;
            const addressType = this.dataset.addressType;
            setDefaultAddress(addressId, addressType);
        });
    });
    
    // Delete address buttons
    document.querySelectorAll('.delete-address').forEach(btn => {
        btn.addEventListener('click', function() {
            const addressId = this.dataset.addressId;
            deleteAddress(addressId);
        });
    });
    
    // Modal close handlers
    closeModal.addEventListener('click', closeAddressModal);
    cancelAddress.addEventListener('click', closeAddressModal);
    
    // Click outside modal to close
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeAddressModal();
        }
    });
    
    // Form submission
    addressForm.addEventListener('submit', function(e) {
        e.preventDefault();
        saveAddress();
    });
    
    function openAddressModal(addressId = null) {
        if (addressId) {
            // Edit mode
            modalTitle.textContent = 'Edit Address';
            loadAddressData(addressId);
        } else {
            // Add mode
            modalTitle.textContent = 'Add New Address';
            addressForm.reset();
            document.getElementById('address-id').value = '';
        }
        
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    function closeAddressModal() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
        clearErrors();
    }
    
    function loadAddressData(addressId) {
        // In a real implementation, this would fetch address data from the server
        // For now, we'll simulate it with the existing data
        const addressCards = document.querySelectorAll('.address-card');
        
        addressCards.forEach(card => {
            const editBtn = card.querySelector('.edit-address');
            if (editBtn && editBtn.dataset.addressId === addressId) {
                // Extract data from the card (this is simplified)
                const name = card.querySelector('.address-name').textContent.trim().split(' ');
                const company = card.querySelector('.address-company')?.textContent.trim() || '';
                
                document.getElementById('address-id').value = addressId;
                document.getElementById('address_first_name').value = name[0] || '';
                document.getElementById('address_last_name').value = name.slice(1).join(' ') || '';
                document.getElementById('address_company').value = company;
                
                // For a real implementation, you'd populate all fields from server data
            }
        });
    }
    
    function saveAddress() {
        const btnText = saveAddressBtn.querySelector('.btn-text');
        const btnLoading = saveAddressBtn.querySelector('.btn-loading');
        
        // Show loading state
        saveAddressBtn.disabled = true;
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline';
        
        // Clear previous errors
        clearErrors();
        
        // Submit form
        const formData = new FormData(addressForm);
        
        fetch(addressForm.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage(data.message, 'success');
                closeAddressModal();
                
                // Reload page to show updated addresses
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showMessage(data.message, 'error');
                if (data.errors) {
                    showValidationErrors(data.errors);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('An error occurred. Please try again.', 'error');
        })
        .finally(() => {
            // Reset loading state
            saveAddressBtn.disabled = false;
            btnText.style.display = 'inline';
            btnLoading.style.display = 'none';
        });
    }
    
    function setDefaultAddress(addressId, addressType) {
        if (confirm(`Set this as your default ${addressType} address?`)) {
            // Implement set default logic
            showMessage(`Default ${addressType} address updated successfully.`, 'success');
        }
    }
    
    function deleteAddress(addressId) {
        if (confirm('Are you sure you want to delete this address?')) {
            // Implement delete logic
            showMessage('Address deleted successfully.', 'success');
        }
    }
    
    function showMessage(message, type) {
        // Remove existing alerts
        const existingAlerts = document.querySelectorAll('.alert');
        existingAlerts.forEach(alert => alert.remove());
        
        // Create new alert
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.innerHTML = type === 'success' 
            ? `<strong>Success!</strong> ${message}`
            : message;
        
        // Insert at top of main content
        const accountMain = document.querySelector('.account-main');
        accountMain.insertBefore(alert, accountMain.firstChild);
        
        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
        
        // Scroll to top to show message
        alert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    function showValidationErrors(errors) {
        Object.keys(errors).forEach(field => {
            const input = document.getElementById('address_' + field) || document.getElementById(field);
            if (input) {
                input.classList.add('error');
                
                // Remove existing error
                const existingError = input.parentNode.querySelector('.field-error');
                if (existingError) existingError.remove();
                
                // Add new error
                const errorDiv = document.createElement('div');
                errorDiv.className = 'field-error';
                errorDiv.textContent = errors[field];
                input.parentNode.appendChild(errorDiv);
            }
        });
    }
    
    function clearErrors() {
        document.querySelectorAll('.field-error').forEach(el => el.remove());
        document.querySelectorAll('.form-control.error').forEach(el => el.classList.remove('error'));
    }
});
</script>

<style>
.addresses-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #f0f0f0;
}

.addresses-header h2 {
    margin: 0;
    color: var(--dark-color);
}

.addresses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 25px;
}

.address-card {
    background: white;
    border: 2px solid #e1e5e9;
    border-radius: 12px;
    padding: 25px;
    transition: all 0.3s ease;
    position: relative;
}

.address-card:hover {
    border-color: var(--primary-color);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.address-card.default {
    border-color: var(--success-color);
    background: #f0f9ff;
}

.address-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.address-type {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--primary-color);
    font-weight: 500;
}

.default-badge {
    background: var(--success-color);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.address-content {
    margin-bottom: 20px;
    line-height: 1.5;
}

.address-name {
    font-weight: bold;
    font-size: 16px;
    color: var(--dark-color);
    margin-bottom: 5px;
}

.address-company {
    color: var(--secondary-color);
    font-style: italic;
    margin-bottom: 5px;
}

.address-street,
.address-city,
.address-country {
    color: var(--dark-color);
}

.address-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.address-actions .btn {
    flex: 1;
    min-width: auto;
}

/* Empty State */
.empty-addresses {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.empty-icon {
    font-size: 64px;
    color: #e1e5e9;
    margin-bottom: 20px;
}

.empty-addresses h3 {
    color: var(--dark-color);
    margin-bottom: 10px;
}

.empty-addresses p {
    color: var(--secondary-color);
    margin-bottom: 25px;
}

/* Modal */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background: white;
    border-radius: 12px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 25px 30px;
    border-bottom: 1px solid #e1e5e9;
}

.modal-header h3 {
    margin: 0;
    color: var(--dark-color);
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: var(--secondary-color);
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    color: var(--dark-color);
}

.modal-body {
    padding: 30px;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    padding: 25px 30px;
    border-top: 1px solid #e1e5e9;
    background: #f8f9fa;
}

/* Form Styles */
.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 0;
}

.form-row .form-group {
    flex: 1;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: var(--dark-color);
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    font-size: 16px;
    transition: border-color 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
}

.form-control.error {
    border-color: var(--danger-color);
}

.field-error {
    color: var(--danger-color);
    font-size: 14px;
    margin-top: 5px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-weight: normal;
}

.checkbox-label input[type="checkbox"] {
    width: auto;
    margin: 0;
}

/* Alerts */
.alert {
    padding: 15px 20px;
    margin-bottom: 25px;
    border-radius: 8px;
    font-size: 15px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Responsive */
@media (max-width: 768px) {
    .addresses-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    .addresses-grid {
        grid-template-columns: 1fr;
    }
    
    .address-actions .btn {
        flex: none;
        width: 100%;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .modal-content {
        width: 95%;
    }
    
    .modal-header,
    .modal-body,
    .modal-footer {
        padding-left: 20px;
        padding-right: 20px;
    }
    
    .modal-footer {
        flex-direction: column;
    }
}
</style>
<?php $this->endSection(); ?>