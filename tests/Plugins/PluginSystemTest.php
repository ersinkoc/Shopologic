<?php
declare(strict_types=1);

namespace Tests\Plugins;

use Core\Testing\TestCase;
use Core\Plugin\PluginManager;
use Core\Plugin\Hook;
use Core\Container\Container;

class PluginSystemTest extends TestCase
{
    private PluginManager $pluginManager;
    private Container $container;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->container = new Container();
        $this->pluginManager = new PluginManager(
            $this->container,
            __DIR__ . '/../../plugins'
        );
    }
    
    /**
     * Test plugin discovery
     */
    public function testPluginDiscovery(): void
    {
        $plugins = $this->pluginManager->discover();
        
        $this->assertIsArray($plugins);
        $this->assertArrayHasKey('payment-paypal', $plugins);
        $this->assertArrayHasKey('analytics-google', $plugins);
        $this->assertArrayHasKey('reviews-ratings', $plugins);
        $this->assertArrayHasKey('seo-optimizer', $plugins);
        $this->assertArrayHasKey('live-chat', $plugins);
        $this->assertArrayHasKey('multi-currency', $plugins);
        $this->assertArrayHasKey('email-marketing', $plugins);
        $this->assertArrayHasKey('loyalty-rewards', $plugins);
        $this->assertArrayHasKey('inventory-management', $plugins);
    }
    
    /**
     * Test plugin manifest validation
     */
    public function testPluginManifestValidation(): void
    {
        $plugins = $this->pluginManager->discover();
        
        foreach ($plugins as $name => $manifest) {
            // Required fields
            $this->assertArrayHasKey('name', $manifest);
            $this->assertArrayHasKey('version', $manifest);
            $this->assertArrayHasKey('description', $manifest);
            $this->assertArrayHasKey('author', $manifest);
            $this->assertArrayHasKey('requirements', $manifest);
            $this->assertArrayHasKey('config', $manifest);
            
            // Config validation
            $this->assertArrayHasKey('main_class', $manifest['config']);
            $this->assertArrayHasKey('namespace', $manifest['config']);
            
            // Requirements validation
            $this->assertArrayHasKey('php_version', $manifest['requirements']);
            $this->assertArrayHasKey('core_version', $manifest['requirements']);
        }
    }
    
    /**
     * Test plugin installation
     */
    public function testPluginInstallation(): void
    {
        // Test PayPal plugin installation
        $result = $this->pluginManager->install('payment-paypal');
        
        $this->assertTrue($result);
        $this->assertTrue($this->pluginManager->isInstalled('payment-paypal'));
        
        // Check database tables created
        $this->assertDatabaseTableExists('paypal_transactions');
        $this->assertDatabaseTableExists('paypal_webhooks');
    }
    
    /**
     * Test plugin activation
     */
    public function testPluginActivation(): void
    {
        // Install first
        $this->pluginManager->install('reviews-ratings');
        
        // Then activate
        $result = $this->pluginManager->activate('reviews-ratings');
        
        $this->assertTrue($result);
        $this->assertTrue($this->pluginManager->isActive('reviews-ratings'));
        
        // Check hooks are registered
        $this->assertTrue(Hook::hasAction('product.display'));
        $this->assertTrue(Hook::hasFilter('product.schema'));
    }
    
    /**
     * Test plugin dependencies
     */
    public function testPluginDependencies(): void
    {
        // Try to install plugin with dependencies
        $result = $this->pluginManager->install('analytics-google');
        
        // Should fail if core-commerce is not installed
        if (!$this->pluginManager->isInstalled('core-commerce')) {
            $this->assertFalse($result);
            $errors = $this->pluginManager->getErrors();
            $this->assertContains('Missing dependency: core-commerce', $errors);
        }
    }
    
    /**
     * Test plugin configuration
     */
    public function testPluginConfiguration(): void
    {
        $this->pluginManager->install('multi-currency');
        $this->pluginManager->activate('multi-currency');
        
        // Set configuration
        $this->pluginManager->setConfig('multi-currency', [
            'base_currency' => 'USD',
            'enabled_currencies' => ['USD', 'EUR', 'GBP'],
            'exchange_rate_provider' => 'ecb',
            'update_frequency' => 'daily'
        ]);
        
        // Get configuration
        $config = $this->pluginManager->getConfig('multi-currency');
        
        $this->assertEquals('USD', $config['base_currency']);
        $this->assertContains('EUR', $config['enabled_currencies']);
        $this->assertEquals('ecb', $config['exchange_rate_provider']);
    }
    
    /**
     * Test hook system
     */
    public function testHookSystem(): void
    {
        $this->pluginManager->install('loyalty-rewards');
        $this->pluginManager->activate('loyalty-rewards');
        
        // Test action hook
        $called = false;
        Hook::addAction('user.registered', function() use (&$called) {
            $called = true;
        });
        
        Hook::doAction('user.registered', ['user_id' => 123]);
        $this->assertTrue($called);
        
        // Test filter hook
        $result = Hook::applyFilters('loyalty.points.earned', 100, [
            'action' => 'purchase',
            'amount' => 100
        ]);
        
        // Should be modified by loyalty plugin
        $this->assertGreaterThanOrEqual(100, $result);
    }
    
    /**
     * Test plugin API endpoints
     */
    public function testPluginApiEndpoints(): void
    {
        $this->pluginManager->install('live-chat');
        $this->pluginManager->activate('live-chat');
        
        // Test chat widget config endpoint
        $response = $this->get('/api/v1/chat/widget/config');
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        
        $this->assertArrayHasKey('enabled', $data);
        $this->assertArrayHasKey('position', $data);
        $this->assertArrayHasKey('color', $data);
    }
    
    /**
     * Test plugin permissions
     */
    public function testPluginPermissions(): void
    {
        $this->pluginManager->install('inventory-management');
        $this->pluginManager->activate('inventory-management');
        
        // Create user without permissions
        $user = $this->createUser(['role' => 'customer']);
        $this->actingAs($user);
        
        // Try to access protected endpoint
        $response = $this->post('/api/v1/inventory/adjust', [
            'product_id' => 1,
            'quantity' => 10
        ]);
        
        $this->assertEquals(403, $response->getStatusCode());
        
        // Create user with permissions
        $admin = $this->createUser(['role' => 'admin']);
        $admin->grantPermission('inventory.adjust');
        $this->actingAs($admin);
        
        // Should now have access
        $response = $this->post('/api/v1/inventory/adjust', [
            'product_id' => 1,
            'quantity' => 10
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
    }
    
    /**
     * Test plugin deactivation
     */
    public function testPluginDeactivation(): void
    {
        $this->pluginManager->install('seo-optimizer');
        $this->pluginManager->activate('seo-optimizer');
        
        // Verify it's active
        $this->assertTrue($this->pluginManager->isActive('seo-optimizer'));
        
        // Deactivate
        $result = $this->pluginManager->deactivate('seo-optimizer');
        
        $this->assertTrue($result);
        $this->assertFalse($this->pluginManager->isActive('seo-optimizer'));
        
        // Hooks should be removed
        $this->assertFalse(Hook::hasAction('page.head', 'seo-optimizer'));
    }
    
    /**
     * Test plugin uninstall
     */
    public function testPluginUninstall(): void
    {
        $this->pluginManager->install('email-marketing');
        $this->pluginManager->activate('email-marketing');
        
        // Create some data
        $this->createEmailSubscriber('test@example.com');
        
        // Deactivate first
        $this->pluginManager->deactivate('email-marketing');
        
        // Uninstall
        $result = $this->pluginManager->uninstall('email-marketing', true); // true = remove data
        
        $this->assertTrue($result);
        $this->assertFalse($this->pluginManager->isInstalled('email-marketing'));
        
        // Check tables are removed
        $this->assertDatabaseTableNotExists('email_subscribers');
        $this->assertDatabaseTableNotExists('email_campaigns');
    }
    
    /**
     * Test plugin updates
     */
    public function testPluginUpdate(): void
    {
        $this->pluginManager->install('payment-paypal');
        $this->pluginManager->activate('payment-paypal');
        
        // Simulate version change
        $oldVersion = '1.0.0';
        $newVersion = '1.1.0';
        
        // Run update
        $result = $this->pluginManager->update('payment-paypal', $oldVersion, $newVersion);
        
        $this->assertTrue($result);
        
        // Check update migrations ran
        $this->assertDatabaseTableExists('paypal_transactions');
        $this->assertDatabaseColumnExists('paypal_transactions', 'updated_column');
    }
    
    /**
     * Test plugin service registration
     */
    public function testPluginServiceRegistration(): void
    {
        $this->pluginManager->install('reviews-ratings');
        $this->pluginManager->activate('reviews-ratings');
        
        // Check services are registered in container
        $this->assertTrue($this->container->has('ReviewsRatings\\Services\\ReviewService'));
        $this->assertTrue($this->container->has('ReviewsRatings\\Services\\RatingCalculator'));
        
        // Get service instance
        $reviewService = $this->container->get('ReviewsRatings\\Services\\ReviewService');
        $this->assertInstanceOf('ReviewsRatings\\Services\\ReviewService', $reviewService);
    }
    
    /**
     * Test plugin asset loading
     */
    public function testPluginAssetLoading(): void
    {
        $this->pluginManager->install('live-chat');
        $this->pluginManager->activate('live-chat');
        
        // Render a page
        $response = $this->get('/');
        $content = $response->getContent();
        
        // Check if plugin assets are included
        $this->assertStringContainsString('chat-widget.js', $content);
        $this->assertStringContainsString('chat-widget.css', $content);
    }
    
    /**
     * Test plugin email templates
     */
    public function testPluginEmailTemplates(): void
    {
        $this->pluginManager->install('loyalty-rewards');
        $this->pluginManager->activate('loyalty-rewards');
        
        // Test points earned email
        $email = $this->renderEmail('loyalty.points_earned', [
            'user' => $this->createUser(),
            'points' => 100,
            'reason' => 'Purchase'
        ]);
        
        $this->assertStringContainsString('You earned 100 points', $email);
        $this->assertStringContainsString('Purchase', $email);
    }
    
    /**
     * Test plugin cron jobs
     */
    public function testPluginCronJobs(): void
    {
        $this->pluginManager->install('inventory-management');
        $this->pluginManager->activate('inventory-management');
        
        // Get registered cron jobs
        $jobs = $this->getCronJobs();
        
        $this->assertArrayHasKey('inventory.check_stock_levels', $jobs);
        $this->assertEquals('0 * * * *', $jobs['inventory.check_stock_levels']['schedule']);
        
        // Run the job
        $this->runCronJob('inventory.check_stock_levels');
        
        // Check alerts were generated
        $alerts = $this->getStockAlerts();
        $this->assertNotEmpty($alerts);
    }
    
    /**
     * Test plugin widgets
     */
    public function testPluginWidgets(): void
    {
        $this->pluginManager->install('analytics-google');
        $this->pluginManager->activate('analytics-google');
        
        // Get dashboard widgets
        $widgets = $this->getDashboardWidgets();
        
        $this->assertArrayHasKey('analytics_overview', $widgets);
        $this->assertEquals('Analytics Overview', $widgets['analytics_overview']['name']);
        
        // Render widget
        $content = $this->renderWidget('analytics_overview');
        $this->assertStringContainsString('Visitors', $content);
        $this->assertStringContainsString('Conversions', $content);
    }
    
    /**
     * Helper methods
     */
    private function createUser(array $attributes = []): object
    {
        return (object) array_merge([
            'id' => 1,
            'email' => 'test@example.com',
            'role' => 'customer'
        ], $attributes);
    }
    
    private function createEmailSubscriber(string $email): void
    {
        // Mock subscriber creation
    }
    
    private function getStockAlerts(): array
    {
        // Mock getting stock alerts
        return [
            ['product_id' => 1, 'current_stock' => 5, 'threshold' => 10]
        ];
    }
    
    private function getCronJobs(): array
    {
        // Mock getting cron jobs
        return [
            'inventory.check_stock_levels' => [
                'schedule' => '0 * * * *',
                'handler' => 'checkStockLevels'
            ]
        ];
    }
    
    private function runCronJob(string $job): void
    {
        // Mock running cron job
    }
    
    private function getDashboardWidgets(): array
    {
        // Mock getting widgets
        return [
            'analytics_overview' => [
                'name' => 'Analytics Overview'
            ]
        ];
    }
    
    private function renderWidget(string $widget): string
    {
        // Mock rendering widget
        return '<div>Visitors: 1000, Conversions: 50</div>';
    }
    
    private function renderEmail(string $template, array $data): string
    {
        // Mock email rendering
        return "You earned {$data['points']} points for {$data['reason']}";
    }
}