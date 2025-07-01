<?php
// Support categories
$categories = [
    ['icon' => '🚀', 'title' => 'Getting Started', 'count' => 24],
    ['icon' => '🛒', 'title' => 'Orders & Shipping', 'count' => 18],
    ['icon' => '💳', 'title' => 'Payments & Billing', 'count' => 15],
    ['icon' => '🔌', 'title' => 'Plugins & Extensions', 'count' => 32],
    ['icon' => '🎨', 'title' => 'Themes & Design', 'count' => 21],
    ['icon' => '⚙️', 'title' => 'Settings & Configuration', 'count' => 27],
    ['icon' => '📊', 'title' => 'Reports & Analytics', 'count' => 12],
    ['icon' => '🔐', 'title' => 'Security & Privacy', 'count' => 9]
];

// Popular articles
$articles = [
    ['title' => 'How to set up your first product', 'views' => 15234, 'helpful' => 98],
    ['title' => 'Configuring payment gateways', 'views' => 12456, 'helpful' => 96],
    ['title' => 'Managing shipping zones and rates', 'views' => 10234, 'helpful' => 94],
    ['title' => 'Installing and activating plugins', 'views' => 9876, 'helpful' => 97],
    ['title' => 'Customizing your store theme', 'views' => 8765, 'helpful' => 95]
];

// Video tutorials
$videos = [
    ['title' => 'Store Setup in 10 Minutes', 'duration' => '10:23', 'thumbnail' => 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=300&h=200&fit=crop'],
    ['title' => 'Advanced Product Management', 'duration' => '15:45', 'thumbnail' => 'https://images.unsplash.com/photo-1472851294608-062f824d29cc?w=300&h=200&fit=crop'],
    ['title' => 'Marketing & SEO Best Practices', 'duration' => '12:30', 'thumbnail' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=300&h=200&fit=crop']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Center - Shopologic</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8f9fa; }
        
        /* Header */
        .header { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 1rem 0; position: sticky; top: 0; z-index: 100; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 1rem; }
        .header-content { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.8rem; font-weight: bold; color: #007bff; text-decoration: none; }
        .nav-links { display: flex; gap: 1.5rem; list-style: none; }
        .nav-links a { text-decoration: none; color: #495057; padding: 0.5rem 1rem; }
        
        /* Hero Section */
        .hero { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 5rem 0; text-align: center; }
        .hero h1 { font-size: 3rem; margin-bottom: 1rem; }
        .hero p { font-size: 1.2rem; opacity: 0.9; margin-bottom: 2rem; }
        .search-container { max-width: 600px; margin: 0 auto; position: relative; }
        .search-input { width: 100%; padding: 1.25rem 3rem 1.25rem 1.5rem; border: none; border-radius: 50px; font-size: 1.1rem; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .search-btn { position: absolute; right: 5px; top: 50%; transform: translateY(-50%); background: #667eea; color: white; border: none; padding: 1rem 1.5rem; border-radius: 50px; cursor: pointer; }
        
        /* Quick Links */
        .quick-links { padding: 3rem 0; }
        .links-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
        .link-card { background: white; border-radius: 10px; padding: 2rem; text-align: center; transition: all 0.3s; cursor: pointer; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .link-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .link-icon { font-size: 3rem; margin-bottom: 1rem; }
        .link-title { font-size: 1.2rem; font-weight: bold; margin-bottom: 0.5rem; color: #343a40; }
        .link-count { color: #6c757d; }
        
        /* Main Content */
        .main-content { display: grid; grid-template-columns: 1fr 350px; gap: 2rem; margin: 2rem 0; }
        
        /* Articles Section */
        .articles-section { background: white; border-radius: 10px; padding: 2rem; }
        .section-header { display: flex; justify-content: between; align-items: center; margin-bottom: 1.5rem; }
        .section-title { font-size: 1.5rem; color: #343a40; }
        .view-all { color: #007bff; text-decoration: none; font-size: 0.9rem; }
        
        .article-list { list-style: none; }
        .article-item { padding: 1rem 0; border-bottom: 1px solid #f8f9fa; }
        .article-item:last-child { border-bottom: none; }
        .article-link { text-decoration: none; color: #343a40; display: block; transition: color 0.3s; }
        .article-link:hover { color: #007bff; }
        .article-meta { display: flex; gap: 1rem; margin-top: 0.5rem; font-size: 0.85rem; color: #6c757d; }
        
        /* Sidebar */
        .sidebar { display: flex; flex-direction: column; gap: 1.5rem; }
        
        /* Contact Card */
        .contact-card { background: white; border-radius: 10px; padding: 2rem; text-align: center; }
        .contact-title { font-size: 1.3rem; margin-bottom: 1rem; color: #343a40; }
        .contact-options { display: flex; flex-direction: column; gap: 1rem; }
        .contact-btn { padding: 0.75rem 1.5rem; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 0.5rem; transition: all 0.3s; }
        .contact-live { background: #28a745; color: white; }
        .contact-email { background: #007bff; color: white; }
        .contact-phone { background: #6c757d; color: white; }
        
        /* Status Card */
        .status-card { background: white; border-radius: 10px; padding: 1.5rem; }
        .status-header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; }
        .status-indicator { width: 12px; height: 12px; background: #28a745; border-radius: 50%; animation: pulse 2s infinite; }
        .status-title { font-weight: bold; color: #343a40; }
        .status-item { display: flex; justify-content: space-between; padding: 0.5rem 0; font-size: 0.9rem; }
        .status-name { color: #495057; }
        .status-value { color: #28a745; font-weight: bold; }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        /* Video Section */
        .video-section { background: white; border-radius: 10px; padding: 2rem; margin-top: 2rem; }
        .video-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-top: 1.5rem; }
        .video-card { position: relative; cursor: pointer; border-radius: 8px; overflow: hidden; }
        .video-thumbnail { width: 100%; height: 200px; object-fit: cover; }
        .video-overlay { position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(to top, rgba(0,0,0,0.8), transparent); color: white; padding: 1rem; }
        .video-title { font-weight: bold; margin-bottom: 0.25rem; }
        .video-duration { font-size: 0.85rem; opacity: 0.8; }
        .play-icon { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 60px; height: 60px; background: rgba(255,255,255,0.9); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; transition: all 0.3s; }
        .video-card:hover .play-icon { background: white; transform: translate(-50%, -50%) scale(1.1); }
        
        /* FAQ Section */
        .faq-section { background: #e7f3ff; padding: 3rem 0; margin-top: 3rem; }
        .faq-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 1rem; margin-top: 2rem; }
        .faq-item { background: white; padding: 1.5rem; border-radius: 8px; cursor: pointer; transition: all 0.3s; }
        .faq-item:hover { box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .faq-question { font-weight: bold; display: flex; justify-content: space-between; align-items: center; }
        .faq-arrow { transition: transform 0.3s; }
        .faq-answer { margin-top: 1rem; color: #6c757d; display: none; }
        .faq-item.active .faq-arrow { transform: rotate(180deg); }
        .faq-item.active .faq-answer { display: block; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-content { grid-template-columns: 1fr; }
            .hero h1 { font-size: 2rem; }
            .faq-grid { grid-template-columns: 1fr; }
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
                        <li><a href="/docs.php">Documentation</a></li>
                        <li><a href="/support.php" style="color: #667eea;">Support</a></li>
                        <li><a href="/community.php">Community</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>👋 How can we help you?</h1>
            <p>Search our knowledge base or browse categories below</p>
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Search for answers...">
                <button class="search-btn">Search</button>
            </div>
        </div>
    </section>

    <!-- Quick Links -->
    <section class="quick-links">
        <div class="container">
            <div class="links-grid">
                <?php foreach ($categories as $category): ?>
                <div class="link-card">
                    <div class="link-icon"><?php echo $category['icon']; ?></div>
                    <div class="link-title"><?php echo $category['title']; ?></div>
                    <div class="link-count"><?php echo $category['count']; ?> articles</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container">
        <div class="main-content">
            <!-- Articles Section -->
            <div class="articles-section">
                <div class="section-header">
                    <h2 class="section-title">📚 Popular Articles</h2>
                </div>
                <ul class="article-list">
                    <?php foreach ($articles as $article): ?>
                    <li class="article-item">
                        <a href="#" class="article-link">
                            <h3><?php echo $article['title']; ?></h3>
                            <div class="article-meta">
                                <span>👁️ <?php echo number_format($article['views']); ?> views</span>
                                <span>👍 <?php echo $article['helpful']; ?>% found helpful</span>
                            </div>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Sidebar -->
            <aside class="sidebar">
                <!-- Contact Card -->
                <div class="contact-card">
                    <h3 class="contact-title">Need Direct Help?</h3>
                    <p style="color: #6c757d; margin-bottom: 1.5rem;">Our support team is here to assist you</p>
                    <div class="contact-options">
                        <button class="contact-btn contact-live">
                            💬 Live Chat
                        </button>
                        <a href="mailto:support@shopologic.com" class="contact-btn contact-email">
                            ✉️ Email Support
                        </a>
                        <a href="tel:1-800-SHOP" class="contact-btn contact-phone">
                            📞 Call Us
                        </a>
                    </div>
                </div>

                <!-- System Status -->
                <div class="status-card">
                    <div class="status-header">
                        <div class="status-indicator"></div>
                        <div class="status-title">System Status</div>
                    </div>
                    <div class="status-item">
                        <span class="status-name">API</span>
                        <span class="status-value">Operational</span>
                    </div>
                    <div class="status-item">
                        <span class="status-name">Checkout</span>
                        <span class="status-value">Operational</span>
                    </div>
                    <div class="status-item">
                        <span class="status-name">Admin Panel</span>
                        <span class="status-value">Operational</span>
                    </div>
                    <div class="status-item">
                        <span class="status-name">CDN</span>
                        <span class="status-value">Operational</span>
                    </div>
                </div>
            </aside>
        </div>

        <!-- Video Tutorials -->
        <div class="video-section">
            <h2 class="section-title">🎥 Video Tutorials</h2>
            <div class="video-grid">
                <?php foreach ($videos as $video): ?>
                <div class="video-card">
                    <img src="<?php echo $video['thumbnail']; ?>" alt="<?php echo $video['title']; ?>" class="video-thumbnail">
                    <div class="video-overlay">
                        <div class="video-title"><?php echo $video['title']; ?></div>
                        <div class="video-duration">Duration: <?php echo $video['duration']; ?></div>
                    </div>
                    <div class="play-icon">▶️</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- FAQ Section -->
    <section class="faq-section">
        <div class="container">
            <h2 class="section-title" style="text-align: center; margin-bottom: 0.5rem;">❓ Frequently Asked Questions</h2>
            <p style="text-align: center; color: #6c757d;">Quick answers to common questions</p>
            
            <div class="faq-grid">
                <div class="faq-item">
                    <div class="faq-question">
                        How do I install a new plugin?
                        <span class="faq-arrow">⌄</span>
                    </div>
                    <div class="faq-answer">
                        To install a new plugin, go to your admin panel, navigate to Plugins > Add New, search for the plugin you want, and click Install. Once installed, click Activate to enable the plugin.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        Can I use multiple payment gateways?
                        <span class="faq-arrow">⌄</span>
                    </div>
                    <div class="faq-answer">
                        Yes! Shopologic supports multiple payment gateways simultaneously. You can enable as many as you need and customers will see all available options at checkout.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        How do I customize my store theme?
                        <span class="faq-arrow">⌄</span>
                    </div>
                    <div class="faq-answer">
                        Navigate to Appearance > Customize in your admin panel. From there, you can modify colors, fonts, layouts, and more. Changes are previewed in real-time before publishing.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        Is my store data backed up automatically?
                        <span class="faq-arrow">⌄</span>
                    </div>
                    <div class="faq-answer">
                        Yes, we perform automatic daily backups of all store data. You can also create manual backups anytime from Settings > Backups in your admin panel.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        // FAQ Toggle
        document.querySelectorAll('.faq-item').forEach(item => {
            item.addEventListener('click', () => {
                item.classList.toggle('active');
            });
        });

        // Search functionality
        document.querySelector('.search-btn').addEventListener('click', () => {
            const query = document.querySelector('.search-input').value;
            if (query) {
                alert('Searching for: ' + query);
                // In real app, this would perform actual search
            }
        });

        // Live chat simulation
        document.querySelector('.contact-live').addEventListener('click', () => {
            alert('Live chat would open here. Support hours: 24/7');
        });
    </script>
</body>
</html>