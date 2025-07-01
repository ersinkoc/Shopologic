<?php $this->layout('layouts/main'); ?>

<div class="test-products">
    <h1>Test Product Cards</h1>
    
    <?php if (isset($featured_products)): ?>
        <?php foreach ($featured_products as $product): ?>
            <div style="border: 1px solid #ccc; margin: 10px; padding: 10px;">
                <h3>Product: <?php echo $product->name ?? 'Unknown'; ?></h3>
                <p>Price: $<?php echo $product->price ?? '0'; ?></p>
                <p>Slug: <?php echo $product->slug ?? 'no-slug'; ?></p>
                
                <?php if (!empty($product->images)): ?>
                    <p>Images: <?php echo count($product->images); ?> images</p>
                    <p>First image: <?php echo $product->images[0]->url ?? 'no-url'; ?></p>
                <?php else: ?>
                    <p>No images</p>
                <?php endif; ?>
                
                <!-- Test the product card partial -->
                <div style="background: #f0f0f0; padding: 10px; margin: 10px 0;">
                    <h4>Product Card Partial:</h4>
                    <?php $this->partial('partials/product-card', ['product' => $product]); ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>