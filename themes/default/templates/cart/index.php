<?php $this->layout('layouts/main'); ?>

<?php $this->section('title', ($title ?? 'Shopping Cart') . ' - Shopologic'); ?>

<?php $this->section('content'); ?>
<div class="cart-page">
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Shopping Cart</h1>
            <?php if (!empty($cart_items)): ?>
                <div class="cart-summary">
                    <span class="item-count"><?php echo count($cart_items); ?> item(s)</span>
                    <span class="total-amount">Total: <?php echo $this->money($cart_totals['total'] ?? 0); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($is_empty ?? true): ?>
            <!-- Empty Cart -->
            <div class="cart-empty">
                <div class="empty-cart-icon">
                    🛒
                </div>
                <h2>Your cart is empty</h2>
                <p>Looks like you haven't added any items to your cart yet.</p>
                <a href="<?php echo $this->url('products'); ?>" class="btn btn-primary btn-lg">
                    Continue Shopping
                </a>
            </div>
        <?php else: ?>
            <!-- Cart Items -->
            <div class="cart-content">
                <div class="cart-items">
                    <div class="cart-header">
                        <div class="col-product">Product</div>
                        <div class="col-price">Price</div>
                        <div class="col-quantity">Quantity</div>
                        <div class="col-total">Total</div>
                        <div class="col-remove"></div>
                    </div>

                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item" data-cart-key="<?php echo $this->e($item['cart_key']); ?>">
                            <div class="col-product">
                                <div class="product-info">
                                    <?php if (!empty($item['image'])): ?>
                                        <div class="product-image">
                                            <img src="<?php echo $this->e($item['image']); ?>" 
                                                 alt="<?php echo $this->e($item['name']); ?>"
                                                 loading="lazy">
                                        </div>
                                    <?php endif; ?>
                                    <div class="product-details">
                                        <h3 class="product-name">
                                            <a href="<?php echo $this->url('product/' . urlencode($item['slug'] ?? '')); ?>">
                                                <?php echo $this->e($item['name']); ?>
                                            </a>
                                        </h3>
                                        <?php if (!empty($item['options'])): ?>
                                            <div class="product-options">
                                                <?php foreach ($item['options'] as $option => $value): ?>
                                                    <span class="option">
                                                        <?php echo $this->e($option); ?>: <?php echo $this->e($value); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-price">
                                <span class="price"><?php echo $this->money($item['price']); ?></span>
                            </div>
                            
                            <div class="col-quantity">
                                <div class="quantity-controls">
                                    <button type="button" class="qty-btn qty-minus" data-action="decrease">-</button>
                                    <input type="number" 
                                           class="qty-input" 
                                           value="<?php echo $item['quantity']; ?>"
                                           min="1" 
                                           max="99"
                                           data-cart-key="<?php echo $this->e($item['cart_key']); ?>">
                                    <button type="button" class="qty-btn qty-plus" data-action="increase">+</button>
                                </div>
                            </div>
                            
                            <div class="col-total">
                                <span class="item-total">
                                    <?php echo $this->money($item['price'] * $item['quantity']); ?>
                                </span>
                            </div>
                            
                            <div class="col-remove">
                                <button type="button" 
                                        class="remove-item" 
                                        data-cart-key="<?php echo $this->e($item['cart_key']); ?>"
                                        title="Remove item">
                                    ×
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Cart Sidebar -->
                <div class="cart-sidebar">
                    <div class="cart-summary-box">
                        <h3>Order Summary</h3>
                        
                        <div class="summary-line">
                            <span>Subtotal:</span>
                            <span class="subtotal-amount"><?php echo $this->money($cart_totals['subtotal'] ?? 0); ?></span>
                        </div>
                        
                        <?php if (($cart_totals['shipping'] ?? 0) > 0): ?>
                            <div class="summary-line">
                                <span>Shipping:</span>
                                <span class="shipping-amount"><?php echo $this->money($cart_totals['shipping']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (($cart_totals['tax'] ?? 0) > 0): ?>
                            <div class="summary-line">
                                <span>Tax:</span>
                                <span class="tax-amount"><?php echo $this->money($cart_totals['tax']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (($cart_totals['discount'] ?? 0) > 0): ?>
                            <div class="summary-line discount">
                                <span>Discount:</span>
                                <span class="discount-amount">-<?php echo $this->money($cart_totals['discount']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="summary-line total">
                            <span>Total:</span>
                            <span class="total-amount"><?php echo $this->money($cart_totals['total'] ?? 0); ?></span>
                        </div>
                        
                        <div class="cart-actions">
                            <a href="<?php echo $this->url('checkout'); ?>" class="btn btn-primary btn-lg btn-block">
                                Proceed to Checkout
                            </a>
                            <a href="<?php echo $continue_shopping_url ?? $this->url('products'); ?>" class="btn btn-secondary btn-block">
                                Continue Shopping
                            </a>
                        </div>
                        
                        <div class="cart-extras">
                            <button type="button" class="btn btn-link btn-sm clear-cart">
                                Clear Cart
                            </button>
                        </div>
                    </div>
                    
                    <!-- Coupon Code -->
                    <div class="coupon-box">
                        <h4>Have a coupon?</h4>
                        <form class="coupon-form">
                            <div class="input-group">
                                <input type="text" 
                                       class="form-control" 
                                       placeholder="Enter coupon code"
                                       name="coupon_code">
                                <button type="submit" class="btn btn-outline-primary">
                                    Apply
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php $this->do_action('cart.after_content'); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cartItems = document.querySelectorAll('.cart-item');
    const removeButtons = document.querySelectorAll('.remove-item');
    const clearCartButton = document.querySelector('.clear-cart');
    const quantityInputs = document.querySelectorAll('.qty-input');
    const qtyButtons = document.querySelectorAll('.qty-btn');
    
    // Update cart item quantity
    function updateCartItem(cartKey, quantity) {
        fetch('/cart/update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                cart_key: cartKey,
                quantity: quantity
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (quantity === 0) {
                    // Remove item from DOM
                    const item = document.querySelector(`[data-cart-key="${cartKey}"]`);
                    if (item) {
                        item.remove();
                    }
                    
                    // Check if cart is empty
                    if (document.querySelectorAll('.cart-item').length === 0) {
                        location.reload(); // Reload to show empty cart
                    }
                } else {
                    // Update item total
                    const item = document.querySelector(`[data-cart-key="${cartKey}"]`);
                    const itemTotal = item.querySelector('.item-total');
                    const price = parseFloat(item.querySelector('.price').textContent.replace(/[^0-9.]/g, ''));
                    itemTotal.textContent = '$' + (price * quantity).toFixed(2);
                }
                
                // Update cart totals
                updateCartTotals(data.cart_totals);
                updateCartCount(data.cart_count);
                
                showMessage(data.message, 'success');
            } else {
                showMessage(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('An error occurred', 'error');
        });
    }
    
    // Remove cart item
    function removeCartItem(cartKey) {
        if (!confirm('Are you sure you want to remove this item?')) {
            return;
        }
        
        updateCartItem(cartKey, 0);
    }
    
    // Clear entire cart
    function clearCart() {
        if (!confirm('Are you sure you want to clear your cart?')) {
            return;
        }
        
        fetch('/cart/clear', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload(); // Reload to show empty cart
            } else {
                showMessage(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('An error occurred', 'error');
        });
    }
    
    // Update cart totals display
    function updateCartTotals(totals) {
        if (totals) {
            const subtotalEl = document.querySelector('.subtotal-amount');
            const totalEl = document.querySelector('.total-amount');
            
            if (subtotalEl) subtotalEl.textContent = '$' + totals.subtotal.toFixed(2);
            if (totalEl) totalEl.textContent = '$' + totals.total.toFixed(2);
        }
    }
    
    // Update cart count in header
    function updateCartCount(count) {
        const cartCounts = document.querySelectorAll('.cart-count, .count');
        cartCounts.forEach(el => {
            el.textContent = count;
        });
    }
    
    // Show message to user
    function showMessage(message, type = 'info') {
        // Simple alert for now - could be improved with toast notifications
        if (type === 'error') {
            alert('Error: ' + message);
        } else {
            // You could implement a toast notification system here
            console.log(message);
        }
    }
    
    // Event listeners
    quantityInputs.forEach(input => {
        input.addEventListener('change', function() {
            const cartKey = this.dataset.cartKey;
            const quantity = parseInt(this.value);
            
            if (quantity > 0) {
                updateCartItem(cartKey, quantity);
            }
        });
    });
    
    qtyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const input = this.parentNode.querySelector('.qty-input');
            const cartKey = input.dataset.cartKey;
            let quantity = parseInt(input.value);
            
            if (this.dataset.action === 'increase') {
                quantity++;
            } else if (this.dataset.action === 'decrease' && quantity > 1) {
                quantity--;
            }
            
            input.value = quantity;
            updateCartItem(cartKey, quantity);
        });
    });
    
    removeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const cartKey = this.dataset.cartKey;
            removeCartItem(cartKey);
        });
    });
    
    if (clearCartButton) {
        clearCartButton.addEventListener('click', clearCart);
    }
});
</script>
<?php $this->endSection(); ?>