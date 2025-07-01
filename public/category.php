<?php
// Get category from URL
$selectedCategory = isset($_GET['cat']) ? $_GET['cat'] : 'all';

// Categories
$categories = [
    'all' => ['name' => 'All Products', 'icon' => '🛍️'],
    'Electronics' => ['name' => 'Electronics', 'icon' => '💻'],
    'Audio' => ['name' => 'Audio', 'icon' => '🎵'],
    'Wearables' => ['name' => 'Wearables', 'icon' => '⌚'],
    'Gaming' => ['name' => 'Gaming', 'icon' => '🎮'],
    'Photography' => ['name' => 'Photography', 'icon' => '📷'],
    'Accessories' => ['name' => 'Accessories', 'icon' => '🔌'],
    'Smartphones' => ['name' => 'Smartphones', 'icon' => '📱'],
    'Home & Garden' => ['name' => 'Home & Garden', 'icon' => '🏠']
];

// Extended product list
$allProducts = [
    ['id' => 1, 'name' => 'Premium Laptop Pro', 'price' => 1299.99, 'original_price' => 1499.99, 'image' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=300&h=200&fit=crop', 'rating' => 4.8, 'reviews' => 127, 'category' => 'Electronics'],
    ['id' => 2, 'name' => 'Wireless Headphones', 'price' => 159.99, 'image' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=300&h=200&fit=crop', 'rating' => 4.6, 'reviews' => 89, 'category' => 'Audio'],
    ['id' => 3, 'name' => 'Smart Watch Elite', 'price' => 349.99, 'image' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=300&h=200&fit=crop', 'rating' => 4.7, 'reviews' => 203, 'category' => 'Wearables'],
    ['id' => 4, 'name' => 'Gaming Mechanical Keyboard', 'price' => 129.99, 'original_price' => 149.99, 'image' => 'https://images.unsplash.com/photo-1541140532154-b024d705b90a?w=300&h=200&fit=crop', 'rating' => 4.9, 'reviews' => 156, 'category' => 'Gaming'],
    ['id' => 5, 'name' => 'Professional Camera', 'price' => 899.99, 'image' => 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=300&h=200&fit=crop', 'rating' => 4.8, 'reviews' => 78, 'category' => 'Photography'],
    ['id' => 6, 'name' => 'Bluetooth Speaker', 'price' => 79.99, 'original_price' => 99.99, 'image' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=300&h=200&fit=crop', 'rating' => 4.5, 'reviews' => 234, 'category' => 'Audio'],
    ['id' => 7, 'name' => 'USB-C Hub', 'price' => 49.99, 'image' => 'https://images.unsplash.com/photo-1625948515291-69613efd103f?w=300&h=200&fit=crop', 'rating' => 4.4, 'reviews' => 67, 'category' => 'Accessories'],
    ['id' => 8, 'name' => 'Gaming Mouse', 'price' => 89.99, 'image' => 'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?w=300&h=200&fit=crop', 'rating' => 4.7, 'reviews' => 312, 'category' => 'Gaming'],
    ['id' => 9, 'name' => 'Smartphone Pro Max', 'price' => 999.99, 'original_price' => 1199.99, 'image' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=300&h=200&fit=crop', 'rating' => 4.8, 'reviews' => 567, 'category' => 'Smartphones'],
    ['id' => 10, 'name' => 'Smart Home Hub', 'price' => 129.99, 'image' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=300&h=200&fit=crop', 'rating' => 4.3, 'reviews' => 89, 'category' => 'Home & Garden']
];

// Filter products by category
$products = $selectedCategory === 'all' ? $allProducts : array_filter($allProducts, function($p) use ($selectedCategory) {
    return $p['category'] === $selectedCategory;
});

// Sort options
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'featured';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($categories[$selectedCategory]['name'] ?? 'Products'); ?> - Shopologic</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8f9fa; }
        
        /* Header */
        .header { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 1rem 0; position: sticky; top: 0; z-index: 100; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 1rem; }
        .header-content { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.8rem; font-weight: bold; color: #007bff; text-decoration: none; }
        .nav-links { display: flex; gap: 2rem; list-style: none; }
        .nav-links a { text-decoration: none; color: #495057; padding: 0.5rem 1rem; }
        
        /* Category Header */
        .category-header { background: linear-gradient(135deg, #007bff, #0056b3); color: white; padding: 3rem 0; text-align: center; }
        .category-header h1 { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .category-header p { font-size: 1.1rem; opacity: 0.9; }
        
        /* Main Layout */
        .main-layout { display: grid; grid-template-columns: 250px 1fr; gap: 2rem; margin: 2rem 0; }
        
        /* Sidebar */
        .sidebar { background: white; border-radius: 10px; padding: 1.5rem; height: fit-content; position: sticky; top: 100px; }
        .sidebar h3 { margin-bottom: 1rem; color: #343a40; }
        .category-list { list-style: none; }
        .category-list li { margin-bottom: 0.5rem; }
        .category-list a { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; text-decoration: none; color: #495057; border-radius: 5px; transition: all 0.3s; }
        .category-list a:hover { background: #f8f9fa; }
        .category-list a.active { background: #007bff; color: white; }
        
        /* Filters */
        .filter-section { margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #dee2e6; }
        .filter-section h4 { margin-bottom: 1rem; color: #495057; }
        .price-range { margin-bottom: 1rem; }
        .price-range input { width: 100%; }
        .filter-checkbox { display: block; margin-bottom: 0.5rem; }
        .filter-checkbox input { margin-right: 0.5rem; }
        
        /* Products Section */
        .products-section { background: white; border-radius: 10px; padding: 2rem; }
        
        /* Toolbar */
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid #dee2e6; }
        .results-count { color: #6c757d; }
        .sort-dropdown { padding: 0.5rem 1rem; border: 1px solid #dee2e6; border-radius: 5px; background: white; }
        
        /* Product Grid */
        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 2rem; }
        .product-card { background: #f8f9fa; border-radius: 10px; overflow: hidden; transition: transform 0.3s, box-shadow 0.3s; cursor: pointer; }
        .product-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .product-image { width: 100%; height: 200px; object-fit: cover; }
        .product-badge { position: absolute; top: 10px; right: 10px; background: #dc3545; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: bold; }
        .product-content { padding: 1rem; }
        .product-name { font-weight: bold; margin-bottom: 0.5rem; color: #343a40; }
        .product-rating { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.9rem; }
        .stars { color: #ffc107; }
        .product-price { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; }
        .current-price { font-size: 1.2rem; font-weight: bold; color: #28a745; }
        .original-price { color: #6c757d; text-decoration: line-through; font-size: 1rem; }
        .add-to-cart-btn { width: 100%; background: #007bff; color: white; border: none; padding: 0.75rem; border-radius: 5px; font-weight: bold; cursor: pointer; }
        .add-to-cart-btn:hover { background: #0056b3; }
        
        /* No Products */
        .no-products { text-align: center; padding: 3rem; color: #6c757d; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-layout { grid-template-columns: 1fr; }
            .sidebar { position: static; }
            .toolbar { flex-direction: column; gap: 1rem; align-items: stretch; }
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
                        <li><a href="/category.php" style="color: #007bff;">Categories</a></li>
                        <li><a href="/cart.php">🛒 Cart</a></li>
                        <li><a href="/admin.php">Admin</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Category Header -->
    <section class="category-header">
        <div class="container">
            <h1><?php echo $categories[$selectedCategory]['icon'] ?? '🛍️'; ?> <?php echo htmlspecialchars($categories[$selectedCategory]['name'] ?? 'Products'); ?></h1>
            <p>Discover our amazing collection of <?php echo strtolower($categories[$selectedCategory]['name'] ?? 'products'); ?></p>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container">
        <div class="main-layout">
            <!-- Sidebar -->
            <aside class="sidebar">
                <h3>Categories</h3>
                <ul class="category-list">
                    <?php foreach ($categories as $key => $category): ?>
                    <li>
                        <a href="/category.php?cat=<?php echo urlencode($key); ?>" class="<?php echo $selectedCategory === $key ? 'active' : ''; ?>">
                            <span><?php echo $category['icon']; ?></span>
                            <span><?php echo htmlspecialchars($category['name']); ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Filters -->
                <div class="filter-section">
                    <h4>Price Range</h4>
                    <div class="price-range">
                        <input type="range" min="0" max="2000" value="1000">
                        <div style="display: flex; justify-content: space-between; font-size: 0.9rem; color: #6c757d;">
                            <span>$0</span>
                            <span>$2000</span>
                        </div>
                    </div>
                </div>

                <div class="filter-section">
                    <h4>Rating</h4>
                    <label class="filter-checkbox">
                        <input type="checkbox"> ⭐⭐⭐⭐⭐ 5 Stars
                    </label>
                    <label class="filter-checkbox">
                        <input type="checkbox"> ⭐⭐⭐⭐ 4+ Stars
                    </label>
                    <label class="filter-checkbox">
                        <input type="checkbox"> ⭐⭐⭐ 3+ Stars
                    </label>
                </div>

                <div class="filter-section">
                    <h4>Availability</h4>
                    <label class="filter-checkbox">
                        <input type="checkbox" checked> In Stock
                    </label>
                    <label class="filter-checkbox">
                        <input type="checkbox"> On Sale
                    </label>
                </div>
            </aside>

            <!-- Products Section -->
            <section class="products-section">
                <!-- Toolbar -->
                <div class="toolbar">
                    <div class="results-count">
                        Showing <?php echo count($products); ?> products
                    </div>
                    <select class="sort-dropdown" onchange="window.location.href='?cat=<?php echo urlencode($selectedCategory); ?>&sort=' + this.value">
                        <option value="featured" <?php echo $sortBy === 'featured' ? 'selected' : ''; ?>>Featured</option>
                        <option value="price-low" <?php echo $sortBy === 'price-low' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price-high" <?php echo $sortBy === 'price-high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="rating" <?php echo $sortBy === 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                        <option value="newest" <?php echo $sortBy === 'newest' ? 'selected' : ''; ?>>Newest</option>
                    </select>
                </div>

                <!-- Product Grid -->
                <?php if (count($products) > 0): ?>
                <div class="product-grid">
                    <?php foreach ($products as $product): ?>
                    <div class="product-card" onclick="window.location.href='/product.php?id=<?php echo $product['id']; ?>'">
                        <?php if (isset($product['original_price'])): ?>
                        <div class="product-badge">Sale</div>
                        <?php endif; ?>
                        <img src="<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                        <div class="product-content">
                            <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="product-rating">
                                <span class="stars">
                                    <?php 
                                    $fullStars = floor($product['rating']);
                                    for ($i = 0; $i < $fullStars; $i++) echo '⭐';
                                    ?>
                                </span>
                                <span><?php echo $product['rating']; ?> (<?php echo $product['reviews']; ?>)</span>
                            </div>
                            <div class="product-price">
                                <span class="current-price">$<?php echo number_format($product['price'], 2); ?></span>
                                <?php if (isset($product['original_price'])): ?>
                                <span class="original-price">$<?php echo number_format($product['original_price'], 2); ?></span>
                                <?php endif; ?>
                            </div>
                            <button class="add-to-cart-btn" onclick="event.stopPropagation(); addToCart(<?php echo $product['id']; ?>)">
                                Add to Cart
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="no-products">
                    <h3>No products found</h3>
                    <p>Try adjusting your filters or browse other categories.</p>
                </div>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <script>
        function addToCart(productId) {
            alert('Product added to cart!');
            // In a real app, this would update the cart state
        }
    </script>
</body>
</html>