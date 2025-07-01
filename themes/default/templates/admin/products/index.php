<?php $this->layout('admin/layouts/admin'); ?>

<?php $this->section('content'); ?>
<div class="admin-products">
    <div class="page-header">
        <h1>Product Management</h1>
        <div class="header-actions">
            <button class="btn btn-primary" onclick="location.href='/admin/products/create'">
                <span class="icon">➕</span> Add New Product
            </button>
            <button class="btn btn-secondary" onclick="location.href='/admin/products/export'">
                <span class="icon">📥</span> Export
            </button>
        </div>
    </div>

    <!-- Product Stats -->
    <div class="product-stats">
        <div class="stat-item">
            <span class="stat-label">Total Products</span>
            <span class="stat-value"><?php echo $stats['total_products']; ?></span>
        </div>
        <div class="stat-item">
            <span class="stat-label">Active</span>
            <span class="stat-value"><?php echo $stats['active_products']; ?></span>
        </div>
        <div class="stat-item">
            <span class="stat-label">Out of Stock</span>
            <span class="stat-value alert"><?php echo $stats['out_of_stock']; ?></span>
        </div>
        <div class="stat-item">
            <span class="stat-label">Low Stock</span>
            <span class="stat-value warning"><?php echo $stats['low_stock']; ?></span>
        </div>
    </div>

    <!-- Filters -->
    <div class="product-filters">
        <form method="GET" action="/admin/products" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <input type="text" 
                           name="q" 
                           placeholder="Search products..." 
                           value="<?php echo $this->e($filters['query'] ?? ''); ?>"
                           class="search-input">
                </div>
                
                <div class="filter-group">
                    <select name="category" class="filter-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['slug']; ?>" 
                                    <?php echo ($filters['category'] ?? '') === $category['slug'] ? 'selected' : ''; ?>>
                                <?php echo $this->e($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <select name="status" class="filter-select">
                        <option value="">All Status</option>
                        <option value="active" <?php echo ($filters['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($filters['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="out_of_stock" <?php echo ($filters['status'] ?? '') === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <select name="sort" class="filter-select">
                        <option value="">Sort By</option>
                        <option value="name_asc" <?php echo ($filters['sort'] ?? '') === 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                        <option value="name_desc" <?php echo ($filters['sort'] ?? '') === 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                        <option value="price_asc" <?php echo ($filters['sort'] ?? '') === 'price_asc' ? 'selected' : ''; ?>>Price (Low to High)</option>
                        <option value="price_desc" <?php echo ($filters['sort'] ?? '') === 'price_desc' ? 'selected' : ''; ?>>Price (High to Low)</option>
                        <option value="stock_asc" <?php echo ($filters['sort'] ?? '') === 'stock_asc' ? 'selected' : ''; ?>>Stock (Low to High)</option>
                        <option value="created_desc" <?php echo ($filters['sort'] ?? '') === 'created_desc' ? 'selected' : ''; ?>>Newest First</option>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="/admin/products" class="btn btn-secondary">Clear</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Products Table -->
    <div class="products-table-container">
        <table class="products-table">
            <thead>
                <tr>
                    <th class="checkbox-column">
                        <input type="checkbox" id="select-all" />
                    </th>
                    <th>Image</th>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td class="checkbox-column">
                        <input type="checkbox" name="product_ids[]" value="<?php echo $product['id']; ?>" />
                    </td>
                    <td class="image-column">
                        <img src="<?php echo $product['image'] ?? $this->theme_asset('images/placeholder.jpg'); ?>" 
                             alt="<?php echo $this->e($product['name']); ?>"
                             class="product-thumb">
                    </td>
                    <td class="product-info">
                        <h4><?php echo $this->e($product['name']); ?></h4>
                        <p class="product-desc"><?php echo $this->e(substr($product['description'] ?? '', 0, 100)); ?>...</p>
                    </td>
                    <td><?php echo $this->e($product['sku'] ?? 'N/A'); ?></td>
                    <td>
                        <?php if (isset($product['sale_price']) && $product['sale_price'] < $product['price']): ?>
                            <span class="price-sale"><?php echo $this->money($product['sale_price']); ?></span>
                            <span class="price-original"><?php echo $this->money($product['price']); ?></span>
                        <?php else: ?>
                            <span class="price"><?php echo $this->money($product['price']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="stock-level <?php echo $product['stock'] <= 10 ? 'low-stock' : ''; ?>">
                            <?php echo $product['stock']; ?>
                        </span>
                    </td>
                    <td><?php echo $this->e($product['category'] ?? 'Uncategorized'); ?></td>
                    <td>
                        <?php if ($product['stock'] == 0): ?>
                            <span class="status-badge status-out-of-stock">Out of Stock</span>
                        <?php elseif ($product['featured'] ?? false): ?>
                            <span class="status-badge status-featured">Featured</span>
                        <?php else: ?>
                            <span class="status-badge status-active">Active</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions-column">
                        <div class="action-buttons">
                            <a href="/admin/products/<?php echo $product['id']; ?>/edit" 
                               class="btn-icon" 
                               title="Edit">✏️</a>
                            <a href="/products/<?php echo $product['slug']; ?>" 
                               target="_blank" 
                               class="btn-icon" 
                               title="View">👁️</a>
                            <button onclick="duplicateProduct(<?php echo $product['id']; ?>)" 
                                    class="btn-icon" 
                                    title="Duplicate">📋</button>
                            <button onclick="deleteProduct(<?php echo $product['id']; ?>)" 
                                    class="btn-icon delete" 
                                    title="Delete">🗑️</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="pagination">
        <?php if ($pagination['current_page'] > 1): ?>
            <a href="<?php echo $this->url_with_params(['page' => $pagination['current_page'] - 1]); ?>" 
               class="page-link">← Previous</a>
        <?php endif; ?>
        
        <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
            <?php if ($i <= 3 || $i > $pagination['total_pages'] - 3 || abs($i - $pagination['current_page']) <= 1): ?>
                <a href="<?php echo $this->url_with_params(['page' => $i]); ?>" 
                   class="page-link <?php echo $i === $pagination['current_page'] ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php elseif ($i == 4 || $i == $pagination['total_pages'] - 3): ?>
                <span class="page-dots">...</span>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
            <a href="<?php echo $this->url_with_params(['page' => $pagination['current_page'] + 1]); ?>" 
               class="page-link">Next →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Bulk Actions -->
    <div class="bulk-actions">
        <select id="bulk-action" class="bulk-select">
            <option value="">Bulk Actions</option>
            <option value="activate">Activate</option>
            <option value="deactivate">Deactivate</option>
            <option value="delete">Delete</option>
            <option value="export">Export</option>
        </select>
        <button onclick="executeBulkAction()" class="btn btn-secondary">Apply</button>
    </div>
</div>

<style>
/* Product Management Styles */
.product-stats {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.stat-item {
    flex: 1;
    text-align: center;
}

.stat-label {
    display: block;
    font-size: 12px;
    color: var(--admin-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
}

.stat-value {
    display: block;
    font-size: 24px;
    font-weight: 600;
    color: var(--admin-dark);
}

.stat-value.alert {
    color: var(--admin-danger);
}

.stat-value.warning {
    color: var(--admin-warning);
}

/* Filters */
.product-filters {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.filter-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.filter-row {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.search-input,
.filter-select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
}

.filter-actions {
    display: flex;
    gap: 10px;
}

/* Products Table */
.products-table-container {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
}

.products-table {
    width: 100%;
    border-collapse: collapse;
}

.products-table th {
    background: #f8f9fa;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    color: var(--admin-secondary);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e2e8f0;
}

.products-table td {
    padding: 15px 12px;
    border-bottom: 1px solid #f1f3f5;
}

.checkbox-column {
    width: 40px;
}

.image-column {
    width: 80px;
}

.product-thumb {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 6px;
}

.product-info h4 {
    margin: 0 0 5px;
    color: var(--admin-dark);
    font-size: 14px;
    font-weight: 600;
}

.product-desc {
    margin: 0;
    color: var(--admin-secondary);
    font-size: 12px;
}

.price-sale {
    color: var(--admin-danger);
    font-weight: 600;
}

.price-original {
    text-decoration: line-through;
    color: var(--admin-secondary);
    font-size: 12px;
    margin-left: 5px;
}

.stock-level {
    font-weight: 600;
}

.stock-level.low-stock {
    color: var(--admin-warning);
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.status-active {
    background: #d1fae5;
    color: #065f46;
}

.status-featured {
    background: #dbeafe;
    color: #1e40af;
}

.status-out-of-stock {
    background: #fee;
    color: #991b1b;
}

.actions-column {
    width: 150px;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-icon {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 16px;
    padding: 4px;
    text-decoration: none;
    transition: transform 0.2s;
}

.btn-icon:hover {
    transform: scale(1.2);
}

.btn-icon.delete:hover {
    filter: hue-rotate(180deg);
}

/* Bulk Actions */
.bulk-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.bulk-select {
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
}
</style>

<script>
// Select all checkboxes
document.getElementById('select-all').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('input[name="product_ids[]"]');
    checkboxes.forEach(cb => cb.checked = this.checked);
});

// Delete product
function deleteProduct(id) {
    if (confirm('Are you sure you want to delete this product?')) {
        fetch(`/admin/products/${id}`, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Error deleting product');
            }
        });
    }
}

// Duplicate product
function duplicateProduct(id) {
    if (confirm('Duplicate this product?')) {
        window.location.href = `/admin/products/${id}/duplicate`;
    }
}

// Execute bulk action
function executeBulkAction() {
    const action = document.getElementById('bulk-action').value;
    if (!action) {
        alert('Please select an action');
        return;
    }
    
    const selectedIds = Array.from(document.querySelectorAll('input[name="product_ids[]"]:checked'))
        .map(cb => cb.value);
    
    if (selectedIds.length === 0) {
        alert('Please select at least one product');
        return;
    }
    
    if (confirm(`Are you sure you want to ${action} ${selectedIds.length} product(s)?`)) {
        // Implement bulk action
        console.log('Bulk action:', action, 'Products:', selectedIds);
    }
}
</script>
<?php $this->endSection(); ?>