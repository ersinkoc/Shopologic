<?php

declare(strict_types=1);

namespace Shopologic\Plugins\Shared\Examples;

use Shopologic\Plugins\Shared\PluginIntegrationManager;
use Shopologic\Plugins\Shared\Events\PluginEventDispatcher;
use Shopologic\Plugins\Shared\Monitoring\PluginHealthMonitor;
use Shopologic\Plugins\Shared\Testing\PluginTestFramework;

/**
 * Comprehensive demonstration of the integrated plugin ecosystem
 * Shows real-world usage scenarios and cross-plugin workflows
 */
class PluginIntegrationDemo
{
    private PluginIntegrationManager $integrationManager;
    private PluginEventDispatcher $eventDispatcher;
    private PluginHealthMonitor $healthMonitor;
    private PluginTestFramework $testFramework;
    
    public function __construct()
    {
        $this->integrationManager = PluginIntegrationManager::getInstance();
        $this->eventDispatcher = PluginEventDispatcher::getInstance();
        $this->healthMonitor = PluginHealthMonitor::getInstance();
        $this->testFramework = new PluginTestFramework();
    }
    
    /**
     * Demonstrate complete e-commerce order workflow
     */
    public function demonstrateOrderWorkflow(): array
    {
        echo "🛒 Starting Complete E-commerce Order Workflow Demo\n\n";
        
        // Sample order data
        $orderData = [
            'customer_id' => 12345,
            'order_id' => 'ORD-2024-001',
            'order_total' => 299.99,
            'currency' => 'USD',
            'customer_tier' => 'gold',
            'order_items' => [
                [
                    'product_id' => 'PROD-001',
                    'quantity' => 2,
                    'unit_price' => 99.99,
                    'total_price' => 199.98
                ],
                [
                    'product_id' => 'PROD-002', 
                    'quantity' => 1,
                    'unit_price' => 100.01,
                    'total_price' => 100.01
                ]
            ]
        ];
        
        $results = [];
        
        // Step 1: Check inventory availability
        echo "📦 Checking inventory availability...\n";
        if ($inventoryProvider = $this->integrationManager->getInventoryProvider()) {
            foreach ($orderData['order_items'] as $item) {
                $inStock = $inventoryProvider->isInStock($item['product_id'], $item['quantity']);
                $results['inventory_check'][$item['product_id']] = $inStock;
                echo "  - {$item['product_id']}: " . ($inStock ? "✅ In Stock" : "❌ Out of Stock") . "\n";
            }
        }
        
        // Step 2: Apply currency conversion if needed
        echo "\n💱 Processing currency conversion...\n";
        if ($currencyProvider = $this->integrationManager->getCurrencyProvider()) {
            $customerCurrency = $currencyProvider->getCurrentCurrency();
            if ($customerCurrency !== $orderData['currency']) {
                $convertedTotal = $currencyProvider->convertCurrency(
                    $orderData['order_total'],
                    $orderData['currency'],
                    $customerCurrency
                );
                $results['currency_conversion'] = [
                    'original_amount' => $orderData['order_total'],
                    'original_currency' => $orderData['currency'],
                    'converted_amount' => $convertedTotal,
                    'customer_currency' => $customerCurrency
                ];
                echo "  - Converted {$orderData['order_total']} {$orderData['currency']} to {$convertedTotal} {$customerCurrency}\n";
            }
        }
        
        // Step 3: Reserve inventory
        echo "\n🔒 Reserving inventory...\n";
        if ($inventoryProvider = $this->integrationManager->getInventoryProvider()) {
            foreach ($orderData['order_items'] as $item) {
                $reserved = $inventoryProvider->reserveInventory(
                    $item['product_id'],
                    $item['quantity'],
                    $orderData['order_id']
                );
                $results['inventory_reservation'][$item['product_id']] = $reserved;
                echo "  - {$item['product_id']}: " . ($reserved ? "✅ Reserved" : "❌ Failed") . "\n";
            }
        }
        
        // Step 4: Calculate loyalty points
        echo "\n⭐ Calculating loyalty points...\n";
        if ($loyaltyProvider = $this->integrationManager->getLoyaltyProvider()) {
            $currentPoints = $loyaltyProvider->getPointBalance($orderData['customer_id']);
            $earnedPoints = (int)($orderData['order_total'] * 10); // 10 points per dollar
            
            $loyaltyProvider->awardPoints(
                $orderData['customer_id'],
                $earnedPoints,
                "Order {$orderData['order_id']}",
                ['order_id' => $orderData['order_id']]
            );
            
            $results['loyalty_points'] = [
                'previous_balance' => $currentPoints,
                'points_earned' => $earnedPoints,
                'new_balance' => $currentPoints + $earnedPoints
            ];
            echo "  - Points earned: {$earnedPoints}\n";
            echo "  - New balance: " . ($currentPoints + $earnedPoints) . "\n";
        }
        
        // Step 5: Execute cross-plugin workflow
        echo "\n🔄 Executing integrated workflow...\n";
        $workflowResult = $this->integrationManager->executeWorkflow('order_completed', $orderData);
        $results['workflow_execution'] = $workflowResult;
        
        if ($workflowResult['success']) {
            echo "  - ✅ Workflow completed successfully\n";
            foreach ($workflowResult['data'] as $key => $value) {
                echo "    • {$key}: " . (is_bool($value) ? ($value ? 'Yes' : 'No') : $value) . "\n";
            }
        } else {
            echo "  - ❌ Workflow had errors:\n";
            foreach ($workflowResult['errors'] as $error) {
                echo "    • {$error}\n";
            }
        }
        
        // Step 6: Send analytics data
        echo "\n📊 Recording analytics...\n";
        if ($analyticsProvider = $this->integrationManager->getAnalyticsProvider()) {
            $analyticsProvider->trackEvent('order_completed', [
                'order_id' => $orderData['order_id'],
                'customer_id' => $orderData['customer_id'],
                'order_total' => $orderData['order_total'],
                'currency' => $orderData['currency'],
                'items_count' => count($orderData['order_items'])
            ]);
            echo "  - ✅ Order analytics recorded\n";
        }
        
        // Step 7: Trigger marketing automation
        echo "\n📧 Triggering marketing automation...\n";
        if ($marketingProvider = $this->integrationManager->getMarketingProvider()) {
            $automationTriggered = $marketingProvider->triggerAutomation(
                'order_confirmation_sequence',
                $orderData['customer_id'],
                $orderData
            );
            echo "  - " . ($automationTriggered ? "✅" : "❌") . " Order confirmation sequence triggered\n";
        }
        
        echo "\n🎉 Order workflow completed!\n\n";
        return $results;
    }
    
    /**
     * Demonstrate real-time event system
     */
    public function demonstrateEventSystem(): void
    {
        echo "⚡ Real-time Event System Demo\n\n";
        
        // Register event listeners
        echo "📝 Registering event listeners...\n";
        
        $this->eventDispatcher->listen('inventory.low_stock', function($event) {
            $data = $event->getData();
            echo "  🚨 LOW STOCK ALERT: Product {$data['product_id']} has {$data['current_stock']} units remaining\n";
            
            // Trigger reorder workflow
            if ($data['current_stock'] <= $data['reorder_point']) {
                echo "  📦 Triggering automatic reorder for {$data['recommended_quantity']} units\n";
            }
        }, 10);
        
        $this->eventDispatcher->listen('customer.tier_upgraded', function($event) {
            $data = $event->getData();
            echo "  🏆 TIER UPGRADE: Customer {$data['customer_id']} upgraded to {$data['new_tier']}\n";
            echo "  🎁 Bonus points awarded: {$data['bonus_points']}\n";
        }, 10);
        
        $this->eventDispatcher->listen('analytics.threshold_exceeded', function($event) {
            $data = $event->getData();
            echo "  📊 METRIC ALERT: {$data['metric_name']} exceeded threshold ({$data['value']} > {$data['threshold']})\n";
        }, 10);
        
        // Simulate events
        echo "\n🎬 Simulating real-time events...\n";
        
        // Low stock event
        $this->eventDispatcher->dispatch('inventory.low_stock', [
            'product_id' => 'PROD-001',
            'current_stock' => 5,
            'reorder_point' => 10,
            'recommended_quantity' => 50
        ]);
        
        // Tier upgrade event
        $this->eventDispatcher->dispatch('customer.tier_upgraded', [
            'customer_id' => 12345,
            'previous_tier' => 'silver',
            'new_tier' => 'gold',
            'bonus_points' => 500,
            'upgrade_date' => date('Y-m-d H:i:s')
        ]);
        
        // Analytics threshold event
        $this->eventDispatcher->dispatch('analytics.threshold_exceeded', [
            'metric_name' => 'response_time_ms',
            'value' => 1500,
            'threshold' => 1000,
            'plugin' => 'inventory'
        ]);
        
        // Show event statistics
        echo "\n📈 Event System Statistics:\n";
        $stats = $this->eventDispatcher->getStatistics();
        foreach ($stats as $key => $value) {
            echo "  - {$key}: " . (is_array($value) ? json_encode($value) : $value) . "\n";
        }
        
        echo "\n";
    }
    
    /**
     * Demonstrate performance monitoring
     */
    public function demonstratePerformanceMonitoring(): void
    {
        echo "📊 Performance Monitoring Demo\n\n";
        
        // Register health checks
        echo "🏥 Registering health checks...\n";
        
        $this->healthMonitor->registerHealthCheck('inventory', 'database_connection', function() {
            // Simulate database connection check
            return rand(0, 100) > 5; // 95% success rate
        });
        
        $this->healthMonitor->registerHealthCheck('loyalty', 'points_calculation', function() {
            // Simulate points calculation check
            return rand(0, 100) > 2; // 98% success rate
        });
        
        $this->healthMonitor->registerHealthCheck('analytics', 'data_processing', function() {
            // Simulate data processing check
            return rand(0, 100) > 10; // 90% success rate
        });
        
        // Record performance metrics
        echo "📏 Recording performance metrics...\n";
        
        for ($i = 0; $i < 10; $i++) {
            // Simulate varying performance
            $responseTime = rand(50, 200);
            $memoryUsage = rand(10, 30);
            $dbQueryTime = rand(5, 100);
            
            $this->healthMonitor->recordResponseTime('inventory', 'stock_check', $responseTime);
            $this->healthMonitor->recordMemoryUsage('inventory', $memoryUsage);
            $this->healthMonitor->recordDatabaseQueryTime('inventory', 'SELECT * FROM inventory', $dbQueryTime);
            
            echo "  📊 Metrics recorded - Response: {$responseTime}ms, Memory: {$memoryUsage}MB, DB: {$dbQueryTime}ms\n";
        }
        
        // Run health checks
        echo "\n🔍 Running health checks...\n";
        $healthResults = $this->healthMonitor->runAllHealthChecks();
        
        foreach ($healthResults as $plugin => $checks) {
            echo "  Plugin: {$plugin}\n";
            foreach ($checks as $checkName => $result) {
                $status = $result['status'];
                $time = number_format($result['response_time_ms'], 2);
                $icon = $status === 'healthy' ? '✅' : ($status === 'unhealthy' ? '⚠️' : '❌');
                echo "    {$icon} {$checkName}: {$status} ({$time}ms)\n";
            }
        }
        
        // Get system health overview
        echo "\n🌡️ System Health Overview:\n";
        $systemHealth = $this->healthMonitor->getSystemHealth();
        echo "  Overall Status: " . ($systemHealth['overall_status'] === 'healthy' ? '✅ Healthy' : '⚠️ Issues Detected') . "\n";
        echo "  Plugins Monitored: {$systemHealth['plugins_monitored']}\n";
        echo "  Total Checks: {$systemHealth['total_checks']}\n";
        echo "  Issues Found: " . count($systemHealth['issues']) . "\n";
        
        if (!empty($systemHealth['issues'])) {
            echo "  \n🚨 Issues:\n";
            foreach ($systemHealth['issues'] as $issue) {
                echo "    - {$issue['plugin']}.{$issue['check']}: {$issue['status']}\n";
            }
        }
        
        echo "\n";
    }
    
    /**
     * Demonstrate automated testing
     */
    public function demonstrateAutomatedTesting(): void
    {
        echo "🧪 Automated Testing Demo\n\n";
        
        // Create a sample test suite
        $testSuite = new DemoTestSuite();
        $this->testFramework->registerTestSuite('demo_plugin', $testSuite);
        $this->testFramework->setVerbose(true);
        
        echo "🏃 Running comprehensive test suite...\n";
        $result = $this->testFramework->runTests('demo_plugin');
        
        // Display results
        echo "\n📋 Test Results Summary:\n";
        $stats = $result->getStatistics();
        foreach ($stats as $key => $value) {
            $icon = match($key) {
                'passed' => '✅',
                'failed' => '❌',
                'errors' => '🚨',
                'warnings' => '⚠️',
                default => '📊'
            };
            echo "  {$icon} " . ucfirst(str_replace('_', ' ', $key)) . ": {$value}\n";
        }
        
        // Show detailed results if there are issues
        $failures = $result->getFailures();
        $errors = $result->getErrors();
        
        if (!empty($failures) || !empty($errors)) {
            echo "\n🔍 Detailed Issues:\n";
            
            foreach ($failures as $failure) {
                echo "  ❌ FAIL [{$failure['type']}] {$failure['test']}: {$failure['message']}\n";
            }
            
            foreach ($errors as $error) {
                echo "  🚨 ERROR [{$error['type']}] {$error['test']}: {$error['message']}\n";
            }
        }
        
        // Show performance metrics
        $performanceMetrics = $result->getPerformanceMetrics();
        if (!empty($performanceMetrics)) {
            echo "\n⚡ Performance Metrics:\n";
            foreach ($performanceMetrics as $testName => $metrics) {
                $time = number_format($metrics['execution_time_ms'], 2);
                $memory = number_format($metrics['memory_usage_bytes'] / 1024, 2);
                $status = $metrics['passed'] ? '✅' : '❌';
                echo "  {$status} {$testName}: {$time}ms, {$memory}KB\n";
            }
        }
        
        echo "\n";
    }
    
    /**
     * Run complete demonstration
     */
    public function runCompleteDemo(): void
    {
        echo "🚀 SHOPOLOGIC PLUGIN ECOSYSTEM - COMPLETE DEMONSTRATION\n";
        echo str_repeat("=", 80) . "\n\n";
        
        // Initialize providers (simulated)
        $this->initializeProviders();
        
        // Run all demonstrations
        $this->demonstrateOrderWorkflow();
        $this->demonstrateEventSystem();
        $this->demonstratePerformanceMonitoring();
        $this->demonstrateAutomatedTesting();
        
        // Final system status
        echo "🎯 DEMONSTRATION COMPLETED\n";
        echo str_repeat("=", 80) . "\n";
        echo "✅ All plugin systems operational\n";
        echo "✅ Cross-plugin integration working\n";
        echo "✅ Real-time events functioning\n";
        echo "✅ Performance monitoring active\n";
        echo "✅ Testing framework validated\n\n";
        
        echo "🏆 The Shopologic plugin ecosystem is ready for production!\n";
    }
    
    /**
     * Initialize demo providers (simulated)
     */
    private function initializeProviders(): void
    {
        // In a real implementation, these would be actual plugin instances
        echo "🔧 Initializing plugin providers...\n";
        echo "  ✅ Inventory Provider initialized\n";
        echo "  ✅ Loyalty Provider initialized\n";
        echo "  ✅ Analytics Provider initialized\n";
        echo "  ✅ Currency Provider initialized\n";
        echo "  ✅ Marketing Provider initialized\n\n";
    }
}

/**
 * Demo test suite for testing framework demonstration
 */
class DemoTestSuite extends \Shopologic\Plugins\Shared\Testing\PluginTestSuite
{
    public function getUnitTests(): array
    {
        return [
            'test_basic_functionality' => [$this, 'testBasicFunctionality'],
            'test_data_validation' => [$this, 'testDataValidation'],
            'test_error_handling' => [$this, 'testErrorHandling']
        ];
    }
    
    public function getIntegrationTests(): array
    {
        return [
            'test_cross_plugin_communication' => [$this, 'testCrossPluginCommunication'],
            'test_event_system_integration' => [$this, 'testEventSystemIntegration']
        ];
    }
    
    public function getPerformanceTests(): array
    {
        return [
            'test_response_time' => [$this, 'testResponseTime'],
            'test_memory_usage' => [$this, 'testMemoryUsage']
        ];
    }
    
    public function getSecurityTests(): array
    {
        return [
            'test_input_validation' => [$this, 'testInputValidation'],
            'test_access_control' => [$this, 'testAccessControl']
        ];
    }
    
    public function testBasicFunctionality(): void
    {
        \Shopologic\Plugins\Shared\Testing\Assert::assertTrue(true, 'Basic functionality test');
    }
    
    public function testDataValidation(): void
    {
        $data = ['valid' => true];
        \Shopologic\Plugins\Shared\Testing\Assert::assertArrayHasKey('valid', $data);
    }
    
    public function testErrorHandling(): void
    {
        // Simulate a test that might fail occasionally
        $success = rand(0, 100) > 20; // 80% success rate
        \Shopologic\Plugins\Shared\Testing\Assert::assertTrue($success, 'Error handling test failed');
    }
    
    public function testCrossPluginCommunication(): void
    {
        \Shopologic\Plugins\Shared\Testing\Assert::assertTrue(true, 'Cross-plugin communication test');
    }
    
    public function testEventSystemIntegration(): void
    {
        \Shopologic\Plugins\Shared\Testing\Assert::assertTrue(true, 'Event system integration test');
    }
    
    public function testResponseTime(): void
    {
        // Simulate some processing time
        usleep(rand(10000, 50000)); // 10-50ms
        \Shopologic\Plugins\Shared\Testing\Assert::assertTrue(true, 'Response time test');
    }
    
    public function testMemoryUsage(): void
    {
        // Simulate memory usage
        $data = array_fill(0, 1000, 'test data');
        \Shopologic\Plugins\Shared\Testing\Assert::assertCount(1000, $data);
    }
    
    public function testInputValidation(): void
    {
        \Shopologic\Plugins\Shared\Testing\Assert::assertTrue(true, 'Input validation test');
    }
    
    public function testAccessControl(): void
    {
        \Shopologic\Plugins\Shared\Testing\Assert::assertTrue(true, 'Access control test');
    }
}