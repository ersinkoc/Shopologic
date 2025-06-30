<?php
declare(strict_types=1);

namespace Shopologic\Plugins\PaymentPaypal;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\PluginInterface;
use Shopologic\Core\Plugin\Hook;
use PayPalPayment\Gateway\PayPalGateway;
use PayPalPayment\Services\PayPalService;

class PayPalPlugin extends AbstractPlugin implements PluginInterface
{
    protected string $name = 'payment-paypal';
    protected string $version = '1.0.0';
    
    /**
     * Called when plugin is installed
     */
    public function install(): bool
    {
        // Run database migrations
        $this->runMigrations();
        
        // Set default configuration
        $this->setDefaultConfig();
        
        return true;
    }
    
    /**
     * Called when plugin is activated
     */
    public function activate(): bool
    {
        // Register payment gateway
        $this->registerPaymentGateway();
        
        // Schedule webhook verification
        $this->scheduleWebhookVerification();
        
        return true;
    }
    
    /**
     * Called when plugin is deactivated
     */
    public function deactivate(): bool
    {
        // Unregister payment gateway
        $this->unregisterPaymentGateway();
        
        // Clear scheduled tasks
        $this->clearScheduledTasks();
        
        return true;
    }
    
    /**
     * Called when plugin is uninstalled
     */
    public function uninstall(): bool
    {
        // Remove database tables (optional, based on user preference)
        if ($this->confirmDataRemoval()) {
            $this->dropTables();
        }
        
        // Remove configuration
        $this->removeConfig();
        
        return true;
    }
    
    /**
     * Called when plugin is updated
     */
    public function update(string $previousVersion): bool
    {
        // Run update migrations based on version
        $this->runUpdateMigrations($previousVersion);
        
        return true;
    }
    
    /**
     * Called when plugin is loaded
     */
    public function boot(): void
    {
        // Register services
        $this->registerServices();
        
        // Register hooks
        $this->registerHooks();
        
        // Register API routes
        $this->registerRoutes();
        
        // Register admin menu items
        $this->registerAdminMenu();
    }
    
    /**
     * Register plugin services
     */
    protected function registerServices(): void
    {
        // Register PayPal service
        $this->container->singleton(PayPalService::class, function ($container) {
            return new PayPalService(
                $this->getConfig('client_id'),
                $this->getConfig('client_secret'),
                $this->getConfig('environment') === 'production'
            );
        });
        
        // Register PayPal gateway
        $this->container->singleton(PayPalGateway::class, function ($container) {
            return new PayPalGateway(
                $container->get(PayPalService::class),
                $this->getConfig()
            );
        });
    }
    
    /**
     * Register plugin hooks
     */
    protected function registerHooks(): void
    {
        // Add PayPal to available payment methods
        Hook::addFilter('checkout.payment_methods', [$this, 'addPaymentMethod'], 10);
        
        // Process PayPal payments
        Hook::addAction('order.payment_process', [$this, 'processPayment'], 10);
        
        // Add PayPal settings to admin
        Hook::addAction('admin.payment_gateways', [$this, 'registerGateway'], 10);
        
        // Add PayPal button to checkout
        Hook::addAction('checkout.payment_form', [$this, 'renderPaymentForm'], 10);
        
        // Handle refunds
        Hook::addFilter('payment.refund', [$this, 'processRefund'], 10);
        
        // Order status changes
        Hook::addAction('order.status_changed', [$this, 'handleOrderStatusChange'], 10);
    }
    
    /**
     * Register API routes
     */
    protected function registerRoutes(): void
    {
        $this->registerRoute('POST', '/api/v1/paypal/create-order', 
            'PayPalPayment\Controllers\PayPalController@createOrder');
            
        $this->registerRoute('POST', '/api/v1/paypal/capture-order', 
            'PayPalPayment\Controllers\PayPalController@captureOrder');
            
        $this->registerRoute('POST', '/api/v1/paypal/webhook', 
            'PayPalPayment\Controllers\PayPalController@handleWebhook');
            
        $this->registerRoute('GET', '/api/v1/paypal/client-token', 
            'PayPalPayment\Controllers\PayPalController@getClientToken');
    }
    
    /**
     * Add PayPal to payment methods
     */
    public function addPaymentMethod(array $methods): array
    {
        if ($this->isConfigured()) {
            $methods['paypal'] = [
                'id' => 'paypal',
                'name' => 'PayPal',
                'description' => 'Pay with PayPal, credit card, or Pay Later',
                'icon' => $this->getAssetUrl('images/paypal-logo.svg'),
                'supports' => ['refunds', 'partial-refunds', 'recurring'],
                'countries' => $this->getSupportedCountries(),
                'currencies' => $this->getSupportedCurrencies()
            ];
        }
        
        return $methods;
    }
    
    /**
     * Process PayPal payment
     */
    public function processPayment(array $data): array
    {
        if ($data['payment_method'] !== 'paypal') {
            return $data;
        }
        
        try {
            $gateway = $this->container->get(PayPalGateway::class);
            $result = $gateway->processPayment(
                $data['order'],
                $data['payment_data']
            );
            
            return array_merge($data, ['payment_result' => $result]);
            
        } catch (\RuntimeException $e) {
            $this->logError('Payment processing failed', [
                'order_id' => $data['order']->id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Check if plugin is properly configured
     */
    protected function isConfigured(): bool
    {
        return !empty($this->getConfig('client_id')) 
            && !empty($this->getConfig('client_secret'));
    }
    
    /**
     * Get supported countries
     */
    protected function getSupportedCountries(): array
    {
        // PayPal supports most countries
        // This is a subset for demonstration
        return [
            'US', 'CA', 'GB', 'DE', 'FR', 'IT', 'ES', 'AU', 
            'JP', 'CN', 'IN', 'BR', 'MX', 'NL', 'PL', 'CH'
        ];
    }
    
    /**
     * Get supported currencies
     */
    protected function getSupportedCurrencies(): array
    {
        return [
            'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 
            'CHF', 'CNY', 'SEK', 'NZD', 'MXN', 'SGD'
        ];
    }
    
    /**
     * Run database migrations
     */
    protected function runMigrations(): void
    {
        $this->api->runMigration($this->getPath('migrations/create_paypal_tables.php'));
    }
    
    /**
     * Set default configuration values
     */
    protected function setDefaultConfig(): void
    {
        $defaults = [
            'environment' => 'sandbox',
            'payment_action' => 'capture',
            'enable_pay_later' => true,
            'enable_venmo' => false,
            'button_color' => 'gold',
            'debug_mode' => false
        ];
        
        foreach ($defaults as $key => $value) {
            if ($this->getConfig($key) === null) {
                $this->setConfig($key, $value);
            }
        }
    }

    /**
     * Register EventListeners
     */
    protected function registerEventListeners(): void
    {
        // TODO: Implement registerEventListeners
    }

    /**
     * Register Permissions
     */
    protected function registerPermissions(): void
    {
        // TODO: Implement registerPermissions
    }

    /**
     * Register ScheduledJobs
     */
    protected function registerScheduledJobs(): void
    {
        // TODO: Implement registerScheduledJobs
    }
}