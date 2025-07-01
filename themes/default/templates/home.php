<?php $this->layout('layouts/main'); ?>

<?php $this->startBlock('content'); ?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <?php $this->do_action('home.before_hero'); ?>
        
        <div class="hero-content">
            <h1>Welcome to Shopologic</h1>
            <p>Discover amazing products at unbeatable prices</p>
            <a href="<?php echo $this->url('shop'); ?>" class="btn btn-primary">Shop Now</a>
        </div>
        
        <?php $this->do_action('home.after_hero'); ?>
    </div>
</section>

<!-- Featured Categories -->
<?php if (!empty($categories)): ?>
<section class="featured-categories">
    <div class="container">
        <h2>Shop by Category</h2>
        <div class="category-grid">
            <?php foreach ($categories as $category): ?>
                <div class="category-card">
                    <a href="<?php echo $this->url('category/' . $category->slug); ?>">
                        <?php if (!empty($category->image)): ?>
                            <img src="<?php echo $this->e($category->image); ?>" alt="<?php echo $this->e($category->name); ?>">
                        <?php else: ?>
                            <div class="category-placeholder">
                                <span><?php echo $this->e($category->icon ?? substr($category->name, 0, 1)); ?></span>
                            </div>
                        <?php endif; ?>
                        <h3><?php echo $this->e($category->name); ?></h3>
                        <?php if (!empty($category->product_count)): ?>
                            <span class="product-count"><?php echo $category->product_count; ?> Products</span>
                        <?php endif; ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Featured Products -->
<?php if (!empty($featured_products)): ?>
<section class="featured-products">
    <div class="container">
        <h2>Featured Products</h2>
        <div class="product-grid">
            <?php foreach ($featured_products as $product): ?>
                <?php $this->partial('partials/product-card', ['product' => $product]); ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- New Arrivals -->
<?php if (!empty($new_arrivals)): ?>
<section class="new-arrivals">
    <div class="container">
        <h2>New Arrivals</h2>
        <div class="product-grid">
            <?php foreach ($new_arrivals as $product): ?>
                <?php $this->partial('partials/product-card', ['product' => $product]); ?>
            <?php endforeach; ?>
        </div>
        <div class="section-footer">
            <a href="<?php echo $this->url('products/new'); ?>" class="btn btn-secondary">View All New Products</a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Best Sellers -->
<?php if (!empty($best_sellers)): ?>
<section class="best-sellers">
    <div class="container">
        <h2>Best Sellers</h2>
        <div class="product-grid">
            <?php foreach ($best_sellers as $product): ?>
                <?php $this->partial('partials/product-card', ['product' => $product]); ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Special Offers -->
<?php if (!empty($special_offers)): ?>
<section class="special-offers">
    <div class="container">
        <h2>Special Offers</h2>
        <div class="offer-banner">
            <div class="offer-content">
                <h3>Limited Time Offer!</h3>
                <p>Get up to 50% off on selected items</p>
                <a href="<?php echo $this->url('deals'); ?>" class="btn btn-warning">Shop Deals</a>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Newsletter Signup -->
<section class="newsletter-section">
    <div class="container">
        <div class="newsletter-content">
            <h2>Stay Updated</h2>
            <p>Subscribe to our newsletter and get exclusive offers!</p>
            <form action="<?php echo $this->url('newsletter/subscribe'); ?>" method="post" class="newsletter-inline-form">
                <?php echo $this->csrf_field(); ?>
                <input type="email" name="email" placeholder="Enter your email address" required>
                <button type="submit">Subscribe</button>
            </form>
        </div>
    </div>
</section>

<!-- Benefits -->
<section class="benefits">
    <div class="container">
        <div class="benefits-grid">
            <div class="benefit-item">
                <div class="benefit-icon">🚚</div>
                <h3>Free Shipping</h3>
                <p>On orders over $50</p>
            </div>
            <div class="benefit-item">
                <div class="benefit-icon">↩️</div>
                <h3>Easy Returns</h3>
                <p>30-day return policy</p>
            </div>
            <div class="benefit-item">
                <div class="benefit-icon">🔒</div>
                <h3>Secure Payment</h3>
                <p>100% secure transactions</p>
            </div>
            <div class="benefit-item">
                <div class="benefit-icon">📞</div>
                <h3>24/7 Support</h3>
                <p>Dedicated customer service</p>
            </div>
        </div>
    </div>
</section>

<?php $this->do_action('home.after_content'); ?>

<?php $this->endBlock(); ?>

<?php $this->startBlock('scripts'); ?>
<script>
// Home page specific JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Example: Lazy load images
    const images = document.querySelectorAll('img[data-src]');
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
                observer.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
});
</script>
<?php $this->endBlock(); ?>