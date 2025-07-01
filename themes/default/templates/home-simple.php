<?php $this->layout('layouts/main'); ?>

<div class="hero-section">
    <h1><?php echo isset($title) ? $title : 'Welcome to Shopologic'; ?></h1>
    <p><?php echo isset($description) ? $description : 'Your online shopping destination'; ?></p>
</div>

<div class="categories">
    <h2>Categories</h2>
    <?php if (isset($categories)): ?>
        <?php foreach ($categories as $category): ?>
            <div class="category">
                <h3><?php echo $category->name ?? 'Unknown'; ?></h3>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="products">
    <h2>Featured Products</h2>
    <?php if (isset($featured_products)): ?>
        <?php foreach ($featured_products as $product): ?>
            <div class="simple-product">
                <h3><?php echo $product->name ?? 'Unknown Product'; ?></h3>
                <p>Price: $<?php echo $product->price ?? '0'; ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>