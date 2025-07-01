<div class="product-card">
    <a href="<?php echo $this->url('product/' . urlencode($product->slug)); ?>" class="product-link">
        <div class="product-image">
            <?php if (!empty($product->images) && isset($product->images[0])): ?>
                <img src="<?php echo $this->e($product->images[0]->url); ?>" 
                     alt="<?php echo $this->e($product->name); ?>"
                     loading="lazy">
            <?php else: ?>
                <div class="product-placeholder">
                    <span>No Image</span>
                </div>
            <?php endif; ?>
            
            <?php if ($product->sale_price && $product->sale_price < $product->price): ?>
                <span class="product-badge sale">Sale</span>
            <?php elseif ($product->is_new): ?>
                <span class="product-badge new">New</span>
            <?php endif; ?>
        </div>
        
        <div class="product-info">
            <h3 class="product-title"><?php echo $this->e($product->name); ?></h3>
            
            <?php if (!empty($product->rating)): ?>
                <div class="product-rating">
                    <span class="stars" data-rating="<?php echo $product->rating; ?>">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= $product->rating): ?>
                                ★
                            <?php else: ?>
                                ☆
                            <?php endif; ?>
                        <?php endfor; ?>
                    </span>
                    <?php if (!empty($product->review_count)): ?>
                        <span class="review-count">(<?php echo $product->review_count; ?>)</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="product-price">
                <?php if ($product->sale_price && $product->sale_price < $product->price): ?>
                    <span class="price-old"><?php echo $this->money($product->price); ?></span>
                    <span class="price-current"><?php echo $this->money($product->sale_price); ?></span>
                    <span class="price-discount">
                        -<?php echo round((($product->price - $product->sale_price) / $product->price) * 100); ?>%
                    </span>
                <?php else: ?>
                    <span class="price-current"><?php echo $this->money($product->price); ?></span>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($product->short_description)): ?>
                <p class="product-description">
                    <?php echo $this->truncate($this->e($product->short_description), 100); ?>
                </p>
            <?php endif; ?>
        </div>
    </a>
    
    <div class="product-actions">
        <form action="<?php echo $this->url('cart/add'); ?>" method="post" class="add-to-cart-form">
            <?php echo $this->csrf_field(); ?>
            <input type="hidden" name="product_id" value="<?php echo $product->id; ?>">
            <input type="hidden" name="quantity" value="1">
            
            <?php if ($product->in_stock): ?>
                <button type="submit" class="btn btn-primary btn-sm">Add to Cart</button>
            <?php else: ?>
                <button type="button" class="btn btn-secondary btn-sm" disabled>Out of Stock</button>
            <?php endif; ?>
        </form>
        
        <button type="button" class="btn-wishlist" data-product-id="<?php echo $product->id; ?>" title="Add to Wishlist">
            ♥
        </button>
    </div>
</div>