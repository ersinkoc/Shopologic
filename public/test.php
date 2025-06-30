<!DOCTYPE html>
<html>
<head>
    <title>Shopologic - E-commerce Platform</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
        .status { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .plugins { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-top: 30px; }
        .plugin { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px; }
        .plugin h3 { margin: 0 0 10px 0; color: #495057; font-size: 16px; }
        .plugin .version { color: #6c757d; font-size: 12px; }
        .active { background: #e7f3ff; border-color: #b8daff; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛒 Shopologic E-commerce Platform</h1>
        
        <div class="status">
            <h2>✅ System Status: Operational</h2>
            <p>The Shopologic e-commerce platform is running successfully!</p>
        </div>
        
        <h2>📦 Active Plugins (<?php echo count($activePlugins); ?>)</h2>
        
        <div class="plugins">
            <?php foreach ($plugins as $key => $plugin): ?>
                <?php if ($plugin['active']): ?>
                <div class="plugin active">
                    <h3><?php echo htmlspecialchars($plugin['name']); ?></h3>
                    <p class="version">v<?php echo htmlspecialchars($plugin['version']); ?></p>
                    <p><?php echo htmlspecialchars(substr($plugin['description'], 0, 100)); ?>...</p>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        
        <a href="/admin.php" class="btn">Access Admin Panel</a>
        <a href="/api.php" class="btn">API Documentation</a>
    </div>
</body>
</html>