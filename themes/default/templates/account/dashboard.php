<?php $this->layout('layouts/main'); ?>

<?php $this->section('title', ($title ?? 'My Account') . ' - Shopologic'); ?>

<?php $this->section('content'); ?>
<div class="account-page">
    <div class="container">
        <div class="account-header">
            <h1>Welcome back, <?php echo $this->e($user['first_name']); ?>!</h1>
            <p>Manage your account, orders, and preferences</p>
        </div>

        <div class="account-content">
            <!-- Account Navigation -->
            <div class="account-sidebar">
                <div class="account-nav">
                    <h3>My Account</h3>
                    <ul class="nav-menu">
                        <li><a href="/account" class="nav-link active">
                            <i class="icon-dashboard"></i>
                            Dashboard
                        </a></li>
                        <li><a href="<?php echo $profile_update_url; ?>" class="nav-link">
                            <i class="icon-user"></i>
                            Profile Settings
                        </a></li>
                        <li><a href="<?php echo $orders_url; ?>" class="nav-link">
                            <i class="icon-orders"></i>
                            Order History
                        </a></li>
                        <li><a href="<?php echo $addresses_url; ?>" class="nav-link">
                            <i class="icon-location"></i>
                            Address Book
                        </a></li>
                        <li><a href="/account/wishlist" class="nav-link">
                            <i class="icon-heart"></i>
                            Wishlist
                        </a></li>
                        <li><a href="/account/reviews" class="nav-link">
                            <i class="icon-star"></i>
                            Reviews
                        </a></li>
                        <li><a href="/auth/logout" class="nav-link logout">
                            <i class="icon-logout"></i>
                            Sign Out
                        </a></li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="account-main">
                <!-- Quick Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="icon-orders"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($recent_orders); ?></h3>
                            <p>Recent Orders</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="icon-heart"></i>
                        </div>
                        <div class="stat-content">
                            <h3>12</h3>
                            <p>Wishlist Items</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="icon-star"></i>
                        </div>
                        <div class="stat-content">
                            <h3>8</h3>
                            <p>Reviews Written</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="icon-location"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($user['addresses'] ?? []); ?></h3>
                            <p>Saved Addresses</p>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>Recent Orders</h2>
                        <a href="<?php echo $orders_url; ?>" class="btn btn-outline-primary">View All Orders</a>
                    </div>

                    <?php if (!empty($recent_orders)): ?>
                        <div class="orders-table">
                            <div class="table-header">
                                <div class="col-order">Order</div>
                                <div class="col-date">Date</div>
                                <div class="col-status">Status</div>
                                <div class="col-total">Total</div>
                                <div class="col-actions">Actions</div>
                            </div>

                            <?php foreach ($recent_orders as $order): ?>
                                <div class="table-row">
                                    <div class="col-order">
                                        <div class="order-number"><?php echo $this->e($order['order_number']); ?></div>
                                        <div class="order-items"><?php echo $order['items_count']; ?> item(s)</div>
                                    </div>
                                    <div class="col-date">
                                        <?php echo date('M j, Y', strtotime($order['date'])); ?>
                                    </div>
                                    <div class="col-status">
                                        <span class="status status-<?php echo $this->e($order['status']); ?>">
                                            <?php echo ucfirst($this->e($order['status'])); ?>
                                        </span>
                                    </div>
                                    <div class="col-total">
                                        <?php echo $this->money($order['total']); ?>
                                    </div>
                                    <div class="col-actions">
                                        <a href="/account/orders/<?php echo $this->e($order['id']); ?>" class="btn btn-sm btn-outline-primary">
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="icon-orders"></i>
                            </div>
                            <h3>No orders yet</h3>
                            <p>Start shopping to see your orders here</p>
                            <a href="/products" class="btn btn-primary">Browse Products</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Account Info -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>Account Information</h2>
                        <a href="<?php echo $profile_update_url; ?>" class="btn btn-outline-primary">Edit Profile</a>
                    </div>

                    <div class="info-grid">
                        <div class="info-card">
                            <h4>Contact Details</h4>
                            <div class="info-item">
                                <label>Name:</label>
                                <span><?php echo $this->e($user['first_name'] . ' ' . $user['last_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Email:</label>
                                <span><?php echo $this->e($user['email']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Phone:</label>
                                <span><?php echo $this->e($user['phone'] ?: 'Not provided'); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Member since:</label>
                                <span><?php echo date('F Y', strtotime($user['created_at'])); ?></span>
                            </div>
                        </div>

                        <div class="info-card">
                            <h4>Default Addresses</h4>
                            <?php 
                            $billingAddress = null;
                            $shippingAddress = null;
                            foreach ($user['addresses'] ?? [] as $address) {
                                if ($address['is_default'] && ($address['type'] === 'billing' || $address['type'] === 'both')) {
                                    $billingAddress = $address;
                                }
                                if ($address['is_default'] && ($address['type'] === 'shipping' || $address['type'] === 'both')) {
                                    $shippingAddress = $address;
                                }
                            }
                            ?>
                            
                            <?php if ($billingAddress): ?>
                                <div class="address-summary">
                                    <strong>Billing:</strong>
                                    <span><?php echo $this->e($billingAddress['city'] . ', ' . $billingAddress['state']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($shippingAddress): ?>
                                <div class="address-summary">
                                    <strong>Shipping:</strong>
                                    <span><?php echo $this->e($shippingAddress['city'] . ', ' . $shippingAddress['state']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!$billingAddress && !$shippingAddress): ?>
                                <p class="no-addresses">No default addresses set</p>
                            <?php endif; ?>
                            
                            <a href="<?php echo $addresses_url; ?>" class="btn btn-sm btn-link">Manage Addresses</a>
                        </div>

                        <div class="info-card">
                            <h4>Preferences</h4>
                            <div class="preference-item">
                                <label>Newsletter:</label>
                                <span class="preference-status <?php echo ($user['preferences']['newsletter'] ?? false) ? 'enabled' : 'disabled'; ?>">
                                    <?php echo ($user['preferences']['newsletter'] ?? false) ? 'Subscribed' : 'Not subscribed'; ?>
                                </span>
                            </div>
                            <div class="preference-item">
                                <label>Marketing emails:</label>
                                <span class="preference-status <?php echo ($user['preferences']['marketing_emails'] ?? false) ? 'enabled' : 'disabled'; ?>">
                                    <?php echo ($user['preferences']['marketing_emails'] ?? false) ? 'Enabled' : 'Disabled'; ?>
                                </span>
                            </div>
                            <a href="<?php echo $profile_update_url; ?>" class="btn btn-sm btn-link">Update Preferences</a>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-section">
                    <h2>Quick Actions</h2>
                    <div class="actions-grid">
                        <a href="/products" class="action-card">
                            <div class="action-icon">
                                <i class="icon-shop"></i>
                            </div>
                            <h4>Continue Shopping</h4>
                            <p>Browse our latest products</p>
                        </a>
                        <a href="/account/orders" class="action-card">
                            <div class="action-icon">
                                <i class="icon-truck"></i>
                            </div>
                            <h4>Track Orders</h4>
                            <p>Check your order status</p>
                        </a>
                        <a href="/account/wishlist" class="action-card">
                            <div class="action-icon">
                                <i class="icon-heart"></i>
                            </div>
                            <h4>My Wishlist</h4>
                            <p>View saved items</p>
                        </a>
                        <a href="/support" class="action-card">
                            <div class="action-icon">
                                <i class="icon-help"></i>
                            </div>
                            <h4>Get Help</h4>
                            <p>Contact customer support</p>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $this->do_action('account.dashboard.after_content', $user); ?>

<style>
.account-page {
    padding: 40px 0;
    background: #f8f9fa;
    min-height: 100vh;
}

.account-header {
    text-align: center;
    margin-bottom: 40px;
}

.account-header h1 {
    color: var(--dark-color);
    margin-bottom: 10px;
}

.account-header p {
    color: var(--secondary-color);
    font-size: 18px;
}

.account-content {
    display: grid;
    grid-template-columns: 250px 1fr;
    gap: 40px;
}

/* Sidebar Navigation */
.account-sidebar {
    position: sticky;
    top: 20px;
    height: fit-content;
}

.account-nav {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
}

.account-nav h3 {
    padding: 20px;
    margin: 0;
    background: var(--primary-color);
    color: white;
    font-size: 18px;
}

.nav-menu {
    list-style: none;
    margin: 0;
    padding: 0;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px 20px;
    color: var(--dark-color);
    text-decoration: none;
    border-bottom: 1px solid #e1e5e9;
    transition: background 0.3s ease;
}

.nav-link:hover,
.nav-link.active {
    background: #f8f9ff;
    color: var(--primary-color);
}

.nav-link.logout {
    color: var(--danger-color);
}

.nav-link.logout:hover {
    background: #fff5f5;
}

/* Main Content */
.account-main {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 20px;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), var(--info-color));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.stat-content h3 {
    margin: 0 0 5px 0;
    font-size: 32px;
    color: var(--dark-color);
}

.stat-content p {
    margin: 0;
    color: var(--secondary-color);
    font-size: 14px;
}

/* Dashboard Sections */
.dashboard-section {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    padding: 30px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.section-header h2 {
    margin: 0;
    color: var(--dark-color);
}

/* Orders Table */
.orders-table {
    border: 1px solid #e1e5e9;
    border-radius: 8px;
    overflow: hidden;
}

.table-header,
.table-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
    gap: 20px;
    padding: 15px 20px;
    align-items: center;
}

.table-header {
    background: var(--light-color);
    font-weight: bold;
    border-bottom: 1px solid #e1e5e9;
}

.table-row {
    border-bottom: 1px solid #f0f0f0;
}

.table-row:last-child {
    border-bottom: none;
}

.table-row:hover {
    background: #f8f9ff;
}

.order-number {
    font-weight: bold;
    color: var(--primary-color);
}

.order-items {
    font-size: 14px;
    color: var(--secondary-color);
}

.status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-processing {
    background: #d1ecf1;
    color: #0c5460;
}

.status-delivered {
    background: #d4edda;
    color: #155724;
}

.status-completed {
    background: #d4edda;
    color: #155724;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-icon {
    font-size: 64px;
    color: #e1e5e9;
    margin-bottom: 20px;
}

.empty-state h3 {
    color: var(--dark-color);
    margin-bottom: 10px;
}

.empty-state p {
    color: var(--secondary-color);
    margin-bottom: 25px;
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
}

.info-card {
    padding: 25px;
    border: 1px solid #e1e5e9;
    border-radius: 8px;
    background: #f8f9fa;
}

.info-card h4 {
    margin-bottom: 20px;
    color: var(--dark-color);
    border-bottom: 2px solid var(--primary-color);
    padding-bottom: 10px;
}

.info-item,
.preference-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e1e5e9;
}

.info-item:last-of-type,
.preference-item:last-of-type {
    border-bottom: none;
    margin-bottom: 15px;
}

.info-item label,
.preference-item label {
    font-weight: 500;
    color: var(--secondary-color);
}

.preference-status.enabled {
    color: var(--success-color);
}

.preference-status.disabled {
    color: var(--secondary-color);
}

.address-summary {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e1e5e9;
}

.no-addresses {
    color: var(--secondary-color);
    font-style: italic;
    margin-bottom: 15px;
}

/* Quick Actions */
.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.action-card {
    padding: 25px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    text-align: center;
    text-decoration: none;
    color: var(--dark-color);
    transition: all 0.3s ease;
}

.action-card:hover {
    border-color: var(--primary-color);
    background: #f8f9ff;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.action-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), var(--info-color));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
    margin: 0 auto 15px;
}

.action-card h4 {
    margin-bottom: 10px;
    color: var(--dark-color);
}

.action-card p {
    margin: 0;
    color: var(--secondary-color);
    font-size: 14px;
}

/* Responsive */
@media (max-width: 768px) {
    .account-content {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .account-sidebar {
        position: static;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .table-header {
        display: none;
    }
    
    .table-row {
        grid-template-columns: 1fr;
        gap: 10px;
        text-align: left;
        padding: 20px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .actions-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .stats-grid,
    .actions-grid {
        grid-template-columns: 1fr;
    }
    
    .section-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
}
</style>
<?php $this->endSection(); ?>