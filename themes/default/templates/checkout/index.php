<?php $this->layout('layouts/main'); ?>

<?php $this->section('title', ($title ?? 'Checkout') . ' - Shopologic'); ?>

<?php $this->section('content'); ?>
<div class="checkout-page">
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Checkout</h1>
            
            <?php if (!$is_logged_in): ?>
                <div class="auth-options">
                    <div class="guest-checkout-info">
                        <p>You can checkout as a guest or create an account for faster future purchases.</p>
                        <div class="auth-buttons">
                            <a href="<?php echo $login_url; ?>?redirect=<?php echo urlencode('/checkout'); ?>" class="btn btn-outline-primary">
                                Login to Account
                            </a>
                            <a href="<?php echo $register_url; ?>?redirect=<?php echo urlencode('/checkout'); ?>" class="btn btn-outline-secondary">
                                Create Account
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="logged-in-info">
                    <p>Welcome back, <?php echo $this->e($user['first_name']); ?>! Your information has been pre-filled.</p>
                </div>
            <?php endif; ?>
            
            <div class="checkout-steps">
                <div class="step active">
                    <span class="step-number">1</span>
                    <span class="step-title">Information</span>
                </div>
                <div class="step">
                    <span class="step-number">2</span>
                    <span class="step-title">Payment</span>
                </div>
                <div class="step">
                    <span class="step-number">3</span>
                    <span class="step-title">Complete</span>
                </div>
            </div>
        </div>

        <form id="checkout-form" action="<?php echo $checkout_process_url; ?>" method="post">
            <?php echo $this->csrf_field(); ?>
            
            <div class="checkout-content">
                <!-- Main Checkout Form -->
                <div class="checkout-main">
                    
                    <!-- Customer Information -->
                    <div class="checkout-section">
                        <h2>Contact Information</h2>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="first_name">First Name *</label>
                                <input type="text" 
                                       id="first_name" 
                                       name="first_name" 
                                       class="form-control" 
                                       value="<?php echo $this->e($default_data['first_name'] ?? ''); ?>"
                                       required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="last_name">Last Name *</label>
                                <input type="text" 
                                       id="last_name" 
                                       name="last_name" 
                                       class="form-control" 
                                       value="<?php echo $this->e($default_data['last_name'] ?? ''); ?>"
                                       required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <label for="email">Email Address *</label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       class="form-control" 
                                       value="<?php echo $this->e($default_data['email'] ?? ''); ?>"
                                       required>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="phone">Phone Number</label>
                                <input type="tel" 
                                       id="phone" 
                                       name="phone" 
                                       class="form-control"
                                       value="<?php echo $this->e($default_data['phone'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Billing Address -->
                    <div class="checkout-section">
                        <h2>Billing Address</h2>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="billing_first_name">First Name *</label>
                                <input type="text" 
                                       id="billing_first_name" 
                                       name="billing_first_name" 
                                       class="form-control" 
                                       value="<?php echo $this->e($default_data['billing_first_name'] ?? $default_data['first_name'] ?? ''); ?>"
                                       required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="billing_last_name">Last Name *</label>
                                <input type="text" 
                                       id="billing_last_name" 
                                       name="billing_last_name" 
                                       class="form-control" 
                                       value="<?php echo $this->e($default_data['billing_last_name'] ?? $default_data['last_name'] ?? ''); ?>"
                                       required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="billing_company">Company (optional)</label>
                            <input type="text" 
                                   id="billing_company" 
                                   name="billing_company" 
                                   class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="billing_address_1">Street Address *</label>
                            <input type="text" 
                                   id="billing_address_1" 
                                   name="billing_address_1" 
                                   class="form-control" 
                                   placeholder="Street address" 
                                   required>
                        </div>
                        <div class="form-group">
                            <input type="text" 
                                   id="billing_address_2" 
                                   name="billing_address_2" 
                                   class="form-control" 
                                   placeholder="Apartment, suite, etc. (optional)">
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="billing_city">City *</label>
                                <input type="text" 
                                       id="billing_city" 
                                       name="billing_city" 
                                       class="form-control" 
                                       required>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="billing_state">State/Province</label>
                                <input type="text" 
                                       id="billing_state" 
                                       name="billing_state" 
                                       class="form-control">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="billing_postcode">Postal Code *</label>
                                <input type="text" 
                                       id="billing_postcode" 
                                       name="billing_postcode" 
                                       class="form-control" 
                                       required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="billing_country">Country *</label>
                            <select id="billing_country" 
                                    name="billing_country" 
                                    class="form-control" 
                                    required>
                                <?php foreach ($countries as $code => $name): ?>
                                    <option value="<?php echo $this->e($code); ?>"
                                            <?php echo $code === 'US' ? 'selected' : ''; ?>>
                                        <?php echo $this->e($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Shipping Address -->
                    <div class="checkout-section">
                        <h2>Shipping Address</h2>
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" 
                                       id="same_as_billing" 
                                       name="same_as_billing" 
                                       checked>
                                <span class="checkmark"></span>
                                Same as billing address
                            </label>
                        </div>
                        
                        <div id="shipping-address" style="display: none;">
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="shipping_first_name">First Name</label>
                                    <input type="text" 
                                           id="shipping_first_name" 
                                           name="shipping_first_name" 
                                           class="form-control">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="shipping_last_name">Last Name</label>
                                    <input type="text" 
                                           id="shipping_last_name" 
                                           name="shipping_last_name" 
                                           class="form-control">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="shipping_company">Company (optional)</label>
                                <input type="text" 
                                       id="shipping_company" 
                                       name="shipping_company" 
                                       class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="shipping_address_1">Street Address</label>
                                <input type="text" 
                                       id="shipping_address_1" 
                                       name="shipping_address_1" 
                                       class="form-control" 
                                       placeholder="Street address">
                            </div>
                            <div class="form-group">
                                <input type="text" 
                                       id="shipping_address_2" 
                                       name="shipping_address_2" 
                                       class="form-control" 
                                       placeholder="Apartment, suite, etc. (optional)">
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="shipping_city">City</label>
                                    <input type="text" 
                                           id="shipping_city" 
                                           name="shipping_city" 
                                           class="form-control">
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="shipping_state">State/Province</label>
                                    <input type="text" 
                                           id="shipping_state" 
                                           name="shipping_state" 
                                           class="form-control">
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="shipping_postcode">Postal Code</label>
                                    <input type="text" 
                                           id="shipping_postcode" 
                                           name="shipping_postcode" 
                                           class="form-control">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="shipping_country">Country</label>
                                <select id="shipping_country" 
                                        name="shipping_country" 
                                        class="form-control">
                                    <?php foreach ($countries as $code => $name): ?>
                                        <option value="<?php echo $this->e($code); ?>"
                                                <?php echo $code === 'US' ? 'selected' : ''; ?>>
                                            <?php echo $this->e($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Shipping Method -->
                    <div class="checkout-section">
                        <h2>Shipping Method</h2>
                        <div class="shipping-methods">
                            <?php foreach ($shipping_methods as $method): ?>
                                <?php if ($method['enabled']): ?>
                                    <label class="shipping-method">
                                        <input type="radio" 
                                               name="shipping_method" 
                                               value="<?php echo $this->e($method['id']); ?>"
                                               data-cost="<?php echo $method['cost']; ?>"
                                               <?php echo $method['id'] === 'standard' ? 'checked' : ''; ?>>
                                        <div class="method-info">
                                            <div class="method-title"><?php echo $this->e($method['title']); ?></div>
                                            <div class="method-description"><?php echo $this->e($method['description']); ?></div>
                                        </div>
                                        <div class="method-cost">
                                            <?php echo $method['cost'] > 0 ? $this->money($method['cost']) : 'Free'; ?>
                                        </div>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="checkout-section">
                        <h2>Payment Method</h2>
                        <div class="payment-methods">
                            <?php foreach ($payment_methods as $method): ?>
                                <?php if ($method['enabled']): ?>
                                    <label class="payment-method">
                                        <input type="radio" 
                                               name="payment_method" 
                                               value="<?php echo $this->e($method['id']); ?>"
                                               <?php echo $method['id'] === 'card' ? 'checked' : ''; ?>>
                                        <div class="method-info">
                                            <div class="method-title">
                                                <i class="icon-<?php echo $this->e($method['icon']); ?>"></i>
                                                <?php echo $this->e($method['title']); ?>
                                            </div>
                                            <div class="method-description"><?php echo $this->e($method['description']); ?></div>
                                        </div>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>

                        <!-- Credit Card Details -->
                        <div id="card-details" class="payment-details">
                            <div class="form-group">
                                <label for="cardholder_name">Cardholder Name *</label>
                                <input type="text" 
                                       id="cardholder_name" 
                                       name="cardholder_name" 
                                       class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="card_number">Card Number *</label>
                                <input type="text" 
                                       id="card_number" 
                                       name="card_number" 
                                       class="form-control" 
                                       placeholder="1234 5678 9012 3456"
                                       maxlength="19">
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label for="expiry_month">Expiry Month *</label>
                                    <select id="expiry_month" 
                                            name="expiry_month" 
                                            class="form-control">
                                        <option value="">Month</option>
                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>">
                                                <?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="expiry_year">Expiry Year *</label>
                                    <select id="expiry_year" 
                                            name="expiry_year" 
                                            class="form-control">
                                        <option value="">Year</option>
                                        <?php for ($i = date('Y'); $i <= date('Y') + 15; $i++): ?>
                                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="cvv">CVV *</label>
                                    <input type="text" 
                                           id="cvv" 
                                           name="cvv" 
                                           class="form-control" 
                                           placeholder="123"
                                           maxlength="4">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Notes -->
                    <div class="checkout-section">
                        <h2>Order Notes</h2>
                        <div class="form-group">
                            <label for="order_notes">Special Instructions (optional)</label>
                            <textarea id="order_notes" 
                                      name="order_notes" 
                                      class="form-control" 
                                      rows="3" 
                                      placeholder="Notes about your order, e.g. special notes for delivery."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Order Summary Sidebar -->
                <div class="checkout-sidebar">
                    <div class="order-summary">
                        <h3>Order Summary</h3>
                        
                        <!-- Order Items -->
                        <div class="order-items">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="order-item">
                                    <div class="item-image">
                                        <?php if (!empty($item['image'])): ?>
                                            <img src="<?php echo $this->e($item['image']); ?>" 
                                                 alt="<?php echo $this->e($item['name']); ?>">
                                        <?php endif; ?>
                                        <span class="item-quantity"><?php echo $item['quantity']; ?></span>
                                    </div>
                                    <div class="item-details">
                                        <div class="item-name"><?php echo $this->e($item['name']); ?></div>
                                        <?php if (!empty($item['options'])): ?>
                                            <div class="item-options">
                                                <?php foreach ($item['options'] as $option => $value): ?>
                                                    <span class="option"><?php echo $this->e($option); ?>: <?php echo $this->e($value); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="item-total">
                                        <?php echo $this->money($item['price'] * $item['quantity']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Order Totals -->
                        <div class="order-totals">
                            <div class="total-line">
                                <span>Subtotal:</span>
                                <span id="subtotal-amount"><?php echo $this->money($cart_totals['subtotal'] ?? 0); ?></span>
                            </div>
                            <div class="total-line">
                                <span>Shipping:</span>
                                <span id="shipping-amount"><?php echo $this->money($cart_totals['shipping'] ?? 0); ?></span>
                            </div>
                            <?php if (($cart_totals['tax'] ?? 0) > 0): ?>
                                <div class="total-line">
                                    <span>Tax:</span>
                                    <span id="tax-amount"><?php echo $this->money($cart_totals['tax']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (($cart_totals['discount'] ?? 0) > 0): ?>
                                <div class="total-line discount">
                                    <span>Discount:</span>
                                    <span id="discount-amount">-<?php echo $this->money($cart_totals['discount']); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="total-line final-total">
                                <span>Total:</span>
                                <span id="final-total"><?php echo $this->money($cart_totals['total'] ?? 0); ?></span>
                            </div>
                        </div>
                        
                        <!-- Checkout Actions -->
                        <div class="checkout-actions">
                            <button type="submit" 
                                    class="btn btn-primary btn-lg btn-block" 
                                    id="place-order-btn">
                                <span class="btn-text">Complete Order</span>
                                <span class="btn-loading" style="display: none;">Processing...</span>
                            </button>
                            <a href="<?php echo $cart_url; ?>" class="btn btn-secondary btn-block">
                                Return to Cart
                            </a>
                        </div>
                        
                        <!-- Security Notice -->
                        <div class="security-notice">
                            <i class="icon-shield"></i>
                            <span>Your payment information is secure and encrypted</span>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php $this->do_action('checkout.after_content'); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('checkout-form');
    const placeOrderBtn = document.getElementById('place-order-btn');
    const btnText = placeOrderBtn.querySelector('.btn-text');
    const btnLoading = placeOrderBtn.querySelector('.btn-loading');
    
    // Auto-fill billing address in shipping
    function copyBillingToShipping() {
        const sameAsBilling = document.getElementById('same_as_billing');
        const shippingAddress = document.getElementById('shipping-address');
        
        if (sameAsBilling.checked) {
            shippingAddress.style.display = 'none';
        } else {
            shippingAddress.style.display = 'block';
        }
    }
    
    document.getElementById('same_as_billing').addEventListener('change', copyBillingToShipping);
    
    // Auto-fill billing from contact info
    function syncContactToBilling() {
        const firstName = document.getElementById('first_name').value;
        const lastName = document.getElementById('last_name').value;
        
        if (firstName && !document.getElementById('billing_first_name').value) {
            document.getElementById('billing_first_name').value = firstName;
        }
        if (lastName && !document.getElementById('billing_last_name').value) {
            document.getElementById('billing_last_name').value = lastName;
        }
    }
    
    document.getElementById('first_name').addEventListener('blur', syncContactToBilling);
    document.getElementById('last_name').addEventListener('blur', syncContactToBilling);
    
    // Payment method visibility
    function togglePaymentDetails() {
        const cardDetails = document.getElementById('card-details');
        const selectedPayment = document.querySelector('input[name="payment_method"]:checked');
        
        if (selectedPayment && selectedPayment.value === 'card') {
            cardDetails.style.display = 'block';
        } else {
            cardDetails.style.display = 'none';
        }
    }
    
    document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
        radio.addEventListener('change', togglePaymentDetails);
    });
    
    // Shipping cost calculation
    function updateShippingCost() {
        const selectedShipping = document.querySelector('input[name="shipping_method"]:checked');
        const shippingAmount = document.getElementById('shipping-amount');
        const finalTotal = document.getElementById('final-total');
        
        if (selectedShipping && shippingAmount) {
            const shippingCost = parseFloat(selectedShipping.dataset.cost) || 0;
            const subtotal = parseFloat('<?php echo $cart_totals['subtotal'] ?? 0; ?>');
            const tax = parseFloat('<?php echo $cart_totals['tax'] ?? 0; ?>');
            const discount = parseFloat('<?php echo $cart_totals['discount'] ?? 0; ?>');
            
            shippingAmount.textContent = shippingCost > 0 ? '$' + shippingCost.toFixed(2) : 'Free';
            
            const newTotal = subtotal + shippingCost + tax - discount;
            finalTotal.textContent = '$' + newTotal.toFixed(2);
        }
    }
    
    document.querySelectorAll('input[name="shipping_method"]').forEach(radio => {
        radio.addEventListener('change', updateShippingCost);
    });
    
    // Card number formatting
    document.getElementById('card_number').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
        let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
        if (formattedValue !== e.target.value) {
            e.target.value = formattedValue;
        }
    });
    
    // CVV validation
    document.getElementById('cvv').addEventListener('input', function(e) {
        e.target.value = e.target.value.replace(/[^0-9]/g, '');
    });
    
    // Form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Show loading state
        placeOrderBtn.disabled = true;
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline';
        
        // Submit form
        const formData = new FormData(form);
        
        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                } else {
                    showMessage(data.message, 'success');
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
            showMessage('An error occurred while processing your order. Please try again.', 'error');
        })
        .finally(() => {
            // Reset loading state
            placeOrderBtn.disabled = false;
            btnText.style.display = 'inline';
            btnLoading.style.display = 'none';
        });
    });
    
    function showMessage(message, type) {
        // Simple alert for now - could be improved with toast notifications
        if (type === 'error') {
            alert('Error: ' + message);
        } else {
            alert(message);
        }
    }
    
    function showValidationErrors(errors) {
        // Clear previous errors
        document.querySelectorAll('.field-error').forEach(el => el.remove());
        document.querySelectorAll('.form-control.error').forEach(el => el.classList.remove('error'));
        
        // Show new errors
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
    
    // Initialize
    copyBillingToShipping();
    togglePaymentDetails();
    updateShippingCost();
});
</script>
<?php $this->endSection(); ?>