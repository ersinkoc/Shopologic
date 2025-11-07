<?php
// CRITICAL SECURITY: Require authentication for admin panel
session_start();

// Check if user is authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin'])) {
    // Not logged in or not an admin - redirect to login
    header('Location: /auth/login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Check if session is still valid (not expired)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    // Session expired after 1 hour of inactivity
    session_unset();
    session_destroy();
    header('Location: /auth/login?error=session_expired&redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();

// Load plugin data
$storageDir = dirname(__DIR__) . '/storage/plugins';
$pluginsFile = $storageDir . '/plugins.json';

$plugins = [];
$totalPlugins = 0;
$activePlugins = 0;

if (file_exists($pluginsFile)) {
    $data = json_decode(file_get_contents($pluginsFile), true);
    $plugins = $data['plugins'] ?? [];
    $totalPlugins = count($plugins);
    $activePlugins = count(array_filter($plugins, fn($p) => $p['active'] ?? false));
}

// Demo stats
$stats = [
    'revenue_today' => 3456.78,
    'orders_today' => 28,
    'new_customers' => 15,
    'products' => 234,
    'low_stock' => 12
];

// Recent activities
$activities = [
    ['time' => '2 min ago', 'action' => 'New order #1234 received', 'icon' => '🛒', 'color' => '#28a745'],
    ['time' => '15 min ago', 'action' => 'Customer John Doe registered', 'icon' => '👤', 'color' => '#007bff'],
    ['time' => '1 hour ago', 'action' => 'Product "Laptop Pro" low on stock', 'icon' => '⚠️', 'color' => '#ffc107'],
    ['time' => '2 hours ago', 'action' => 'Email campaign sent to 500 customers', 'icon' => '✉️', 'color' => '#17a2b8'],
    ['time' => '3 hours ago', 'action' => 'Backup completed successfully', 'icon' => '✅', 'color' => '#28a745']
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Shopologic Admin Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; }
        
        /* Header */
        .header { background: #1a1d23; color: white; padding: 1rem 0; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header-content { max-width: 1400px; margin: 0 auto; padding: 0 2rem; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: bold; display: flex; align-items: center; gap: 0.5rem; }
        .header-nav { display: flex; gap: 2rem; }
        .header-nav a { color: rgba(255,255,255,0.8); text-decoration: none; transition: color 0.3s; padding: 0.5rem 1rem; border-radius: 5px; }
        .header-nav a:hover { color: white; background: rgba(255,255,255,0.1); }
        
        /* Main Layout */
        .main-layout { display: grid; grid-template-columns: 250px 1fr; }
        
        /* Sidebar */
        .sidebar { background: white; height: calc(100vh - 70px); border-right: 1px solid #dee2e6; position: sticky; top: 70px; overflow-y: auto; }
        .sidebar-menu { list-style: none; padding: 1rem 0; }
        .sidebar-item { margin: 0.25rem 0; }
        .sidebar-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1.5rem; color: #495057; text-decoration: none; transition: all 0.3s; position: relative; }
        .sidebar-link:hover { background: #f8f9fa; color: #007bff; }
        .sidebar-link.active { background: #007bff; color: white; }
        .sidebar-link.active::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: #0056b3; }
        .sidebar-icon { font-size: 1.2rem; width: 24px; text-align: center; }
        
        /* Content Area */
        .content { padding: 2rem; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .page-title { font-size: 1.8rem; color: #343a40; }
        .header-actions { display: flex; gap: 1rem; }
        .btn { padding: 0.5rem 1.5rem; border: none; border-radius: 5px; font-weight: 500; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; }
        .btn-secondary { background: white; color: #495057; border: 1px solid #dee2e6; }
        .btn-secondary:hover { background: #f8f9fa; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: white; border-radius: 10px; padding: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; transition: transform 0.3s, box-shadow 0.3s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .stat-info h3 { color: #6c757d; font-size: 0.9rem; font-weight: 500; margin-bottom: 0.5rem; }
        .stat-value { font-size: 2rem; font-weight: bold; color: #343a40; }
        .stat-change { font-size: 0.85rem; margin-top: 0.25rem; }
        .change-positive { color: #28a745; }
        .change-negative { color: #dc3545; }
        .stat-icon { font-size: 2.5rem; opacity: 0.8; }
        
        /* Dashboard Grid */
        .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; }
        
        /* Card Styles */
        .card { background: white; border-radius: 10px; padding: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .card-title { font-size: 1.2rem; font-weight: 600; color: #343a40; }
        .card-actions { display: flex; gap: 0.5rem; }
        
        /* Table */
        .table { width: 100%; border-collapse: collapse; }
        .table th { text-align: left; padding: 0.75rem; border-bottom: 2px solid #dee2e6; color: #6c757d; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; }
        .table td { padding: 0.75rem; border-bottom: 1px solid #f8f9fa; }
        .table tr:hover { background: #f8f9fa; }
        .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 500; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        
        /* Activity Feed */
        .activity-item { display: flex; align-items: start; gap: 1rem; padding: 1rem 0; border-bottom: 1px solid #f8f9fa; }
        .activity-item:last-child { border-bottom: none; }
        .activity-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .activity-content { flex: 1; }
        .activity-action { color: #343a40; margin-bottom: 0.25rem; }
        .activity-time { color: #6c757d; font-size: 0.85rem; }
        
        /* Quick Actions */
        .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; }
        .quick-action { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 1.5rem; text-align: center; cursor: pointer; transition: all 0.3s; text-decoration: none; color: #495057; }
        .quick-action:hover { background: white; border-color: #007bff; color: #007bff; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .quick-action-icon { font-size: 2rem; margin-bottom: 0.5rem; }
        .quick-action-label { font-weight: 500; }
        
        /* Charts Placeholder */
        .chart-placeholder { background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 8px; height: 300px; display: flex; align-items: center; justify-content: center; color: #6c757d; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-layout { grid-template-columns: 1fr; }
            .sidebar { position: static; height: auto; }
            .dashboard-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                🛒 Shopologic Admin
            </div>
            <nav class="header-nav">
                <a href="/analytics.php">📊 Analytics</a>
                <a href="/plugins.php">🔌 Plugins</a>
                <a href="/themes.php">🎨 Themes</a>
                <a href="/settings.php">⚙️ Settings</a>
                <a href="/" style="background: rgba(255,255,255,0.1);">🏪 View Store</a>
            </nav>
        </div>
    </header>
    
    <div class="main-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li class="sidebar-item">
                    <a href="/admin.php" class="sidebar-link active">
                        <span class="sidebar-icon">📊</span>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="/orders.php" class="sidebar-link">
                        <span class="sidebar-icon">🛍️</span>
                        <span>Orders</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="/products-admin.php" class="sidebar-link">
                        <span class="sidebar-icon">📦</span>
                        <span>Products</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="/customers.php" class="sidebar-link">
                        <span class="sidebar-icon">👥</span>
                        <span>Customers</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="/marketing.php" class="sidebar-link">
                        <span class="sidebar-icon">📣</span>
                        <span>Marketing</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="/reports.php" class="sidebar-link">
                        <span class="sidebar-icon">📈</span>
                        <span>Reports</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="/plugins.php" class="sidebar-link">
                        <span class="sidebar-icon">🔌</span>
                        <span>Plugins</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="/themes.php" class="sidebar-link">
                        <span class="sidebar-icon">🎨</span>
                        <span>Themes</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="/settings.php" class="sidebar-link">
                        <span class="sidebar-icon">⚙️</span>
                        <span>Settings</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="/support.php" class="sidebar-link">
                        <span class="sidebar-icon">❓</span>
                        <span>Help & Support</span>
                    </a>
                </li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="content">
            <div class="page-header">
                <h1 class="page-title">Dashboard Overview</h1>
                <div class="header-actions">
                    <button class="btn btn-secondary">📥 Export Report</button>
                    <button class="btn btn-primary">➕ New Product</button>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Today's Revenue</h3>
                        <div class="stat-value">$<?php echo number_format($stats['revenue_today'], 2); ?></div>
                        <div class="stat-change change-positive">↑ 12.5% vs yesterday</div>
                    </div>
                    <div class="stat-icon">💰</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Orders Today</h3>
                        <div class="stat-value"><?php echo $stats['orders_today']; ?></div>
                        <div class="stat-change change-positive">↑ 8 more than yesterday</div>
                    </div>
                    <div class="stat-icon">📦</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>New Customers</h3>
                        <div class="stat-value"><?php echo $stats['new_customers']; ?></div>
                        <div class="stat-change change-negative">↓ 2 less than yesterday</div>
                    </div>
                    <div class="stat-icon">👤</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Active Plugins</h3>
                        <div class="stat-value"><?php echo $activePlugins; ?>/<?php echo $totalPlugins; ?></div>
                        <div class="stat-change" style="color: #28a745;">All systems operational</div>
                    </div>
                    <div class="stat-icon">🔌</div>
                </div>
            </div>
            
            <div class="dashboard-grid">
                <!-- Left Column -->
                <div>
                    <!-- Sales Chart -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">📈 Sales Overview</h2>
                            <div class="card-actions">
                                <select style="padding: 0.25rem 0.5rem; border: 1px solid #dee2e6; border-radius: 5px;">
                                    <option>Last 7 days</option>
                                    <option>Last 30 days</option>
                                    <option>Last 90 days</option>
                                </select>
                            </div>
                        </div>
                        <div class="chart-placeholder">
                            <div>
                                <div style="font-size: 3rem; margin-bottom: 1rem;">📊</div>
                                <div>Sales chart visualization would appear here</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Orders -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">🛒 Recent Orders</h2>
                            <a href="/orders.php" style="color: #007bff; text-decoration: none; font-size: 0.9rem;">View all →</a>
                        </div>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>#SH-2025-1234</td>
                                    <td>John Doe</td>
                                    <td>$234.56</td>
                                    <td><span class="badge badge-warning">Processing</span></td>
                                </tr>
                                <tr>
                                    <td>#SH-2025-1233</td>
                                    <td>Jane Smith</td>
                                    <td>$567.89</td>
                                    <td><span class="badge badge-success">Completed</span></td>
                                </tr>
                                <tr>
                                    <td>#SH-2025-1232</td>
                                    <td>Bob Johnson</td>
                                    <td>$123.45</td>
                                    <td><span class="badge badge-info">Shipped</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div>
                    <!-- Quick Actions -->
                    <div class="card">
                        <h2 class="card-title" style="margin-bottom: 1rem;">⚡ Quick Actions</h2>
                        <div class="quick-actions">
                            <a href="/products-admin.php?action=new" class="quick-action">
                                <div class="quick-action-icon">📦</div>
                                <div class="quick-action-label">Add Product</div>
                            </a>
                            <a href="/orders.php" class="quick-action">
                                <div class="quick-action-icon">🛍️</div>
                                <div class="quick-action-label">View Orders</div>
                            </a>
                            <a href="/marketing.php?action=campaign" class="quick-action">
                                <div class="quick-action-icon">📧</div>
                                <div class="quick-action-label">Email Campaign</div>
                            </a>
                            <a href="/reports.php" class="quick-action">
                                <div class="quick-action-icon">📊</div>
                                <div class="quick-action-label">Reports</div>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Activity Feed -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">🔔 Recent Activity</h2>
                        </div>
                        <div>
                            <?php foreach ($activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon" style="background: <?php echo $activity['color']; ?>20; color: <?php echo $activity['color']; ?>;">
                                    <?php echo $activity['icon']; ?>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-action"><?php echo $activity['action']; ?></div>
                                    <div class="activity-time"><?php echo $activity['time']; ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- System Status -->
                    <div class="card">
                        <h2 class="card-title" style="margin-bottom: 1rem;">🔧 System Status</h2>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span>Database</span>
                                <span style="color: #28a745;">✅ Operational</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span>API Services</span>
                                <span style="color: #28a745;">✅ Operational</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span>Email Service</span>
                                <span style="color: #28a745;">✅ Operational</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span>Storage</span>
                                <span style="color: #ffc107;">⚠️ 78% Used</span>
                            </div>
                        </div>
                        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #dee2e6;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <small style="color: #6c757d;">PHP <?php echo PHP_VERSION; ?></small>
                                <small style="color: #6c757d;">Uptime: 99.9%</small>
                            </div>
                            <a href="/test.php" class="btn btn-secondary" style="width: 100%; justify-content: center;">Run System Test</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Simulate real-time updates
        function updateStats() {
            // This would fetch real data in production
            console.log('Updating dashboard stats...');
        }
        
        // Update every 30 seconds
        setInterval(updateStats, 30000);
    </script>
</body>
</html>