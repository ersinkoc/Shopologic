<?php $this->layout('layouts/main'); ?>

<?php $this->section('title', $title ?? 'Category'); ?>

<?php $this->section('content'); ?>
<div class="category-page">
    <div class="container">
        <!-- Breadcrumbs -->
        <nav class="breadcrumbs">
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                <?php if ($index === count($breadcrumbs) - 1): ?>
                    <span class="breadcrumb-current"><?php echo $this->e($crumb['name']); ?></span>
                <?php else: ?>
                    <a href="<?php echo $this->e($crumb['url']); ?>" class="breadcrumb-link">
                        <?php echo $this->e($crumb['name']); ?>
                    </a>
                    <span class="breadcrumb-separator">›</span>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <!-- Category Header -->
        <div class="category-header">
            <div class="category-info">
                <div class="category-icon-large">
                    <?php echo $category['icon']; ?>
                </div>
                <div class="category-details">
                    <h1 class="category-title"><?php echo $this->e($category['name']); ?></h1>
                    <p class="category-description"><?php echo $this->e($category['description']); ?></p>
                    <div class="category-stats">
                        <span class="product-count">
                            <?php echo number_format($pagination['total']); ?> 
                            <?php echo $pagination['total'] === 1 ? 'product' : 'products'; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Related Categories -->
            <?php if (!empty($related_categories)): ?>
                <div class="related-categories">
                    <h3>Related Categories</h3>
                    <div class="category-links">
                        <?php foreach ($related_categories as $relatedCategory): ?>
                            <a href="/category/<?php echo $this->e($relatedCategory['slug']); ?>" 
                               class="related-category-link">
                                <span class="category-icon"><?php echo $relatedCategory['icon']; ?></span>
                                <span class="category-name"><?php echo $this->e($relatedCategory['name']); ?></span>
                                <span class="product-count">(<?php echo $relatedCategory['product_count']; ?>)</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="category-content">
            <!-- Filters Sidebar -->
            <div class="category-sidebar">
                <div class="filters-container">
                    <h3>Refine Results</h3>
                    
                    <!-- Active Filters -->
                    <?php if (!empty($filters['brand']) || $filters['min_price'] !== null || $filters['max_price'] !== null): ?>
                        <div class="active-filters">
                            <h4>Active Filters:</h4>
                            <div class="filter-tags">
                                <?php if (!empty($filters['brand'])): ?>
                                    <span class="filter-tag">
                                        Brand: <?php echo $this->e($filters['brand']); ?>
                                        <a href="<?php echo $this->removeFilterUrl('brand'); ?>" class="remove-filter">×</a>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($filters['min_price'] !== null || $filters['max_price'] !== null): ?>
                                    <span class="filter-tag">
                                        Price: $<?php echo $filters['min_price'] ?? '0'; ?> - $<?php echo $filters['max_price'] ?? '∞'; ?>
                                        <a href="<?php echo $this->removeFilterUrl(['min_price', 'max_price']); ?>" class="remove-filter">×</a>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Brands Filter -->
                    <?php if (!empty($available_filters['brands'])): ?>
                        <div class="filter-section">
                            <h4>Brands</h4>
                            <div class="filter-options">
                                <?php foreach (array_slice($available_filters['brands'], 0, 10) as $brand): ?>
                                    <label class="filter-option">
                                        <input type="checkbox" 
                                               name="brand" 
                                               value="<?php echo $this->e($brand['name']); ?>"
                                               <?php echo $filters['brand'] === $brand['name'] ? 'checked' : ''; ?>
                                               onchange="updateFilter(this)">
                                        <span class="filter-label">
                                            <?php echo $this->e($brand['name']); ?>
                                            <span class="filter-count">(<?php echo $brand['count']; ?>)</span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Price Filter -->
                    <div class="filter-section">
                        <h4>Price Range</h4>
                        <div class="price-filter">
                            <div class="price-inputs">
                                <input type="number" 
                                       id="min-price" 
                                       placeholder="Min" 
                                       value="<?php echo $filters['min_price'] ?? ''; ?>"
                                       min="0">
                                <span>to</span>
                                <input type="number" 
                                       id="max-price" 
                                       placeholder="Max" 
                                       value="<?php echo $filters['max_price'] ?? ''; ?>"
                                       min="0">
                                <button type="button" onclick="applyPriceFilter()" class="btn btn-sm btn-primary">Apply</button>
                            </div>
                            
                            <div class="price-ranges">
                                <?php foreach ($available_filters['price_ranges'] as $range): ?>
                                    <label class="filter-option">
                                        <input type="radio" 
                                               name="price_range" 
                                               value="<?php echo $range['min'] . '-' . ($range['max'] ?? ''); ?>"
                                               onchange="applyPriceRange(<?php echo $range['min']; ?>, <?php echo $range['max'] ?? 'null'; ?>)">
                                        <span class="filter-label"><?php echo $this->e($range['label']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Other Filters -->
                    <div class="filter-section">
                        <h4>Availability</h4>
                        <div class="filter-options">
                            <label class="filter-option">
                                <input type="checkbox" 
                                       name="in_stock" 
                                       value="1"
                                       <?php echo $filters['in_stock'] ? 'checked' : ''; ?>
                                       onchange="updateFilter(this)">
                                <span class="filter-label">In Stock Only</span>
                            </label>
                            <label class="filter-option">
                                <input type="checkbox" 
                                       name="featured" 
                                       value="1"
                                       <?php echo $filters['featured'] ? 'checked' : ''; ?>
                                       onchange="updateFilter(this)">
                                <span class="filter-label">Featured Products</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Products Area -->
            <div class="category-main">
                <!-- Results Header with Sorting -->
                <div class="results-header">
                    <div class="results-info">
                        <?php if ($pagination['total'] > 0): ?>
                            <span class="results-count">
                                Showing <?php echo $pagination['from']; ?>-<?php echo $pagination['to']; ?> 
                                of <?php echo number_format($pagination['total']); ?> products
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="sort-options">
                        <label for="sort-select">Sort by:</label>
                        <select id="sort-select" onchange="updateSort(this.value)">
                            <?php foreach ($sort_options as $value => $label): ?>
                                <option value="<?php echo $this->e($value); ?>"
                                        <?php echo $current_sort === $value ? 'selected' : ''; ?>">
                                    <?php echo $this->e($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Products Grid -->
                <?php if (!empty($products)): ?>
                    <div class="products-grid">
                        <?php foreach ($products as $product): ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <a href="<?php echo $this->e($product['url']); ?>">
                                        <img src="/themes/default/assets/images/product-placeholder.jpg" 
                                             alt="<?php echo $this->e($product['name']); ?>"
                                             loading="lazy">
                                    </a>
                                    <?php if ($product['has_sale']): ?>
                                        <span class="sale-badge">-<?php echo $product['discount_percentage']; ?>%</span>
                                    <?php endif; ?>
                                    <?php if ($product['featured']): ?>
                                        <span class="featured-badge">Featured</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="product-info">
                                    <h3 class="product-title">
                                        <a href="<?php echo $this->e($product['url']); ?>">
                                            <?php echo $this->e($product['name']); ?>
                                        </a>
                                    </h3>
                                    
                                    <div class="product-meta">
                                        <span class="product-brand"><?php echo $this->e($product['brand']); ?></span>
                                    </div>
                                    
                                    <div class="product-rating">
                                        <div class="stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span class="star <?php echo $i <= $product['rating'] ? 'filled' : ''; ?>">★</span>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="rating-text"><?php echo $product['rating']; ?> (<?php echo $product['reviews_count']; ?>)</span>
                                    </div>
                                    
                                    <div class="product-price">
                                        <?php if ($product['has_sale']): ?>
                                            <span class="sale-price"><?php echo $this->money($product['sale_price']); ?></span>
                                            <span class="original-price"><?php echo $this->money($product['price']); ?></span>
                                        <?php else: ?>
                                            <span class="regular-price"><?php echo $this->money($product['price']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="product-stock">
                                        <?php if ($product['is_in_stock']): ?>
                                            <span class="in-stock">In Stock</span>
                                        <?php else: ?>
                                            <span class="out-of-stock">Out of Stock</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="product-actions">
                                        <?php if ($product['is_in_stock']): ?>
                                            <button type="button" 
                                                    class="btn btn-primary add-to-cart-btn"
                                                    data-product-id="<?php echo $product['id']; ?>"
                                                    onclick="addToCart(<?php echo $product['id']; ?>)">
                                                Add to Cart
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-secondary" disabled>
                                                Out of Stock
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" 
                                                class="btn btn-outline-secondary wishlist-btn"
                                                data-product-id="<?php echo $product['id']; ?>">
                                            <i class="icon-heart"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($pagination['total_pages'] > 1): ?>
                        <div class="pagination-container">
                            <nav class="pagination">
                                <?php if ($pagination['current_page'] > 1): ?>
                                    <a href="<?php echo $this->addPageParam($pagination['current_page'] - 1); ?>" 
                                       class="pagination-link prev">
                                        <i class="icon-arrow-left"></i> Previous
                                    </a>
                                <?php endif; ?>
                                
                                <div class="pagination-numbers">
                                    <?php 
                                    $start = max(1, $pagination['current_page'] - 2);
                                    $end = min($pagination['total_pages'], $pagination['current_page'] + 2);
                                    ?>
                                    
                                    <?php if ($start > 1): ?>
                                        <a href="<?php echo $this->addPageParam(1); ?>" class="pagination-link">1</a>
                                        <?php if ($start > 2): ?>
                                            <span class="pagination-ellipsis">...</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = $start; $i <= $end; $i++): ?>
                                        <?php if ($i === $pagination['current_page']): ?>
                                            <span class="pagination-link current"><?php echo $i; ?></span>
                                        <?php else: ?>
                                            <a href="<?php echo $this->addPageParam($i); ?>" class="pagination-link"><?php echo $i; ?></a>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    
                                    <?php if ($end < $pagination['total_pages']): ?>
                                        <?php if ($end < $pagination['total_pages'] - 1): ?>
                                            <span class="pagination-ellipsis">...</span>
                                        <?php endif; ?>
                                        <a href="<?php echo $this->addPageParam($pagination['total_pages']); ?>" class="pagination-link"><?php echo $pagination['total_pages']; ?></a>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                                    <a href="<?php echo $this->addPageParam($pagination['current_page'] + 1); ?>" 
                                       class="pagination-link next">
                                        Next <i class="icon-arrow-right"></i>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- No Results -->
                    <div class="no-results">
                        <div class="no-results-icon">
                            <i class="icon-search"></i>
                        </div>
                        <h3>No products found</h3>
                        <p>No products match your current filters in this category.</p>
                        
                        <div class="no-results-actions">
                            <a href="/category/<?php echo $this->e($category['slug']); ?>" class="btn btn-primary">
                                Clear Filters
                            </a>
                            <a href="/categories" class="btn btn-secondary">Browse Categories</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Category Page Styles */
.category-page {
    padding: 20px 0 40px;
}

.breadcrumbs {
    margin-bottom: 20px;
    font-size: 0.9rem;
}

.breadcrumb-link {
    color: var(--primary-color);
    text-decoration: none;
}

.breadcrumb-link:hover {
    text-decoration: underline;
}

.breadcrumb-separator {
    margin: 0 8px;
    color: var(--secondary-color);
}

.breadcrumb-current {
    color: var(--dark-color);
    font-weight: 500;
}

.category-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 40px;
    margin-bottom: 40px;
    padding: 30px;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-radius: 12px;
}

.category-info {
    display: flex;
    align-items: center;
    gap: 25px;
    flex: 1;
}

.category-icon-large {
    font-size: 4rem;
    width: 100px;
    height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary-color), #0056b3);
    border-radius: 50%;
    flex-shrink: 0;
}

.category-details {
    flex: 1;
}

.category-title {
    font-size: 2.5rem;
    margin-bottom: 10px;
    color: var(--dark-color);
}

.category-description {
    font-size: 1.1rem;
    color: var(--secondary-color);
    margin-bottom: 15px;
    line-height: 1.5;
}

.category-stats .product-count {
    color: var(--primary-color);
    font-weight: 600;
    font-size: 1.1rem;
}

.related-categories {
    min-width: 300px;
}

.related-categories h3 {
    margin-bottom: 15px;
    color: var(--dark-color);
    font-size: 1.2rem;
}

.category-links {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.related-category-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 15px;
    background: #fff;
    border-radius: 8px;
    text-decoration: none;
    color: inherit;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease;
}

.related-category-link:hover {
    transform: translateX(5px);
    color: var(--primary-color);
}

.related-category-link .category-icon {
    font-size: 1.5rem;
}

.related-category-link .category-name {
    flex: 1;
    font-weight: 500;
}

.related-category-link .product-count {
    font-size: 0.8rem;
    color: var(--secondary-color);
}

.category-content {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 40px;
}

.category-sidebar {
    background: #fff;
    border-radius: 12px;
    padding: 25px;
    height: fit-content;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* Reuse search page styles for filters and products */
.filters-container h3 {
    margin-bottom: 20px;
    color: var(--dark-color);
    border-bottom: 2px solid var(--primary-color);
    padding-bottom: 10px;
}

.filter-section {
    margin-bottom: 25px;
}

.filter-section h4 {
    margin-bottom: 15px;
    color: var(--dark-color);
    font-size: 1.1rem;
}

.filter-options {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-option {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 5px 0;
}

.filter-option input[type="checkbox"],
.filter-option input[type="radio"] {
    margin: 0;
}

.filter-label {
    flex: 1;
    font-size: 0.9rem;
}

.filter-count {
    color: var(--secondary-color);
    font-size: 0.8rem;
}

.price-filter {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.price-inputs {
    display: flex;
    align-items: center;
    gap: 8px;
}

.price-inputs input {
    width: 70px;
    padding: 5px 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 0.9rem;
}

.price-inputs span {
    font-size: 0.9rem;
    color: var(--secondary-color);
}

.category-main {
    min-width: 0;
}

/* Responsive Design */
@media (max-width: 768px) {
    .category-header {
        flex-direction: column;
        gap: 20px;
    }
    
    .category-info {
        flex-direction: column;
        text-align: center;
    }
    
    .related-categories {
        min-width: unset;
        width: 100%;
    }
    
    .category-content {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .category-sidebar {
        order: 2;
    }
    
    .category-main {
        order: 1;
    }
}
</style>

<script>
// Filter and sort functions (same as search page)
function updateFilter(checkbox) {
    const url = new URL(window.location);
    
    if (checkbox.checked) {
        url.searchParams.set(checkbox.name, checkbox.value);
    } else {
        url.searchParams.delete(checkbox.name);
    }
    
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

function updateSort(sortValue) {
    const url = new URL(window.location);
    url.searchParams.set('sort', sortValue);
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

function applyPriceFilter() {
    const minPrice = document.getElementById('min-price').value;
    const maxPrice = document.getElementById('max-price').value;
    
    const url = new URL(window.location);
    
    if (minPrice) {
        url.searchParams.set('min_price', minPrice);
    } else {
        url.searchParams.delete('min_price');
    }
    
    if (maxPrice) {
        url.searchParams.set('max_price', maxPrice);
    } else {
        url.searchParams.delete('max_price');
    }
    
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

function applyPriceRange(min, max) {
    const url = new URL(window.location);
    
    url.searchParams.set('min_price', min);
    if (max !== null) {
        url.searchParams.set('max_price', max);
    } else {
        url.searchParams.delete('max_price');
    }
    
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

// Add to cart functionality (same as search page)
function addToCart(productId) {
    const button = document.querySelector(`[data-product-id="${productId}"]`);
    const originalText = button.textContent;
    
    button.disabled = true;
    button.textContent = 'Adding...';
    
    fetch('/cart/add', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            product_id: productId,
            quantity: 1
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.textContent = 'Added!';
            button.classList.add('btn-success');
            
            // Update cart count in header
            const cartCounts = document.querySelectorAll('#header-cart-count, #header-cart-count-icon');
            cartCounts.forEach(el => {
                el.textContent = data.cart_count || '0';
            });
            
            setTimeout(() => {
                button.disabled = false;
                button.textContent = originalText;
                button.classList.remove('btn-success');
            }, 2000);
        } else {
            button.textContent = 'Error';
            setTimeout(() => {
                button.disabled = false;
                button.textContent = originalText;
            }, 2000);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        button.textContent = 'Error';
        setTimeout(() => {
            button.disabled = false;
            button.textContent = originalText;
        }, 2000);
    });
}
</script>

<?php $this->do_action('category.after_content'); ?>
<?php $this->endSection(); ?>