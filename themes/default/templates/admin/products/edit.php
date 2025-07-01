<?php $this->layout('admin/layouts/admin'); ?>

<?php $this->section('content'); ?>
<div class="admin-product-edit">
    <div class="page-header">
        <h1>Edit Product</h1>
        <div class="header-actions">
            <a href="/products/<?php echo $product['slug']; ?>" target="_blank" class="btn btn-secondary">
                <span class="icon">👁️</span> View Product
            </a>
            <a href="/admin/products" class="btn btn-secondary">
                <span class="icon">←</span> Back to Products
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['product_errors'])): ?>
        <div class="alert alert-error">
            <h4>Please fix the following errors:</h4>
            <ul>
                <?php foreach ($_SESSION['product_errors'] as $field => $error): ?>
                    <li><?php echo $this->e($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php unset($_SESSION['product_errors']); ?>
    <?php endif; ?>

    <form action="/admin/products/<?php echo $product['id']; ?>" method="POST" enctype="multipart/form-data" class="product-form">
        <input type="hidden" name="_method" value="PUT">
        
        <div class="form-grid">
            <!-- Main Column -->
            <div class="form-main">
                <!-- Basic Information -->
                <div class="form-section">
                    <h2>Basic Information</h2>
                    
                    <div class="form-group">
                        <label for="name">Product Name *</label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               value="<?php echo $this->e($_SESSION['product_data']['name'] ?? $product['name']); ?>"
                               placeholder="Enter product name"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="slug">URL Slug</label>
                        <input type="text" 
                               id="slug" 
                               name="slug" 
                               value="<?php echo $this->e($_SESSION['product_data']['slug'] ?? $product['slug']); ?>"
                               placeholder="product-url-slug">
                        <small>Leave blank to auto-generate from product name</small>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" 
                                  name="description" 
                                  rows="10"
                                  placeholder="Enter detailed product description"><?php echo $this->e($_SESSION['product_data']['description'] ?? $product['description']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="short_description">Short Description</label>
                        <textarea id="short_description" 
                                  name="short_description" 
                                  rows="3"
                                  placeholder="Brief product summary (shown in product lists)"><?php echo $this->e($_SESSION['product_data']['short_description'] ?? $product['short_description']); ?></textarea>
                    </div>
                </div>

                <!-- Product Images -->
                <div class="form-section">
                    <h2>Product Images</h2>
                    
                    <div class="image-upload-area">
                        <div class="upload-primary">
                            <label>Primary Image</label>
                            <div class="upload-box" id="primary-image-upload">
                                <input type="file" name="primary_image" accept="image/*" id="primary-image-input">
                                <div class="upload-placeholder" <?php echo !empty($product['images'][0]) ? 'style="display:none;"' : ''; ?>>
                                    <span class="icon">📷</span>
                                    <p>Click to upload or drag and drop</p>
                                    <small>JPG, PNG, WebP (Max 5MB)</small>
                                </div>
                                <?php if (!empty($product['images'][0])): ?>
                                    <img id="primary-image-preview" src="<?php echo $product['images'][0]; ?>" style="max-width: 100%; max-height: 250px;">
                                <?php else: ?>
                                    <img id="primary-image-preview" style="display: none;">
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="upload-gallery">
                            <label>Gallery Images</label>
                            <div class="gallery-grid" id="gallery-grid">
                                <?php if (!empty($product['images'])): ?>
                                    <?php foreach (array_slice($product['images'], 1) as $index => $image): ?>
                                        <div class="gallery-image">
                                            <img src="<?php echo $image; ?>" alt="">
                                            <button type="button" onclick="this.parentElement.remove()">×</button>
                                            <input type="hidden" name="existing_images[]" value="<?php echo $image; ?>">
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                
                                <div class="upload-box small" id="gallery-upload">
                                    <input type="file" name="gallery_images[]" accept="image/*" multiple id="gallery-input">
                                    <div class="upload-placeholder">
                                        <span class="icon">➕</span>
                                        <p>Add Images</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pricing -->
                <div class="form-section">
                    <h2>Pricing</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="price">Regular Price *</label>
                            <div class="input-group">
                                <span class="input-prefix">$</span>
                                <input type="number" 
                                       id="price" 
                                       name="price" 
                                       value="<?php echo $_SESSION['product_data']['price'] ?? $product['price']; ?>"
                                       step="0.01" 
                                       min="0"
                                       placeholder="0.00"
                                       required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="sale_price">Sale Price</label>
                            <div class="input-group">
                                <span class="input-prefix">$</span>
                                <input type="number" 
                                       id="sale_price" 
                                       name="sale_price" 
                                       value="<?php echo $_SESSION['product_data']['sale_price'] ?? $product['sale_price'] ?? ''; ?>"
                                       step="0.01" 
                                       min="0"
                                       placeholder="0.00">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="cost">Cost</label>
                            <div class="input-group">
                                <span class="input-prefix">$</span>
                                <input type="number" 
                                       id="cost" 
                                       name="cost" 
                                       value="<?php echo $_SESSION['product_data']['cost'] ?? $product['cost'] ?? ''; ?>"
                                       step="0.01" 
                                       min="0"
                                       placeholder="0.00">
                            </div>
                            <small>Your cost for this product</small>
                        </div>
                    </div>
                </div>

                <!-- Inventory -->
                <div class="form-section">
                    <h2>Inventory</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="sku">SKU *</label>
                            <input type="text" 
                                   id="sku" 
                                   name="sku" 
                                   value="<?php echo $this->e($_SESSION['product_data']['sku'] ?? $product['sku']); ?>"
                                   placeholder="PROD-001"
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="barcode">Barcode</label>
                            <input type="text" 
                                   id="barcode" 
                                   name="barcode" 
                                   value="<?php echo $this->e($_SESSION['product_data']['barcode'] ?? $product['barcode'] ?? ''); ?>"
                                   placeholder="1234567890123">
                        </div>

                        <div class="form-group">
                            <label for="stock">Stock Quantity</label>
                            <input type="number" 
                                   id="stock" 
                                   name="stock" 
                                   value="<?php echo $_SESSION['product_data']['stock'] ?? $product['stock']; ?>"
                                   min="0"
                                   placeholder="0">
                        </div>

                        <div class="form-group">
                            <label for="low_stock_threshold">Low Stock Alert</label>
                            <input type="number" 
                                   id="low_stock_threshold" 
                                   name="low_stock_threshold" 
                                   value="<?php echo $_SESSION['product_data']['low_stock_threshold'] ?? $product['low_stock_threshold'] ?? '10'; ?>"
                                   min="0"
                                   placeholder="10">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-group">
                            <input type="checkbox" 
                                   name="track_inventory" 
                                   value="1" 
                                   <?php echo ($_SESSION['product_data']['track_inventory'] ?? $product['track_inventory'] ?? '1') == '1' ? 'checked' : ''; ?>>
                            <span>Track inventory for this product</span>
                        </label>
                    </div>
                </div>

                <!-- Shipping -->
                <div class="form-section">
                    <h2>Shipping</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="weight">Weight</label>
                            <div class="input-group">
                                <input type="number" 
                                       id="weight" 
                                       name="weight" 
                                       value="<?php echo $_SESSION['product_data']['weight'] ?? $product['weight'] ?? ''; ?>"
                                       step="0.01" 
                                       min="0"
                                       placeholder="0.00">
                                <span class="input-suffix">lbs</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="length">Length</label>
                            <div class="input-group">
                                <input type="number" 
                                       id="length" 
                                       name="length" 
                                       value="<?php echo $_SESSION['product_data']['length'] ?? $product['length'] ?? ''; ?>"
                                       step="0.01" 
                                       min="0"
                                       placeholder="0.00">
                                <span class="input-suffix">in</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="width">Width</label>
                            <div class="input-group">
                                <input type="number" 
                                       id="width" 
                                       name="width" 
                                       value="<?php echo $_SESSION['product_data']['width'] ?? $product['width'] ?? ''; ?>"
                                       step="0.01" 
                                       min="0"
                                       placeholder="0.00">
                                <span class="input-suffix">in</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="height">Height</label>
                            <div class="input-group">
                                <input type="number" 
                                       id="height" 
                                       name="height" 
                                       value="<?php echo $_SESSION['product_data']['height'] ?? $product['height'] ?? ''; ?>"
                                       step="0.01" 
                                       min="0"
                                       placeholder="0.00">
                                <span class="input-suffix">in</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-group">
                            <input type="checkbox" 
                                   name="requires_shipping" 
                                   value="1" 
                                   <?php echo ($_SESSION['product_data']['requires_shipping'] ?? $product['requires_shipping'] ?? '1') == '1' ? 'checked' : ''; ?>>
                            <span>This product requires shipping</span>
                        </label>
                    </div>
                </div>

                <!-- Attributes -->
                <div class="form-section">
                    <h2>Product Attributes</h2>
                    
                    <div id="attributes-container">
                        <?php 
                        $productAttributes = $_SESSION['product_data']['attributes'] ?? $product['attributes'] ?? [];
                        foreach ($attributes as $attribute => $values): 
                        ?>
                        <div class="attribute-group">
                            <label><?php echo $this->e($attribute); ?></label>
                            <div class="attribute-values">
                                <?php foreach ($values as $value): ?>
                                <label class="checkbox-inline">
                                    <input type="checkbox" 
                                           name="attributes[<?php echo $attribute; ?>][]" 
                                           value="<?php echo $this->e($value); ?>"
                                           <?php echo in_array($value, $productAttributes[$attribute] ?? []) ? 'checked' : ''; ?>>
                                    <span><?php echo $this->e($value); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- SEO -->
                <div class="form-section">
                    <h2>Search Engine Optimization</h2>
                    
                    <div class="form-group">
                        <label for="meta_title">Meta Title</label>
                        <input type="text" 
                               id="meta_title" 
                               name="meta_title" 
                               value="<?php echo $this->e($_SESSION['product_data']['meta_title'] ?? $product['meta_title'] ?? ''); ?>"
                               placeholder="Product page title">
                        <small>Leave blank to use product name</small>
                    </div>

                    <div class="form-group">
                        <label for="meta_description">Meta Description</label>
                        <textarea id="meta_description" 
                                  name="meta_description" 
                                  rows="3"
                                  placeholder="Brief description for search engines"><?php echo $this->e($_SESSION['product_data']['meta_description'] ?? $product['meta_description'] ?? ''); ?></textarea>
                        <small>Recommended: 150-160 characters</small>
                    </div>

                    <div class="form-group">
                        <label for="meta_keywords">Meta Keywords</label>
                        <input type="text" 
                               id="meta_keywords" 
                               name="meta_keywords" 
                               value="<?php echo $this->e($_SESSION['product_data']['meta_keywords'] ?? $product['meta_keywords'] ?? ''); ?>"
                               placeholder="keyword1, keyword2, keyword3">
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="form-sidebar">
                <!-- Publish -->
                <div class="sidebar-section">
                    <h3>Update Product</h3>
                    <div class="publish-actions">
                        <button type="submit" name="action" value="update" class="btn btn-primary btn-block">
                            Update Product
                        </button>
                        <button type="submit" name="action" value="save_draft" class="btn btn-secondary btn-block">
                            Save as Draft
                        </button>
                    </div>
                    
                    <div class="product-meta">
                        <p><strong>Product ID:</strong> <?php echo $product['id']; ?></p>
                        <p><strong>Created:</strong> <?php echo date('M d, Y', strtotime($product['created_at'] ?? 'now')); ?></p>
                        <p><strong>Last Updated:</strong> <?php echo date('M d, Y', strtotime($product['updated_at'] ?? 'now')); ?></p>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="active" <?php echo ($_SESSION['product_data']['status'] ?? $product['status']) == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="draft" <?php echo ($_SESSION['product_data']['status'] ?? $product['status']) == 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="inactive" <?php echo ($_SESSION['product_data']['status'] ?? $product['status']) == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="visibility">Visibility</label>
                        <select id="visibility" name="visibility">
                            <option value="visible" <?php echo ($_SESSION['product_data']['visibility'] ?? $product['visibility'] ?? 'visible') == 'visible' ? 'selected' : ''; ?>>Visible</option>
                            <option value="hidden" <?php echo ($_SESSION['product_data']['visibility'] ?? $product['visibility'] ?? '') == 'hidden' ? 'selected' : ''; ?>>Hidden</option>
                            <option value="password" <?php echo ($_SESSION['product_data']['visibility'] ?? $product['visibility'] ?? '') == 'password' ? 'selected' : ''; ?>>Password Protected</option>
                        </select>
                    </div>
                </div>

                <!-- Category -->
                <div class="sidebar-section">
                    <h3>Category *</h3>
                    <div class="category-selector">
                        <select name="category" required>
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['slug']; ?>" 
                                        <?php echo ($_SESSION['product_data']['category'] ?? $product['category']) == $category['slug'] ? 'selected' : ''; ?>>
                                    <?php echo $this->e($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Brand -->
                <div class="sidebar-section">
                    <h3>Brand</h3>
                    <div class="form-group">
                        <select name="brand">
                            <option value="">Select a brand</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?php echo $this->e($brand); ?>" 
                                        <?php echo ($_SESSION['product_data']['brand'] ?? $product['brand'] ?? '') == $brand ? 'selected' : ''; ?>>
                                    <?php echo $this->e($brand); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Tags -->
                <div class="sidebar-section">
                    <h3>Tags</h3>
                    <div class="form-group">
                        <input type="text" 
                               name="tags" 
                               value="<?php echo $this->e($_SESSION['product_data']['tags'] ?? implode(', ', $product['tags'] ?? [])); ?>"
                               placeholder="tag1, tag2, tag3">
                        <small>Separate tags with commas</small>
                    </div>
                </div>

                <!-- Featured -->
                <div class="sidebar-section">
                    <h3>Product Options</h3>
                    <div class="form-group">
                        <label class="checkbox-group">
                            <input type="checkbox" 
                                   name="featured" 
                                   value="1" 
                                   <?php echo ($_SESSION['product_data']['featured'] ?? $product['featured'] ?? '') == '1' ? 'checked' : ''; ?>>
                            <span>Featured Product</span>
                        </label>
                        <label class="checkbox-group">
                            <input type="checkbox" 
                                   name="digital" 
                                   value="1" 
                                   <?php echo ($_SESSION['product_data']['digital'] ?? $product['digital'] ?? '') == '1' ? 'checked' : ''; ?>>
                            <span>Digital Product</span>
                        </label>
                        <label class="checkbox-group">
                            <input type="checkbox" 
                                   name="allow_reviews" 
                                   value="1" 
                                   <?php echo ($_SESSION['product_data']['allow_reviews'] ?? $product['allow_reviews'] ?? '1') == '1' ? 'checked' : ''; ?>>
                            <span>Allow Reviews</span>
                        </label>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="sidebar-section danger-zone">
                    <h3>Danger Zone</h3>
                    <button type="button" onclick="deleteProduct(<?php echo $product['id']; ?>)" class="btn btn-danger btn-block">
                        Delete This Product
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<?php unset($_SESSION['product_data']); ?>

<style>
/* Additional styles for edit page */
.product-meta {
    margin: 20px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
    font-size: 12px;
}

.product-meta p {
    margin: 5px 0;
    color: var(--admin-secondary);
}

.danger-zone {
    border: 1px solid #fee;
    background: #fff5f5;
}

.danger-zone h3 {
    color: var(--admin-danger);
}

.btn-danger {
    background: var(--admin-danger);
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.gallery-image {
    position: relative;
    border-radius: 6px;
    overflow: hidden;
}

.gallery-image img {
    width: 100%;
    height: 120px;
    object-fit: cover;
}

.gallery-image button {
    position: absolute;
    top: 5px;
    right: 5px;
    background: rgba(255, 255, 255, 0.9);
    border: none;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    cursor: pointer;
    font-size: 16px;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.gallery-image button:hover {
    background: white;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
</style>

<script>
// Reuse scripts from create page
// Auto-generate slug from name
document.getElementById('name').addEventListener('input', function() {
    const slug = document.getElementById('slug');
    if (!slug.value || slug.value === '<?php echo $product['slug']; ?>') {
        slug.value = this.value.toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .trim();
    }
});

// Image preview
document.getElementById('primary-image-input').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('primary-image-preview');
            preview.src = e.target.result;
            preview.style.display = 'block';
            document.querySelector('#primary-image-upload .upload-placeholder').style.display = 'none';
        };
        reader.readAsDataURL(file);
    }
});

// Gallery images preview
document.getElementById('gallery-input').addEventListener('change', function(e) {
    const files = Array.from(e.target.files);
    files.forEach(file => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const div = document.createElement('div');
            div.className = 'gallery-image';
            div.innerHTML = `
                <img src="${e.target.result}" alt="">
                <button type="button" onclick="this.parentElement.remove()">×</button>
            `;
            document.getElementById('gallery-grid').insertBefore(
                div, 
                document.getElementById('gallery-upload')
            );
        };
        reader.readAsDataURL(file);
    });
});

// Delete product
function deleteProduct(id) {
    if (confirm('Are you sure you want to permanently delete this product? This action cannot be undone.')) {
        fetch(`/admin/products/${id}`, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = '/admin/products';
            } else {
                alert(data.message || 'Error deleting product');
            }
        });
    }
}
</script>
<?php $this->endSection(); ?>