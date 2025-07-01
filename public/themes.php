<?php
// Demo themes data
$themes = [
    [
        'id' => 1,
        'name' => 'Modern Store',
        'author' => 'Shopologic Team',
        'price' => 0,
        'image' => 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=400&h=300&fit=crop',
        'rating' => 4.8,
        'downloads' => 15420,
        'category' => 'Minimalist',
        'active' => true,
        'features' => ['Responsive Design', 'SEO Optimized', 'Fast Loading', 'RTL Support']
    ],
    [
        'id' => 2,
        'name' => 'Fashion Hub',
        'author' => 'StyleCraft',
        'price' => 79,
        'image' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=400&h=300&fit=crop',
        'rating' => 4.9,
        'downloads' => 8934,
        'category' => 'Fashion',
        'active' => false,
        'features' => ['Mega Menu', 'Product Quick View', 'Instagram Feed', 'Lookbook']
    ],
    [
        'id' => 3,
        'name' => 'Tech Pro',
        'author' => 'DigitalThemes',
        'price' => 89,
        'image' => 'https://images.unsplash.com/photo-1472851294608-062f824d29cc?w=400&h=300&fit=crop',
        'rating' => 4.7,
        'downloads' => 6721,
        'category' => 'Electronics',
        'active' => false,
        'features' => ['Product Compare', 'Advanced Filters', 'Video Backgrounds', '3D Product View']
    ],
    [
        'id' => 4,
        'name' => 'Organic Market',
        'author' => 'GreenThemes',
        'price' => 69,
        'image' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=400&h=300&fit=crop',
        'rating' => 4.6,
        'downloads' => 4523,
        'category' => 'Food & Grocery',
        'active' => false,
        'features' => ['Recipe Blog', 'Store Locator', 'Nutrition Info', 'Subscription Box']
    ],
    [
        'id' => 5,
        'name' => 'Luxury Elite',
        'author' => 'PremiumDesigns',
        'price' => 149,
        'image' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400&h=300&fit=crop',
        'rating' => 5.0,
        'downloads' => 3210,
        'category' => 'Luxury',
        'active' => false,
        'features' => ['Parallax Effects', 'Custom Animations', 'VIP Member Area', 'AR Try-On']
    ],
    [
        'id' => 6,
        'name' => 'Handmade Crafts',
        'author' => 'ArtisanThemes',
        'price' => 59,
        'image' => 'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?w=400&h=300&fit=crop',
        'rating' => 4.5,
        'downloads' => 2890,
        'category' => 'Handmade',
        'active' => false,
        'features' => ['Vendor Profiles', 'Workshop Calendar', 'Custom Orders', 'Material Details']
    ]
];

// Theme categories
$themeCategories = [
    'All Themes' => count($themes),
    'Minimalist' => 8,
    'Fashion' => 12,
    'Electronics' => 6,
    'Food & Grocery' => 5,
    'Luxury' => 4,
    'Handmade' => 7,
    'Multi-Purpose' => 15
];

$selectedCategory = $_GET['category'] ?? 'all';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theme Store - Shopologic</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f5f6fa; }
        
        /* Header */
        .header { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 1rem 0; position: sticky; top: 0; z-index: 100; }
        .container { max-width: 1400px; margin: 0 auto; padding: 0 1rem; }
        .header-content { display: flex; justify-content: space-between; align-items: center; gap: 2rem; }
        .logo { font-size: 1.8rem; font-weight: bold; color: #007bff; text-decoration: none; }
        .nav-links { display: flex; gap: 1.5rem; list-style: none; }
        .nav-links a { text-decoration: none; color: #495057; padding: 0.5rem 1rem; border-radius: 5px; transition: all 0.3s; }
        .nav-links a:hover { background: #f8f9fa; }
        
        /* Hero */
        .hero { background: linear-gradient(135deg, #ff6b6b 0%, #ffd93d 100%); color: white; padding: 4rem 0; text-align: center; }
        .hero h1 { font-size: 3rem; margin-bottom: 1rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.2); }
        .hero p { font-size: 1.2rem; opacity: 0.9; margin-bottom: 2rem; }
        .hero-buttons { display: flex; gap: 1rem; justify-content: center; }
        .hero-btn { padding: 1rem 2rem; border: none; border-radius: 30px; font-size: 1.1rem; font-weight: bold; cursor: pointer; text-decoration: none; transition: all 0.3s; }
        .hero-btn.primary { background: white; color: #ff6b6b; }
        .hero-btn.secondary { background: transparent; color: white; border: 2px solid white; }
        
        /* Filters Bar */
        .filters-bar { background: white; padding: 1.5rem 0; border-bottom: 1px solid #dee2e6; position: sticky; top: 70px; z-index: 90; }
        .filters-content { display: flex; justify-content: space-between; align-items: center; gap: 2rem; }
        .filter-tabs { display: flex; gap: 1rem; flex-wrap: wrap; }
        .filter-tab { padding: 0.5rem 1.5rem; background: #f8f9fa; border: none; border-radius: 25px; cursor: pointer; transition: all 0.3s; }
        .filter-tab:hover { background: #e9ecef; }
        .filter-tab.active { background: #007bff; color: white; }
        .filter-options { display: flex; gap: 1rem; align-items: center; }
        .filter-select { padding: 0.5rem 1rem; border: 1px solid #dee2e6; border-radius: 5px; background: white; }
        
        /* Themes Grid */
        .themes-section { padding: 3rem 0; }
        .themes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 2rem; }
        .theme-card { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.08); transition: all 0.3s; position: relative; }
        .theme-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
        
        .theme-preview { position: relative; overflow: hidden; height: 250px; }
        .theme-image { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s; }
        .theme-card:hover .theme-image { transform: scale(1.05); }
        .theme-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); opacity: 0; transition: opacity 0.3s; display: flex; align-items: center; justify-content: center; gap: 1rem; }
        .theme-card:hover .theme-overlay { opacity: 1; }
        .overlay-btn { padding: 0.75rem 1.5rem; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; transition: all 0.3s; }
        .preview-btn { background: white; color: #333; }
        .preview-btn:hover { background: #f8f9fa; }
        .demo-btn { background: #007bff; color: white; }
        .demo-btn:hover { background: #0056b3; }
        
        .active-badge { position: absolute; top: 10px; right: 10px; background: #28a745; color: white; padding: 0.5rem 1rem; border-radius: 25px; font-size: 0.85rem; font-weight: bold; }
        
        .theme-content { padding: 1.5rem; }
        .theme-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; }
        .theme-info h3 { font-size: 1.3rem; margin-bottom: 0.25rem; color: #343a40; }
        .theme-author { color: #6c757d; font-size: 0.9rem; }
        .theme-price { font-size: 1.5rem; font-weight: bold; color: #28a745; }
        .theme-price.premium { color: #007bff; }
        
        .theme-rating { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; }
        .stars { color: #ffc107; }
        .rating-text { color: #6c757d; font-size: 0.9rem; }
        
        .theme-features { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
        .feature-tag { background: #e9ecef; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem; color: #495057; }
        
        .theme-actions { display: flex; gap: 0.5rem; }
        .theme-btn { flex: 1; padding: 0.75rem; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; transition: all 0.3s; }
        .install-btn { background: #007bff; color: white; }
        .install-btn:hover { background: #0056b3; }
        .customize-btn { background: #f8f9fa; color: #495057; border: 1px solid #dee2e6; }
        .customize-btn:hover { background: #e9ecef; }
        
        /* Theme Details Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 1000; overflow-y: auto; }
        .modal-content { background: white; max-width: 1200px; margin: 2rem auto; border-radius: 15px; overflow: hidden; }
        .modal-header { position: relative; }
        .modal-header img { width: 100%; height: 400px; object-fit: cover; }
        .close-modal { position: absolute; top: 20px; right: 20px; background: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 10px rgba(0,0,0,0.2); }
        .modal-body { padding: 2rem; }
        .modal-grid { display: grid; grid-template-columns: 1fr 350px; gap: 2rem; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .themes-grid { grid-template-columns: 1fr; }
            .modal-grid { grid-template-columns: 1fr; }
            .filters-content { flex-direction: column; align-items: stretch; }
            .hero h1 { font-size: 2rem; }
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
                        <li><a href="/">Store</a></li>
                        <li><a href="/plugins.php">Plugins</a></li>
                        <li><a href="/themes.php" style="color: #ff6b6b;">Themes</a></li>
                        <li><a href="/admin.php">Admin</a></li>
                        <li><a href="/support.php">Support</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>🎨 Beautiful Themes for Your Store</h1>
            <p>Choose from our collection of professionally designed themes</p>
            <div class="hero-buttons">
                <a href="#themes" class="hero-btn primary">Browse Themes</a>
                <a href="#" class="hero-btn secondary">Custom Design</a>
            </div>
        </div>
    </section>

    <!-- Filters Bar -->
    <section class="filters-bar">
        <div class="container">
            <div class="filters-content">
                <div class="filter-tabs">
                    <?php foreach ($themeCategories as $category => $count): ?>
                    <button class="filter-tab <?php echo ($selectedCategory === 'all' && $category === 'All Themes') || $selectedCategory === $category ? 'active' : ''; ?>">
                        <?php echo $category; ?> (<?php echo $count; ?>)
                    </button>
                    <?php endforeach; ?>
                </div>
                <div class="filter-options">
                    <select class="filter-select">
                        <option>Most Popular</option>
                        <option>Newest First</option>
                        <option>Price: Low to High</option>
                        <option>Price: High to Low</option>
                        <option>Best Rated</option>
                    </select>
                </div>
            </div>
        </div>
    </section>

    <!-- Themes Grid -->
    <section class="themes-section" id="themes">
        <div class="container">
            <div class="themes-grid">
                <?php foreach ($themes as $theme): ?>
                <div class="theme-card">
                    <div class="theme-preview">
                        <img src="<?php echo $theme['image']; ?>" alt="<?php echo $theme['name']; ?>" class="theme-image">
                        <?php if ($theme['active']): ?>
                        <span class="active-badge">Active Theme</span>
                        <?php endif; ?>
                        <div class="theme-overlay">
                            <button class="overlay-btn preview-btn" onclick="showThemeDetails(<?php echo $theme['id']; ?>)">
                                👁️ Preview
                            </button>
                            <a href="#" class="overlay-btn demo-btn">
                                🔗 Live Demo
                            </a>
                        </div>
                    </div>
                    
                    <div class="theme-content">
                        <div class="theme-header">
                            <div class="theme-info">
                                <h3><?php echo $theme['name']; ?></h3>
                                <div class="theme-author">by <?php echo $theme['author']; ?></div>
                            </div>
                            <div class="theme-price <?php echo $theme['price'] > 0 ? 'premium' : ''; ?>">
                                <?php echo $theme['price'] > 0 ? '$' . $theme['price'] : 'Free'; ?>
                            </div>
                        </div>
                        
                        <div class="theme-rating">
                            <span class="stars">
                                <?php 
                                $fullStars = floor($theme['rating']);
                                for ($i = 0; $i < $fullStars; $i++) echo '⭐';
                                ?>
                            </span>
                            <span class="rating-text"><?php echo $theme['rating']; ?> (<?php echo number_format($theme['downloads']); ?> downloads)</span>
                        </div>
                        
                        <div class="theme-features">
                            <?php foreach ($theme['features'] as $feature): ?>
                            <span class="feature-tag"><?php echo $feature; ?></span>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="theme-actions">
                            <?php if ($theme['active']): ?>
                                <button class="theme-btn customize-btn">Customize</button>
                                <button class="theme-btn customize-btn">Settings</button>
                            <?php else: ?>
                                <button class="theme-btn install-btn">
                                    <?php echo $theme['price'] > 0 ? 'Purchase & Install' : 'Install Theme'; ?>
                                </button>
                                <button class="theme-btn customize-btn">Details</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Theme Details Modal -->
    <div class="modal" id="themeModal">
        <div class="modal-content">
            <div class="modal-header">
                <img src="https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=1200&h=400&fit=crop" alt="Theme Preview">
                <div class="close-modal" onclick="closeModal()">✕</div>
            </div>
            <div class="modal-body">
                <div class="modal-grid">
                    <div>
                        <h2>Modern Store Theme</h2>
                        <p style="color: #6c757d; margin-bottom: 2rem;">A clean and modern theme perfect for any type of online store. Built with performance and user experience in mind.</p>
                        
                        <h3 style="margin-bottom: 1rem;">Key Features</h3>
                        <ul style="list-style: none; margin-bottom: 2rem;">
                            <li style="padding: 0.5rem 0;">✓ Fully responsive design</li>
                            <li style="padding: 0.5rem 0;">✓ SEO optimized structure</li>
                            <li style="padding: 0.5rem 0;">✓ Fast loading times</li>
                            <li style="padding: 0.5rem 0;">✓ RTL language support</li>
                            <li style="padding: 0.5rem 0;">✓ Customizable color schemes</li>
                            <li style="padding: 0.5rem 0;">✓ Multiple header layouts</li>
                        </ul>
                        
                        <h3 style="margin-bottom: 1rem;">Screenshots</h3>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                            <img src="https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=200&h=150&fit=crop" style="width: 100%; border-radius: 5px;">
                            <img src="https://images.unsplash.com/photo-1472851294608-062f824d29cc?w=200&h=150&fit=crop" style="width: 100%; border-radius: 5px;">
                            <img src="https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=200&h=150&fit=crop" style="width: 100%; border-radius: 5px;">
                        </div>
                    </div>
                    
                    <div>
                        <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 10px; margin-bottom: 1rem;">
                            <h3 style="margin-bottom: 1rem;">Theme Information</h3>
                            <div style="margin-bottom: 0.75rem;"><strong>Version:</strong> 2.1.0</div>
                            <div style="margin-bottom: 0.75rem;"><strong>Last Updated:</strong> June 15, 2025</div>
                            <div style="margin-bottom: 0.75rem;"><strong>Compatible With:</strong> Shopologic 3.0+</div>
                            <div style="margin-bottom: 0.75rem;"><strong>Support:</strong> 6 months included</div>
                        </div>
                        
                        <button style="width: 100%; padding: 1rem; background: #007bff; color: white; border: none; border-radius: 5px; font-size: 1.1rem; font-weight: bold; cursor: pointer; margin-bottom: 0.5rem;">
                            Install This Theme
                        </button>
                        <button style="width: 100%; padding: 1rem; background: white; color: #495057; border: 1px solid #dee2e6; border-radius: 5px; font-weight: bold; cursor: pointer;">
                            View Live Demo
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showThemeDetails(themeId) {
            document.getElementById('themeModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal() {
            document.getElementById('themeModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('themeModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>