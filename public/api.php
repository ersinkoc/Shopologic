<?php
// Check if this is an actual API request or documentation request
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Load plugin data for API info
$storageDir = dirname(__DIR__) . '/storage/plugins';
$pluginsFile = $storageDir . '/plugins.json';

$apiPlugins = [];
if (file_exists($pluginsFile)) {
    $data = json_decode(file_get_contents($pluginsFile), true);
    $plugins = $data['plugins'] ?? [];
    $apiPlugins = array_filter($plugins, function($plugin) {
        return strpos(strtolower($plugin['name']), 'api') !== false || 
               strpos(strtolower($plugin['description'] ?? ''), 'api') !== false;
    });
}

// If this is a real API request (not just viewing the documentation)
if ($requestMethod !== 'GET' || strpos($requestUri, '/api/') !== false) {
    header('Content-Type: application/json');

    if (strpos($requestUri, '/api/') !== false) {
        // SECURITY: Require authentication for API endpoints
        $authenticated = false;
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

        // Check for Bearer token (JWT)
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            // Validate JWT token (requires proper JWT implementation)
            // For now, we require the token to be set
            if (!empty($token) && strlen($token) > 32) {
                $authenticated = true; // TODO: Implement proper JWT validation
            }
        }

        // Check for API key in header
        if (!$authenticated) {
            $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
            if (!empty($apiKey) && strlen($apiKey) >= 32) {
                // TODO: Validate API key against database
                // For now, reject without proper validation
                $authenticated = false;
            }
        }

        // Require authentication for all API endpoints
        if (!$authenticated) {
            http_response_code(401);
            echo json_encode([
                'error' => 'Unauthorized',
                'message' => 'API access requires authentication. Please provide a valid Bearer token or API key.',
                'code' => 'UNAUTHORIZED'
            ]);
            exit;
        }

        // Parse API endpoint
        $endpoint = str_replace('/api/', '', $requestUri);

        switch ($endpoint) {
            case 'status':
                echo json_encode([
                    'status' => 'operational',
                    'timestamp' => date('c'),
                    'version' => '1.0.0',
                    'plugins' => count($plugins ?? [])
                ]);
                break;

            case 'plugins':
                echo json_encode([
                    'total' => count($plugins ?? []),
                    'active' => count(array_filter($plugins ?? [], fn($p) => $p['active'] ?? false)),
                    'plugins' => array_map(function($plugin) {
                        return [
                            'name' => $plugin['name'],
                            'version' => $plugin['version'],
                            'active' => $plugin['active'] ?? false
                        ];
                    }, $plugins ?? [])
                ]);
                break;

            default:
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
        }
    } else {
        echo json_encode([
            'message' => 'Shopologic API',
            'version' => '1.0.0',
            'endpoints' => [
                '/api/status' => 'System status',
                '/api/plugins' => 'Plugin information'
            ]
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Shopologic API Documentation</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8f9fa; line-height: 1.6; }
        .header { background: #28a745; color: white; padding: 2rem 0; text-align: center; }
        .header h1 { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .header p { font-size: 1.1rem; opacity: 0.9; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .nav { background: white; border-radius: 8px; padding: 1rem; margin-bottom: 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .nav a { display: inline-block; margin-right: 1rem; padding: 0.5rem 1rem; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
        .nav a:hover { background: #0056b3; }
        .section { background: white; border-radius: 8px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section h2 { color: #343a40; margin-bottom: 1rem; border-bottom: 2px solid #28a745; padding-bottom: 0.5rem; }
        .endpoint { background: #f8f9fa; border-left: 4px solid #28a745; padding: 1rem; margin: 1rem 0; border-radius: 0 4px 4px 0; }
        .endpoint h3 { color: #28a745; margin-bottom: 0.5rem; }
        .method { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: bold; color: white; margin-right: 0.5rem; }
        .method.get { background: #28a745; }
        .method.post { background: #007bff; }
        .method.put { background: #ffc107; color: #212529; }
        .method.delete { background: #dc3545; }
        .code { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 1rem; font-family: 'Courier New', monospace; overflow-x: auto; }
        .plugin-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem; }
        .plugin-card { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 1rem; }
        .test-button { background: #28a745; color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; margin-top: 0.5rem; }
        .test-button:hover { background: #1e7e34; }
    </style>
</head>
<body>
    <div class="header">
        <h1>📡 Shopologic API</h1>
        <p>RESTful API Documentation & Testing Interface</p>
    </div>
    
    <div class="container">
        <div class="nav">
            <a href="/">🏠 Home</a>
            <a href="/admin.php">⚙️ Admin</a>
            <a href="/test.php">🧪 System Test</a>
            <a href="#endpoints">📋 Endpoints</a>
            <a href="#plugins">🔌 Plugin APIs</a>
        </div>
        
        <div class="section">
            <h2>API Overview</h2>
            <p>The Shopologic API provides programmatic access to your e-commerce platform. All API responses are in JSON format.</p>
            
            <h3>Base URL</h3>
            <div class="code">
                <?php echo (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']; ?>/api/
            </div>
            
            <h3>Authentication</h3>
            <p>API authentication will be implemented based on your specific requirements. Currently in development mode.</p>
        </div>
        
        <div class="section" id="endpoints">
            <h2>Available Endpoints</h2>
            
            <div class="endpoint">
                <h3><span class="method get">GET</span> /api/status</h3>
                <p>Get current system status and basic information.</p>
                <button class="test-button" onclick="testEndpoint('/api/status')">Test Endpoint</button>
                <div class="code" style="margin-top: 1rem;">
{
  "status": "operational",
  "timestamp": "2025-06-30T22:30:00+00:00",
  "version": "1.0.0",
  "plugins": 77
}
                </div>
            </div>
            
            <div class="endpoint">
                <h3><span class="method get">GET</span> /api/plugins</h3>
                <p>Get information about all installed plugins.</p>
                <button class="test-button" onclick="testEndpoint('/api/plugins')">Test Endpoint</button>
                <div class="code" style="margin-top: 1rem;">
{
  "total": 77,
  "active": 77,
  "plugins": [
    {
      "name": "Core Commerce",
      "version": "1.0.0",
      "active": true
    }
  ]
}
                </div>
            </div>
        </div>
        
        <div class="section" id="plugins">
            <h2>Plugin APIs</h2>
            <p>The following plugins provide API functionality:</p>
            
            <div class="plugin-grid">
                <?php if (empty($apiPlugins)): ?>
                    <div class="plugin-card">
                        <h3>Core API</h3>
                        <p>Basic API functionality is provided by the core system. Additional API features are available through plugins.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($apiPlugins as $plugin): ?>
                    <div class="plugin-card">
                        <h3><?php echo htmlspecialchars($plugin['name']); ?></h3>
                        <p><?php echo htmlspecialchars(substr($plugin['description'] ?? '', 0, 100)); ?>...</p>
                        <p><strong>Version:</strong> <?php echo htmlspecialchars($plugin['version']); ?></p>
                        <p><strong>Status:</strong> <?php echo $plugin['active'] ? '✅ Active' : '❌ Inactive'; ?></p>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="section">
            <h2>Live API Testing</h2>
            <p>Test API endpoints directly from this page:</p>
            
            <div style="margin: 1rem 0;">
                <label for="endpoint-url">Endpoint URL:</label><br>
                <input type="text" id="endpoint-url" value="/api/status" style="width: 300px; padding: 0.5rem; margin: 0.5rem 0; border: 1px solid #ccc; border-radius: 4px;">
                <button class="test-button" onclick="testCustomEndpoint()">Test</button>
            </div>
            
            <div id="test-results" class="code" style="display: none;">
                <h4>Response:</h4>
                <pre id="response-content"></pre>
            </div>
        </div>
        
        <div class="section">
            <h2>System Information</h2>
            <div class="code">
Platform: Shopologic Enterprise E-commerce
PHP Version: <?php echo PHP_VERSION; ?>

Total Plugins: <?php echo count($plugins ?? []); ?>

Active Plugins: <?php echo count(array_filter($plugins ?? [], fn($p) => $p['active'] ?? false)); ?>

API Status: ✅ Operational
            </div>
        </div>
    </div>
    
    <script>
        async function testEndpoint(url) {
            try {
                const response = await fetch(url);
                const data = await response.json();
                
                document.getElementById('test-results').style.display = 'block';
                document.getElementById('response-content').textContent = JSON.stringify(data, null, 2);
            } catch (error) {
                document.getElementById('test-results').style.display = 'block';
                document.getElementById('response-content').textContent = 'Error: ' + error.message;
            }
        }
        
        function testCustomEndpoint() {
            const url = document.getElementById('endpoint-url').value;
            testEndpoint(url);
        }
    </script>
</body>
</html>