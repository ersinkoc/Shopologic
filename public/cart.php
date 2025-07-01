<?php
// Demo cart items
$cartItems = [
    [
        'id' => 1,
        'name' => 'Premium Laptop Pro',
        'price' => 1299.99,
        'quantity' => 1,
        'image' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=100&h=100&fit=crop'
    ],
    [
        'id' => 3,
        'name' => 'Smart Watch Elite',
        'price' => 349.99,
        'quantity' => 2,
        'image' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=100&h=100&fit=crop'
    ],
    [
        'id' => 6,
        'name' => 'Bluetooth Speaker',
        'price' => 79.99,
        'quantity' => 1,
        'image' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=100&h=100&fit=crop'
    ]
];

// Calculate totals
$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$shipping = $subtotal > 50 ? 0 : 9.99;
$tax = $subtotal * 0.08; // 8% tax
$total = $subtotal + $shipping + $tax;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Shopologic</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8f9fa; }
        
        /* Header */
        .header { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 1rem 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 1rem; }
        .header-content { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.8rem; font-weight: bold; color: #007bff; text-decoration: none; }
        .nav-links { display: flex; gap: 2rem; list-style: none; }
        .nav-links a { text-decoration: none; color: #495057; padding: 0.5rem 1rem; }
        
        /* Cart Section */
        .cart-section { padding: 3rem 0; }
        .page-title { font-size: 2rem; margin-bottom: 2rem; color: #343a40; }
        
        /* Cart Layout */
        .cart-layout { display: grid; grid-template-columns: 1fr 400px; gap: 2rem; }
        
        /* Cart Items */
        .cart-items { background: white; border-radius: 10px; padding: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .cart-table { width: 100%; }
        .cart-table th { text-align: left; padding: 1rem; border-bottom: 2px solid #dee2e6; color: #6c757d; font-weight: 600; }
        .cart-table td { padding: 1.5rem 1rem; border-bottom: 1px solid #f8f9fa; }
        
        /* Cart Item */
        .cart-item { display: flex; align-items: center; gap: 1rem; }
        .item-image { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; }
        .item-details h3 { font-size: 1.1rem; margin-bottom: 0.25rem; }
        .item-details p { color: #6c757d; font-size: 0.9rem; }
        
        /* Quantity Controls */
        .quantity-controls { display: flex; align-items: center; border: 1px solid #dee2e6; border-radius: 5px; }
        .quantity-btn { background: none; border: none; padding: 0.5rem 0.75rem; cursor: pointer; }
        .quantity-btn:hover { background: #f8f9fa; }
        .quantity-value { padding: 0.5rem; min-width: 40px; text-align: center; }
        
        /* Price */
        .item-price { font-weight: bold; font-size: 1.1rem; }
        .item-total { font-weight: bold; font-size: 1.1rem; color: #28a745; }
        
        /* Remove Button */
        .remove-btn { background: none; border: none; color: #dc3545; cursor: pointer; font-size: 1.2rem; padding: 0.5rem; }
        .remove-btn:hover { background: #fee; border-radius: 5px; }
        
        /* Order Summary */
        .order-summary { background: white; border-radius: 10px; padding: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); height: fit-content; position: sticky; top: 20px; }
        .summary-title { font-size: 1.3rem; margin-bottom: 1.5rem; color: #343a40; }
        .summary-row { display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid #f8f9fa; }
        .summary-row.total { border-top: 2px solid #dee2e6; margin-top: 1rem; font-size: 1.2rem; font-weight: bold; }
        .promo-section { margin: 1.5rem 0; }
        .promo-input { display: flex; gap: 0.5rem; }
        .promo-input input { flex: 1; padding: 0.75rem; border: 1px solid #dee2e6; border-radius: 5px; }
        .promo-input button { background: #6c757d; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 5px; cursor: pointer; }
        
        /* Checkout Button */
        .checkout-btn { width: 100%; background: #28a745; color: white; border: none; padding: 1rem; border-radius: 5px; font-size: 1.1rem; font-weight: bold; cursor: pointer; margin-top: 1.5rem; }
        .checkout-btn:hover { background: #218838; }
        .continue-shopping { display: block; text-align: center; margin-top: 1rem; color: #007bff; text-decoration: none; }
        
        /* Empty Cart */
        .empty-cart { text-align: center; padding: 4rem; }
        .empty-cart-icon { font-size: 4rem; margin-bottom: 1rem; }
        .empty-cart h2 { color: #6c757d; margin-bottom: 1rem; }
        .shop-now-btn { background: #007bff; color: white; padding: 1rem 2rem; border-radius: 5px; text-decoration: none; display: inline-block; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .cart-layout { grid-template-columns: 1fr; }
            .order-summary { position: static; }
            .cart-table { font-size: 0.9rem; }
            .item-image { width: 60px; height: 60px; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="/" class="logo">🛒 Shopologic</a>
                <nav>
                    <ul class="nav-links">
                        <li><a href="/">Home</a></li>
                        <li><a href="/category.php">Categories</a></li>
                        <li><a href="/cart.php" style="color: #007bff;">🛒 Cart (<?php echo count($cartItems); ?>)</a></li>
                        <li><a href="/checkout.php">Checkout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Cart Section -->
    <section class="cart-section">
        <div class="container">
            <h1 class="page-title">Shopping Cart</h1>
            
            <?php if (count($cartItems) > 0): ?>
            <div class="cart-layout">
                <!-- Cart Items -->
                <div class="cart-items">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cartItems as $index => $item): ?>
                            <tr>
                                <td>
                                    <div class="cart-item">
                                        <img src="<?php echo $item['image']; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="item-image">
                                        <div class="item-details">
                                            <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                            <p>SKU: #<?php echo str_pad($item['id'], 6, '0', STR_PAD_LEFT); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="item-price">$<?php echo number_format($item['price'], 2); ?></td>
                                <td>
                                    <div class="quantity-controls">
                                        <button class="quantity-btn" onclick="updateQuantity(<?php echo $index; ?>, -1)">−</button>
                                        <span class="quantity-value"><?php echo $item['quantity']; ?></span>
                                        <button class="quantity-btn" onclick="updateQuantity(<?php echo $index; ?>, 1)">+</button>
                                    </div>
                                </td>
                                <td class="item-total">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                <td>
                                    <button class="remove-btn" onclick="removeItem(<?php echo $index; ?>)">🗑️</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Order Summary -->
                <div class="order-summary">
                    <h2 class="summary-title">Order Summary</h2>
                    
                    <div class="summary-row">
                        <span>Subtotal (<?php echo count($cartItems); ?> items)</span>
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
                    
                    <div class="promo-section">
                        <div class="promo-input">
                            <input type="text" placeholder="Promo code">
                            <button>Apply</button>
                        </div>
                    </div>
                    
                    <div class="summary-row total">
                        <span>Total</span>
                        <span>$<?php echo number_format($total, 2); ?></span>
                    </div>
                    
                    <button class="checkout-btn" onclick="window.location.href='/checkout.php'">
                        Proceed to Checkout
                    </button>
                    
                    <a href="/" class="continue-shopping">← Continue Shopping</a>
                </div>
            </div>
            <?php else: ?>
            <!-- Empty Cart -->
            <div class="empty-cart">
                <div class="empty-cart-icon">🛒</div>
                <h2>Your cart is empty</h2>
                <p>Looks like you haven't added anything to your cart yet.</p>
                <a href="/" class="shop-now-btn">Start Shopping</a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <script>
        function updateQuantity(index, delta) {
            // In a real app, this would update the cart state
            alert('Quantity updated!');
            location.reload();
        }
        
        function removeItem(index) {
            if (confirm('Remove this item from cart?')) {
                alert('Item removed!');
                location.reload();
            }
        }
    </script>
</body>
</html>