<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php $this->escape($title ?? 'Shopologic Admin'); ?></title>
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Admin CSS -->
    <link rel="stylesheet" href="<?php echo $this->theme_asset('css/admin.css'); ?>">
    
    <!-- Additional head content -->
    <?php $this->block('head'); ?>
</head>
<body class="admin-body">
    <!-- Admin Header -->
    <header class="admin-header">
        <div class="admin-header-content">
            <div class="admin-logo">
                <a href="/admin">
                    <h1>Shopologic Admin</h1>
                </a>
            </div>
            
            <nav class="admin-nav">
                <ul>
                    <li><a href="/admin" class="<?php echo $this->isActive('dashboard'); ?>">Dashboard</a></li>
                    <li><a href="/admin/products" class="<?php echo $this->isActive('products'); ?>">Products</a></li>
                    <li><a href="/admin/orders" class="<?php echo $this->isActive('orders'); ?>">Orders</a></li>
                    <li><a href="/admin/customers" class="<?php echo $this->isActive('customers'); ?>">Customers</a></li>
                    <li><a href="/admin/reports" class="<?php echo $this->isActive('reports'); ?>">Reports</a></li>
                    <li><a href="/admin/settings" class="<?php echo $this->isActive('settings'); ?>">Settings</a></li>
                </ul>
            </nav>
            
            <div class="admin-user">
                <span class="admin-username">
                    <?php echo $this->e($user['name'] ?? 'Admin'); ?>
                </span>
                <div class="admin-user-menu">
                    <a href="/admin/profile">Profile</a>
                    <a href="/" target="_blank">View Store</a>
                    <a href="/admin/logout">Logout</a>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Admin Sidebar -->
    <aside class="admin-sidebar">
        <nav class="admin-sidebar-nav">
            <div class="sidebar-section">
                <h3>Store Management</h3>
                <ul>
                    <li><a href="/admin/products" class="<?php echo $this->isActive('products'); ?>">
                        <span class="icon">📦</span> Products
                    </a></li>
                    <li><a href="/admin/categories" class="<?php echo $this->isActive('categories'); ?>">
                        <span class="icon">📁</span> Categories
                    </a></li>
                    <li><a href="/admin/inventory" class="<?php echo $this->isActive('inventory'); ?>">
                        <span class="icon">📊</span> Inventory
                    </a></li>
                    <li><a href="/admin/orders" class="<?php echo $this->isActive('orders'); ?>">
                        <span class="icon">🛒</span> Orders
                    </a></li>
                    <li><a href="/admin/customers" class="<?php echo $this->isActive('customers'); ?>">
                        <span class="icon">👥</span> Customers
                    </a></li>
                </ul>
            </div>
            
            <div class="sidebar-section">
                <h3>Marketing</h3>
                <ul>
                    <li><a href="/admin/promotions" class="<?php echo $this->isActive('promotions'); ?>">
                        <span class="icon">🎯</span> Promotions
                    </a></li>
                    <li><a href="/admin/coupons" class="<?php echo $this->isActive('coupons'); ?>">
                        <span class="icon">🎟️</span> Coupons
                    </a></li>
                    <li><a href="/admin/email-campaigns" class="<?php echo $this->isActive('email-campaigns'); ?>">
                        <span class="icon">📧</span> Email Campaigns
                    </a></li>
                    <li><a href="/admin/reviews" class="<?php echo $this->isActive('reviews'); ?>">
                        <span class="icon">⭐</span> Reviews
                    </a></li>
                </ul>
            </div>
            
            <div class="sidebar-section">
                <h3>Analytics</h3>
                <ul>
                    <li><a href="/admin/reports" class="<?php echo $this->isActive('reports'); ?>">
                        <span class="icon">📈</span> Reports
                    </a></li>
                    <li><a href="/admin/analytics" class="<?php echo $this->isActive('analytics'); ?>">
                        <span class="icon">📊</span> Analytics
                    </a></li>
                    <li><a href="/admin/performance" class="<?php echo $this->isActive('performance'); ?>">
                        <span class="icon">⚡</span> Performance
                    </a></li>
                </ul>
            </div>
            
            <div class="sidebar-section">
                <h3>Settings</h3>
                <ul>
                    <li><a href="/admin/settings/general" class="<?php echo $this->isActive('settings-general'); ?>">
                        <span class="icon">⚙️</span> General
                    </a></li>
                    <li><a href="/admin/settings/payment" class="<?php echo $this->isActive('settings-payment'); ?>">
                        <span class="icon">💳</span> Payment
                    </a></li>
                    <li><a href="/admin/settings/shipping" class="<?php echo $this->isActive('settings-shipping'); ?>">
                        <span class="icon">🚚</span> Shipping
                    </a></li>
                    <li><a href="/admin/plugins" class="<?php echo $this->isActive('plugins'); ?>">
                        <span class="icon">🔌</span> Plugins
                    </a></li>
                    <li><a href="/admin/themes" class="<?php echo $this->isActive('themes'); ?>">
                        <span class="icon">🎨</span> Themes
                    </a></li>
                </ul>
            </div>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <main class="admin-main">
        <!-- Flash Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php echo $this->e($_SESSION['success_message']); ?>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <?php echo $this->e($_SESSION['error_message']); ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Page Content -->
        <?php $this->block('content'); ?>
    </main>
    
    <!-- Admin Footer -->
    <footer class="admin-footer">
        <div class="admin-footer-content">
            <p>&copy; <?php echo date('Y'); ?> Shopologic. All rights reserved.</p>
            <p>Version 1.0.0 | <a href="/admin/help">Help</a> | <a href="/admin/docs">Documentation</a></p>
        </div>
    </footer>
    
    <!-- Admin JavaScript -->
    <script src="<?php echo $this->theme_asset('js/admin.js'); ?>"></script>
    
    <!-- Additional scripts -->
    <?php $this->block('scripts'); ?>
</body>
</html>