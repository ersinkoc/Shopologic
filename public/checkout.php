<?php
// Get cart data from session or URL parameters
$cartItems = [
    [
        'id' => 1,
        'name' => 'Premium Laptop Pro',
        'price' => 1299.99,
        'quantity' => 1,
        'image' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=100&h=100&fit=crop'
    ]
];

// Calculate totals
$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$shipping = $subtotal > 50 ? 0 : 9.99;
$tax = $subtotal * 0.08;
$total = $subtotal + $shipping + $tax;

// Countries for shipping
$countries = ['United States', 'Canada', 'United Kingdom', 'Germany', 'France', 'Spain', 'Italy', 'Australia', 'Japan'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Shopologic</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8f9fa; }
        
        /* Header */
        .header { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 1rem 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 1rem; }
        .header-content { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.8rem; font-weight: bold; color: #007bff; text-decoration: none; }
        
        /* Progress Bar */
        .progress-bar { background: white; padding: 2rem 0; border-bottom: 1px solid #dee2e6; }
        .progress-steps { display: flex; justify-content: center; gap: 3rem; }
        .step { display: flex; align-items: center; gap: 0.5rem; color: #6c757d; }
        .step.active { color: #007bff; }
        .step.completed { color: #28a745; }
        .step-icon { width: 30px; height: 30px; border-radius: 50%; border: 2px solid currentColor; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .step.completed .step-icon { background: #28a745; color: white; border-color: #28a745; }
        .step.active .step-icon { background: #007bff; color: white; border-color: #007bff; }
        
        /* Checkout Layout */
        .checkout-layout { display: grid; grid-template-columns: 1fr 400px; gap: 2rem; margin: 2rem 0; }
        
        /* Forms */
        .checkout-forms { background: white; border-radius: 10px; padding: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-section { margin-bottom: 2rem; }
        .form-section h2 { font-size: 1.5rem; margin-bottom: 1.5rem; color: #343a40; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: #495057; font-weight: 500; }
        .form-group input, .form-group select { width: 100%; padding: 0.75rem; border: 1px solid #ced4da; border-radius: 5px; font-size: 1rem; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #007bff; }
        
        /* Payment Methods */
        .payment-methods { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .payment-method { border: 2px solid #dee2e6; border-radius: 8px; padding: 1rem; text-align: center; cursor: pointer; transition: all 0.3s; }
        .payment-method:hover { border-color: #007bff; }
        .payment-method.selected { border-color: #007bff; background: #e7f3ff; }
        .payment-method input[type="radio"] { display: none; }
        .payment-icon { font-size: 2rem; margin-bottom: 0.5rem; }
        
        /* Order Summary */
        .order-summary { background: white; border-radius: 10px; padding: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); height: fit-content; position: sticky; top: 20px; }
        .summary-title { font-size: 1.5rem; margin-bottom: 1.5rem; color: #343a40; }
        .order-item { display: flex; gap: 1rem; padding: 1rem 0; border-bottom: 1px solid #f8f9fa; }
        .item-image { width: 60px; height: 60px; object-fit: cover; border-radius: 5px; }
        .item-details { flex: 1; }
        .item-name { font-weight: 600; margin-bottom: 0.25rem; }
        .item-quantity { color: #6c757d; font-size: 0.9rem; }
        .summary-row { display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid #f8f9fa; }
        .summary-row.total { border-top: 2px solid #dee2e6; margin-top: 1rem; font-size: 1.2rem; font-weight: bold; }
        
        /* Buttons */
        .button-group { display: flex; justify-content: space-between; margin-top: 2rem; }
        .btn { padding: 1rem 2rem; border: none; border-radius: 5px; font-size: 1rem; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-primary { background: #28a745; color: white; }
        .btn:hover { opacity: 0.9; }
        
        /* Security Badge */
        .security-badge { background: #e8f5e9; border: 1px solid #4caf50; border-radius: 5px; padding: 1rem; margin-top: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .security-badge .icon { color: #4caf50; font-size: 1.2rem; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .checkout-layout { grid-template-columns: 1fr; }
            .order-summary { position: static; }
            .form-row { grid-template-columns: 1fr; }
            .payment-methods { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="/" class="logo">🛒 Shopologic</a>
                <div style="color: #6c757d;">
                    Secure Checkout 🔒
                </div>
            </div>
        </div>
    </header>

    <!-- Progress Bar -->
    <div class="progress-bar">
        <div class="container">
            <div class="progress-steps">
                <div class="step completed">
                    <div class="step-icon">✓</div>
                    <span>Cart</span>
                </div>
                <div class="step active">
                    <div class="step-icon">2</div>
                    <span>Checkout</span>
                </div>
                <div class="step">
                    <div class="step-icon">3</div>
                    <span>Confirmation</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Checkout Content -->
    <div class="container">
        <div class="checkout-layout">
            <!-- Checkout Forms -->
            <div class="checkout-forms">
                <form id="checkout-form">
                    <!-- Shipping Information -->
                    <div class="form-section">
                        <h2>Shipping Information</h2>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first-name">First Name *</label>
                                <input type="text" id="first-name" name="first_name" required>
                            </div>
                            <div class="form-group">
                                <label for="last-name">Last Name *</label>
                                <input type="text" id="last-name" name="last_name" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone">
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Street Address *</label>
                            <input type="text" id="address" name="address" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">City *</label>
                                <input type="text" id="city" name="city" required>
                            </div>
                            <div class="form-group">
                                <label for="state">State/Province *</label>
                                <input type="text" id="state" name="state" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="zip">ZIP/Postal Code *</label>
                                <input type="text" id="zip" name="zip" required>
                            </div>
                            <div class="form-group">
                                <label for="country">Country *</label>
                                <select id="country" name="country" required>
                                    <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo $country; ?>"><?php echo $country; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Information -->
                    <div class="form-section">
                        <h2>Payment Method</h2>
                        
                        <div class="payment-methods">
                            <label class="payment-method selected">
                                <input type="radio" name="payment_method" value="card" checked>
                                <div class="payment-icon">💳</div>
                                <div>Credit Card</div>
                            </label>
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="paypal">
                                <div class="payment-icon">🅿️</div>
                                <div>PayPal</div>
                            </label>
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="apple">
                                <div class="payment-icon">🍎</div>
                                <div>Apple Pay</div>
                            </label>
                        </div>
                        
                        <div id="card-fields">
                            <div class="form-group">
                                <label for="card-number">Card Number *</label>
                                <input type="text" id="card-number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19">
                            </div>
                            
                            <div class="form-group">
                                <label for="card-name">Name on Card *</label>
                                <input type="text" id="card-name" name="card_name">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="expiry">Expiry Date *</label>
                                    <input type="text" id="expiry" name="expiry" placeholder="MM/YY" maxlength="5">
                                </div>
                                <div class="form-group">
                                    <label for="cvv">CVV *</label>
                                    <input type="text" id="cvv" name="cvv" placeholder="123" maxlength="4">
                                </div>
                            </div>
                        </div>
                        
                        <div class="security-badge">
                            <span class="icon">🔒</span>
                            <span>Your payment information is encrypted and secure</span>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="button-group">
                        <a href="/cart.php" class="btn btn-secondary">
                            ← Back to Cart
                        </a>
                        <button type="submit" class="btn btn-primary">
                            Complete Order →
                        </button>
                    </div>
                </form>
            </div>

            <!-- Order Summary -->
            <div class="order-summary">
                <h2 class="summary-title">Order Summary</h2>
                
                <!-- Items -->
                <?php foreach ($cartItems as $item): ?>
                <div class="order-item">
                    <img src="<?php echo $item['image']; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="item-image">
                    <div class="item-details">
                        <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                        <div class="item-quantity">Qty: <?php echo $item['quantity']; ?></div>
                    </div>
                    <div>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                </div>
                <?php endforeach; ?>
                
                <!-- Totals -->
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>$<?php echo number_format($subtotal, 2); ?></span>
                </div>
                
                <div class="summary-row">
                    <span>Shipping</span>
                    <span><?php echo $shipping > 0 ? '$' . number_format($shipping, 2) : 'FREE'; ?></span>
                </div>
                
                <div class="summary-row">
                    <span>Tax</span>
                    <span>$<?php echo number_format($tax, 2); ?></span>
                </div>
                
                <div class="summary-row total">
                    <span>Total</span>
                    <span>$<?php echo number_format($total, 2); ?></span>
                </div>
                
                <div style="margin-top: 1.5rem; padding: 1rem; background: #f8f9fa; border-radius: 5px;">
                    <div style="font-weight: bold; margin-bottom: 0.5rem;">Order Protection</div>
                    <div style="font-size: 0.9rem; color: #6c757d;">
                        ✓ Secure SSL encryption<br>
                        ✓ 30-day return policy<br>
                        ✓ Order tracking available
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Payment method selection
        document.querySelectorAll('.payment-method').forEach(method => {
            method.addEventListener('click', function() {
                document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('selected'));
                this.classList.add('selected');
                
                // Show/hide card fields
                const cardFields = document.getElementById('card-fields');
                if (this.querySelector('input').value === 'card') {
                    cardFields.style.display = 'block';
                } else {
                    cardFields.style.display = 'none';
                }
            });
        });

        // Form formatting
        document.getElementById('card-number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formattedValue;
        });

        document.getElementById('expiry').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.slice(0, 2) + '/' + value.slice(2, 4);
            }
            e.target.value = value;
        });

        // Form submission
        document.getElementById('checkout-form').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Order placed successfully! Redirecting to confirmation page...');
            window.location.href = '/order-confirmation.php';
        });
    </script>
</body>
</html>