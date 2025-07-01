<?php $this->layout('layouts/main'); ?>

<?php $this->section('title', ($title ?? 'Search Results') . ' - Shopologic'); ?>

<?php $this->section('content'); ?>
<div class="search-page">
    <div class="container">
        <!-- Search Header -->
        <div class="search-header">
            <div class="search-title">
                <?php if (!empty($query)): ?>
                    <h1>Search Results for "<?php echo $this->e($query); ?>"</h1>
                    <p class="search-stats">
                        Showing <?php echo $pagination['from']; ?>-<?php echo $pagination['to']; ?> 
                        of <?php echo number_format($pagination['total']); ?> results
                    </p>
                <?php else: ?>
                    <h1>All Products</h1>
                    <p class="search-stats">
                        <?php echo number_format($pagination['total']); ?> products available
                    </p>
                <?php endif; ?>
            </div>
            
            <!-- Search Form -->
            <div class="search-form-container">
                <form action="/search" method="get" class="search-form">
                    <div class="search-input-wrapper">
                        <input type="text" 
                               name="q" 
                               class="search-input" 
                               placeholder="Search products..." 
                               value="<?php echo $this->e($query); ?>"
                               autocomplete="off"
                               id="search-input">
                        <button type="submit" class="search-button">
                            <i class="icon-search"></i>
                            Search
                        </button>
                        <div id="search-suggestions" class="search-suggestions" style="display: none;"></div>
                    </div>
                    
                    <!-- Preserve other filters -->
                    <?php if (!empty($filters['category'])): ?>
                        <input type="hidden" name="category" value="<?php echo $this->e($filters['category']); ?>">
                    <?php endif; ?>
                    <?php if (!empty($filters['brand'])): ?>
                        <input type="hidden" name="brand" value="<?php echo $this->e($filters['brand']); ?>">
                    <?php endif; ?>
                    <?php if ($filters['min_price'] !== null): ?>
                        <input type="hidden" name="min_price" value="<?php echo $filters['min_price']; ?>">
                    <?php endif; ?>
                    <?php if ($filters['max_price'] !== null): ?>
                        <input type="hidden" name="max_price" value="<?php echo $filters['max_price']; ?>">
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="search-content">
            <!-- Filters Sidebar -->
            <div class="search-sidebar">
                <div class="filters-container">
                    <h3>Refine Results</h3>
                    
                    <!-- Active Filters -->
                    <?php if (!empty($query) || !empty($filters['category']) || !empty($filters['brand']) || $filters['min_price'] !== null || $filters['max_price'] !== null): ?>
                        <div class="active-filters">
                            <h4>Active Filters:</h4>
                            <div class="filter-tags">
                                <?php if (!empty($query)): ?>
                                    <span class="filter-tag">
                                        Search: <?php echo $this->e($query); ?>
                                        <a href="/search" class="remove-filter">×</a>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($filters['category'])): ?>
                                    <span class="filter-tag">
                                        Category: <?php echo $this->e($available_filters['categories'][array_search($filters['category'], array_column($available_filters['categories'], 'slug'))]['name'] ?? $filters['category']); ?>
                                        <a href="<?php echo $this->removeFilterUrl('category'); ?>" class="remove-filter">×</a>
                                    </span>
                                <?php endif; ?>
                                
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
                    
                    <!-- Categories Filter -->
                    <?php if (!empty($available_filters['categories'])): ?>
                        <div class="filter-section">
                            <h4>Categories</h4>
                            <div class="filter-options">
                                <?php foreach ($available_filters['categories'] as $category): ?>
                                    <label class="filter-option">
                                        <input type="checkbox" 
                                               name="category" 
                                               value="<?php echo $this->e($category['slug']); ?>"
                                               <?php echo $filters['category'] === $category['slug'] ? 'checked' : ''; ?>
                                               onchange="updateFilter(this)">
                                        <span class="filter-label">
                                            <?php echo $this->e($category['name']); ?>
                                            <span class="filter-count">(<?php echo $category['count']; ?>)</span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
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

            <!-- Main Results -->
            <div class="search-main">
                <!-- Results Header with Sorting -->
                <div class="results-header">
                    <div class="results-info">
                        <?php if ($pagination['total'] > 0): ?>
                            <span class="results-count">
                                <?php echo number_format($pagination['total']); ?> 
                                <?php echo $pagination['total'] === 1 ? 'product' : 'products'; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="sort-options">
                        <label for="sort-select">Sort by:</label>
                        <select id="sort-select" onchange="updateSort(this.value)">
                            <?php foreach ($sort_options as $value => $label): ?>
                                <option value="<?php echo $this->e($value); ?>"
                                        <?php echo $current_sort === $value ? 'selected' : ''; ?>>
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
                                        <span class="product-category"><?php echo $this->e($product['category_name']); ?></span>
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
                        <?php if (!empty($query)): ?>
                            <p>We couldn't find any products matching "<?php echo $this->e($query); ?>"</p>
                            <div class="no-results-suggestions">
                                <h4>Try:</h4>
                                <ul>
                                    <li>Checking your spelling</li>
                                    <li>Using more general keywords</li>
                                    <li>Browsing our categories</li>
                                    <li>Removing some filters</li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <p>No products match your current filters.</p>
                        <?php endif; ?>
                        
                        <div class="no-results-actions">
                            <a href="/search" class="btn btn-primary">View All Products</a>
                            <a href="/" class="btn btn-secondary">Back to Home</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php $this->do_action('search.after_content'); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search-input');
    const suggestionsContainer = document.getElementById('search-suggestions');
    let searchTimeout;
    
    // Search suggestions
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        clearTimeout(searchTimeout);
        
        if (query.length < 2) {
            suggestionsContainer.style.display = 'none';
            return;
        }
        
        searchTimeout = setTimeout(() => {
            fetchSuggestions(query);
        }, 300);
    });
    
    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
            suggestionsContainer.style.display = 'none';
        }
    });
    
    function fetchSuggestions(query) {
        fetch(`/search/suggestions?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.suggestions.length > 0) {
                    showSuggestions(data.suggestions);
                } else {
                    suggestionsContainer.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error fetching suggestions:', error);
                suggestionsContainer.style.display = 'none';
            });
    }
    
    function showSuggestions(suggestions) {
        let html = '';
        suggestions.forEach(suggestion => {
            html += `
                <a href="${suggestion.url}" class="suggestion-item ${suggestion.type}">
                    <span class="suggestion-text">${suggestion.text}</span>
                    ${suggestion.label ? `<span class="suggestion-label">${suggestion.label}</span>` : ''}
                    ${suggestion.price ? `<span class="suggestion-price">$${suggestion.price}</span>` : ''}
                </a>
            `;
        });
        
        suggestionsContainer.innerHTML = html;
        suggestionsContainer.style.display = 'block';
    }
});

// Filter and sort functions
function updateFilter(checkbox) {
    const url = new URL(window.location);
    
    if (checkbox.checked) {
        url.searchParams.set(checkbox.name, checkbox.value);
    } else {
        url.searchParams.delete(checkbox.name);
    }
    
    // Reset to page 1 when filtering
    url.searchParams.delete('page');
    
    window.location.href = url.toString();
}

function updateSort(sortValue) {
    const url = new URL(window.location);
    url.searchParams.set('sort', sortValue);
    url.searchParams.delete('page'); // Reset to page 1
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

// Add to cart functionality
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
<?php $this->endSection(); ?>