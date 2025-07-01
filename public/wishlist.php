<?php
// Demo wishlist items
$wishlistItems = [
    [
        'id' => 2,
        'name' => 'Wireless Headphones',
        'price' => 159.99,
        'image' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=300&h=200&fit=crop',
        'rating' => 4.6,
        'reviews' => 89,
        'in_stock' => true,
        'added_date' => '2025-06-15'
    ],
    [
        'id' => 5,
        'name' => 'Professional Camera',
        'price' => 899.99,
        'image' => 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=300&h=200&fit=crop',
        'rating' => 4.8,
        'reviews' => 78,
        'in_stock' => true,
        'added_date' => '2025-06-10'
    ],
    [
        'id' => 9,
        'name' => 'Smartphone Pro Max',
        'price' => 999.99,
        'original_price' => 1199.99,
        'image' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=300&h=200&fit=crop',
        'rating' => 4.8,
        'reviews' => 567,
        'in_stock' => false,
        'added_date' => '2025-06-01'
    ],
    [
        'id' => 11,
        'name' => 'Fitness Tracker',
        'price' => 99.99,
        'image' => 'https://images.unsplash.com/photo-1576243345690-4e4b79b63288?w=300&h=200&fit=crop',
        'rating' => 4.5,
        'reviews' => 445,
        'in_stock' => true,
        'added_date' => '2025-05-28'
    ]
];

// Calculate savings
$totalSavings = 0;
foreach ($wishlistItems as $item) {
    if (isset($item['original_price'])) {
        $totalSavings += ($item['original_price'] - $item['price']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist - Shopologic</title>
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
        
        /* Wishlist Header */
        .wishlist-header { background: linear-gradient(135deg, #e91e63, #c2185b); color: white; padding: 3rem 0; text-align: center; }
        .wishlist-header h1 { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .wishlist-header p { font-size: 1.1rem; opacity: 0.9; }
        
        /* Stats Bar */
        .stats-bar { background: white; padding: 1.5rem 0; border-bottom: 1px solid #dee2e6; margin-bottom: 2rem; }
        .stats-content { display: flex; justify-content: space-around; text-align: center; }
        .stat { display: flex; flex-direction: column; gap: 0.5rem; }
        .stat-value { font-size: 1.8rem; font-weight: bold; color: #343a40; }
        .stat-label { color: #6c757d; font-size: 0.9rem; }
        
        /* Wishlist Actions */
        .wishlist-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .action-buttons { display: flex; gap: 1rem; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-outline { background: white; border: 1px solid #dee2e6; color: #495057; }
        .btn:hover { opacity: 0.9; }
        
        /* Wishlist Grid */
        .wishlist-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 2rem; }
        .wishlist-item { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: relative; transition: transform 0.3s; }
        .wishlist-item:hover { transform: translateY(-5px); }
        .item-image { width: 100%; height: 200px; object-fit: cover; }
        .remove-btn { position: absolute; top: 10px; right: 10px; background: white; border: none; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .remove-btn:hover { background: #ffebee; color: #dc3545; }
        .item-content { padding: 1.5rem; }
        .item-name { font-size: 1.1rem; font-weight: bold; margin-bottom: 0.5rem; color: #343a40; }
        .item-rating { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; font-size: 0.9rem; }
        .stars { color: #ffc107; }
        .item-price { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; }
        .current-price { font-size: 1.3rem; font-weight: bold; color: #28a745; }
        .original-price { color: #6c757d; text-decoration: line-through; }
        .discount-badge { background: #dc3545; color: white; padding: 0.25rem 0.5rem; border-radius: 20px; font-size: 0.75rem; }
        .item-status { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; font-size: 0.9rem; }
        .in-stock { color: #28a745; }
        .out-stock { color: #dc3545; }
        .item-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
        .item-btn { padding: 0.75rem; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; text-decoration: none; text-align: center; }
        .add-cart-btn { background: #007bff; color: white; }
        .view-btn { background: #f8f9fa; color: #495057; border: 1px solid #dee2e6; }
        .added-date { color: #6c757d; font-size: 0.85rem; margin-top: 1rem; }
        
        /* Empty Wishlist */
        .empty-wishlist { text-align: center; padding: 4rem 2rem; background: white; border-radius: 10px; }
        .empty-icon { font-size: 4rem; margin-bottom: 1rem; }
        .empty-wishlist h2 { color: #343a40; margin-bottom: 1rem; }
        .empty-wishlist p { color: #6c757d; margin-bottom: 2rem; }
        
        /* Share Modal */
        .share-section { background: white; border-radius: 10px; padding: 2rem; margin-top: 2rem; text-align: center; }
        .share-title { font-size: 1.3rem; margin-bottom: 1rem; color: #343a40; }
        .share-buttons { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
        .share-btn { padding: 0.75rem 1.5rem; border-radius: 5px; text-decoration: none; color: white; display: inline-flex; align-items: center; gap: 0.5rem; }
        .share-facebook { background: #1877f2; }
        .share-twitter { background: #1da1f2; }
        .share-pinterest { background: #bd081c; }
        .share-email { background: #6c757d; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .wishlist-actions { flex-direction: column; gap: 1rem; }
            .action-buttons { width: 100%; justify-content: center; }
            .stats-content { flex-direction: column; gap: 1rem; }
            .wishlist-grid { grid-template-columns: 1fr; }
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
                        <li><a href="/cart.php">Cart</a></li>
                        <li><a href="/wishlist.php" style="color: #e91e63;">❤️ Wishlist</a></li>
                        <li><a href="/account.php">Account</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Wishlist Header -->
    <section class="wishlist-header">
        <div class="container">
            <h1>❤️ My Wishlist</h1>
            <p>Save your favorite items and track price changes</p>
        </div>
    </section>

    <!-- Stats Bar -->
    <section class="stats-bar">
        <div class="container">
            <div class="stats-content">
                <div class="stat">
                    <div class="stat-value"><?php echo count($wishlistItems); ?></div>
                    <div class="stat-label">Items Saved</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?php echo count(array_filter($wishlistItems, fn($item) => $item['in_stock'])); ?></div>
                    <div class="stat-label">Available Now</div>
                </div>
                <div class="stat">
                    <div class="stat-value">$<?php echo number_format($totalSavings, 2); ?></div>
                    <div class="stat-label">Potential Savings</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Wishlist Content -->
    <div class="container">
        <?php if (!empty($wishlistItems)): ?>
        <!-- Wishlist Actions -->
        <div class="wishlist-actions">
            <h2>Your Saved Items</h2>
            <div class="action-buttons">
                <button class="btn btn-primary" onclick="addAllToCart()">
                    🛒 Add All to Cart
                </button>
                <button class="btn btn-outline" onclick="shareWishlist()">
                    📤 Share List
                </button>
            </div>
        </div>

        <!-- Wishlist Grid -->
        <div class="wishlist-grid">
            <?php foreach ($wishlistItems as $item): ?>
            <div class="wishlist-item">
                <button class="remove-btn" onclick="removeFromWishlist(<?php echo $item['id']; ?>)" title="Remove from wishlist">
                    ❌
                </button>
                <img src="<?php echo $item['image']; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="item-image">
                <div class="item-content">
                    <h3 class="item-name"><?php echo htmlspecialchars($item['name']); ?></h3>
                    <div class="item-rating">
                        <span class="stars">
                            <?php 
                            $fullStars = floor($item['rating']);
                            for ($i = 0; $i < $fullStars; $i++) echo '⭐';
                            ?>
                        </span>
                        <span><?php echo $item['rating']; ?> (<?php echo $item['reviews']; ?> reviews)</span>
                    </div>
                    <div class="item-price">
                        <span class="current-price">$<?php echo number_format($item['price'], 2); ?></span>
                        <?php if (isset($item['original_price'])): ?>
                        <span class="original-price">$<?php echo number_format($item['original_price'], 2); ?></span>
                        <span class="discount-badge">
                            <?php echo round((($item['original_price'] - $item['price']) / $item['original_price']) * 100); ?>% OFF
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="item-status">
                        <?php if ($item['in_stock']): ?>
                        <span class="in-stock">✓ In Stock</span>
                        <?php else: ?>
                        <span class="out-stock">✗ Out of Stock</span>
                        <?php endif; ?>
                    </div>
                    <div class="item-actions">
                        <button class="item-btn add-cart-btn" onclick="addToCart(<?php echo $item['id']; ?>)" <?php echo !$item['in_stock'] ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>
                            Add to Cart
                        </button>
                        <a href="/product.php?id=<?php echo $item['id']; ?>" class="item-btn view-btn">
                            View Details
                        </a>
                    </div>
                    <div class="added-date">
                        Added on <?php echo date('M d, Y', strtotime($item['added_date'])); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Share Section -->
        <div class="share-section">
            <h3 class="share-title">Share Your Wishlist</h3>
            <p style="color: #6c757d; margin-bottom: 1.5rem;">Share your favorite items with friends and family</p>
            <div class="share-buttons">
                <a href="#" class="share-btn share-facebook">📘 Facebook</a>
                <a href="#" class="share-btn share-twitter">🐦 Twitter</a>
                <a href="#" class="share-btn share-pinterest">📌 Pinterest</a>
                <a href="#" class="share-btn share-email">✉️ Email</a>
            </div>
        </div>

        <?php else: ?>
        <!-- Empty Wishlist -->
        <div class="empty-wishlist">
            <div class="empty-icon">💔</div>
            <h2>Your wishlist is empty</h2>
            <p>Start adding items you love to your wishlist. They'll show up here so you can find them easily later.</p>
            <a href="/" class="btn btn-primary">Start Shopping</a>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function removeFromWishlist(itemId) {
            if (confirm('Remove this item from your wishlist?')) {
                alert('Item removed from wishlist!');
                location.reload();
            }
        }

        function addToCart(itemId) {
            alert('Item added to cart!');
            // In a real app, this would update the cart state
        }

        function addAllToCart() {
            const inStockCount = <?php echo count(array_filter($wishlistItems, fn($item) => $item['in_stock'])); ?>;
            if (confirm(`Add ${inStockCount} available items to cart?`)) {
                alert(`${inStockCount} items added to cart!`);
            }
        }

        function shareWishlist() {
            alert('Share functionality would open a modal with sharing options');
        }
    </script>
</body>
</html>