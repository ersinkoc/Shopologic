<?php $this->layout('layouts/main'); ?>

<?php $this->section('title', ($title ?? 'Login') . ' - Shopologic'); ?>

<?php $this->section('content'); ?>
<div class="auth-page">
    <div class="container">
        <div class="auth-container">
            <div class="auth-header">
                <h1>Welcome Back</h1>
                <p>Sign in to your account to continue shopping</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <?php echo $this->e($error); ?>
                </div>
            <?php endif; ?>

            <form id="login-form" action="<?php echo $login_action_url; ?>" method="post" class="auth-form">
                <?php echo $this->csrf_field(); ?>
                
                <input type="hidden" name="redirect_url" value="<?php echo $this->e($redirect_url ?? '/'); ?>">
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="form-control" 
                           value="<?php echo $this->e($email ?? ''); ?>"
                           required 
                           autocomplete="email">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-control" 
                           required 
                           autocomplete="current-password">
                </div>

                <div class="form-group form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember_me">
                        <span class="checkmark"></span>
                        Remember me
                    </label>
                    <a href="<?php echo $forgot_password_url; ?>" class="forgot-password">
                        Forgot your password?
                    </a>
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-block" id="login-btn">
                    <span class="btn-text">Sign In</span>
                    <span class="btn-loading" style="display: none;">Signing in...</span>
                </button>
            </form>

            <div class="auth-divider">
                <span>or</span>
            </div>

            <div class="social-login">
                <button type="button" class="btn btn-social btn-google">
                    <i class="icon-google"></i>
                    Continue with Google
                </button>
                <button type="button" class="btn btn-social btn-facebook">
                    <i class="icon-facebook"></i>
                    Continue with Facebook
                </button>
            </div>

            <div class="auth-footer">
                <p>Don't have an account? 
                    <a href="<?php echo $register_url; ?>" class="register-link">Create one now</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php $this->do_action('auth.login.after_content'); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('login-form');
    const loginBtn = document.getElementById('login-btn');
    const btnText = loginBtn.querySelector('.btn-text');
    const btnLoading = loginBtn.querySelector('.btn-loading');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Show loading state
        loginBtn.disabled = true;
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
                
                // Redirect after successful login
                setTimeout(() => {
                    window.location.href = data.redirect_url || '/account';
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
            loginBtn.disabled = false;
            btnText.style.display = 'inline';
            btnLoading.style.display = 'none';
        });
    });
    
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
            }, 3000);
        }
    }
    
    function showValidationErrors(errors) {
        Object.keys(errors).forEach(field => {
            const input = document.getElementById(field);
            if (input) {
                input.classList.add('error');
                
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
        alert('Google login integration would be implemented here');
    });
    
    document.querySelector('.btn-facebook').addEventListener('click', function() {
        alert('Facebook login integration would be implemented here');
    });
});
</script>

<style>
.auth-page {
    padding: 60px 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
}

.auth-container {
    max-width: 400px;
    margin: 0 auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    overflow: hidden;
}

.auth-header {
    text-align: center;
    padding: 40px 40px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.auth-header h1 {
    margin-bottom: 10px;
    font-size: 28px;
}

.auth-header p {
    opacity: 0.9;
    font-size: 16px;
    margin: 0;
}

.auth-form {
    padding: 30px 40px;
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
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-control.error {
    border-color: var(--danger-color);
}

.field-error {
    color: var(--danger-color);
    font-size: 14px;
    margin-top: 5px;
}

.form-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 14px;
}

.checkbox-label input[type="checkbox"] {
    width: auto;
    margin: 0;
}

.forgot-password {
    color: #667eea;
    text-decoration: none;
    font-size: 14px;
}

.forgot-password:hover {
    text-decoration: underline;
}

.btn-lg {
    padding: 14px 24px;
    font-size: 16px;
    font-weight: 600;
}

.btn-block {
    width: 100%;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
}

.auth-divider {
    text-align: center;
    padding: 20px 40px;
    position: relative;
}

.auth-divider::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 40px;
    right: 40px;
    height: 1px;
    background: #e1e5e9;
}

.auth-divider span {
    background: white;
    padding: 0 15px;
    color: #6c757d;
    font-size: 14px;
}

.social-login {
    padding: 0 40px 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.btn-social {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 12px 16px;
    border: 2px solid #e1e5e9;
    background: white;
    color: var(--dark-color);
    border-radius: 8px;
    transition: all 0.3s ease;
    font-weight: 500;
}

.btn-social:hover {
    border-color: #667eea;
    background: #f8f9ff;
}

.btn-google:hover {
    border-color: #db4437;
    background: #fdf2f2;
}

.btn-facebook:hover {
    border-color: #3b5998;
    background: #f0f2ff;
}

.auth-footer {
    text-align: center;
    padding: 20px 40px 40px;
    border-top: 1px solid #e1e5e9;
    background: #f8f9fa;
}

.auth-footer p {
    margin: 0;
    color: #6c757d;
}

.register-link {
    color: #667eea;
    text-decoration: none;
    font-weight: 500;
}

.register-link:hover {
    text-decoration: underline;
}

.alert {
    padding: 12px 16px;
    margin: 20px 40px 0;
    border-radius: 8px;
    font-size: 14px;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

@media (max-width: 480px) {
    .auth-container {
        margin: 20px;
        max-width: none;
    }
    
    .auth-header,
    .auth-form,
    .social-login,
    .auth-footer {
        padding-left: 20px;
        padding-right: 20px;
    }
    
    .auth-divider {
        padding-left: 20px;
        padding-right: 20px;
    }
    
    .auth-divider::before {
        left: 20px;
        right: 20px;
    }
    
    .form-options {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
}
</style>
<?php $this->endSection(); ?>