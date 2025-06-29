<?php
/**
 * Stripe Payment Form Template
 * 
 * Available variables:
 * @var Order $order The current order
 */
?>

<div id="stripe-payment-form" class="payment-method-form">
    <div class="stripe-payment-header">
        <h3>Credit/Debit Card Payment</h3>
        <div class="stripe-badge">
            <img src="/plugins/payment-stripe/assets/images/powered-by-stripe.svg" alt="Powered by Stripe">
        </div>
    </div>

    <div class="stripe-payment-content">
        <!-- Card Element Container -->
        <div class="form-group">
            <label for="card-element">Card Details</label>
            <div id="card-element" class="stripe-card-element">
                <!-- Stripe Elements will be inserted here -->
            </div>
            <div id="card-errors" class="stripe-error" role="alert"></div>
        </div>

        <!-- Save Payment Method Checkbox -->
        <?php if ($order->customer && $order->customer->id): ?>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="save-payment-method" name="save_payment_method" value="1">
                    Save this card for future purchases
                </label>
            </div>
        <?php endif; ?>

        <!-- Billing Address (if not already collected) -->
        <div id="billing-address-form" style="display: none;">
            <h4>Billing Address</h4>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="billing-name">Full Name</label>
                    <input type="text" id="billing-name" name="billing_name" class="form-control" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="billing-email">Email</label>
                    <input type="email" id="billing-email" name="billing_email" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label for="billing-line1">Address</label>
                <input type="text" id="billing-line1" name="billing_line1" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="billing-line2">Address Line 2 (Optional)</label>
                <input type="text" id="billing-line2" name="billing_line2" class="form-control">
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="billing-city">City</label>
                    <input type="text" id="billing-city" name="billing_city" class="form-control" required>
                </div>
                <div class="form-group col-md-3">
                    <label for="billing-state">State/Province</label>
                    <input type="text" id="billing-state" name="billing_state" class="form-control">
                </div>
                <div class="form-group col-md-3">
                    <label for="billing-postal">Postal Code</label>
                    <input type="text" id="billing-postal" name="billing_postal" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label for="billing-country">Country</label>
                <select id="billing-country" name="billing_country" class="form-control" required>
                    <option value="">Select Country</option>
                    <option value="US">United States</option>
                    <option value="CA">Canada</option>
                    <option value="GB">United Kingdom</option>
                    <option value="DE">Germany</option>
                    <option value="FR">France</option>
                    <option value="IT">Italy</option>
                    <option value="ES">Spain</option>
                    <option value="AU">Australia</option>
                    <option value="JP">Japan</option>
                    <!-- Add more countries as needed -->
                </select>
            </div>
        </div>

        <!-- Payment Summary -->
        <div class="payment-summary">
            <div class="summary-row">
                <span>Order Total:</span>
                <span class="amount"><?php echo $order->currency . ' ' . number_format($order->total, 2); ?></span>
            </div>
        </div>

        <!-- Security Notice -->
        <div class="security-notice">
            <i class="icon-lock"></i>
            <span>Your payment information is encrypted and secure.</span>
        </div>
    </div>
</div>

<script>
// Store order data for JavaScript
window.stripeOrderData = {
    orderId: <?php echo json_encode($order->id); ?>,
    amount: <?php echo json_encode($order->total); ?>,
    currency: <?php echo json_encode(strtolower($order->currency)); ?>,
    customerEmail: <?php echo json_encode($order->customer->email ?? ''); ?>,
    returnUrl: <?php echo json_encode('/checkout/confirmation?order=' . $order->id); ?>
};
</script>