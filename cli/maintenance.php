<?php

declare(strict_types=1);

/**
 * Shopologic Maintenance Mode CLI Tool
 * 
 * Manages maintenance mode for the application
 */

define('SHOPOLOGIC_ROOT', dirname(__DIR__));

require_once SHOPOLOGIC_ROOT . '/core/src/Autoloader.php';

use Shopologic\Core\Autoloader;

Autoloader::register();

$command = $argv[1] ?? 'status';

switch ($command) {
    case 'enable':
        enableMaintenanceMode($argv[2] ?? null);
        break;
        
    case 'disable':
        disableMaintenanceMode();
        break;
        
    case 'status':
        showMaintenanceStatus();
        break;
        
    case 'update':
        updateMaintenanceMessage($argv[2] ?? '');
        break;
        
    case 'allow':
        allowIpAddress($argv[2] ?? '');
        break;
        
    case 'deny':
        denyIpAddress($argv[2] ?? '');
        break;
        
    case 'help':
    default:
        showHelp();
        break;
}

/**
 * Enable maintenance mode
 */
function enableMaintenanceMode(?string $message = null): void
{
    echo "Enabling maintenance mode...\n";
    
    $maintenanceFile = SHOPOLOGIC_ROOT . '/storage/maintenance.json';
    
    $data = [
        'enabled' => true,
        'started_at' => date('Y-m-d H:i:s'),
        'message' => $message ?? 'We are currently performing scheduled maintenance. We\'ll be back shortly!',
        'allowed_ips' => [],
        'estimated_time' => null
    ];
    
    // Preserve existing allowed IPs if file exists
    if (file_exists($maintenanceFile)) {
        $existing = json_decode(file_get_contents($maintenanceFile), true);
        $data['allowed_ips'] = $existing['allowed_ips'] ?? [];
    }
    
    file_put_contents($maintenanceFile, json_encode($data, JSON_PRETTY_PRINT));
    
    // Create maintenance page if it doesn't exist
    createMaintenancePage();
    
    echo "✅ Maintenance mode enabled\n";
    
    if (!empty($data['allowed_ips'])) {
        echo "Allowed IPs: " . implode(', ', $data['allowed_ips']) . "\n";
    }
}

/**
 * Disable maintenance mode
 */
function disableMaintenanceMode(): void
{
    echo "Disabling maintenance mode...\n";
    
    $maintenanceFile = SHOPOLOGIC_ROOT . '/storage/maintenance.json';
    
    if (!file_exists($maintenanceFile)) {
        echo "⚠️  Maintenance mode is not enabled\n";
        return;
    }
    
    $data = json_decode(file_get_contents($maintenanceFile), true);
    $duration = '';
    
    if (isset($data['started_at'])) {
        $started = new DateTime($data['started_at']);
        $ended = new DateTime();
        $interval = $started->diff($ended);
        $duration = $interval->format(' (Duration: %h hours, %i minutes)');
    }
    
    unlink($maintenanceFile);
    
    echo "✅ Maintenance mode disabled$duration\n";
}

/**
 * Show maintenance status
 */
function showMaintenanceStatus(): void
{
    $maintenanceFile = SHOPOLOGIC_ROOT . '/storage/maintenance.json';
    
    if (!file_exists($maintenanceFile)) {
        echo "✅ Maintenance mode is NOT enabled\n";
        return;
    }
    
    $data = json_decode(file_get_contents($maintenanceFile), true);
    
    echo "🔧 Maintenance mode is ENABLED\n\n";
    echo "Started at: {$data['started_at']}\n";
    
    if (isset($data['started_at'])) {
        $started = new DateTime($data['started_at']);
        $now = new DateTime();
        $interval = $started->diff($now);
        echo "Duration: " . $interval->format('%h hours, %i minutes') . "\n";
    }
    
    echo "Message: {$data['message']}\n";
    
    if (!empty($data['allowed_ips'])) {
        echo "\nAllowed IPs:\n";
        foreach ($data['allowed_ips'] as $ip) {
            echo "  - $ip\n";
        }
    }
    
    if (isset($data['estimated_time'])) {
        echo "\nEstimated completion: {$data['estimated_time']}\n";
    }
}

/**
 * Update maintenance message
 */
function updateMaintenanceMessage(string $message): void
{
    if (empty($message)) {
        echo "❌ Please provide a message\n";
        echo "Usage: php cli/maintenance.php update \"Your message here\"\n";
        exit(1);
    }
    
    $maintenanceFile = SHOPOLOGIC_ROOT . '/storage/maintenance.json';
    
    if (!file_exists($maintenanceFile)) {
        echo "❌ Maintenance mode is not enabled\n";
        exit(1);
    }
    
    $data = json_decode(file_get_contents($maintenanceFile), true);
    $data['message'] = $message;
    $data['updated_at'] = date('Y-m-d H:i:s');
    
    file_put_contents($maintenanceFile, json_encode($data, JSON_PRETTY_PRINT));
    
    echo "✅ Maintenance message updated\n";
}

/**
 * Allow IP address
 */
function allowIpAddress(string $ip): void
{
    if (empty($ip)) {
        echo "❌ Please provide an IP address\n";
        echo "Usage: php cli/maintenance.php allow 192.168.1.100\n";
        exit(1);
    }
    
    // Validate IP address
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        echo "❌ Invalid IP address: $ip\n";
        exit(1);
    }
    
    $maintenanceFile = SHOPOLOGIC_ROOT . '/storage/maintenance.json';
    
    if (!file_exists($maintenanceFile)) {
        echo "❌ Maintenance mode is not enabled\n";
        exit(1);
    }
    
    $data = json_decode(file_get_contents($maintenanceFile), true);
    
    if (!in_array($ip, $data['allowed_ips'])) {
        $data['allowed_ips'][] = $ip;
        file_put_contents($maintenanceFile, json_encode($data, JSON_PRETTY_PRINT));
        echo "✅ IP address $ip added to allowed list\n";
    } else {
        echo "⚠️  IP address $ip is already allowed\n";
    }
}

/**
 * Deny IP address
 */
function denyIpAddress(string $ip): void
{
    if (empty($ip)) {
        echo "❌ Please provide an IP address\n";
        echo "Usage: php cli/maintenance.php deny 192.168.1.100\n";
        exit(1);
    }
    
    $maintenanceFile = SHOPOLOGIC_ROOT . '/storage/maintenance.json';
    
    if (!file_exists($maintenanceFile)) {
        echo "❌ Maintenance mode is not enabled\n";
        exit(1);
    }
    
    $data = json_decode(file_get_contents($maintenanceFile), true);
    
    if (in_array($ip, $data['allowed_ips'])) {
        $data['allowed_ips'] = array_values(array_diff($data['allowed_ips'], [$ip]));
        file_put_contents($maintenanceFile, json_encode($data, JSON_PRETTY_PRINT));
        echo "✅ IP address $ip removed from allowed list\n";
    } else {
        echo "⚠️  IP address $ip was not in allowed list\n";
    }
}

/**
 * Create maintenance page
 */
function createMaintenancePage(): void
{
    $maintenancePage = SHOPOLOGIC_ROOT . '/public/maintenance.html';
    
    if (file_exists($maintenancePage)) {
        return;
    }
    
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode - Shopologic</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .maintenance-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            padding: 60px;
            text-align: center;
            max-width: 600px;
            width: 100%;
        }
        
        .icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 30px;
            background: #f0f0f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
        }
        
        h1 {
            font-size: 36px;
            margin-bottom: 20px;
            color: #333;
        }
        
        .message {
            font-size: 18px;
            line-height: 1.6;
            color: #666;
            margin-bottom: 40px;
        }
        
        .progress {
            background: #f0f0f0;
            border-radius: 10px;
            height: 10px;
            overflow: hidden;
            margin-bottom: 40px;
        }
        
        .progress-bar {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            width: 0%;
            animation: progress 3s ease-in-out infinite;
        }
        
        @keyframes progress {
            0% { width: 0%; }
            50% { width: 70%; }
            100% { width: 0%; }
        }
        
        .contact {
            font-size: 14px;
            color: #999;
        }
        
        .contact a {
            color: #667eea;
            text-decoration: none;
        }
        
        .contact a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 600px) {
            .maintenance-container {
                padding: 40px 20px;
            }
            
            h1 {
                font-size: 28px;
            }
            
            .message {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="icon">🔧</div>
        <h1>We'll be back soon!</h1>
        <p class="message" id="maintenance-message">
            We are currently performing scheduled maintenance. 
            We'll be back shortly!
        </p>
        <div class="progress">
            <div class="progress-bar"></div>
        </div>
        <p class="contact">
            Need to get in touch? Email us at 
            <a href="mailto:support@shopologic.com">support@shopologic.com</a>
        </p>
    </div>
    
    <script>
        // Update message from maintenance.json if available
        fetch('/api/maintenance-status')
            .then(response => response.json())
            .then(data => {
                if (data.message) {
                    document.getElementById('maintenance-message').textContent = data.message;
                }
            })
            .catch(() => {
                // Fallback to default message
            });
            
        // Auto-refresh every 30 seconds
        setInterval(() => {
            fetch('/api/maintenance-status')
                .then(response => {
                    if (response.status === 404) {
                        // Maintenance mode disabled, refresh page
                        window.location.reload();
                    }
                })
                .catch(() => {});
        }, 30000);
    </script>
</body>
</html>
HTML;
    
    file_put_contents($maintenancePage, $html);
}

/**
 * Show help
 */
function showHelp(): void
{
    echo "Shopologic Maintenance Mode Tool\n";
    echo "==============================\n\n";
    echo "Usage: php cli/maintenance.php <command> [options]\n\n";
    echo "Commands:\n";
    echo "  enable [message]  Enable maintenance mode\n";
    echo "  disable           Disable maintenance mode\n";
    echo "  status            Show current status\n";
    echo "  update <message>  Update maintenance message\n";
    echo "  allow <ip>        Allow IP address access\n";
    echo "  deny <ip>         Deny IP address access\n";
    echo "  help              Show this help\n\n";
    echo "Examples:\n";
    echo "  php cli/maintenance.php enable\n";
    echo "  php cli/maintenance.php enable \"System upgrade in progress\"\n";
    echo "  php cli/maintenance.php allow 192.168.1.100\n";
    echo "  php cli/maintenance.php update \"Almost done! 5 more minutes\"\n";
    echo "  php cli/maintenance.php disable\n";
}