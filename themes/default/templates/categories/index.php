<?php $this->layout('layouts/main'); ?>

<?php $this->section('title', $title ?? 'Categories'); ?>

<?php $this->section('content'); ?>
<div class="categories-page">
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <h1>Shop by Category</h1>
                <p class="page-description">
                    Browse our <?php echo $total_categories; ?> categories featuring 
                    <?php echo number_format($total_products); ?> products
                </p>
            </div>
            
            <!-- Category Stats -->
            <div class="category-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo $total_categories; ?></span>
                    <span class="stat-label">Categories</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($total_products); ?></span>
                    <span class="stat-label">Products</span>
                </div>
            </div>
        </div>

        <!-- Categories Grid -->
        <div class="categories-grid">
            <?php foreach ($categories as $category): ?>
                <div class="category-card">
                    <div class="category-header">
                        <div class="category-icon">
                            <?php echo $category['icon']; ?>
                        </div>
                        <div class="category-info">
                            <h3 class="category-name">
                                <a href="/category/<?php echo $this->e($category['slug']); ?>">
                                    <?php echo $this->e($category['name']); ?>
                                </a>
                            </h3>
                            <p class="category-description">
                                <?php echo $this->e($category['description']); ?>
                            </p>
                            <div class="category-meta">
                                <span class="product-count">
                                    <?php echo number_format($category['product_count']); ?> 
                                    <?php echo $category['product_count'] === 1 ? 'product' : 'products'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Featured Products Preview -->
                    <?php if (!empty($category['featured_products'])): ?>
                        <div class="category-preview">
                            <h4>Featured Products</h4>
                            <div class="featured-products">
                                <?php foreach ($category['featured_products'] as $product): ?>
                                    <div class="featured-product">
                                        <a href="<?php echo $this->e($product['url']); ?>" class="product-link">
                                            <div class="product-image">
                                                <img src="/themes/default/assets/images/product-placeholder.jpg" 
                                                     alt="<?php echo $this->e($product['name']); ?>"
                                                     loading="lazy">
                                            </div>
                                            <div class="product-info">
                                                <span class="product-name"><?php echo $this->e($product['name']); ?></span>
                                                <span class="product-price">
                                                    <?php if ($product['has_sale']): ?>
                                                        <span class="sale-price"><?php echo $this->money($product['sale_price']); ?></span>
                                                        <span class="original-price"><?php echo $this->money($product['price']); ?></span>
                                                    <?php else: ?>
                                                        <span class="regular-price"><?php echo $this->money($product['price']); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="category-actions">
                        <a href="/category/<?php echo $this->e($category['slug']); ?>" 
                           class="btn btn-primary category-btn">
                            Shop <?php echo $this->e($category['name']); ?>
                        </a>
                        <a href="/search?category=<?php echo $this->e($category['slug']); ?>" 
                           class="btn btn-outline-secondary search-btn">
                            <i class="icon-search"></i>
                            Search in Category
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Popular Categories Section -->
        <div class="popular-categories-section">
            <h2>Most Popular Categories</h2>
            <div class="popular-categories">
                <?php foreach (array_slice($categories, 0, 6) as $category): ?>
                    <a href="/category/<?php echo $this->e($category['slug']); ?>" 
                       class="popular-category">
                        <span class="category-icon"><?php echo $category['icon']; ?></span>
                        <span class="category-name"><?php echo $this->e($category['name']); ?></span>
                        <span class="product-count"><?php echo $category['product_count']; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2>Quick Actions</h2>
            <div class="action-buttons">
                <a href="/products" class="action-btn">
                    <i class="icon-grid"></i>
                    <span>View All Products</span>
                </a>
                <a href="/search" class="action-btn">
                    <i class="icon-search"></i>
                    <span>Advanced Search</span>
                </a>
                <a href="/products?featured=1" class="action-btn">
                    <i class="icon-star"></i>
                    <span>Featured Products</span>
                </a>
                <a href="/products?sort=newest" class="action-btn">
                    <i class="icon-clock"></i>
                    <span>New Arrivals</span>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
/* Categories Page Styles */
.categories-page {
    padding: 40px 0;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 40px;
    padding: 30px;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-radius: 12px;
}

.page-title h1 {
    font-size: 2.5rem;
    margin-bottom: 10px;
    color: var(--dark-color);
}

.page-description {
    font-size: 1.1rem;
    color: var(--secondary-color);
    margin: 0;
}

.category-stats {
    display: flex;
    gap: 30px;
}

.stat-item {
    text-align: center;
}

.stat-number {
    display: block;
    font-size: 2rem;
    font-weight: bold;
    color: var(--primary-color);
}

.stat-label {
    display: block;
    font-size: 0.9rem;
    color: var(--secondary-color);
    text-transform: uppercase;
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 30px;
    margin-bottom: 60px;
}

.category-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    padding: 25px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.category-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.category-header {
    display: flex;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 20px;
}

.category-icon {
    font-size: 3rem;
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary-color), #0056b3);
    border-radius: 50%;
    flex-shrink: 0;
}

.category-info {
    flex: 1;
}

.category-name a {
    font-size: 1.4rem;
    font-weight: 600;
    color: var(--dark-color);
    text-decoration: none;
    margin-bottom: 8px;
    display: block;
}

.category-name a:hover {
    color: var(--primary-color);
}

.category-description {
    color: var(--secondary-color);
    line-height: 1.5;
    margin-bottom: 10px;
}

.category-meta {
    display: flex;
    align-items: center;
    gap: 15px;
}

.product-count {
    color: var(--primary-color);
    font-weight: 500;
    font-size: 0.9rem;
}

.category-preview {
    margin: 20px 0;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.category-preview h4 {
    margin-bottom: 15px;
    color: var(--dark-color);
    font-size: 1.1rem;
}

.featured-products {
    display: flex;
    gap: 15px;
    overflow-x: auto;
    padding-bottom: 10px;
}

.featured-product {
    flex-shrink: 0;
    width: 120px;
}

.product-link {
    text-decoration: none;
    color: inherit;
    display: block;
}

.featured-product .product-image {
    width: 100%;
    height: 80px;
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 8px;
}

.featured-product .product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.featured-product .product-info {
    text-align: center;
}

.featured-product .product-name {
    display: block;
    font-size: 0.8rem;
    font-weight: 500;
    margin-bottom: 5px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.featured-product .product-price {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--primary-color);
}

.category-actions {
    display: flex;
    gap: 10px;
    justify-content: space-between;
    margin-top: 20px;
}

.category-btn {
    flex: 1;
}

.search-btn {
    flex: 0.8;
}

.popular-categories-section {
    margin-bottom: 50px;
}

.popular-categories-section h2 {
    margin-bottom: 25px;
    text-align: center;
    color: var(--dark-color);
}

.popular-categories {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
}

.popular-category {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    text-decoration: none;
    color: inherit;
    transition: transform 0.3s ease;
}

.popular-category:hover {
    transform: translateY(-3px);
    color: var(--primary-color);
}

.popular-category .category-icon {
    font-size: 2rem;
    margin-bottom: 10px;
    width: auto;
    height: auto;
    background: none;
}

.popular-category .category-name {
    font-weight: 600;
    margin-bottom: 5px;
    text-align: center;
}

.popular-category .product-count {
    font-size: 0.8rem;
    color: var(--secondary-color);
}

.quick-actions {
    text-align: center;
}

.quick-actions h2 {
    margin-bottom: 30px;
    color: var(--dark-color);
}

.action-buttons {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    max-width: 800px;
    margin: 0 auto;
}

.action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 20px;
    background: var(--primary-color);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 500;
    transition: background-color 0.3s ease;
}

.action-btn:hover {
    background: #0056b3;
    color: white;
}

.action-btn i {
    font-size: 1.2rem;
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }
    
    .category-stats {
        justify-content: center;
    }
    
    .categories-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .category-header {
        flex-direction: column;
        text-align: center;
    }
    
    .category-actions {
        flex-direction: column;
    }
    
    .popular-categories {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .action-buttons {
        grid-template-columns: 1fr;
    }
}
</style>

<?php $this->do_action('categories.after_content'); ?>
<?php $this->endSection(); ?>