<?php $this->layout('layouts/main'); ?>

<?php $this->section('title', ($title ?? 'Order Confirmation') . ' - Shopologic'); ?>

<?php $this->section('content'); ?>
<div class="checkout-success-page">
    <div class="container">
        <div class="success-header">
            <div class="success-icon">
                <i class="icon-check-circle"></i>
                ✓
            </div>
            <h1>Thank you for your order!</h1>
            <p class="success-message">
                Your order has been successfully placed and we've sent you a confirmation email.
            </p>
        </div>

        <div class="order-details">
            <div class="order-info">
                <h2>Order Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Order Number:</label>
                        <strong><?php echo $this->e($order['order_number']); ?></strong>
                    </div>
                    <div class="info-item">
                        <label>Order Date:</label>
                        <span><?php echo date('F j, Y', strtotime($order['created_at'])); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Order Status:</label>
                        <span class="status status-<?php echo $this->e($order['status']); ?>">
                            <?php echo ucfirst($this->e($order['status'])); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <label>Payment Status:</label>
                        <span class="status status-<?php echo $this->e($order['payment']['status']); ?>">
                            <?php echo ucfirst($this->e($order['payment']['status'])); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="customer-info">
                <h3>Customer Information</h3>
                <div class="info-section">
                    <div class="contact-info">
                        <h4>Contact Details</h4>
                        <p>
                            <?php echo $this->e($order['customer']['first_name'] . ' ' . $order['customer']['last_name']); ?><br>
                            <?php echo $this->e($order['customer']['email']); ?><br>
                            <?php if (!empty($order['customer']['phone'])): ?>
                                <?php echo $this->e($order['customer']['phone']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="info-section">
                    <div class="address-info">
                        <h4>Billing Address</h4>
                        <p>
                            <?php echo $this->e($order['billing_address']['first_name'] . ' ' . $order['billing_address']['last_name']); ?><br>
                            <?php if (!empty($order['billing_address']['company'])): ?>
                                <?php echo $this->e($order['billing_address']['company']); ?><br>
                            <?php endif; ?>
                            <?php echo $this->e($order['billing_address']['address_1']); ?><br>
                            <?php if (!empty($order['billing_address']['address_2'])): ?>
                                <?php echo $this->e($order['billing_address']['address_2']); ?><br>
                            <?php endif; ?>
                            <?php echo $this->e($order['billing_address']['city']); ?>, 
                            <?php echo $this->e($order['billing_address']['state']); ?> 
                            <?php echo $this->e($order['billing_address']['postcode']); ?><br>
                            <?php echo $this->e($order['billing_address']['country']); ?>
                        </p>
                    </div>

                    <div class="address-info">
                        <h4>Shipping Address</h4>
                        <p>
                            <?php echo $this->e($order['shipping_address']['first_name'] . ' ' . $order['shipping_address']['last_name']); ?><br>
                            <?php if (!empty($order['shipping_address']['company'])): ?>
                                <?php echo $this->e($order['shipping_address']['company']); ?><br>
                            <?php endif; ?>
                            <?php echo $this->e($order['shipping_address']['address_1']); ?><br>
                            <?php if (!empty($order['shipping_address']['address_2'])): ?>
                                <?php echo $this->e($order['shipping_address']['address_2']); ?><br>
                            <?php endif; ?>
                            <?php echo $this->e($order['shipping_address']['city']); ?>, 
                            <?php echo $this->e($order['shipping_address']['state']); ?> 
                            <?php echo $this->e($order['shipping_address']['postcode']); ?><br>
                            <?php echo $this->e($order['shipping_address']['country']); ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="order-items">
                <h3>Order Items</h3>
                <div class="items-table">
                    <div class="table-header">
                        <div class="col-product">Product</div>
                        <div class="col-quantity">Quantity</div>
                        <div class="col-price">Price</div>
                        <div class="col-total">Total</div>
                    </div>
                    
                    <?php foreach ($order['items'] as $item): ?>
                        <div class="table-row">
                            <div class="col-product">
                                <div class="item-name"><?php echo $this->e($item['name']); ?></div>
                                <?php if (!empty($item['options'])): ?>
                                    <div class="item-options">
                                        <?php foreach ($item['options'] as $option => $value): ?>
                                            <span class="option"><?php echo $this->e($option); ?>: <?php echo $this->e($value); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-quantity"><?php echo $item['quantity']; ?></div>
                            <div class="col-price"><?php echo $this->money($item['price']); ?></div>
                            <div class="col-total"><?php echo $this->money($item['total']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="order-totals">
                <h3>Order Summary</h3>
                <div class="totals-table">
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span><?php echo $this->money($order['totals']['subtotal']); ?></span>
                    </div>
                    <?php if ($order['totals']['shipping'] > 0): ?>
                        <div class="total-row">
                            <span>Shipping (<?php echo $this->e($order['shipping']['method']); ?>):</span>
                            <span><?php echo $this->money($order['totals']['shipping']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($order['totals']['tax'] > 0): ?>
                        <div class="total-row">
                            <span>Tax:</span>
                            <span><?php echo $this->money($order['totals']['tax']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($order['totals']['discount'] > 0): ?>
                        <div class="total-row discount">
                            <span>Discount:</span>
                            <span>-<?php echo $this->money($order['totals']['discount']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="total-row final-total">
                        <span>Total:</span>
                        <span><?php echo $this->money($order['totals']['total']); ?></span>
                    </div>
                </div>
            </div>

            <?php if (!empty($order['notes'])): ?>
                <div class="order-notes">
                    <h3>Order Notes</h3>
                    <p><?php echo $this->e($order['notes']); ?></p>
                </div>
            <?php endif; ?>

            <!-- Payment Information -->
            <div class="payment-info">
                <h3>Payment Information</h3>
                <div class="payment-details">
                    <div class="payment-method">
                        <label>Payment Method:</label>
                        <span><?php echo ucwords(str_replace('_', ' ', $this->e($order['payment']['method']))); ?></span>
                    </div>
                    
                    <?php if (!empty($order['payment']['transaction_id'])): ?>
                        <div class="transaction-id">
                            <label>Transaction ID:</label>
                            <span><?php echo $this->e($order['payment']['transaction_id']); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($order['payment']['method'] === 'bank_transfer' && !empty($order['payment']['gateway_response']['bank_details'])): ?>
                        <div class="bank-details">
                            <h4>Bank Transfer Details</h4>
                            <p><strong>Please transfer the payment to:</strong></p>
                            <ul>
                                <li><strong>Account Name:</strong> <?php echo $this->e($order['payment']['gateway_response']['bank_details']['account_name']); ?></li>
                                <li><strong>Account Number:</strong> <?php echo $this->e($order['payment']['gateway_response']['bank_details']['account_number']); ?></li>
                                <li><strong>Routing Number:</strong> <?php echo $this->e($order['payment']['gateway_response']['bank_details']['routing_number']); ?></li>
                                <li><strong>Reference:</strong> <?php echo $this->e($order['payment']['gateway_response']['bank_details']['reference']); ?></li>
                            </ul>
                            <p><em>Please include the reference number in your transfer.</em></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Next Steps -->
        <div class="next-steps">
            <h3>What happens next?</h3>
            <div class="steps-grid">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h4>Order Processing</h4>
                        <p>We'll process your order and prepare it for shipment within 1-2 business days.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h4>Shipping Notification</h4>
                        <p>You'll receive an email with tracking information once your order ships.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h4>Delivery</h4>
                        <p>Your order will be delivered to your specified address within the estimated timeframe.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="success-actions">
            <a href="<?php echo $continue_shopping_url; ?>" class="btn btn-primary btn-lg">
                Continue Shopping
            </a>
            <a href="#" onclick="window.print(); return false;" class="btn btn-secondary">
                Print Order Details
            </a>
        </div>

        <!-- Support -->
        <div class="support-info">
            <h3>Need Help?</h3>
            <p>
                If you have any questions about your order, please don't hesitate to 
                <a href="<?php echo $this->url('contact'); ?>">contact our customer support team</a>. 
                Have your order number ready: <strong><?php echo $this->e($order['order_number']); ?></strong>
            </p>
        </div>
    </div>
</div>

<?php $this->do_action('checkout.success.after_content', $order); ?>

<script>
// Track order completion for analytics
document.addEventListener('DOMContentLoaded', function() {
    // Fire order completion event for plugins/analytics
    const orderData = {
        order_id: '<?php echo $this->e($order['id']); ?>',
        order_number: '<?php echo $this->e($order['order_number']); ?>',
        total: <?php echo $order['totals']['total']; ?>,
        currency: 'USD',
        items: <?php echo json_encode($order['items']); ?>
    };
    
    // Dispatch custom event for tracking
    window.dispatchEvent(new CustomEvent('orderCompleted', {
        detail: orderData
    }));
    
    // Google Analytics tracking (if available)
    if (typeof gtag !== 'undefined') {
        gtag('event', 'purchase', {
            'transaction_id': orderData.order_number,
            'value': orderData.total,
            'currency': orderData.currency,
            'items': orderData.items.map(item => ({
                'item_id': item.product_id,
                'item_name': item.name,
                'quantity': item.quantity,
                'price': item.price
            }))
        });
    }
});
</script>
<?php $this->endSection(); ?>