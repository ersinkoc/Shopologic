<?php
// Get search query
$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';

// All products (expanded list)
$allProducts = [
    ['id' => 1, 'name' => 'Premium Laptop Pro', 'price' => 1299.99, 'original_price' => 1499.99, 'image' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=300&h=200&fit=crop', 'rating' => 4.8, 'reviews' => 127, 'category' => 'Electronics', 'description' => 'High-performance laptop with Intel i7 processor and 16GB RAM'],
    ['id' => 2, 'name' => 'Wireless Headphones', 'price' => 159.99, 'image' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=300&h=200&fit=crop', 'rating' => 4.6, 'reviews' => 89, 'category' => 'Audio', 'description' => 'Noise-cancelling wireless headphones with 30-hour battery'],
    ['id' => 3, 'name' => 'Smart Watch Elite', 'price' => 349.99, 'image' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=300&h=200&fit=crop', 'rating' => 4.7, 'reviews' => 203, 'category' => 'Wearables', 'description' => 'Advanced smartwatch with health tracking and GPS'],
    ['id' => 4, 'name' => 'Gaming Mechanical Keyboard', 'price' => 129.99, 'original_price' => 149.99, 'image' => 'https://images.unsplash.com/photo-1541140532154-b024d705b90a?w=300&h=200&fit=crop', 'rating' => 4.9, 'reviews' => 156, 'category' => 'Gaming', 'description' => 'RGB mechanical keyboard with Cherry MX switches'],
    ['id' => 5, 'name' => 'Professional Camera', 'price' => 899.99, 'image' => 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=300&h=200&fit=crop', 'rating' => 4.8, 'reviews' => 78, 'category' => 'Photography', 'description' => 'DSLR camera with 24MP sensor and 4K video recording'],
    ['id' => 6, 'name' => 'Bluetooth Speaker', 'price' => 79.99, 'original_price' => 99.99, 'image' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=300&h=200&fit=crop', 'rating' => 4.5, 'reviews' => 234, 'category' => 'Audio', 'description' => 'Portable waterproof speaker with rich bass sound'],
    ['id' => 7, 'name' => 'USB-C Hub', 'price' => 49.99, 'image' => 'https://images.unsplash.com/photo-1625948515291-69613efd103f?w=300&h=200&fit=crop', 'rating' => 4.4, 'reviews' => 67, 'category' => 'Accessories', 'description' => '7-in-1 USB-C hub with HDMI and card reader'],
    ['id' => 8, 'name' => 'Gaming Mouse', 'price' => 89.99, 'image' => 'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?w=300&h=200&fit=crop', 'rating' => 4.7, 'reviews' => 312, 'category' => 'Gaming', 'description' => 'High-precision gaming mouse with RGB lighting'],
    ['id' => 9, 'name' => 'Smartphone Pro Max', 'price' => 999.99, 'original_price' => 1199.99, 'image' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=300&h=200&fit=crop', 'rating' => 4.8, 'reviews' => 567, 'category' => 'Smartphones', 'description' => 'Latest flagship smartphone with triple camera system'],
    ['id' => 10, 'name' => 'Smart Home Hub', 'price' => 129.99, 'image' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=300&h=200&fit=crop', 'rating' => 4.3, 'reviews' => 89, 'category' => 'Home & Garden', 'description' => 'Central hub for all your smart home devices'],
    ['id' => 11, 'name' => 'Fitness Tracker', 'price' => 99.99, 'image' => 'https://images.unsplash.com/photo-1576243345690-4e4b79b63288?w=300&h=200&fit=crop', 'rating' => 4.5, 'reviews' => 445, 'category' => 'Wearables', 'description' => 'Track your health and fitness goals'],
    ['id' => 12, 'name' => 'Portable SSD', 'price' => 149.99, 'image' => 'https://images.unsplash.com/photo-1597872200969-2b65d56bd16b?w=300&h=200&fit=crop', 'rating' => 4.6, 'reviews' => 156, 'category' => 'Accessories', 'description' => '1TB portable SSD with USB-C']
];

// Filter products based on search query
$searchResults = [];
if ($searchQuery) {
    $searchResults = array_filter($allProducts, function($product) use ($searchQuery) {
        $query = strtolower($searchQuery);
        return strpos(strtolower($product['name']), $query) !== false ||
               strpos(strtolower($product['description']), $query) !== false ||
               strpos(strtolower($product['category']), $query) !== false;
    });
}

// Categories for filter
$categories = array_unique(array_column($allProducts, 'category'));
sort($categories);

// Filters from URL
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : '';
$minPrice = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$maxPrice = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 2000;
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'relevance';

// Apply category filter
if ($selectedCategory && $searchResults) {
    $searchResults = array_filter($searchResults, function($product) use ($selectedCategory) {
        return $product['category'] === $selectedCategory;
    });
}

// Apply price filter
if ($searchResults) {
    $searchResults = array_filter($searchResults, function($product) use ($minPrice, $maxPrice) {
        return $product['price'] >= $minPrice && $product['price'] <= $maxPrice;
    });
}

// Sort results
if ($sortBy === 'price-low') {
    usort($searchResults, function($a, $b) { return $a['price'] - $b['price']; });
} elseif ($sortBy === 'price-high') {
    usort($searchResults, function($a, $b) { return $b['price'] - $a['price']; });
} elseif ($sortBy === 'rating') {
    usort($searchResults, function($a, $b) { return $b['rating'] - $a['rating']; });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search: <?php echo htmlspecialchars($searchQuery); ?> - Shopologic</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8f9fa; }
        
        /* Header */
        .header { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 1rem 0; position: sticky; top: 0; z-index: 100; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 1rem; }
        .header-content { display: flex; justify-content: space-between; align-items: center; gap: 2rem; }
        .logo { font-size: 1.8rem; font-weight: bold; color: #007bff; text-decoration: none; white-space: nowrap; }
        .search-bar { flex: 1; max-width: 600px; position: relative; }
        .search-bar input { width: 100%; padding: 0.75rem 3rem 0.75rem 1rem; border: 2px solid #007bff; border-radius: 25px; font-size: 1rem; }
        .search-btn { position: absolute; right: 5px; top: 50%; transform: translateY(-50%); background: #007bff; color: white; border: none; padding: 0.5rem 1rem; border-radius: 20px; cursor: pointer; }
        .nav-links { display: flex; gap: 1rem; list-style: none; }
        .nav-links a { text-decoration: none; color: #495057; padding: 0.5rem; white-space: nowrap; }
        
        /* Search Header */
        .search-header { background: #f8f9fa; padding: 2rem 0; border-bottom: 1px solid #dee2e6; }
        .search-info { display: flex; justify-content: space-between; align-items: center; }
        .search-info h1 { font-size: 1.5rem; color: #343a40; }
        .result-count { color: #6c757d; }
        
        /* Main Layout */
        .main-layout { display: grid; grid-template-columns: 250px 1fr; gap: 2rem; margin: 2rem 0; }
        
        /* Filters Sidebar */
        .filters-sidebar { background: white; border-radius: 10px; padding: 1.5rem; height: fit-content; position: sticky; top: 100px; }
        .filter-section { margin-bottom: 2rem; }
        .filter-section h3 { font-size: 1.1rem; margin-bottom: 1rem; color: #343a40; }
        .filter-option { display: block; margin-bottom: 0.75rem; }
        .filter-option input[type="checkbox"], .filter-option input[type="radio"] { margin-right: 0.5rem; }
        .price-inputs { display: flex; gap: 0.5rem; align-items: center; margin-top: 1rem; }
        .price-inputs input { width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 5px; }
        .apply-filters-btn { width: 100%; background: #007bff; color: white; border: none; padding: 0.75rem; border-radius: 5px; font-weight: bold; cursor: pointer; margin-top: 1rem; }
        .clear-filters { display: block; text-align: center; margin-top: 0.5rem; color: #6c757d; text-decoration: none; font-size: 0.9rem; }
        
        /* Results Section */
        .results-section { background: white; border-radius: 10px; padding: 1.5rem; }
        
        /* Sort Bar */
        .sort-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #dee2e6; }
        .view-options { display: flex; gap: 0.5rem; }
        .view-btn { background: none; border: 1px solid #dee2e6; padding: 0.5rem; border-radius: 5px; cursor: pointer; }
        .view-btn.active { background: #007bff; color: white; border-color: #007bff; }
        .sort-dropdown { padding: 0.5rem 1rem; border: 1px solid #dee2e6; border-radius: 5px; background: white; }
        
        /* Product Grid */
        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1.5rem; }
        .product-card { background: #f8f9fa; border-radius: 10px; overflow: hidden; transition: transform 0.3s, box-shadow 0.3s; cursor: pointer; }
        .product-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .product-image { width: 100%; height: 200px; object-fit: cover; position: relative; }
        .product-badge { position: absolute; top: 10px; right: 10px; background: #dc3545; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: bold; }
        .product-content { padding: 1rem; }
        .product-category { color: #6c757d; font-size: 0.85rem; margin-bottom: 0.5rem; }
        .product-name { font-weight: bold; margin-bottom: 0.5rem; color: #343a40; }
        .product-description { color: #6c757d; font-size: 0.9rem; margin-bottom: 0.5rem; line-height: 1.4; }
        .product-rating { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; font-size: 0.9rem; }
        .stars { color: #ffc107; }
        .product-price { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; }
        .current-price { font-size: 1.2rem; font-weight: bold; color: #28a745; }
        .original-price { color: #6c757d; text-decoration: line-through; font-size: 1rem; }
        .add-to-cart-btn { width: 100%; background: #007bff; color: white; border: none; padding: 0.75rem; border-radius: 5px; font-weight: bold; cursor: pointer; }
        .add-to-cart-btn:hover { background: #0056b3; }
        
        /* No Results */
        .no-results { text-align: center; padding: 4rem 2rem; }
        .no-results-icon { font-size: 4rem; margin-bottom: 1rem; }
        .no-results h2 { color: #343a40; margin-bottom: 1rem; }
        .no-results p { color: #6c757d; margin-bottom: 2rem; }
        .suggestions { margin-top: 2rem; }
        .suggestion-links { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
        .suggestion-link { background: #e9ecef; padding: 0.5rem 1rem; border-radius: 20px; text-decoration: none; color: #495057; }
        .suggestion-link:hover { background: #dee2e6; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-content { flex-direction: column; }
            .search-bar { max-width: 100%; }
            .main-layout { grid-template-columns: 1fr; }
            .filters-sidebar { position: static; }
            .sort-bar { flex-direction: column; gap: 1rem; align-items: stretch; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="/" class="logo">🛒 Shopologic</a>
                
                <form class="search-bar" method="GET" action="/search.php">
                    <input type="text" name="q" placeholder="Search products..." value="<?php echo htmlspecialchars($searchQuery); ?>" required>
                    <button type="submit" class="search-btn">🔍</button>
                </form>
                
                <nav>
                    <ul class="nav-links">
                        <li><a href="/cart.php">🛒 Cart</a></li>
                        <li><a href="/account.php">👤 Account</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Search Header -->
    <?php if ($searchQuery): ?>
    <section class="search-header">
        <div class="container">
            <div class="search-info">
                <h1>Search results for "<?php echo htmlspecialchars($searchQuery); ?>"</h1>
                <div class="result-count"><?php echo count($searchResults); ?> products found</div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="container">
        <div class="main-layout">
            <!-- Filters Sidebar -->
            <aside class="filters-sidebar">
                <h2 style="font-size: 1.3rem; margin-bottom: 1.5rem;">Filters</h2>
                
                <form method="GET" action="/search.php">
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>">
                    
                    <!-- Categories -->
                    <div class="filter-section">
                        <h3>Category</h3>
                        <label class="filter-option">
                            <input type="radio" name="category" value="" <?php echo !$selectedCategory ? 'checked' : ''; ?>>
                            All Categories
                        </label>
                        <?php foreach ($categories as $category): ?>
                        <label class="filter-option">
                            <input type="radio" name="category" value="<?php echo $category; ?>" <?php echo $selectedCategory === $category ? 'checked' : ''; ?>>
                            <?php echo $category; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Price Range -->
                    <div class="filter-section">
                        <h3>Price Range</h3>
                        <div class="price-inputs">
                            <input type="number" name="min_price" placeholder="Min" value="<?php echo $minPrice > 0 ? $minPrice : ''; ?>">
                            <span>-</span>
                            <input type="number" name="max_price" placeholder="Max" value="<?php echo $maxPrice < 2000 ? $maxPrice : ''; ?>">
                        </div>
                    </div>
                    
                    <!-- Rating -->
                    <div class="filter-section">
                        <h3>Customer Rating</h3>
                        <label class="filter-option">
                            <input type="checkbox" name="rating[]" value="4">
                            ⭐⭐⭐⭐ & Up
                        </label>
                        <label class="filter-option">
                            <input type="checkbox" name="rating[]" value="3">
                            ⭐⭐⭐ & Up
                        </label>
                    </div>
                    
                    <button type="submit" class="apply-filters-btn">Apply Filters</button>
                    <a href="/search.php?q=<?php echo urlencode($searchQuery); ?>" class="clear-filters">Clear All Filters</a>
                </form>
            </aside>

            <!-- Results Section -->
            <section class="results-section">
                <?php if ($searchQuery && !empty($searchResults)): ?>
                <!-- Sort Bar -->
                <div class="sort-bar">
                    <div class="view-options">
                        <button class="view-btn active" title="Grid View">⊞</button>
                        <button class="view-btn" title="List View">☰</button>
                    </div>
                    
                    <select class="sort-dropdown" onchange="window.location.href='?q=<?php echo urlencode($searchQuery); ?>&sort=' + this.value">
                        <option value="relevance" <?php echo $sortBy === 'relevance' ? 'selected' : ''; ?>>Most Relevant</option>
                        <option value="price-low" <?php echo $sortBy === 'price-low' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price-high" <?php echo $sortBy === 'price-high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="rating" <?php echo $sortBy === 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                    </select>
                </div>

                <!-- Product Grid -->
                <div class="product-grid">
                    <?php foreach ($searchResults as $product): ?>
                    <div class="product-card" onclick="window.location.href='/product.php?id=<?php echo $product['id']; ?>'">
                        <div style="position: relative;">
                            <img src="<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                            <?php if (isset($product['original_price'])): ?>
                            <div class="product-badge">Sale</div>
                            <?php endif; ?>
                        </div>
                        <div class="product-content">
                            <div class="product-category"><?php echo htmlspecialchars($product['category']); ?></div>
                            <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="product-description"><?php echo htmlspecialchars(substr($product['description'], 0, 80)); ?>...</p>
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

                <?php elseif ($searchQuery): ?>
                <!-- No Results -->
                <div class="no-results">
                    <div class="no-results-icon">🔍</div>
                    <h2>No products found</h2>
                    <p>We couldn't find any products matching "<?php echo htmlspecialchars($searchQuery); ?>"</p>
                    
                    <div class="suggestions">
                        <h3>Try searching for:</h3>
                        <div class="suggestion-links">
                            <a href="/search.php?q=laptop" class="suggestion-link">Laptops</a>
                            <a href="/search.php?q=headphones" class="suggestion-link">Headphones</a>
                            <a href="/search.php?q=gaming" class="suggestion-link">Gaming</a>
                            <a href="/search.php?q=camera" class="suggestion-link">Cameras</a>
                            <a href="/search.php?q=smart" class="suggestion-link">Smart Devices</a>
                        </div>
                    </div>
                </div>

                <?php else: ?>
                <!-- No Search Query -->
                <div class="no-results">
                    <div class="no-results-icon">🔍</div>
                    <h2>Start your search</h2>
                    <p>Enter a product name, category, or keyword to find what you're looking for</p>
                    
                    <div class="suggestions">
                        <h3>Popular searches:</h3>
                        <div class="suggestion-links">
                            <a href="/search.php?q=laptop" class="suggestion-link">Laptops</a>
                            <a href="/search.php?q=wireless" class="suggestion-link">Wireless</a>
                            <a href="/search.php?q=gaming" class="suggestion-link">Gaming</a>
                            <a href="/search.php?q=smart" class="suggestion-link">Smart Home</a>
                            <a href="/search.php?q=audio" class="suggestion-link">Audio</a>
                        </div>
                    </div>
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