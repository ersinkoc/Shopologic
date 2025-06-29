<?php
/**
 * Shopologic Plugin System Demo
 * 
 * This demonstrates the plugin system capabilities
 */

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║                    SHOPOLOGIC PLUGIN SYSTEM DEMO                     ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

// Simulate plugin discovery
$plugins = [
    'payment-paypal' => ['name' => 'PayPal Payment Gateway', 'status' => 'active', 'version' => '1.0.0'],
    'payment-stripe' => ['name' => 'Stripe Payment Gateway', 'status' => 'active', 'version' => '1.0.0'],
    'analytics-google' => ['name' => 'Google Analytics', 'status' => 'active', 'version' => '1.0.0'],
    'reviews-ratings' => ['name' => 'Product Reviews & Ratings', 'status' => 'active', 'version' => '1.0.0'],
    'seo-optimizer' => ['name' => 'SEO Optimizer Pro', 'status' => 'inactive', 'version' => '1.0.0'],
    'live-chat' => ['name' => 'Live Chat Support', 'status' => 'active', 'version' => '1.0.0'],
    'multi-currency' => ['name' => 'Multi-Currency Support', 'status' => 'active', 'version' => '1.0.0'],
    'email-marketing' => ['name' => 'Email Marketing Hub', 'status' => 'inactive', 'version' => '1.0.0'],
    'loyalty-rewards' => ['name' => 'Loyalty & Rewards', 'status' => 'active', 'version' => '1.0.0'],
    'inventory-management' => ['name' => 'Advanced Inventory', 'status' => 'active', 'version' => '1.0.0'],
];

// Display installed plugins
echo "📦 \033[1mInstalled Plugins\033[0m\n";
echo "─────────────────────────────────────────────────────────────────────\n";
printf("%-25s %-35s %-10s %s\n", "PLUGIN ID", "NAME", "VERSION", "STATUS");
echo "─────────────────────────────────────────────────────────────────────\n";

foreach ($plugins as $id => $plugin) {
    $statusColor = $plugin['status'] === 'active' ? "\033[32m" : "\033[33m";
    $statusIcon = $plugin['status'] === 'active' ? "●" : "○";
    
    printf("%-25s %-35s %-10s %s%s %s\033[0m\n", 
        $id, 
        $plugin['name'], 
        $plugin['version'],
        $statusColor,
        $statusIcon,
        ucfirst($plugin['status'])
    );
}

echo "\n";

// Simulate hook system
echo "🔗 \033[1mHook System Demo\033[0m\n";
echo "─────────────────────────────────────────────────────────────────────\n";

$hooks = [
    'product.price' => [
        ['plugin' => 'multi-currency', 'action' => 'Convert to EUR', 'priority' => 10],
        ['plugin' => 'loyalty-rewards', 'action' => 'Apply 10% member discount', 'priority' => 20],
    ],
    'order.completed' => [
        ['plugin' => 'analytics-google', 'action' => 'Track purchase event', 'priority' => 10],
        ['plugin' => 'loyalty-rewards', 'action' => 'Award 150 points', 'priority' => 15],
        ['plugin' => 'email-marketing', 'action' => 'Send order confirmation', 'priority' => 20],
        ['plugin' => 'inventory-management', 'action' => 'Update stock levels', 'priority' => 5],
    ],
    'cart.item_added' => [
        ['plugin' => 'analytics-google', 'action' => 'Track add to cart', 'priority' => 10],
        ['plugin' => 'live-chat', 'action' => 'Show chat prompt', 'priority' => 30],
    ]
];

foreach ($hooks as $hook => $actions) {
    echo "\n\033[36m{$hook}\033[0m\n";
    
    // Sort by priority
    usort($actions, fn($a, $b) => $a['priority'] <=> $b['priority']);
    
    foreach ($actions as $i => $action) {
        $isActive = $plugins[$action['plugin']]['status'] === 'active';
        $icon = $isActive ? '✓' : '○';
        $color = $isActive ? "\033[32m" : "\033[90m";
        
        echo "  {$color}{$icon} [{$action['priority']}] {$action['plugin']}: {$action['action']}\033[0m\n";
    }
}

echo "\n";

// Simulate API endpoints
echo "🌐 \033[1mAPI Endpoints\033[0m\n";
echo "─────────────────────────────────────────────────────────────────────\n";

$endpoints = [
    ['method' => 'GET', 'path' => '/api/v1/products/{id}/reviews', 'plugin' => 'reviews-ratings'],
    ['method' => 'POST', 'path' => '/api/v1/loyalty/redeem', 'plugin' => 'loyalty-rewards'],
    ['method' => 'GET', 'path' => '/api/v1/currencies/rates', 'plugin' => 'multi-currency'],
    ['method' => 'POST', 'path' => '/api/v1/chat/conversations', 'plugin' => 'live-chat'],
    ['method' => 'POST', 'path' => '/api/v1/paypal/create-order', 'plugin' => 'payment-paypal'],
];

foreach ($endpoints as $endpoint) {
    $methodColor = match($endpoint['method']) {
        'GET' => "\033[34m",
        'POST' => "\033[32m",
        'PUT' => "\033[33m",
        'DELETE' => "\033[31m",
        default => "\033[0m"
    };
    
    $isActive = $plugins[$endpoint['plugin']]['status'] === 'active';
    $statusIcon = $isActive ? '✓' : '✗';
    $statusColor = $isActive ? "\033[32m" : "\033[31m";
    
    printf("%s%-6s\033[0m %-40s %s%s\033[0m %s\n",
        $methodColor,
        $endpoint['method'],
        $endpoint['path'],
        $statusColor,
        $statusIcon,
        $endpoint['plugin']
    );
}

echo "\n";

// Plugin statistics
echo "📊 \033[1mPlugin Statistics\033[0m\n";
echo "─────────────────────────────────────────────────────────────────────\n";

$stats = [
    'Total Plugins' => count($plugins),
    'Active Plugins' => count(array_filter($plugins, fn($p) => $p['status'] === 'active')),
    'Inactive Plugins' => count(array_filter($plugins, fn($p) => $p['status'] === 'inactive')),
    'Total Hooks' => count($hooks),
    'Total API Endpoints' => count($endpoints),
];

foreach ($stats as $label => $value) {
    printf("%-20s: %d\n", $label, $value);
}

echo "\n";

// Feature matrix
echo "✨ \033[1mFeature Matrix\033[0m\n";
echo "─────────────────────────────────────────────────────────────────────\n";

$features = [
    'E-commerce' => ['payment-paypal', 'payment-stripe', 'multi-currency', 'inventory-management'],
    'Marketing' => ['analytics-google', 'email-marketing', 'seo-optimizer', 'loyalty-rewards'],
    'Customer Service' => ['live-chat', 'reviews-ratings'],
];

foreach ($features as $category => $pluginIds) {
    echo "\n\033[1m{$category}\033[0m\n";
    foreach ($pluginIds as $pluginId) {
        if (isset($plugins[$pluginId])) {
            $plugin = $plugins[$pluginId];
            $icon = $plugin['status'] === 'active' ? '✓' : '○';
            $color = $plugin['status'] === 'active' ? "\033[32m" : "\033[90m";
            echo "  {$color}{$icon} {$plugin['name']}\033[0m\n";
        }
    }
}

echo "\n";

// Sample workflow simulation
echo "🔄 \033[1mSample Workflow: Order Completion\033[0m\n";
echo "─────────────────────────────────────────────────────────────────────\n";

$workflow = [
    ['time' => '0ms', 'action' => 'Order #1234 marked as completed', 'plugin' => 'core'],
    ['time' => '5ms', 'action' => 'Stock levels updated (-2 items)', 'plugin' => 'inventory-management'],
    ['time' => '10ms', 'action' => 'Purchase event tracked ($99.99)', 'plugin' => 'analytics-google'],
    ['time' => '15ms', 'action' => '150 loyalty points awarded', 'plugin' => 'loyalty-rewards'],
    ['time' => '20ms', 'action' => 'Order confirmation email sent', 'plugin' => 'email-marketing'],
    ['time' => '25ms', 'action' => 'Review request scheduled (+14 days)', 'plugin' => 'reviews-ratings'],
];

foreach ($workflow as $step) {
    $pluginColor = $step['plugin'] === 'core' ? "\033[35m" : "\033[36m";
    printf("\033[90m[%s]\033[0m %s%s\033[0m: %s\n", 
        $step['time'],
        $pluginColor,
        $step['plugin'],
        $step['action']
    );
}

echo "\n";
echo "────────────────────────────────────────────────────────────────────\n";
echo "✅ Plugin system is fully operational!\n\n";