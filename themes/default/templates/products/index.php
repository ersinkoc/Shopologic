<?php $this->layout('layouts/main'); ?>

<?php $this->startBlock('content'); ?>

<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <div class="row">
            <div class="col-md-8">
                <h1><?php echo $this->e($title); ?></h1>
                <p class="lead"><?php echo $this->e($description ?? 'Browse our complete product catalog'); ?></p>
            </div>
            <div class="col-md-4">
                <!-- Search and Filters -->
                <div class="search-filters">
                    <form action="<?php echo $this->url('products'); ?>" method="get" class="search-form">
                        <div class="input-group">
                            <input type="text" name="q" class="form-control" placeholder="Search products..." 
                                   value="<?php echo $this->e($current_search ?? ''); ?>">
                            <?php if (!empty($current_category)): ?>
                                <input type="hidden" name="category" value="<?php echo $this->e($current_category); ?>">
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary">Search</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="products-section">
    <div class="container">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3">
                <div class="sidebar">
                    <!-- Categories -->
                    <div class="widget">
                        <h4>Categories</h4>
                        <ul class="category-list">
                            <li class="<?php echo empty($current_category) ? 'active' : ''; ?>">
                                <a href="<?php echo $this->url('products'); ?>">All Products</a>
                                <span class="count">(<?php echo $total_products; ?>)</span>
                            </li>
                            <?php if (!empty($categories)): ?>
                                <?php foreach ($categories as $category): ?>
                                    <li class="<?php echo $current_category === $category->slug ? 'active' : ''; ?>">
                                        <a href="<?php echo $this->url('products?category=' . urlencode($category->slug)); ?>">
                                            <span class="icon"><?php echo $category->icon; ?></span>
                                            <?php echo $this->e($category->name); ?>
                                        </a>
                                        <span class="count">(<?php echo $category->product_count; ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <!-- Price Filter -->
                    <div class="widget">
                        <h4>Price Range</h4>
                        <div class="price-filter">
                            <div class="price-inputs">
                                <input type="number" placeholder="Min" class="form-control" id="price-min">
                                <span>to</span>
                                <input type="number" placeholder="Max" class="form-control" id="price-max">
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" id="apply-price-filter">Apply</button>
                        </div>
                    </div>
                    
                    <!-- Features -->
                    <div class="widget">
                        <h4>Features</h4>
                        <div class="feature-filters">
                            <label class="checkbox-label">
                                <input type="checkbox" name="in_stock" value="1"> In Stock Only
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="on_sale" value="1"> On Sale
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="new_arrivals" value="1"> New Arrivals
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="featured" value="1"> Featured
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9">
                <!-- Toolbar -->
                <div class="products-toolbar">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="results-info">
                                <?php if (!empty($current_search)): ?>
                                    <p>Showing results for: <strong>"<?php echo $this->e($current_search); ?>"</strong></p>
                                <?php endif; ?>
                                <p class="product-count">
                                    Showing <?php echo count($products); ?> of <?php echo $total_products; ?> products
                                    <?php if ($total_pages > 1): ?>
                                        (Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>)
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="sort-options">
                                <form action="<?php echo $this->url('products'); ?>" method="get" class="sort-form">
                                    <?php if (!empty($current_category)): ?>
                                        <input type="hidden" name="category" value="<?php echo $this->e($current_category); ?>">
                                    <?php endif; ?>
                                    <?php if (!empty($current_search)): ?>
                                        <input type="hidden" name="q" value="<?php echo $this->e($current_search); ?>">
                                    <?php endif; ?>
                                    <label for="sort">Sort by:</label>
                                    <select name="sort" id="sort" class="form-control" onchange="this.form.submit()">
                                        <option value="newest" <?php echo $current_sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                        <option value="price_low" <?php echo $current_sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                        <option value="price_high" <?php echo $current_sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                        <option value="name_asc" <?php echo $current_sort === 'name_asc' ? 'selected' : ''; ?>>Name: A to Z</option>
                                        <option value="name_desc" <?php echo $current_sort === 'name_desc' ? 'selected' : ''; ?>>Name: Z to A</option>
                                        <option value="rating" <?php echo $current_sort === 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                                    </select>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Products Grid -->
                <?php if (!empty($products)): ?>
                    <div class="products-grid">
                        <div class="row">
                            <?php foreach ($products as $product): ?>
                                <div class="col-lg-4 col-md-6 col-sm-6 mb-4">
                                    <?php $this->partial('partials/product-card', ['product' => $product]); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination-wrapper">
                            <nav aria-label="Product pagination">
                                <ul class="pagination justify-content-center">
                                    <?php if (!empty($pagination['prev'])): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo $this->e($pagination['prev']); ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($pagination['pages'])): ?>
                                        <?php foreach ($pagination['pages'] as $page): ?>
                                            <li class="page-item <?php echo $page['current'] ? 'active' : ''; ?>">
                                                <a class="page-link" href="<?php echo $this->e($page['url']); ?>">
                                                    <?php echo $page['number']; ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($pagination['next'])): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo $this->e($pagination['next']); ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="no-products">
                        <div class="empty-state">
                            <div class="empty-icon">🔍</div>
                            <h3>No products found</h3>
                            <?php if (!empty($current_search)): ?>
                                <p>We couldn't find any products matching your search for "<strong><?php echo $this->e($current_search); ?></strong>".</p>
                                <a href="<?php echo $this->url('products'); ?>" class="btn btn-primary">View All Products</a>
                            <?php elseif (!empty($current_category)): ?>
                                <p>There are no products in this category yet.</p>
                                <a href="<?php echo $this->url('products'); ?>" class="btn btn-primary">Browse All Categories</a>
                            <?php else: ?>
                                <p>No products are available at the moment.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php $this->endBlock(); ?>

<?php $this->startBlock('scripts'); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Price filter
    const priceMinInput = document.getElementById('price-min');
    const priceMaxInput = document.getElementById('price-max');
    const applyPriceFilter = document.getElementById('apply-price-filter');
    
    if (applyPriceFilter) {
        applyPriceFilter.addEventListener('click', function() {
            const min = priceMinInput.value;
            const max = priceMaxInput.value;
            const url = new URL(window.location);
            
            if (min) url.searchParams.set('price_min', min);
            else url.searchParams.delete('price_min');
            
            if (max) url.searchParams.set('price_max', max);
            else url.searchParams.delete('price_max');
            
            window.location.href = url.toString();
        });
    }
    
    // Feature filters
    const featureCheckboxes = document.querySelectorAll('.feature-filters input[type="checkbox"]');
    featureCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const url = new URL(window.location);
            
            if (this.checked) {
                url.searchParams.set(this.name, this.value);
            } else {
                url.searchParams.delete(this.name);
            }
            
            window.location.href = url.toString();
        });
    });
});
</script>
<?php $this->endBlock(); ?>