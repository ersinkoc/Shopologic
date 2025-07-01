<?php
// Load plugin data
$storageDir = dirname(__DIR__) . '/storage/plugins';
$pluginsFile = $storageDir . '/plugins.json';

$plugins = [];
$categories = [];
if (file_exists($pluginsFile)) {
    $data = json_decode(file_get_contents($pluginsFile), true);
    $plugins = $data['plugins'] ?? [];
    
    // Categorize plugins
    foreach ($plugins as $plugin) {
        $category = 'Other';
        $name = strtolower($plugin['name']);
        
        if (strpos($name, 'payment') !== false || strpos($name, 'checkout') !== false) {
            $category = 'Payment & Checkout';
        } elseif (strpos($name, 'shipping') !== false || strpos($name, 'delivery') !== false) {
            $category = 'Shipping & Delivery';
        } elseif (strpos($name, 'analytics') !== false || strpos($name, 'reporting') !== false) {
            $category = 'Analytics & Reporting';
        } elseif (strpos($name, 'marketing') !== false || strpos($name, 'email') !== false || strpos($name, 'seo') !== false) {
            $category = 'Marketing & SEO';
        } elseif (strpos($name, 'inventory') !== false || strpos($name, 'product') !== false || strpos($name, 'catalog') !== false) {
            $category = 'Product Management';
        } elseif (strpos($name, 'customer') !== false || strpos($name, 'loyalty') !== false || strpos($name, 'review') !== false) {
            $category = 'Customer Experience';
        } elseif (strpos($name, 'security') !== false || strpos($name, 'fraud') !== false || strpos($name, 'backup') !== false) {
            $category = 'Security & Protection';
        } elseif (strpos($name, 'api') !== false || strpos($name, 'integration') !== false || strpos($name, 'sync') !== false) {
            $category = 'Integration & API';
        } elseif (strpos($name, 'mobile') !== false || strpos($name, 'pwa') !== false || strpos($name, 'app') !== false) {
            $category = 'Mobile & Apps';
        } elseif (strpos($name, 'multi') !== false || strpos($name, 'language') !== false || strpos($name, 'currency') !== false) {
            $category = 'Multi-store & International';
        }
        
        if (!isset($categories[$category])) {
            $categories[$category] = [];
        }
        $categories[$category][] = $plugin;
    }
}

// Sort categories
ksort($categories);

// Get filter from URL
$selectedCategory = $_GET['category'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plugin Marketplace - Shopologic</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; }
        
        /* Header */
        .header { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 1rem 0; position: sticky; top: 0; z-index: 100; }
        .container { max-width: 1400px; margin: 0 auto; padding: 0 1rem; }
        .header-content { display: flex; justify-content: space-between; align-items: center; gap: 2rem; }
        .logo { font-size: 1.8rem; font-weight: bold; color: #007bff; text-decoration: none; }
        .search-bar { flex: 1; max-width: 500px; position: relative; }
        .search-bar input { width: 100%; padding: 0.75rem 3rem 0.75rem 1rem; border: 1px solid #dee2e6; border-radius: 25px; font-size: 1rem; }
        .search-btn { position: absolute; right: 5px; top: 50%; transform: translateY(-50%); background: #007bff; color: white; border: none; padding: 0.5rem 1rem; border-radius: 20px; cursor: pointer; }
        .nav-links { display: flex; gap: 1.5rem; list-style: none; }
        .nav-links a { text-decoration: none; color: #495057; padding: 0.5rem 1rem; border-radius: 5px; transition: all 0.3s; }
        .nav-links a:hover { background: #f8f9fa; }
        
        /* Hero Section */
        .hero { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 4rem 0; text-align: center; }
        .hero h1 { font-size: 3rem; margin-bottom: 1rem; }
        .hero p { font-size: 1.2rem; opacity: 0.9; margin-bottom: 2rem; }
        .stats { display: flex; justify-content: center; gap: 4rem; margin-top: 2rem; }
        .stat { text-align: center; }
        .stat-number { font-size: 2.5rem; font-weight: bold; }
        .stat-label { opacity: 0.8; }
        
        /* Main Layout */
        .main-layout { display: grid; grid-template-columns: 250px 1fr; gap: 2rem; margin: 2rem 0; }
        
        /* Sidebar */
        .sidebar { background: white; border-radius: 10px; padding: 1.5rem; height: fit-content; position: sticky; top: 100px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .sidebar h3 { margin-bottom: 1rem; color: #343a40; }
        .category-list { list-style: none; }
        .category-item { margin-bottom: 0.5rem; }
        .category-link { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; text-decoration: none; color: #495057; border-radius: 5px; transition: all 0.3s; }
        .category-link:hover { background: #f8f9fa; }
        .category-link.active { background: #007bff; color: white; }
        .category-count { background: rgba(0,0,0,0.1); padding: 0.25rem 0.5rem; border-radius: 20px; font-size: 0.85rem; }
        
        /* Plugin Grid */
        .plugins-section { background: white; border-radius: 10px; padding: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .section-title { font-size: 1.5rem; color: #343a40; }
        .sort-dropdown { padding: 0.5rem 1rem; border: 1px solid #dee2e6; border-radius: 5px; background: white; }
        
        .plugin-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem; }
        .plugin-card { border: 1px solid #dee2e6; border-radius: 10px; padding: 1.5rem; transition: all 0.3s; position: relative; overflow: hidden; }
        .plugin-card:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(0,0,0,0.1); border-color: #007bff; }
        
        .plugin-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; }
        .plugin-icon { width: 50px; height: 50px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; }
        .plugin-status { display: flex; gap: 0.5rem; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: bold; }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-inactive { background: #f8d7da; color: #721c24; }
        .badge-premium { background: #fff3cd; color: #856404; }
        .badge-new { background: #cce5ff; color: #004085; }
        
        .plugin-name { font-size: 1.2rem; font-weight: bold; margin-bottom: 0.5rem; color: #343a40; }
        .plugin-author { color: #6c757d; font-size: 0.9rem; margin-bottom: 0.5rem; }
        .plugin-description { color: #495057; margin-bottom: 1rem; line-height: 1.5; }
        
        .plugin-features { list-style: none; margin-bottom: 1rem; }
        .plugin-features li { padding: 0.25rem 0; color: #6c757d; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem; }
        .plugin-features li::before { content: '✓'; color: #28a745; font-weight: bold; }
        
        .plugin-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #f8f9fa; }
        .plugin-meta { display: flex; gap: 1rem; font-size: 0.85rem; color: #6c757d; }
        .plugin-actions { display: flex; gap: 0.5rem; }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; text-decoration: none; transition: all 0.3s; }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-outline { background: white; border: 1px solid #dee2e6; color: #495057; }
        .btn:hover { opacity: 0.9; }
        
        /* Featured Section */
        .featured-section { margin-bottom: 2rem; }
        .featured-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
        .featured-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px; padding: 2rem; position: relative; overflow: hidden; }
        .featured-card::before { content: ''; position: absolute; top: -50%; right: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%); }
        .featured-badge { background: rgba(255,255,255,0.2); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem; display: inline-block; margin-bottom: 1rem; }
        .featured-title { font-size: 1.5rem; margin-bottom: 0.5rem; }
        .featured-description { opacity: 0.9; margin-bottom: 1.5rem; }
        .featured-btn { background: white; color: #667eea; padding: 0.75rem 1.5rem; border-radius: 5px; text-decoration: none; display: inline-block; font-weight: bold; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-layout { grid-template-columns: 1fr; }
            .sidebar { position: static; }
            .plugin-grid { grid-template-columns: 1fr; }
            .stats { flex-direction: column; gap: 2rem; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="/" class="logo">🛒 Shopologic</a>
                
                <form class="search-bar" method="GET" action="/plugins.php">
                    <input type="text" name="search" placeholder="Search plugins..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <button type="submit" class="search-btn">🔍</button>
                </form>
                
                <nav>
                    <ul class="nav-links">
                        <li><a href="/">Store</a></li>
                        <li><a href="/plugins.php" style="color: #007bff;">Plugins</a></li>
                        <li><a href="/themes.php">Themes</a></li>
                        <li><a href="/admin.php">Admin</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>🚀 Plugin Marketplace</h1>
            <p>Extend your store with powerful plugins built for Shopologic</p>
            
            <div class="stats">
                <div class="stat">
                    <div class="stat-number"><?php echo count($plugins); ?></div>
                    <div class="stat-label">Total Plugins</div>
                </div>
                <div class="stat">
                    <div class="stat-number"><?php echo count(array_filter($plugins, fn($p) => $p['active'] ?? false)); ?></div>
                    <div class="stat-label">Active Plugins</div>
                </div>
                <div class="stat">
                    <div class="stat-number"><?php echo count($categories); ?></div>
                    <div class="stat-label">Categories</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container">
        <!-- Featured Plugins -->
        <div class="featured-section">
            <h2 style="margin-bottom: 1.5rem;">🌟 Featured Plugins</h2>
            <div class="featured-grid">
                <div class="featured-card">
                    <span class="featured-badge">Most Popular</span>
                    <h3 class="featured-title">Advanced Email Marketing</h3>
                    <p class="featured-description">Complete email marketing automation with campaigns, templates, and analytics</p>
                    <a href="#" class="featured-btn">Learn More</a>
                </div>
                <div class="featured-card">
                    <span class="featured-badge">New Release</span>
                    <h3 class="featured-title">AI Product Recommendations</h3>
                    <p class="featured-description">Boost sales with intelligent product recommendations powered by machine learning</p>
                    <a href="#" class="featured-btn">Learn More</a>
                </div>
                <div class="featured-card">
                    <span class="featured-badge">Editor's Choice</span>
                    <h3 class="featured-title">Multi-Vendor Marketplace</h3>
                    <p class="featured-description">Transform your store into a thriving marketplace with vendor management</p>
                    <a href="#" class="featured-btn">Learn More</a>
                </div>
            </div>
        </div>

        <div class="main-layout">
            <!-- Sidebar -->
            <aside class="sidebar">
                <h3>Categories</h3>
                <ul class="category-list">
                    <li class="category-item">
                        <a href="/plugins.php" class="category-link <?php echo $selectedCategory === 'all' ? 'active' : ''; ?>">
                            <span>All Plugins</span>
                            <span class="category-count"><?php echo count($plugins); ?></span>
                        </a>
                    </li>
                    <?php foreach ($categories as $category => $categoryPlugins): ?>
                    <li class="category-item">
                        <a href="/plugins.php?category=<?php echo urlencode($category); ?>" 
                           class="category-link <?php echo $selectedCategory === $category ? 'active' : ''; ?>">
                            <span><?php echo htmlspecialchars($category); ?></span>
                            <span class="category-count"><?php echo count($categoryPlugins); ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </aside>

            <!-- Plugins Grid -->
            <section class="plugins-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <?php 
                        if ($searchQuery) {
                            echo 'Search Results for "' . htmlspecialchars($searchQuery) . '"';
                        } elseif ($selectedCategory !== 'all' && isset($categories[$selectedCategory])) {
                            echo htmlspecialchars($selectedCategory);
                        } else {
                            echo 'All Plugins';
                        }
                        ?>
                    </h2>
                    <select class="sort-dropdown">
                        <option>Most Popular</option>
                        <option>Newest First</option>
                        <option>Name (A-Z)</option>
                        <option>Recently Updated</option>
                    </select>
                </div>

                <div class="plugin-grid">
                    <?php 
                    // Filter plugins
                    $displayPlugins = $plugins;
                    if ($selectedCategory !== 'all' && isset($categories[$selectedCategory])) {
                        $displayPlugins = $categories[$selectedCategory];
                    }
                    if ($searchQuery) {
                        $displayPlugins = array_filter($displayPlugins, function($plugin) use ($searchQuery) {
                            return stripos($plugin['name'], $searchQuery) !== false || 
                                   stripos($plugin['description'] ?? '', $searchQuery) !== false;
                        });
                    }
                    
                    foreach ($displayPlugins as $plugin): 
                        // Generate random features for demo
                        $features = [
                            'Easy configuration',
                            'Multi-language support',
                            'Mobile optimized',
                            'Regular updates',
                            'Premium support'
                        ];
                        shuffle($features);
                        $pluginFeatures = array_slice($features, 0, rand(2, 4));
                    ?>
                    <div class="plugin-card">
                        <div class="plugin-header">
                            <div class="plugin-icon">
                                <?php 
                                $icons = ['🔌', '⚡', '🎯', '📊', '🛡️', '🚀', '💳', '📦', '🌐', '📱'];
                                echo $icons[array_rand($icons)];
                                ?>
                            </div>
                            <div class="plugin-status">
                                <?php if ($plugin['active'] ?? false): ?>
                                    <span class="status-badge badge-active">Active</span>
                                <?php endif; ?>
                                <?php if (rand(0, 3) === 0): ?>
                                    <span class="status-badge badge-new">New</span>
                                <?php endif; ?>
                                <?php if (rand(0, 4) === 0): ?>
                                    <span class="status-badge badge-premium">Premium</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <h3 class="plugin-name"><?php echo htmlspecialchars($plugin['name']); ?></h3>
                        <div class="plugin-author">by <?php echo htmlspecialchars($plugin['author'] ?? 'Shopologic Team'); ?></div>
                        <p class="plugin-description">
                            <?php echo htmlspecialchars(substr($plugin['description'] ?? 'Enhance your store with this powerful plugin.', 0, 150)); ?>...
                        </p>
                        
                        <ul class="plugin-features">
                            <?php foreach ($pluginFeatures as $feature): ?>
                            <li><?php echo $feature; ?></li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <div class="plugin-footer">
                            <div class="plugin-meta">
                                <span>v<?php echo htmlspecialchars($plugin['version']); ?></span>
                                <span>⭐ <?php echo rand(35, 50) / 10; ?></span>
                                <span>↓ <?php echo rand(100, 5000); ?></span>
                            </div>
                            <div class="plugin-actions">
                                <?php if ($plugin['active'] ?? false): ?>
                                    <button class="btn btn-outline">Settings</button>
                                <?php else: ?>
                                    <button class="btn btn-primary">Install</button>
                                <?php endif; ?>
                                <button class="btn btn-outline">Details</button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (empty($displayPlugins)): ?>
                <div style="text-align: center; padding: 4rem 2rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
                    <h3>No plugins found</h3>
                    <p style="color: #6c757d;">Try adjusting your search or browse all plugins.</p>
                </div>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <script>
        // Plugin installation simulation
        document.querySelectorAll('.btn-primary').forEach(btn => {
            if (btn.textContent === 'Install') {
                btn.addEventListener('click', function() {
                    this.textContent = 'Installing...';
                    this.disabled = true;
                    
                    setTimeout(() => {
                        this.textContent = 'Settings';
                        this.classList.remove('btn-primary');
                        this.classList.add('btn-outline');
                        this.disabled = false;
                        
                        // Update status
                        const card = this.closest('.plugin-card');
                        const statusDiv = card.querySelector('.plugin-status');
                        if (!statusDiv.querySelector('.badge-active')) {
                            const activeBadge = document.createElement('span');
                            activeBadge.className = 'status-badge badge-active';
                            activeBadge.textContent = 'Active';
                            statusDiv.insertBefore(activeBadge, statusDiv.firstChild);
                        }
                    }, 2000);
                });
            }
        });
    </script>
</body>
</html>