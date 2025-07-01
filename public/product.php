<?php
// Get product ID from URL
$productId = isset($_GET['id']) ? intval($_GET['id']) : 1;

// Demo products data (same as index.php)
$products = [
    1 => [
        'id' => 1,
        'name' => 'Premium Laptop Pro',
        'price' => 1299.99,
        'original_price' => 1499.99,
        'images' => [
            'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=600&h=400&fit=crop',
            'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?w=600&h=400&fit=crop',
            'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?w=600&h=400&fit=crop'
        ],
        'rating' => 4.8,
        'reviews' => 127,
        'category' => 'Electronics',
        'badge' => 'Sale',
        'description' => 'High-performance laptop with Intel i7 processor and 16GB RAM',
        'long_description' => 'Experience unparalleled performance with our Premium Laptop Pro. Featuring the latest Intel Core i7 processor, 16GB of DDR4 RAM, and a lightning-fast 512GB NVMe SSD, this laptop is designed for professionals who demand the best. The stunning 15.6" Full HD display delivers crisp, vibrant visuals, while the backlit keyboard ensures comfortable typing in any lighting condition.',
        'features' => [
            'Intel Core i7-11800H Processor',
            '16GB DDR4 RAM (Expandable to 32GB)',
            '512GB NVMe SSD Storage',
            '15.6" Full HD IPS Display',
            'NVIDIA GeForce RTX 3050 Graphics',
            'Windows 11 Pro',
            'Backlit Keyboard',
            'Fingerprint Reader',
            'Wi-Fi 6 & Bluetooth 5.2',
            'Up to 10 hours battery life'
        ],
        'in_stock' => true,
        'stock_count' => 15
    ],
    2 => [
        'id' => 2,
        'name' => 'Wireless Headphones',
        'price' => 159.99,
        'original_price' => null,
        'images' => [
            'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&h=400&fit=crop',
            'https://images.unsplash.com/photo-1583394838336-acd977736f90?w=600&h=400&fit=crop',
            'https://images.unsplash.com/photo-1484704849700-f032a568e944?w=600&h=400&fit=crop'
        ],
        'rating' => 4.6,
        'reviews' => 89,
        'category' => 'Audio',
        'badge' => 'Popular',
        'description' => 'Noise-cancelling wireless headphones with 30-hour battery',
        'long_description' => 'Immerse yourself in pure audio bliss with our premium Wireless Headphones. Featuring advanced active noise cancellation technology, these headphones create your personal oasis of sound. With 30 hours of battery life, premium drivers, and exceptional comfort, they\'re perfect for long flights, commutes, or extended listening sessions.',
        'features' => [
            'Active Noise Cancellation (ANC)',
            '30-hour battery life',
            'Quick charge - 5 minutes for 2 hours',
            '40mm custom drivers',
            'Bluetooth 5.0 connectivity',
            'Multi-device pairing',
            'Foldable design with carrying case',
            'Built-in microphone for calls',
            'Touch controls',
            'Compatible with voice assistants'
        ],
        'in_stock' => true,
        'stock_count' => 42
    ]
];

// Get current product
$product = $products[$productId] ?? $products[1];

// Related products (excluding current)
$relatedProducts = array_filter($products, function($p) use ($productId) {
    return $p['id'] != $productId;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - Shopologic</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8f9fa; }
        
        /* Header (simplified) */
        .header { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 1rem 0; position: sticky; top: 0; z-index: 100; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 1rem; }
        .header-content { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.8rem; font-weight: bold; color: #007bff; text-decoration: none; }
        .nav-links { display: flex; gap: 2rem; list-style: none; }
        .nav-links a { text-decoration: none; color: #495057; padding: 0.5rem 1rem; }
        
        /* Breadcrumb */
        .breadcrumb { padding: 1rem 0; }
        .breadcrumb a { color: #6c757d; text-decoration: none; }
        .breadcrumb a:hover { color: #007bff; }
        
        /* Product Detail */
        .product-detail { background: white; padding: 2rem 0; margin-bottom: 3rem; }
        .product-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; }
        
        /* Image Gallery */
        .product-gallery { position: sticky; top: 100px; }
        .main-image { width: 100%; border-radius: 10px; margin-bottom: 1rem; }
        .thumbnail-list { display: flex; gap: 1rem; }
        .thumbnail { width: 100px; height: 80px; object-fit: cover; border-radius: 5px; cursor: pointer; border: 2px solid transparent; }
        .thumbnail.active { border-color: #007bff; }
        
        /* Product Info */
        .product-info h1 { font-size: 2rem; margin-bottom: 1rem; color: #343a40; }
        .product-meta { display: flex; align-items: center; gap: 2rem; margin-bottom: 1.5rem; }
        .rating { display: flex; align-items: center; gap: 0.5rem; }
        .stars { color: #ffc107; }
        .category-badge { background: #e9ecef; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.9rem; }
        .price-section { margin: 2rem 0; }
        .current-price { font-size: 2rem; font-weight: bold; color: #28a745; }
        .original-price { font-size: 1.5rem; color: #6c757d; text-decoration: line-through; margin-left: 1rem; }
        .savings { color: #dc3545; margin-left: 1rem; }
        
        /* Stock Status */
        .stock-status { margin: 1.5rem 0; }
        .in-stock { color: #28a745; font-weight: bold; }
        .out-stock { color: #dc3545; font-weight: bold; }
        
        /* Add to Cart Section */
        .cart-section { display: flex; align-items: center; gap: 1rem; margin: 2rem 0; }
        .quantity-selector { display: flex; align-items: center; border: 1px solid #dee2e6; border-radius: 5px; }
        .quantity-btn { background: none; border: none; padding: 0.75rem 1rem; cursor: pointer; font-size: 1.2rem; }
        .quantity-btn:hover { background: #f8f9fa; }
        .quantity-input { border: none; text-align: center; width: 60px; font-size: 1.1rem; }
        .add-to-cart-btn { flex: 1; background: #007bff; color: white; border: none; padding: 1rem 2rem; border-radius: 5px; font-size: 1.1rem; font-weight: bold; cursor: pointer; }
        .add-to-cart-btn:hover { background: #0056b3; }
        .buy-now-btn { background: #28a745; color: white; border: none; padding: 1rem 2rem; border-radius: 5px; font-size: 1.1rem; font-weight: bold; cursor: pointer; }
        
        /* Product Tabs */
        .product-tabs { margin-top: 3rem; }
        .tab-nav { display: flex; border-bottom: 2px solid #dee2e6; }
        .tab-btn { background: none; border: none; padding: 1rem 2rem; font-size: 1rem; cursor: pointer; position: relative; }
        .tab-btn.active { color: #007bff; }
        .tab-btn.active::after { content: ''; position: absolute; bottom: -2px; left: 0; right: 0; height: 2px; background: #007bff; }
        .tab-content { padding: 2rem 0; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
        
        /* Features List */
        .features-list { list-style: none; }
        .features-list li { padding: 0.5rem 0; padding-left: 1.5rem; position: relative; }
        .features-list li:before { content: '✓'; position: absolute; left: 0; color: #28a745; font-weight: bold; }
        
        /* Related Products */
        .related-products { padding: 3rem 0; }
        .section-title { font-size: 1.8rem; margin-bottom: 2rem; text-align: center; }
        .product-grid-small { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; }
        .product-card { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: transform 0.3s; }
        .product-card:hover { transform: translateY(-5px); }
        .product-card img { width: 100%; height: 200px; object-fit: cover; }
        .product-card-body { padding: 1rem; }
        .product-card-title { font-weight: bold; margin-bottom: 0.5rem; }
        .product-card-price { color: #28a745; font-weight: bold; font-size: 1.2rem; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .product-grid { grid-template-columns: 1fr; }
            .product-gallery { position: static; }
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
                        <li><a href="/cart.php">🛒 Cart</a></li>
                        <li><a href="/admin.php">Admin</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Breadcrumb -->
    <div class="container">
        <div class="breadcrumb">
            <a href="/">Home</a> / 
            <a href="/category.php?cat=<?php echo urlencode($product['category']); ?>"><?php echo htmlspecialchars($product['category']); ?></a> / 
            <span><?php echo htmlspecialchars($product['name']); ?></span>
        </div>
    </div>

    <!-- Product Detail -->
    <section class="product-detail">
        <div class="container">
            <div class="product-grid">
                <!-- Image Gallery -->
                <div class="product-gallery">
                    <img src="<?php echo $product['images'][0]; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="main-image" id="mainImage">
                    <div class="thumbnail-list">
                        <?php foreach ($product['images'] as $index => $image): ?>
                        <img src="<?php echo $image; ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?> - Image <?php echo $index + 1; ?>" 
                             class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>"
                             onclick="changeImage(this)">
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Product Info -->
                <div class="product-info">
                    <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                    
                    <div class="product-meta">
                        <div class="rating">
                            <div class="stars">
                                <?php 
                                $fullStars = floor($product['rating']);
                                for ($i = 0; $i < $fullStars; $i++) echo '⭐';
                                ?>
                            </div>
                            <span><?php echo $product['rating']; ?> (<?php echo $product['reviews']; ?> reviews)</span>
                        </div>
                        <div class="category-badge"><?php echo htmlspecialchars($product['category']); ?></div>
                    </div>

                    <p style="font-size: 1.1rem; color: #6c757d; margin-bottom: 2rem;">
                        <?php echo htmlspecialchars($product['long_description']); ?>
                    </p>

                    <div class="price-section">
                        <span class="current-price">$<?php echo number_format($product['price'], 2); ?></span>
                        <?php if ($product['original_price']): ?>
                            <span class="original-price">$<?php echo number_format($product['original_price'], 2); ?></span>
                            <span class="savings">Save $<?php echo number_format($product['original_price'] - $product['price'], 2); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="stock-status">
                        <?php if ($product['in_stock']): ?>
                            <span class="in-stock">✓ In Stock</span> (<?php echo $product['stock_count']; ?> available)
                        <?php else: ?>
                            <span class="out-stock">✗ Out of Stock</span>
                        <?php endif; ?>
                    </div>

                    <div class="cart-section">
                        <div class="quantity-selector">
                            <button class="quantity-btn" onclick="changeQuantity(-1)">-</button>
                            <input type="number" class="quantity-input" id="quantity" value="1" min="1" max="<?php echo $product['stock_count']; ?>">
                            <button class="quantity-btn" onclick="changeQuantity(1)">+</button>
                        </div>
                        <button class="add-to-cart-btn" onclick="addToCart()">
                            Add to Cart
                        </button>
                        <button class="buy-now-btn" onclick="buyNow()">
                            Buy Now
                        </button>
                    </div>

                    <!-- Product Tabs -->
                    <div class="product-tabs">
                        <div class="tab-nav">
                            <button class="tab-btn active" onclick="switchTab('features')">Features</button>
                            <button class="tab-btn" onclick="switchTab('specifications')">Specifications</button>
                            <button class="tab-btn" onclick="switchTab('reviews')">Reviews</button>
                            <button class="tab-btn" onclick="switchTab('shipping')">Shipping</button>
                        </div>
                        
                        <div class="tab-content">
                            <div class="tab-pane active" id="features">
                                <h3>Key Features</h3>
                                <ul class="features-list">
                                    <?php foreach ($product['features'] as $feature): ?>
                                    <li><?php echo htmlspecialchars($feature); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            
                            <div class="tab-pane" id="specifications">
                                <h3>Technical Specifications</h3>
                                <p>Detailed technical specifications will be displayed here.</p>
                            </div>
                            
                            <div class="tab-pane" id="reviews">
                                <h3>Customer Reviews</h3>
                                <p><?php echo $product['reviews']; ?> customers have reviewed this product.</p>
                                <p>Average rating: <?php echo $product['rating']; ?>/5</p>
                            </div>
                            
                            <div class="tab-pane" id="shipping">
                                <h3>Shipping Information</h3>
                                <ul class="features-list">
                                    <li>Free shipping on orders over $50</li>
                                    <li>Express shipping available</li>
                                    <li>Ships within 24 hours</li>
                                    <li>30-day return policy</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Related Products -->
    <section class="related-products">
        <div class="container">
            <h2 class="section-title">Related Products</h2>
            <div class="product-grid-small">
                <?php foreach (array_slice($relatedProducts, 0, 3) as $related): ?>
                <a href="/product.php?id=<?php echo $related['id']; ?>" style="text-decoration: none; color: inherit;">
                    <div class="product-card">
                        <img src="<?php echo $related['images'][0]; ?>" alt="<?php echo htmlspecialchars($related['name']); ?>">
                        <div class="product-card-body">
                            <div class="product-card-title"><?php echo htmlspecialchars($related['name']); ?></div>
                            <div class="product-card-price">$<?php echo number_format($related['price'], 2); ?></div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <script>
        // Change main image
        function changeImage(thumbnail) {
            document.getElementById('mainImage').src = thumbnail.src;
            document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
            thumbnail.classList.add('active');
        }

        // Quantity controls
        function changeQuantity(delta) {
            const input = document.getElementById('quantity');
            const newValue = parseInt(input.value) + delta;
            if (newValue >= 1 && newValue <= <?php echo $product['stock_count']; ?>) {
                input.value = newValue;
            }
        }

        // Add to cart
        function addToCart() {
            const quantity = document.getElementById('quantity').value;
            alert(`Added ${quantity} x <?php echo addslashes($product['name']); ?> to cart!`);
            // In real app, this would update cart state
        }

        // Buy now
        function buyNow() {
            window.location.href = '/checkout.php?product=<?php echo $product['id']; ?>&quantity=' + document.getElementById('quantity').value;
        }

        // Tab switching
        function switchTab(tabName) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(tabName).classList.add('active');
        }
    </script>
</body>
</html>