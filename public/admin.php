<?php
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
?>
<!DOCTYPE html>
<html>
<head>
    <title>Shopologic Admin Panel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8f9fa; }
        .header { background: #343a40; color: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { font-size: 1.5rem; }
        .sidebar { position: fixed; left: 0; top: 70px; width: 250px; height: calc(100vh - 70px); background: white; border-right: 1px solid #dee2e6; padding: 1rem 0; }
        .sidebar ul { list-style: none; }
        .sidebar li { margin: 0.5rem 0; }
        .sidebar a { display: block; padding: 0.75rem 1.5rem; color: #495057; text-decoration: none; transition: all 0.3s; }
        .sidebar a:hover, .sidebar a.active { background: #007bff; color: white; }
        .main { margin-left: 250px; padding: 2rem; }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .card { background: white; border-radius: 8px; padding: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .card h3 { color: #495057; margin-bottom: 0.5rem; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .card .number { font-size: 2rem; font-weight: bold; color: #007bff; }
        .table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .table th, .table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #dee2e6; }
        .table th { background: #f8f9fa; font-weight: 600; }
        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 500; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .btn { display: inline-block; padding: 0.5rem 1rem; background: #007bff; color: white; border: none; border-radius: 4px; text-decoration: none; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.75rem; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🛒 Shopologic Admin Panel</h1>
    </div>
    
    <div class="sidebar">
        <ul>
            <li><a href="#dashboard" class="active">📊 Dashboard</a></li>
            <li><a href="#plugins">🔌 Plugins</a></li>
            <li><a href="#products">📦 Products</a></li>
            <li><a href="#orders">🛍️ Orders</a></li>
            <li><a href="#customers">👥 Customers</a></li>
            <li><a href="#analytics">📈 Analytics</a></li>
            <li><a href="#settings">⚙️ Settings</a></li>
            <li><a href="/">🏠 Back to Store</a></li>
        </ul>
    </div>
    
    <div class="main">
        <h2>Dashboard</h2>
        
        <div class="dashboard-grid">
            <div class="card">
                <h3>Total Plugins</h3>
                <div class="number"><?php echo $totalPlugins; ?></div>
            </div>
            <div class="card">
                <h3>Active Plugins</h3>
                <div class="number"><?php echo $activePlugins; ?></div>
            </div>
            <div class="card">
                <h3>System Status</h3>
                <div class="number">✅</div>
            </div>
            <div class="card">
                <h3>PHP Version</h3>
                <div class="number"><?php echo PHP_VERSION; ?></div>
            </div>
        </div>
        
        <div class="card">
            <h3>Plugin Management</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Plugin Name</th>
                        <th>Version</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($plugins, 0, 10) as $key => $plugin): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($plugin['name']); ?></td>
                        <td><?php echo htmlspecialchars($plugin['version']); ?></td>
                        <td>
                            <?php if ($plugin['active']): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm">Configure</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (count($plugins) > 10): ?>
                <p style="margin-top: 1rem; color: #6c757d;">
                    Showing 10 of <?php echo count($plugins); ?> plugins. <a href="#plugins">View all plugins →</a>
                </p>
            <?php endif; ?>
        </div>
        
        <div class="card" style="margin-top: 2rem;">
            <h3>Quick Actions</h3>
            <p style="margin-bottom: 1rem;">Common administrative tasks:</p>
            <a href="/test.php" class="btn">System Test</a>
            <a href="/api.php" class="btn">API Documentation</a>
            <button class="btn">Clear Cache</button>
            <button class="btn">Run Migrations</button>
        </div>
        
        <div class="card" style="margin-top: 2rem;">
            <h3>System Information</h3>
            <table class="table">
                <tr><td><strong>Platform</strong></td><td>Shopologic Enterprise E-commerce</td></tr>
                <tr><td><strong>PHP Version</strong></td><td><?php echo PHP_VERSION; ?></td></tr>
                <tr><td><strong>Server Software</strong></td><td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td></tr>
                <tr><td><strong>Document Root</strong></td><td><?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'; ?></td></tr>
                <tr><td><strong>Total Plugins</strong></td><td><?php echo $totalPlugins; ?></td></tr>
                <tr><td><strong>Active Plugins</strong></td><td><?php echo $activePlugins; ?></td></tr>
            </table>
        </div>
    </div>
</body>
</html>