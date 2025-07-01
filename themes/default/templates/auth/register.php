<?php $this->layout('layouts/main'); ?>

<?php $this->section('title', ($title ?? 'Create Account') . ' - Shopologic'); ?>

<?php $this->section('content'); ?>
<div class="auth-page">
    <div class="container">
        <div class="auth-container register-container">
            <div class="auth-header">
                <h1>Create Account</h1>
                <p>Join Shopologic and start your shopping journey</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <?php echo $this->e($error); ?>
                </div>
            <?php endif; ?>

            <form id="register-form" action="<?php echo $register_action_url; ?>" method="post" class="auth-form">
                <?php echo $this->csrf_field(); ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" 
                               id="first_name" 
                               name="first_name" 
                               class="form-control <?php echo isset($errors['first_name']) ? 'error' : ''; ?>" 
                               value="<?php echo $this->e($form_data['first_name'] ?? ''); ?>"
                               required 
                               autocomplete="given-name">
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
                               value="<?php echo $this->e($form_data['last_name'] ?? ''); ?>"
                               required 
                               autocomplete="family-name">
                        <?php if (isset($errors['last_name'])): ?>
                            <div class="field-error"><?php echo $this->e($errors['last_name']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="form-control <?php echo isset($errors['email']) ? 'error' : ''; ?>" 
                           value="<?php echo $this->e($form_data['email'] ?? ''); ?>"
                           required 
                           autocomplete="email">
                    <?php if (isset($errors['email'])): ?>
                        <div class="field-error"><?php echo $this->e($errors['email']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" 
                           id="phone" 
                           name="phone" 
                           class="form-control <?php echo isset($errors['phone']) ? 'error' : ''; ?>" 
                           value="<?php echo $this->e($form_data['phone'] ?? ''); ?>"
                           autocomplete="tel">
                    <?php if (isset($errors['phone'])): ?>
                        <div class="field-error"><?php echo $this->e($errors['phone']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-control <?php echo isset($errors['password']) ? 'error' : ''; ?>" 
                               required 
                               autocomplete="new-password"
                               minlength="8">
                        <div class="password-strength" id="password-strength"></div>
                        <?php if (isset($errors['password'])): ?>
                            <div class="field-error"><?php echo $this->e($errors['password']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="password_confirm">Confirm Password *</label>
                        <input type="password" 
                               id="password_confirm" 
                               name="password_confirm" 
                               class="form-control <?php echo isset($errors['password_confirm']) ? 'error' : ''; ?>" 
                               required 
                               autocomplete="new-password"
                               minlength="8">
                        <?php if (isset($errors['password_confirm'])): ?>
                            <div class="field-error"><?php echo $this->e($errors['password_confirm']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" 
                               name="newsletter" 
                               value="1"
                               <?php echo isset($form_data['newsletter']) && $form_data['newsletter'] ? 'checked' : ''; ?>>
                        <span class="checkmark"></span>
                        Subscribe to our newsletter for special offers and updates
                    </label>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" 
                               name="terms" 
                               value="1"
                               required>
                        <span class="checkmark"></span>
                        I agree to the <a href="/terms" target="_blank">Terms of Service</a> and 
                        <a href="/privacy" target="_blank">Privacy Policy</a> *
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-block" id="register-btn">
                    <span class="btn-text">Create Account</span>
                    <span class="btn-loading" style="display: none;">Creating account...</span>
                </button>
            </form>

            <div class="auth-divider">
                <span>or</span>
            </div>

            <div class="social-login">
                <button type="button" class="btn btn-social btn-google">
                    <i class="icon-google"></i>
                    Sign up with Google
                </button>
                <button type="button" class="btn btn-social btn-facebook">
                    <i class="icon-facebook"></i>
                    Sign up with Facebook
                </button>
            </div>

            <div class="auth-footer">
                <p>Already have an account? 
                    <a href="<?php echo $login_url; ?>" class="login-link">Sign in here</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php $this->do_action('auth.register.after_content'); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('register-form');
    const registerBtn = document.getElementById('register-btn');
    const btnText = registerBtn.querySelector('.btn-text');
    const btnLoading = registerBtn.querySelector('.btn-loading');
    const passwordInput = document.getElementById('password');
    const passwordConfirmInput = document.getElementById('password_confirm');
    const passwordStrength = document.getElementById('password-strength');
    
    // Password strength indicator
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        const strength = calculatePasswordStrength(password);
        updatePasswordStrength(strength);
    });
    
    // Password confirmation validation
    passwordConfirmInput.addEventListener('input', function() {
        validatePasswordConfirmation();
    });
    
    passwordInput.addEventListener('input', function() {
        if (passwordConfirmInput.value) {
            validatePasswordConfirmation();
        }
    });
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validate form
        if (!validateForm()) {
            return;
        }
        
        // Show loading state
        registerBtn.disabled = true;
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
                
                // Redirect after successful registration
                setTimeout(() => {
                    window.location.href = data.redirect_url || '/account';
                }, 2000);
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
            registerBtn.disabled = false;
            btnText.style.display = 'inline';
            btnLoading.style.display = 'none';
        });
    });
    
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
        const password = passwordInput.value;
        const confirm = passwordConfirmInput.value;
        
        if (confirm && password !== confirm) {
            passwordConfirmInput.classList.add('error');
            
            // Remove existing error
            const existingError = passwordConfirmInput.parentNode.querySelector('.field-error');
            if (existingError) existingError.remove();
            
            // Add new error
            const errorDiv = document.createElement('div');
            errorDiv.className = 'field-error';
            errorDiv.textContent = 'Passwords do not match';
            passwordConfirmInput.parentNode.appendChild(errorDiv);
            
            return false;
        } else {
            passwordConfirmInput.classList.remove('error');
            const existingError = passwordConfirmInput.parentNode.querySelector('.field-error');
            if (existingError) existingError.remove();
            return true;
        }
    }
    
    function validateForm() {
        let isValid = true;
        
        // Check required fields
        const requiredFields = ['first_name', 'last_name', 'email', 'password', 'password_confirm'];
        requiredFields.forEach(fieldName => {
            const field = document.getElementById(fieldName);
            if (!field.value.trim()) {
                field.classList.add('error');
                isValid = false;
            } else {
                field.classList.remove('error');
            }
        });
        
        // Check terms checkbox
        const termsCheckbox = document.querySelector('input[name="terms"]');
        if (!termsCheckbox.checked) {
            showMessage('Please accept the Terms of Service and Privacy Policy', 'error');
            isValid = false;
        }
        
        // Check password confirmation
        if (!validatePasswordConfirmation()) {
            isValid = false;
        }
        
        return isValid;
    }
    
    function showMessage(message, type) {
        // Remove existing alerts
        const existingAlerts = document.querySelectorAll('.alert');
        existingAlerts.forEach(alert => alert.remove());
        
        // Create new alert
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.textContent = message;
        
        // Insert after auth header
        const authHeader = document.querySelector('.auth-header');
        authHeader.insertAdjacentElement('afterend', alert);
        
        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
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
    
    // Social login handlers (placeholder)
    document.querySelector('.btn-google').addEventListener('click', function() {
        alert('Google registration integration would be implemented here');
    });
    
    document.querySelector('.btn-facebook').addEventListener('click', function() {
        alert('Facebook registration integration would be implemented here');
    });
});
</script>

<style>
.register-container {
    max-width: 500px;
}

.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 0;
}

.form-row .form-group {
    flex: 1;
}

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

@media (max-width: 480px) {
    .form-row {
        flex-direction: column;
        gap: 0;
    }
}
</style>
<?php $this->endSection(); ?>