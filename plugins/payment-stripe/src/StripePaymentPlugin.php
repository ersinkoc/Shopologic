<?php

declare(strict_types=1);

namespace Shopologic\Plugins\PaymentStripe;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Container\ContainerInterface;
use Shopologic\Core\Events\EventDispatcherInterface;
use Shopologic\Core\Router\RouterInterface;
use Shopologic\Core\Hook\HookSystem;
use Shopologic\Plugins\PaymentStripe\Gateway\StripeGateway;
use Shopologic\Plugins\PaymentStripe\Api\StripeApiController;
use Shopologic\Plugins\PaymentStripe\Services\StripeClient;
use Shopologic\Plugins\PaymentStripe\Services\StripeWebhookHandler;
use Shopologic\Plugins\PaymentStripe\Services\StripeCustomerService;
use Shopologic\Plugins\PaymentStripe\Services\StripePaymentMethodService;
use Shopologic\Plugins\PaymentStripe\Services\StripeFraudDetectionService;
use Shopologic\Plugins\PaymentStripe\Services\StripeAnalyticsService;
use Shopologic\Plugins\PaymentStripe\Services\StripeRetryService;
use Shopologic\Plugins\PaymentStripe\Services\StripeChargeback;
use Shopologic\Plugins\PaymentStripe\Services\StripeConnectService;
use Shopologic\Plugins\PaymentStripe\Services\StripeSubscriptionService;
use Shopologic\Plugins\PaymentStripe\Services\StripeInvoiceService;
use Shopologic\Plugins\PaymentStripe\Repository\StripePaymentRepository;
use Shopologic\Plugins\PaymentStripe\Repository\StripeCustomerRepository;
use Shopologic\Plugins\PaymentStripe\Repository\StripeWebhookRepository;
use Shopologic\Plugins\PaymentStripe\Repository\StripeFraudRepository;
use Shopologic\Plugins\PaymentStripe\Repository\StripeAnalyticsRepository;
use Shopologic\Plugins\PaymentStripe\Repository\StripeChargebackRepository;

class StripePaymentPlugin extends AbstractPlugin
{
    private array $config;

    public function __construct(
        ContainerInterface $container,
        EventDispatcherInterface $eventDispatcher,
        array $config = []
    ) {
        parent::__construct($container, $eventDispatcher);
        $this->config = $config;
    }

    public function install(): void
    {
        $this->runMigrations();
        $this->createDefaultConfig();
    }

    public function uninstall(): void
    {
        $this->rollbackMigrations();
        $this->removeConfig();
    }

    public function activate(): void
    {
        $this->registerServices();
        $this->registerEventListeners();
        $this->registerHooks();
        $this->registerRoutes();
        $this->registerPermissions();
        $this->registerScheduledJobs();
    }

    public function deactivate(): void
    {
        HookSystem::removeActionsForPlugin($this->getName());
        HookSystem::removeFiltersForPlugin($this->getName());
    }

    public function upgrade(string $fromVersion, string $toVersion): void
    {
        $this->runMigrations();
    }

    protected function registerServices(): void
    {
        // Register Stripe client service with enhanced configuration
        $this->container->singleton(StripeClient::class, function ($container) {
            return new StripeClient(
                $this->getPluginConfig('secret_key'),
                $this->getPluginConfig('publishable_key'),
                [
                    'api_version' => '2023-10-16',
                    'timeout' => $this->getPluginConfig('timeout', 30),
                    'max_retries' => $this->getPluginConfig('max_retries', 3),
                    'enable_telemetry' => $this->getPluginConfig('enable_telemetry', true),
                    'proxy' => $this->getPluginConfig('proxy'),
                    'verify_ssl' => $this->getPluginConfig('verify_ssl', true)
                ]
            );
        });

        // Register enhanced repositories
        $this->container->singleton(StripePaymentRepository::class);
        $this->container->singleton(StripeCustomerRepository::class);
        $this->container->singleton(StripeWebhookRepository::class);
        $this->container->singleton(StripeFraudRepository::class);
        $this->container->singleton(StripeAnalyticsRepository::class);
        $this->container->singleton(StripeChargebackRepository::class);

        // Register core services
        $this->container->singleton(StripeCustomerService::class);
        $this->container->singleton(StripePaymentMethodService::class);
        $this->container->singleton(StripeWebhookHandler::class);

        // Register advanced enterprise services
        $this->container->singleton(StripeFraudDetectionService::class, function ($container) {
            return new StripeFraudDetectionService(
                $container->get(StripeClient::class),
                $container->get(StripeFraudRepository::class),
                $this->getPluginConfig('fraud_detection', [])
            );
        });

        $this->container->singleton(StripeAnalyticsService::class, function ($container) {
            return new StripeAnalyticsService(
                $container->get(StripeAnalyticsRepository::class),
                $container->get(StripePaymentRepository::class)
            );
        });

        $this->container->singleton(StripeRetryService::class, function ($container) {
            return new StripeRetryService(
                $container->get(StripeClient::class),
                $this->getPluginConfig('retry_settings', [])
            );
        });

        $this->container->singleton(StripeChargeback::class, function ($container) {
            return new StripeChargeback(
                $container->get(StripeClient::class),
                $container->get(StripeChargebackRepository::class)
            );
        });

        $this->container->singleton(StripeConnectService::class, function ($container) {
            return new StripeConnectService(
                $container->get(StripeClient::class),
                $this->getPluginConfig('connect_settings', [])
            );
        });

        $this->container->singleton(StripeSubscriptionService::class, function ($container) {
            return new StripeSubscriptionService(
                $container->get(StripeClient::class),
                $container->get(StripeCustomerService::class)
            );
        });

        $this->container->singleton(StripeInvoiceService::class, function ($container) {
            return new StripeInvoiceService(
                $container->get(StripeClient::class),
                $this->getPluginConfig('invoice_settings', [])
            );
        });

        // Register enhanced payment gateway
        $this->container->singleton(StripeGateway::class, function ($container) {
            return new StripeGateway(
                $container->get(StripeClient::class),
                $container->get(StripePaymentRepository::class),
                $container->get(StripeCustomerService::class),
                $container->get(StripeFraudDetectionService::class),
                $container->get(StripeRetryService::class),
                $this->config
            );
        });

        // Register API controller
        $this->container->singleton(StripeApiController::class);

        // Tag services for discovery
        $this->container->tag([StripeGateway::class], 'payment.gateway');
        $this->container->tag([
            StripeFraudDetectionService::class,
            StripeAnalyticsService::class,
            StripeRetryService::class,
            StripeConnectService::class
        ], 'stripe.service');
    }

    protected function registerEventListeners(): void
    {
        // Listen for order creation to prepare payment
        $this->eventDispatcher->listen('order.created', function ($event) {
            $order = $event->getOrder();
            if ($order->payment_method === 'stripe') {
                $this->container->get(StripeGateway::class)->preparePayment($order);
            }
        });

        // Listen for payment events
        $this->eventDispatcher->listen('payment.processing', function ($event) {
            $payment = $event->getPayment();
            if ($payment->gateway === 'stripe') {
                $this->logger->info('Processing Stripe payment', ['payment_id' => $payment->id]);
            }
        });
    }

    protected function registerHooks(): void
    {
        // Add Stripe payment option to checkout
        HookSystem::addFilter('checkout.payment_methods', function ($methods) {
            if ($this->isConfigured()) {
                $methods[] = [
                    'id' => 'stripe',
                    'name' => 'Credit/Debit Card',
                    'description' => 'Pay securely with your credit or debit card',
                    'icon' => $this->getAssetUrl('images/stripe-icon.png'),
                    'supported_currencies' => $this->getPluginConfig('supported_currencies', ['USD']),
                    'requires_billing_address' => true
                ];
            }
            return $methods;
        }, 10);

        // Add Stripe JS to checkout page
        HookSystem::addAction('checkout.scripts', function () {
            if ($this->isConfigured()) {
                echo sprintf(
                    '<script src="https://js.stripe.com/v3/"></script>' . PHP_EOL .
                    '<script>window.stripePublishableKey = "%s";</script>' . PHP_EOL .
                    '<script src="%s"></script>' . PHP_EOL,
                    $this->getPluginConfig('publishable_key'),
                    $this->getAssetUrl('js/stripe-elements.js')
                );
            }
        });

        // Add Stripe CSS to checkout page
        HookSystem::addAction('checkout.styles', function () {
            if ($this->isConfigured()) {
                echo sprintf(
                    '<link rel="stylesheet" href="%s">' . PHP_EOL,
                    $this->getAssetUrl('css/stripe.css')
                );
            }
        });

        // Handle payment form rendering
        HookSystem::addAction('checkout.payment_form.stripe', function ($order) {
            include $this->getPluginPath() . '/templates/payment-form.php';
        });
    }

    protected function registerRoutes(): void
    {
        $router = $this->container->get(RouterInterface::class);
        $controller = $this->container->get(StripeApiController::class);

        // Core payment processing endpoints
        $router->post('/api/payments/stripe/process', [$controller, 'processPayment']);
        $router->post('/api/payments/stripe/webhook', [$controller, 'handleWebhook']);
        $router->get('/api/payments/stripe/methods', [$controller, 'getPaymentMethods']);
        $router->post('/api/payments/stripe/setup-intent', [$controller, 'createSetupIntent']);
        $router->post('/api/payments/stripe/payment-intent', [$controller, 'createPaymentIntent']);
        
        // Enhanced payment operations
        $router->post('/api/payments/stripe/refund/{id}', [$controller, 'refundPayment']);
        $router->post('/api/payments/stripe/partial-refund/{id}', [$controller, 'partialRefund']);
        $router->post('/api/payments/stripe/capture/{id}', [$controller, 'capturePayment']);
        $router->post('/api/payments/stripe/cancel/{id}', [$controller, 'cancelPayment']);
        $router->get('/api/payments/stripe/status/{id}', [$controller, 'getPaymentStatus']);
        
        // Advanced payment features
        $router->post('/api/payments/stripe/save-method', [$controller, 'savePaymentMethod']);
        $router->delete('/api/payments/stripe/method/{id}', [$controller, 'deletePaymentMethod']);
        $router->post('/api/payments/stripe/verify-3ds', [$controller, 'verify3DSecure']);
        $router->post('/api/payments/stripe/dispute/{id}/respond', [$controller, 'respondToDispute']);
        
        // Subscription management
        $router->post('/api/payments/stripe/subscription', [$controller, 'createSubscription']);
        $router->put('/api/payments/stripe/subscription/{id}', [$controller, 'updateSubscription']);
        $router->delete('/api/payments/stripe/subscription/{id}', [$controller, 'cancelSubscription']);
        $router->post('/api/payments/stripe/subscription/{id}/pause', [$controller, 'pauseSubscription']);
        $router->post('/api/payments/stripe/subscription/{id}/resume', [$controller, 'resumeSubscription']);
        
        // Connect/marketplace endpoints
        $router->post('/api/payments/stripe/connect/account', [$controller, 'createConnectAccount']);
        $router->get('/api/payments/stripe/connect/account/{id}', [$controller, 'getConnectAccount']);
        $router->post('/api/payments/stripe/connect/transfer', [$controller, 'createTransfer']);
        $router->post('/api/payments/stripe/connect/payout', [$controller, 'createPayout']);
        
        // Analytics and reporting
        $router->get('/api/payments/stripe/analytics/overview', [$controller, 'getAnalyticsOverview']);
        $router->get('/api/payments/stripe/analytics/revenue', [$controller, 'getRevenueAnalytics']);
        $router->get('/api/payments/stripe/analytics/failures', [$controller, 'getFailureAnalytics']);
        $router->get('/api/payments/stripe/analytics/fraud', [$controller, 'getFraudAnalytics']);
        $router->get('/api/payments/stripe/analytics/customers', [$controller, 'getCustomerAnalytics']);
        
        // Fraud detection and security
        $router->post('/api/payments/stripe/fraud/review', [$controller, 'reviewFraudulent']);
        $router->post('/api/payments/stripe/fraud/approve', [$controller, 'approveFraudulent']);
        $router->post('/api/payments/stripe/fraud/block', [$controller, 'blockFraudulent']);
        $router->get('/api/payments/stripe/fraud/radar-rules', [$controller, 'getRadarRules']);
        $router->post('/api/payments/stripe/fraud/radar-rules', [$controller, 'updateRadarRules']);
        
        // Invoice management
        $router->post('/api/payments/stripe/invoice', [$controller, 'createInvoice']);
        $router->get('/api/payments/stripe/invoice/{id}', [$controller, 'getInvoice']);
        $router->post('/api/payments/stripe/invoice/{id}/send', [$controller, 'sendInvoice']);
        $router->post('/api/payments/stripe/invoice/{id}/pay', [$controller, 'payInvoice']);
        $router->delete('/api/payments/stripe/invoice/{id}', [$controller, 'voidInvoice']);
        
        // Admin configuration endpoints
        $router->get('/admin/payments/stripe/settings', [$controller, 'getSettings']);
        $router->post('/admin/payments/stripe/settings', [$controller, 'updateSettings']);
        $router->get('/admin/payments/stripe/test', [$controller, 'testConnection']);
        $router->post('/admin/payments/stripe/test-webhook', [$controller, 'testWebhook']);
        $router->get('/admin/payments/stripe/logs', [$controller, 'getLogs']);
        $router->get('/admin/payments/stripe/health', [$controller, 'getHealthStatus']);
        
        // Batch operations
        $router->post('/admin/payments/stripe/batch/refund', [$controller, 'batchRefund']);
        $router->post('/admin/payments/stripe/batch/capture', [$controller, 'batchCapture']);
        $router->post('/admin/payments/stripe/batch/export', [$controller, 'batchExport']);
    }

    protected function registerPermissions(): void
    {
        $permissions = [
            'payment.stripe.process' => 'Process payments via Stripe',
            'payment.stripe.refund' => 'Issue refunds via Stripe',
            'payment.stripe.configure' => 'Configure Stripe settings',
            'payment.stripe.view_transactions' => 'View Stripe transactions'
        ];

        foreach ($permissions as $key => $description) {
            $this->addPermission($key, $description);
        }
    }

    protected function registerScheduledJobs(): void
    {
        // Sync payment statuses every 5 minutes
        $this->scheduleJob('*/5 * * * *', function () {
            $this->container->get(StripeGateway::class)->syncPaymentStatuses();
        });

        // Clean up old webhook logs daily
        $this->scheduleJob('0 2 * * *', function () {
            $this->container->get(StripeWebhookRepository::class)->cleanupOldLogs(30);
        });
        
        // Process failed payment retries every 15 minutes
        $this->scheduleJob('*/15 * * * *', function () {
            $this->container->get(StripeRetryService::class)->processFailedPayments();
        });
        
        // Update fraud detection models daily
        $this->scheduleJob('0 3 * * *', function () {
            $this->container->get(StripeFraudDetectionService::class)->updateModels();
        });
        
        // Generate analytics reports hourly
        $this->scheduleJob('0 * * * *', function () {
            $this->container->get(StripeAnalyticsService::class)->generateHourlyReports();
        });
        
        // Sync dispute information every 4 hours
        $this->scheduleJob('0 */4 * * *', function () {
            $this->container->get(StripeChargeback::class)->syncDisputes();
        });
        
        // Monitor Radar rules and update weekly
        $this->scheduleJob('0 1 * * 0', function () {
            $this->container->get(StripeFraudDetectionService::class)->optimizeRadarRules();
        });
        
        // Archive old payment data monthly
        $this->scheduleJob('0 1 1 * *', function () {
            $this->container->get(StripePaymentRepository::class)->archiveOldPayments();
        });
        
        // Generate compliance reports monthly
        $this->scheduleJob('0 2 1 * *', function () {
            $this->container->get(StripeAnalyticsService::class)->generateComplianceReport();
        });
        
        // Check and renew webhook endpoints monthly
        $this->scheduleJob('0 3 1 * *', function () {
            $this->container->get(StripeWebhookHandler::class)->validateWebhookEndpoints();
        });
    }

    private function isConfigured(): bool
    {
        return !empty($this->getPluginConfig('secret_key')) && 
               !empty($this->getPluginConfig('publishable_key'));
    }

    private function createDefaultConfig(): void
    {
        $this->updatePluginConfig([
            // Basic settings
            'capture_method' => 'automatic',
            'enable_3d_secure' => true,
            'save_payment_methods' => true,
            'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY'],
            
            // Security and fraud detection
            'fraud_detection' => [
                'enable_radar' => true,
                'risk_threshold' => 32,
                'block_suspicious_cards' => true,
                'enable_3ds_adaptive' => true,
                'velocity_checks' => true,
                'ip_geolocation_check' => true,
                'device_fingerprinting' => true
            ],
            
            // Retry and failure handling
            'retry_settings' => [
                'max_attempts' => 3,
                'retry_intervals' => [300, 1800, 7200], // 5min, 30min, 2hrs
                'smart_retry' => true,
                'decline_on_decline_codes' => ['card_declined', 'insufficient_funds'],
                'retry_on_decline_codes' => ['generic_decline', 'try_again_later']
            ],
            
            // Analytics and reporting
            'analytics' => [
                'enable_detailed_tracking' => true,
                'track_conversion_funnel' => true,
                'enable_cohort_analysis' => true,
                'custom_metrics' => []
            ],
            
            // Performance optimization
            'performance' => [
                'cache_customer_data' => true,
                'cache_payment_methods' => true,
                'batch_webhook_processing' => true,
                'async_processing' => true,
                'connection_pooling' => true
            ],
            
            // Connect/marketplace settings
            'connect_settings' => [
                'enable_express_accounts' => true,
                'enable_custom_accounts' => false,
                'application_fee_percent' => 2.5,
                'instant_payouts' => false
            ],
            
            // Invoice settings
            'invoice_settings' => [
                'auto_advance' => true,
                'collection_method' => 'charge_automatically',
                'days_until_due' => 30,
                'default_tax_rates' => []
            ],
            
            // Compliance and regulatory
            'compliance' => [
                'enable_sca_compliance' => true,
                'pci_compliance_level' => 'level_1',
                'data_retention_days' => 2555, // 7 years
                'gdpr_compliance' => true,
                'audit_logging' => true
            ],
            
            // Notification settings
            'notifications' => [
                'webhook_tolerance' => 300,
                'retry_failed_webhooks' => true,
                'email_notifications' => [
                    'payment_failed' => true,
                    'chargeback_received' => true,
                    'large_payment_threshold' => 10000
                ]
            ]
        ]);
    }

    private function runMigrations(): void
    {
        $migrationPath = $this->getPluginPath() . '/migrations';
        $migrations = glob($migrationPath . '/*.php');
        
        foreach ($migrations as $migration) {
            require_once $migration;
            $className = basename($migration, '.php');
            $migrationClass = new $className();
            $migrationClass->up();
        }
    }

    private function rollbackMigrations(): void
    {
        $migrationPath = $this->getPluginPath() . '/migrations';
        $migrations = array_reverse(glob($migrationPath . '/*.php'));
        
        foreach ($migrations as $migration) {
            require_once $migration;
            $className = basename($migration, '.php');
            $migrationClass = new $className();
            $migrationClass->down();
        }
    }

    private function removeConfig(): void
    {
        // Remove all plugin configuration
        $this->database->table('plugin_config')
            ->where('plugin_name', $this->getName())
            ->delete();
    }

    private function getPluginPath(): string
    {
        return dirname(__DIR__);
    }

    private function getAssetUrl(string $path): string
    {
        return '/plugins/payment-stripe/assets/' . ltrim($path, '/');
    }

    public function getName(): string
    {
        return 'payment-stripe';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }
}