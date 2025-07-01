<?php $this->layout('layouts/main'); ?>

<?php $this->section('title', ($title ?? 'Profile Settings') . ' - Shopologic'); ?>

<?php $this->section('content'); ?>
<div class="account-page">
    <div class="container">
        <div class="account-header">
            <h1>Profile Settings</h1>
            <p>Update your personal information and preferences</p>
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
                        <li><a href="/account/profile" class="nav-link active">
                            <i class="icon-user"></i>
                            Profile Settings
                        </a></li>
                        <li><a href="/account/orders" class="nav-link">
                            <i class="icon-orders"></i>
                            Order History
                        </a></li>
                        <li><a href="/account/addresses" class="nav-link">
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
                <?php if (isset($_GET['updated']) && $_GET['updated'] === '1'): ?>
                    <div class="alert alert-success">
                        <strong>Success!</strong> Your profile has been updated successfully.
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        <?php echo $this->e($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Personal Information -->
                <div class="profile-section">
                    <div class="section-header">
                        <h2>Personal Information</h2>
                    </div>

                    <form id="profile-form" action="<?php echo $profile_update_url; ?>" method="post" class="profile-form">
                        <?php echo $this->csrf_field(); ?>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name *</label>
                                <input type="text" 
                                       id="first_name" 
                                       name="first_name" 
                                       class="form-control <?php echo isset($errors['first_name']) ? 'error' : ''; ?>" 
                                       value="<?php echo $this->e($form_data['first_name'] ?? $user['first_name']); ?>"
                                       required>
                                <?php if (isset($errors['first_name'])): ?>
                                    <div class="field-error"><?php echo $this->e($errors['first_name']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name *</label>
                                <input type="text" 
                                       id="last_name" 
                                       name="last_name" 
                                       class="form-control <?php echo isset($errors['last_name']) ? 'error' : ''; ?>" 
                                       value="<?php echo $this->e($form_data['last_name'] ?? $user['last_name']); ?>"
                                       required>
                                <?php if (isset($errors['last_name'])): ?>
                                    <div class="field-error"><?php echo $this->e($errors['last_name']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   class="form-control" 
                                   value="<?php echo $this->e($user['email']); ?>"
                                   disabled
                                   readonly>
                            <div class="field-note">
                                Email address cannot be changed. Contact support if you need to update your email.
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" 
                                   id="phone" 
                                   name="phone" 
                                   class="form-control <?php echo isset($errors['phone']) ? 'error' : ''; ?>" 
                                   value="<?php echo $this->e($form_data['phone'] ?? $user['phone']); ?>">
                            <?php if (isset($errors['phone'])): ?>
                                <div class="field-error"><?php echo $this->e($errors['phone']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="update-profile-btn">
                                <span class="btn-text">Update Profile</span>
                                <span class="btn-loading" style="display: none;">Updating...</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Email Preferences -->
                <div class="profile-section">
                    <div class="section-header">
                        <h2>Email Preferences</h2>
                    </div>

                    <form id="preferences-form" action="<?php echo $profile_update_url; ?>" method="post" class="preferences-form">
                        <?php echo $this->csrf_field(); ?>

                        <div class="preference-group">
                            <label class="preference-item">
                                <input type="checkbox" 
                                       name="preferences[newsletter]" 
                                       value="1"
                                       <?php echo ($user['preferences']['newsletter'] ?? false) ? 'checked' : ''; ?>>
                                <div class="preference-content">
                                    <h4>Newsletter Subscription</h4>
                                    <p>Receive our weekly newsletter with new products, sales, and exclusive offers.</p>
                                </div>
                            </label>
                        </div>

                        <div class="preference-group">
                            <label class="preference-item">
                                <input type="checkbox" 
                                       name="preferences[marketing_emails]" 
                                       value="1"
                                       <?php echo ($user['preferences']['marketing_emails'] ?? false) ? 'checked' : ''; ?>>
                                <div class="preference-content">
                                    <h4>Marketing Emails</h4>
                                    <p>Get notified about special promotions, discounts, and personalized recommendations.</p>
                                </div>
                            </label>
                        </div>

                        <div class="preference-group">
                            <label class="preference-item">
                                <input type="checkbox" 
                                       name="preferences[order_updates]" 
                                       value="1"
                                       <?php echo ($user['preferences']['order_updates'] ?? true) ? 'checked' : ''; ?>>
                                <div class="preference-content">
                                    <h4>Order Updates</h4>
                                    <p>Receive important updates about your orders, shipping, and delivery status.</p>
                                </div>
                            </label>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="update-preferences-btn">
                                <span class="btn-text">Update Preferences</span>
                                <span class="btn-loading" style="display: none;">Updating...</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Change Password -->
                <div class="profile-section">
                    <div class="section-header">
                        <h2>Change Password</h2>
                    </div>

                    <form id="password-form" action="/account/password" method="post" class="password-form">
                        <?php echo $this->csrf_field(); ?>

                        <div class="form-group">
                            <label for="current_password">Current Password *</label>
                            <input type="password" 
                                   id="current_password" 
                                   name="current_password" 
                                   class="form-control" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password *</label>
                            <input type="password" 
                                   id="new_password" 
                                   name="new_password" 
                                   class="form-control" 
                                   required
                                   minlength="8">
                            <div class="password-strength" id="password-strength"></div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password *</label>
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   class="form-control" 
                                   required
                                   minlength="8">
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-secondary" id="change-password-btn">
                                <span class="btn-text">Change Password</span>
                                <span class="btn-loading" style="display: none;">Changing...</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Account Deletion -->
                <div class="profile-section danger-section">
                    <div class="section-header">
                        <h2>Danger Zone</h2>
                    </div>

                    <div class="danger-content">
                        <h4>Delete Account</h4>
                        <p>Permanently delete your account and all associated data. This action cannot be undone.</p>
                        <button type="button" class="btn btn-danger" id="delete-account-btn">
                            Delete My Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $this->do_action('account.profile.after_content', $user); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const profileForm = document.getElementById('profile-form');
    const preferencesForm = document.getElementById('preferences-form');
    const passwordForm = document.getElementById('password-form');
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordStrength = document.getElementById('password-strength');
    
    // Profile form submission
    profileForm.addEventListener('submit', function(e) {
        e.preventDefault();
        submitForm(this, 'update-profile-btn');
    });
    
    // Preferences form submission
    preferencesForm.addEventListener('submit', function(e) {
        e.preventDefault();
        submitForm(this, 'update-preferences-btn');
    });
    
    // Password form submission
    passwordForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validate password confirmation
        if (newPasswordInput.value !== confirmPasswordInput.value) {
            showMessage('New passwords do not match', 'error');
            return;
        }
        
        submitForm(this, 'change-password-btn');
    });
    
    // Password strength indicator
    newPasswordInput.addEventListener('input', function() {
        const password = this.value;
        const strength = calculatePasswordStrength(password);
        updatePasswordStrength(strength);
    });
    
    // Password confirmation validation
    confirmPasswordInput.addEventListener('input', function() {
        validatePasswordConfirmation();
    });
    
    newPasswordInput.addEventListener('input', function() {
        if (confirmPasswordInput.value) {
            validatePasswordConfirmation();
        }
    });
    
    // Delete account confirmation
    document.getElementById('delete-account-btn').addEventListener('click', function() {
        if (confirm('Are you absolutely sure you want to delete your account? This action cannot be undone.')) {
            if (confirm('This will permanently delete all your data, orders, and personal information. Are you sure?')) {
                alert('Account deletion would be processed here. Please contact support for assistance.');
            }
        }
    });
    
    function submitForm(form, buttonId) {
        const btn = document.getElementById(buttonId);
        const btnText = btn.querySelector('.btn-text');
        const btnLoading = btn.querySelector('.btn-loading');
        
        // Show loading state
        btn.disabled = true;
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline';
        
        // Clear previous errors
        clearErrors();
        
        // Submit form
        const formData = new FormData(form);
        
        fetch(form.action, {
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
                
                // Reset password form if successful
                if (form.id === 'password-form') {
                    form.reset();
                    passwordStrength.innerHTML = '';
                }
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
            btn.disabled = false;
            btnText.style.display = 'inline';
            btnLoading.style.display = 'none';
        });
    }
    
    function calculatePasswordStrength(password) {
        let score = 0;
        let feedback = [];
        
        if (password.length >= 8) score += 25;
        else feedback.push('At least 8 characters');
        
        if (/[a-z]/.test(password)) score += 25;
        else feedback.push('lowercase letter');
        
        if (/[A-Z]/.test(password)) score += 25;
        else feedback.push('uppercase letter');
        
        if (/[0-9]/.test(password)) score += 25;
        else feedback.push('number');
        
        if (/[^A-Za-z0-9]/.test(password)) score += 25;
        else feedback.push('special character');
        
        return { score: Math.min(score, 100), feedback };
    }
    
    function updatePasswordStrength(strength) {
        const colors = {
            0: '#dc3545',
            25: '#fd7e14',
            50: '#ffc107',
            75: '#20c997',
            100: '#28a745'
        };
        
        const labels = {
            0: 'Very Weak',
            25: 'Weak',
            50: 'Fair',
            75: 'Good',
            100: 'Strong'
        };
        
        const color = colors[Math.floor(strength.score / 25) * 25];
        const label = labels[Math.floor(strength.score / 25) * 25];
        
        passwordStrength.innerHTML = `
            <div class="strength-bar">
                <div class="strength-fill" style="width: ${strength.score}%; background-color: ${color};"></div>
            </div>
            <div class="strength-label" style="color: ${color};">${label}</div>
            ${strength.feedback.length > 0 ? `<div class="strength-feedback">Missing: ${strength.feedback.join(', ')}</div>` : ''}
        `;
    }
    
    function validatePasswordConfirmation() {
        const password = newPasswordInput.value;
        const confirm = confirmPasswordInput.value;
        
        if (confirm && password !== confirm) {
            confirmPasswordInput.classList.add('error');
            
            // Remove existing error
            const existingError = confirmPasswordInput.parentNode.querySelector('.field-error');
            if (existingError) existingError.remove();
            
            // Add new error
            const errorDiv = document.createElement('div');
            errorDiv.className = 'field-error';
            errorDiv.textContent = 'Passwords do not match';
            confirmPasswordInput.parentNode.appendChild(errorDiv);
            
            return false;
        } else {
            confirmPasswordInput.classList.remove('error');
            const existingError = confirmPasswordInput.parentNode.querySelector('.field-error');
            if (existingError) existingError.remove();
            return true;
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
            const input = document.getElementById(field);
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
.profile-section {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    padding: 30px;
    margin-bottom: 30px;
}

.section-header {
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.section-header h2 {
    margin: 0;
    color: var(--dark-color);
}

.profile-form,
.preferences-form,
.password-form {
    max-width: 600px;
}

.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 0;
}

.form-row .form-group {
    flex: 1;
}

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
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

.form-control:disabled {
    background: #f8f9fa;
    color: var(--secondary-color);
}

.form-control.error {
    border-color: var(--danger-color);
}

.field-error {
    color: var(--danger-color);
    font-size: 14px;
    margin-top: 5px;
}

.field-note {
    font-size: 14px;
    color: var(--secondary-color);
    margin-top: 5px;
}

.form-actions {
    margin-top: 30px;
}

/* Password Strength */
.password-strength {
    margin-top: 8px;
}

.strength-bar {
    height: 4px;
    background: #e1e5e9;
    border-radius: 2px;
    overflow: hidden;
    margin-bottom: 5px;
}

.strength-fill {
    height: 100%;
    transition: width 0.3s ease, background-color 0.3s ease;
}

.strength-label {
    font-size: 12px;
    font-weight: 500;
}

.strength-feedback {
    font-size: 11px;
    color: #6c757d;
    margin-top: 2px;
}

/* Preferences */
.preference-group {
    margin-bottom: 20px;
}

.preference-item {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 20px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    cursor: pointer;
    transition: border-color 0.3s ease, background 0.3s ease;
}

.preference-item:hover {
    border-color: var(--primary-color);
    background: #f8f9ff;
}

.preference-item input[type="checkbox"] {
    width: auto;
    margin: 0;
    flex-shrink: 0;
}

.preference-content h4 {
    margin-bottom: 5px;
    color: var(--dark-color);
}

.preference-content p {
    margin: 0;
    color: var(--secondary-color);
    font-size: 14px;
}

/* Danger Zone */
.danger-section {
    border: 2px solid var(--danger-color);
    background: #fff5f5;
}

.danger-section .section-header {
    border-bottom-color: var(--danger-color);
}

.danger-section .section-header h2 {
    color: var(--danger-color);
}

.danger-content h4 {
    color: var(--danger-color);
    margin-bottom: 10px;
}

.danger-content p {
    color: var(--secondary-color);
    margin-bottom: 20px;
}

.btn-danger {
    background: var(--danger-color);
    color: white;
    border: none;
}

.btn-danger:hover {
    background: #c82333;
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
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .preference-item {
        flex-direction: column;
        gap: 10px;
    }
}
</style>
<?php $this->endSection(); ?>