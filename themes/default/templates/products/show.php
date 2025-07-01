<?php $this->layout('layouts/main'); ?>

<?php $this->startBlock('content'); ?>

<!-- Product Detail -->
<section class="product-detail">
    <div class="container">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="breadcrumb-nav">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo $this->url(); ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?php echo $this->url('products'); ?>">Products</a></li>
                <li class="breadcrumb-item"><a href="<?php echo $this->url('products?category=' . urlencode($product->category_slug)); ?>"><?php echo $this->e($product->category_name); ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo $this->e($product->name); ?></li>
            </ol>
        </nav>
        
        <div class="row">
            <!-- Product Images -->
            <div class="col-md-6">
                <div class="product-images">
                    <?php if (!empty($product->images)): ?>
                        <div class="main-image">
                            <img src="<?php echo $this->e($product->images[0]->url); ?>" 
                                 alt="<?php echo $this->e($product->images[0]->alt); ?>" 
                                 class="img-fluid" id="main-product-image">
                        </div>
                        
                        <?php if (count($product->images) > 1): ?>
                            <div class="thumbnail-images">
                                <?php foreach ($product->images as $index => $image): ?>
                                    <img src="<?php echo $this->e($image->url); ?>" 
                                         alt="<?php echo $this->e($image->alt); ?>" 
                                         class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>"
                                         onclick="changeMainImage('<?php echo $this->e($image->url); ?>', this)">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="main-image">
                            <div class="no-image">
                                <span>No Image Available</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Product Info -->
            <div class="col-md-6">
                <div class="product-info">
                    <h1 class="product-title"><?php echo $this->e($product->name); ?></h1>
                    
                    <?php if (!empty($product->rating)): ?>
                        <div class="product-rating">
                            <div class="stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= $product->rating): ?>
                                        <span class="star filled">★</span>
                                    <?php else: ?>
                                        <span class="star">☆</span>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                            <span class="rating-text"><?php echo $product->rating; ?>/5</span>
                            <?php if (!empty($product->review_count)): ?>
                                <span class="review-count">(<?php echo $product->review_count; ?> reviews)</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="product-price">
                        <?php if ($product->sale_price && $product->sale_price < $product->price): ?>
                            <span class="price-old">$<?php echo number_format($product->price, 2); ?></span>
                            <span class="price-current">$<?php echo number_format($product->sale_price, 2); ?></span>
                            <span class="price-discount">
                                Save $<?php echo number_format($product->price - $product->sale_price, 2); ?>
                                (<?php echo round((($product->price - $product->sale_price) / $product->price) * 100); ?>% off)
                            </span>
                        <?php else: ?>
                            <span class="price-current">$<?php echo number_format($product->price, 2); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($product->short_description)): ?>
                        <div class="product-description">
                            <p><?php echo $this->e($product->short_description); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Product Actions -->
                    <div class="product-actions">
                        <form action="<?php echo $this->url('cart/add'); ?>" method="post" class="add-to-cart-form">
                            <?php echo $this->csrf_field(); ?>
                            <input type="hidden" name="product_id" value="<?php echo $product->id; ?>">
                            
                            <div class="quantity-selector">
                                <label for="quantity">Quantity:</label>
                                <div class="quantity-input">
                                    <button type="button" class="qty-btn minus" onclick="changeQuantity(-1)">-</button>
                                    <input type="number" name="quantity" id="quantity" value="1" min="1" max="10" class="form-control">
                                    <button type="button" class="qty-btn plus" onclick="changeQuantity(1)">+</button>
                                </div>
                            </div>
                            
                            <div class="action-buttons">
                                <?php if ($product->in_stock): ?>
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="icon-cart"></i> Add to Cart
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-secondary btn-lg" disabled>
                                        Out of Stock
                                    </button>
                                <?php endif; ?>
                                
                                <button type="button" class="btn btn-outline-secondary" data-product-id="<?php echo $product->id; ?>" onclick="addToWishlist(this)">
                                    <i class="icon-heart"></i> Add to Wishlist
                                </button>
                                
                                <button type="button" class="btn btn-outline-secondary" onclick="shareProduct()">
                                    <i class="icon-share"></i> Share
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Product Meta -->
                    <div class="product-meta">
                        <div class="meta-item">
                            <span class="label">Category:</span>
                            <a href="<?php echo $this->url('products?category=' . urlencode($product->category_slug)); ?>" class="value">
                                <?php echo $this->e($product->category_name); ?>
                            </a>
                        </div>
                        <div class="meta-item">
                            <span class="label">SKU:</span>
                            <span class="value">PRD-<?php echo str_pad($product->id, 6, '0', STR_PAD_LEFT); ?></span>
                        </div>
                        <?php if (isset($product->stock_quantity)): ?>
                            <div class="meta-item">
                                <span class="label">Stock:</span>
                                <span class="value <?php echo $product->stock_quantity > 5 ? 'in-stock' : 'low-stock'; ?>">
                                    <?php if ($product->stock_quantity > 5): ?>
                                        In Stock (<?php echo $product->stock_quantity; ?> available)
                                    <?php elseif ($product->stock_quantity > 0): ?>
                                        Low Stock (<?php echo $product->stock_quantity; ?> left)
                                    <?php else: ?>
                                        Out of Stock
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Product Tabs -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="product-tabs">
                    <ul class="nav nav-tabs" id="productTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="description-tab" data-bs-toggle="tab" data-bs-target="#description" type="button" role="tab">
                                Description
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="specifications-tab" data-bs-toggle="tab" data-bs-target="#specifications" type="button" role="tab">
                                Specifications
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab">
                                Reviews (<?php echo $product->review_count ?? 0; ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="shipping-tab" data-bs-toggle="tab" data-bs-target="#shipping" type="button" role="tab">
                                Shipping & Returns
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="productTabsContent">
                        <div class="tab-pane fade show active" id="description" role="tabpanel">
                            <div class="tab-content-inner">
                                <?php if (!empty($product->description)): ?>
                                    <p><?php echo nl2br($this->e($product->description)); ?></p>
                                <?php else: ?>
                                    <p>Product description will be available soon.</p>
                                <?php endif; ?>
                                
                                <h4>Key Features:</h4>
                                <ul>
                                    <li>High-quality construction and materials</li>
                                    <li>Modern design and user-friendly interface</li>
                                    <li>Excellent value for money</li>
                                    <li>Comprehensive warranty coverage</li>
                                    <li>Fast and reliable performance</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="tab-pane fade" id="specifications" role="tabpanel">
                            <div class="tab-content-inner">
                                <div class="specifications-table">
                                    <table class="table table-striped">
                                        <tbody>
                                            <tr>
                                                <td><strong>Brand</strong></td>
                                                <td>Shopologic</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Model</strong></td>
                                                <td><?php echo $this->e($product->name); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Weight</strong></td>
                                                <td>1.2 kg</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Dimensions</strong></td>
                                                <td>25 x 15 x 8 cm</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Warranty</strong></td>
                                                <td>1 Year Manufacturer Warranty</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <div class="tab-pane fade" id="reviews" role="tabpanel">
                            <div class="tab-content-inner">
                                <div class="reviews-summary">
                                    <h4>Customer Reviews</h4>
                                    <?php if (!empty($product->rating)): ?>
                                        <div class="overall-rating">
                                            <span class="rating-number"><?php echo $product->rating; ?></span>
                                            <div class="rating-stars">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <?php if ($i <= $product->rating): ?>
                                                        <span class="star filled">★</span>
                                                    <?php else: ?>
                                                        <span class="star">☆</span>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                            </div>
                                            <span class="review-count">Based on <?php echo $product->review_count ?? 0; ?> reviews</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="sample-reviews">
                                    <div class="review-item">
                                        <div class="review-header">
                                            <strong>John D.</strong>
                                            <div class="review-rating">
                                                ★★★★★
                                            </div>
                                            <span class="review-date">2 weeks ago</span>
                                        </div>
                                        <p>"Excellent product! Great quality and fast delivery. Highly recommend!"</p>
                                    </div>
                                    
                                    <div class="review-item">
                                        <div class="review-header">
                                            <strong>Sarah M.</strong>
                                            <div class="review-rating">
                                                ★★★★☆
                                            </div>
                                            <span class="review-date">1 month ago</span>
                                        </div>
                                        <p>"Good value for money. The product works as expected and the design is modern."</p>
                                    </div>
                                </div>
                                
                                <button type="button" class="btn btn-primary" onclick="openReviewForm()">Write a Review</button>
                            </div>
                        </div>
                        
                        <div class="tab-pane fade" id="shipping" role="tabpanel">
                            <div class="tab-content-inner">
                                <h4>Shipping Information</h4>
                                <ul>
                                    <li><strong>Free shipping</strong> on orders over $50</li>
                                    <li><strong>Standard delivery:</strong> 3-5 business days ($5.99)</li>
                                    <li><strong>Express delivery:</strong> 1-2 business days ($12.99)</li>
                                    <li><strong>Same-day delivery:</strong> Available in select cities ($19.99)</li>
                                </ul>
                                
                                <h4>Returns & Exchanges</h4>
                                <ul>
                                    <li><strong>30-day return policy</strong> for all items</li>
                                    <li>Items must be in original condition with packaging</li>
                                    <li>Free returns for defective items</li>
                                    <li>Customer pays return shipping for non-defective items</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Related Products -->
<?php if (!empty($related_products)): ?>
<section class="related-products">
    <div class="container">
        <h3>Related Products</h3>
        <div class="row">
            <?php foreach ($related_products as $relatedProduct): ?>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                    <?php $this->partial('partials/product-card', ['product' => $relatedProduct]); ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php $this->endBlock(); ?>

<?php $this->startBlock('scripts'); ?>
<script>
// Image gallery
function changeMainImage(src, thumbnail) {
    document.getElementById('main-product-image').src = src;
    
    // Update active thumbnail
    document.querySelectorAll('.thumbnail').forEach(thumb => thumb.classList.remove('active'));
    thumbnail.classList.add('active');
}

// Quantity controls
function changeQuantity(delta) {
    const input = document.getElementById('quantity');
    const currentValue = parseInt(input.value);
    const newValue = currentValue + delta;
    
    if (newValue >= 1 && newValue <= 10) {
        input.value = newValue;
    }
}

// Wishlist
function addToWishlist(button) {
    const productId = button.getAttribute('data-product-id');
    
    // Simulate wishlist addition
    button.innerHTML = '<i class="icon-heart-filled"></i> Added to Wishlist';
    button.classList.add('btn-success');
    button.disabled = true;
    
    // Show notification
    showNotification('Product added to wishlist!', 'success');
}

// Share product
function shareProduct() {
    if (navigator.share) {
        navigator.share({
            title: document.title,
            url: window.location.href
        });
    } else {
        // Fallback: copy to clipboard
        navigator.clipboard.writeText(window.location.href).then(() => {
            showNotification('Product link copied to clipboard!', 'info');
        });
    }
}

// Review form
function openReviewForm() {
    showNotification('Review form will be available soon!', 'info');
}

// Notification system
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} notification`;
    notification.textContent = message;
    notification.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 250px;';
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Tab functionality (if Bootstrap is not available)
document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Remove active class from all tabs and content
        document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.remove('show', 'active');
        });
        
        // Add active class to clicked tab
        this.classList.add('active');
        
        // Show corresponding content
        const targetId = this.getAttribute('data-bs-target');
        const targetPane = document.querySelector(targetId);
        if (targetPane) {
            targetPane.classList.add('show', 'active');
        }
    });
});
</script>
<?php $this->endBlock(); ?>