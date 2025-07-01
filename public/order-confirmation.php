<?php
// Demo order data
$order = [
    'id' => 'SH-2025-0630-' . rand(1000, 9999),
    'date' => date('F d, Y'),
    'time' => date('g:i A'),
    'email' => 'john.doe@example.com',
    'items' => [
        [
            'name' => 'Premium Laptop Pro',
            'quantity' => 1,
            'price' => 1299.99,
            'image' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=80&h=80&fit=crop'
        ]
    ],
    'subtotal' => 1299.99,
    'shipping' => 0,
    'tax' => 103.99,
    'total' => 1403.98,
    'shipping_address' => [
        'name' => 'John Doe',
        'address' => '123 Main Street, Apt 4B',
        'city' => 'New York, NY 10001',
        'phone' => '(555) 123-4567'
    ],
    'payment_method' => 'Credit Card ending in 4242',
    'estimated_delivery' => date('F d-', strtotime('+3 days')) . date('d, Y', strtotime('+5 days'))
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - Shopologic</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8f9fa; }
        
        /* Header */
        .header { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 1rem 0; }
        .container { max-width: 800px; margin: 0 auto; padding: 0 1rem; }
        .header-content { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.8rem; font-weight: bold; color: #007bff; text-decoration: none; }
        
        /* Success Message */
        .success-section { background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 3rem 0; text-align: center; }
        .success-icon { font-size: 4rem; margin-bottom: 1rem; animation: checkmark 0.6s ease-in-out; }
        .success-title { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .order-number { font-size: 1.2rem; opacity: 0.9; }
        
        @keyframes checkmark {
            0% { transform: scale(0) rotate(0deg); }
            50% { transform: scale(1.2) rotate(360deg); }
            100% { transform: scale(1) rotate(360deg); }
        }
        
        /* Order Details */
        .order-details { background: white; border-radius: 10px; padding: 2rem; margin: 2rem auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .section-title { font-size: 1.5rem; margin-bottom: 1.5rem; color: #343a40; border-bottom: 2px solid #dee2e6; padding-bottom: 0.5rem; }
        
        /* Info Grid */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem; }
        .info-block { background: #f8f9fa; padding: 1.5rem; border-radius: 8px; }
        .info-label { color: #6c757d; font-size: 0.9rem; margin-bottom: 0.5rem; }
        .info-value { font-weight: 600; color: #343a40; }
        
        /* Items Table */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; }
        .items-table th { text-align: left; padding: 1rem; border-bottom: 2px solid #dee2e6; color: #6c757d; }
        .items-table td { padding: 1rem; border-bottom: 1px solid #f8f9fa; }
        .item-info { display: flex; align-items: center; gap: 1rem; }
        .item-image { width: 60px; height: 60px; object-fit: cover; border-radius: 5px; }
        
        /* Summary */
        .order-summary { background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; }
        .summary-row { display: flex; justify-content: space-between; padding: 0.5rem 0; }
        .summary-row.total { border-top: 2px solid #dee2e6; margin-top: 0.5rem; padding-top: 1rem; font-size: 1.1rem; font-weight: bold; }
        
        /* Timeline */
        .timeline { display: flex; justify-content: space-between; margin: 2rem 0; position: relative; }
        .timeline::before { content: ''; position: absolute; top: 20px; left: 10%; right: 10%; height: 2px; background: #dee2e6; }
        .timeline-step { text-align: center; position: relative; z-index: 1; flex: 1; }
        .step-icon { width: 40px; height: 40px; background: white; border: 2px solid #dee2e6; border-radius: 50%; margin: 0 auto 0.5rem; display: flex; align-items: center; justify-content: center; }
        .timeline-step.active .step-icon { background: #28a745; border-color: #28a745; color: white; }
        .timeline-step.completed .step-icon { background: #28a745; border-color: #28a745; color: white; }
        .step-label { font-size: 0.9rem; color: #6c757d; }
        
        /* Actions */
        .actions { display: flex; gap: 1rem; justify-content: center; margin-top: 2rem; flex-wrap: wrap; }
        .btn { padding: 1rem 2rem; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-outline { background: white; border: 2px solid #dee2e6; color: #495057; }
        
        /* Help Section */
        .help-section { background: #e3f2fd; border-radius: 10px; padding: 2rem; margin-top: 2rem; text-align: center; }
        .help-title { font-size: 1.3rem; margin-bottom: 1rem; color: #1976d2; }
        .help-links { display: flex; gap: 2rem; justify-content: center; flex-wrap: wrap; }
        .help-link { color: #1976d2; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; }
        .help-link:hover { text-decoration: underline; }
        
        /* Print Styles */
        @media print {
            .header, .actions, .help-section { display: none; }
            .success-section { background: white; color: black; }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .info-grid { grid-template-columns: 1fr; }
            .timeline { flex-direction: column; gap: 1rem; }
            .timeline::before { display: none; }
            .actions { flex-direction: column; }
            .help-links { flex-direction: column; gap: 1rem; }
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
                    <?php echo $order['date']; ?> at <?php echo $order['time']; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Success Message -->
    <section class="success-section">
        <div class="container">
            <div class="success-icon">✅</div>
            <h1 class="success-title">Order Confirmed!</h1>
            <p class="order-number">Order #<?php echo $order['id']; ?></p>
        </div>
    </section>

    <!-- Order Details -->
    <div class="container">
        <div class="order-details">
            <h2 class="section-title">Thank you for your order!</h2>
            <p style="margin-bottom: 2rem; color: #6c757d;">
                We've sent a confirmation email to <strong><?php echo htmlspecialchars($order['email']); ?></strong> with your order details and tracking information.
            </p>

            <!-- Order Timeline -->
            <div class="timeline">
                <div class="timeline-step completed">
                    <div class="step-icon">✓</div>
                    <div class="step-label">Order Placed</div>
                </div>
                <div class="timeline-step active">
                    <div class="step-icon">📦</div>
                    <div class="step-label">Processing</div>
                </div>
                <div class="timeline-step">
                    <div class="step-icon">🚚</div>
                    <div class="step-label">Shipped</div>
                </div>
                <div class="timeline-step">
                    <div class="step-icon">📍</div>
                    <div class="step-label">Delivered</div>
                </div>
            </div>

            <!-- Info Grid -->
            <div class="info-grid">
                <div class="info-block">
                    <div class="info-label">Shipping Address</div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($order['shipping_address']['name']); ?><br>
                        <?php echo htmlspecialchars($order['shipping_address']['address']); ?><br>
                        <?php echo htmlspecialchars($order['shipping_address']['city']); ?><br>
                        <?php echo htmlspecialchars($order['shipping_address']['phone']); ?>
                    </div>
                </div>
                <div class="info-block">
                    <div class="info-label">Payment Method</div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($order['payment_method']); ?><br><br>
                        <div class="info-label">Estimated Delivery</div>
                        <div class="info-value"><?php echo $order['estimated_delivery']; ?></div>
                    </div>
                </div>
            </div>

            <!-- Items -->
            <h3 style="margin: 2rem 0 1rem;">Order Items</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th style="text-align: center;">Quantity</th>
                        <th style="text-align: right;">Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order['items'] as $item): ?>
                    <tr>
                        <td>
                            <div class="item-info">
                                <img src="<?php echo $item['image']; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="item-image">
                                <div><?php echo htmlspecialchars($item['name']); ?></div>
                            </div>
                        </td>
                        <td style="text-align: center;"><?php echo $item['quantity']; ?></td>
                        <td style="text-align: right;">$<?php echo number_format($item['price'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Order Summary -->
            <div class="order-summary">
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>$<?php echo number_format($order['subtotal'], 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Shipping</span>
                    <span><?php echo $order['shipping'] > 0 ? '$' . number_format($order['shipping'], 2) : 'FREE'; ?></span>
                </div>
                <div class="summary-row">
                    <span>Tax</span>
                    <span>$<?php echo number_format($order['tax'], 2); ?></span>
                </div>
                <div class="summary-row total">
                    <span>Total</span>
                    <span>$<?php echo number_format($order['total'], 2); ?></span>
                </div>
            </div>

            <!-- Actions -->
            <div class="actions">
                <button class="btn btn-outline" onclick="window.print()">
                    🖨️ Print Order
                </button>
                <a href="/orders.php" class="btn btn-secondary">
                    📋 View All Orders
                </a>
                <a href="/" class="btn btn-primary">
                    Continue Shopping
                </a>
            </div>
        </div>

        <!-- Help Section -->
        <div class="help-section">
            <h3 class="help-title">Need Help?</h3>
            <p style="margin-bottom: 1.5rem; color: #6c757d;">
                Our customer service team is here to assist you
            </p>
            <div class="help-links">
                <a href="#" class="help-link">
                    📞 Contact Support
                </a>
                <a href="#" class="help-link">
                    📦 Track Your Order
                </a>
                <a href="#" class="help-link">
                    ↩️ Return Policy
                </a>
                <a href="#" class="help-link">
                    ❓ FAQs
                </a>
            </div>
        </div>
    </div>

    <script>
        // Confetti animation
        function createConfetti() {
            const colors = ['#28a745', '#007bff', '#ffc107', '#dc3545'];
            const confettiCount = 50;
            
            for (let i = 0; i < confettiCount; i++) {
                const confetti = document.createElement('div');
                confetti.style.cssText = `
                    position: fixed;
                    width: 10px;
                    height: 10px;
                    background: ${colors[Math.floor(Math.random() * colors.length)]};
                    left: ${Math.random() * 100}%;
                    top: -10px;
                    opacity: ${Math.random()};
                    transform: rotate(${Math.random() * 360}deg);
                    animation: fall ${3 + Math.random() * 2}s linear;
                `;
                document.body.appendChild(confetti);
                
                setTimeout(() => confetti.remove(), 5000);
            }
        }

        // CSS for confetti animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fall {
                to {
                    transform: translateY(100vh) rotate(720deg);
                }
            }
        `;
        document.head.appendChild(style);

        // Trigger confetti on load
        window.addEventListener('load', createConfetti);
    </script>
</body>
</html>