<?php

declare(strict_types=1);

namespace Shopologic\Plugins\ShippingFedex;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Container\ContainerInterface;
use Shopologic\Core\Events\EventDispatcherInterface;
use Shopologic\Core\Router\RouterInterface;
use Shopologic\Core\Hook\HookSystem;
use Shopologic\Plugins\ShippingFedEx\Services\FedExApiClient;
use Shopologic\Plugins\ShippingFedEx\Services\FedExRateCalculator;
use Shopologic\Plugins\ShippingFedEx\Services\FedExLabelGenerator;
use Shopologic\Plugins\ShippingFedEx\Services\FedExTrackingService;
use Shopologic\Plugins\ShippingFedEx\Services\FedExAddressValidator;
use Shopologic\Plugins\ShippingFedEx\Services\FedExRouteOptimizer;
use Shopologic\Plugins\ShippingFedEx\Services\FedExCostPredictor;
use Shopologic\Plugins\ShippingFedEx\Services\FedExCarrierComparison;
use Shopologic\Plugins\ShippingFedEx\Services\FedExInsuranceCalculator;
use Shopologic\Plugins\ShippingFedEx\Services\FedExTransitTimePredictor;
use Shopologic\Plugins\ShippingFedEx\Services\FedExPickupOptimizer;
use Shopologic\Plugins\ShippingFedEx\Services\FedExAnalyticsService;
use Shopologic\Plugins\ShippingFedEx\Services\FedExCarbonFootprintCalculator;
use Shopologic\Plugins\ShippingFedEx\Repository\FedExShipmentRepository;
use Shopologic\Plugins\ShippingFedEx\Repository\FedExTrackingRepository;
use Shopologic\Plugins\ShippingFedEx\Repository\FedExAnalyticsRepository;
use Shopologic\Plugins\ShippingFedEx\Repository\FedExRouteRepository;
use Shopologic\Plugins\ShippingFedEx\Api\FedExApiController;
use Shopologic\Plugins\ShippingFedEx\Shipping\FedExShippingMethod;

class FedExShippingPlugin extends AbstractPlugin
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
        $this->seedServiceZones();
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
        // Register enhanced FedEx API client
        $this->container->singleton(FedExApiClient::class, function ($container) {
            return new FedExApiClient(
                $this->getPluginConfig('account_number'),
                $this->getPluginConfig('meter_number'),
                $this->getPluginConfig('key'),
                $this->getPluginConfig('password'),
                $this->getPluginConfig('environment', 'sandbox'),
                [
                    'timeout' => $this->getPluginConfig('api_timeout', 30),
                    'retry_attempts' => $this->getPluginConfig('retry_attempts', 3),
                    'rate_limiting' => $this->getPluginConfig('rate_limiting', true),
                    'cache_duration' => $this->getPluginConfig('cache_duration', 3600),
                    'enable_compression' => $this->getPluginConfig('enable_compression', true)
                ]
            );
        });

        // Register enhanced repositories
        $this->container->singleton(FedExShipmentRepository::class);
        $this->container->singleton(FedExTrackingRepository::class);
        $this->container->singleton(FedExAnalyticsRepository::class);
        $this->container->singleton(FedExRouteRepository::class);

        // Register core services
        $this->container->singleton(FedExRateCalculator::class);
        $this->container->singleton(FedExLabelGenerator::class);
        $this->container->singleton(FedExTrackingService::class);
        $this->container->singleton(FedExAddressValidator::class);

        // Register advanced enterprise services
        $this->container->singleton(FedExRouteOptimizer::class, function ($container) {
            return new FedExRouteOptimizer(
                $container->get(FedExApiClient::class),
                $container->get(FedExRouteRepository::class),
                $this->getPluginConfig('optimization_settings', [])
            );
        });

        $this->container->singleton(FedExCostPredictor::class, function ($container) {
            return new FedExCostPredictor(
                $container->get(FedExAnalyticsRepository::class),
                $this->getPluginConfig('prediction_settings', [])
            );
        });

        $this->container->singleton(FedExCarrierComparison::class, function ($container) {
            return new FedExCarrierComparison(
                $container->get(FedExApiClient::class),
                $this->getPluginConfig('comparison_settings', [])
            );
        });

        $this->container->singleton(FedExInsuranceCalculator::class, function ($container) {
            return new FedExInsuranceCalculator(
                $this->getPluginConfig('insurance_settings', [])
            );
        });

        $this->container->singleton(FedExTransitTimePredictor::class, function ($container) {
            return new FedExTransitTimePredictor(
                $container->get(FedExApiClient::class),
                $container->get(FedExAnalyticsRepository::class)
            );
        });

        $this->container->singleton(FedExPickupOptimizer::class, function ($container) {
            return new FedExPickupOptimizer(
                $container->get(FedExApiClient::class),
                $container->get(FedExRouteOptimizer::class),
                $this->getPluginConfig('pickup_settings', [])
            );
        });

        $this->container->singleton(FedExAnalyticsService::class, function ($container) {
            return new FedExAnalyticsService(
                $container->get(FedExAnalyticsRepository::class),
                $container->get(FedExShipmentRepository::class)
            );
        });

        $this->container->singleton(FedExCarbonFootprintCalculator::class, function ($container) {
            return new FedExCarbonFootprintCalculator(
                $container->get(FedExApiClient::class),
                $this->getPluginConfig('sustainability_settings', [])
            );
        });

        // Register enhanced shipping method
        $this->container->singleton(FedExShippingMethod::class, function ($container) {
            return new FedExShippingMethod(
                $container->get(FedExApiClient::class),
                $container->get(FedExRateCalculator::class),
                $container->get(FedExLabelGenerator::class),
                $container->get(FedExTrackingService::class),
                $container->get(FedExAddressValidator::class),
                $container->get(FedExShipmentRepository::class),
                $container->get(FedExRouteOptimizer::class),
                $container->get(FedExCostPredictor::class),
                $container->get(FedExTransitTimePredictor::class),
                $container->get(FedExCarbonFootprintCalculator::class),
                $this->config
            );
        });

        // Register API controller
        $this->container->singleton(FedExApiController::class);

        // Tag services for discovery
        $this->container->tag([FedExShippingMethod::class], 'shipping.method');
        $this->container->tag([
            FedExRouteOptimizer::class,
            FedExCostPredictor::class,
            FedExCarrierComparison::class,
            FedExAnalyticsService::class
        ], 'fedex.service');
    }

    protected function registerEventListeners(): void
    {
        // Listen for order shipped events
        $this->eventDispatcher->listen('order.shipped', function ($event) {
            $order = $event->getOrder();
            if ($order->shipping_method === 'fedex') {
                $this->container->get(FedExTrackingService::class)->startTracking($order);
            }
        });

        // Listen for shipping calculation events
        $this->eventDispatcher->listen('shipping.calculate', function ($event) {
            if ($this->isConfigured()) {
                $request = $event->getRequest();
                $fedexMethod = $this->container->get(FedExShippingMethod::class);
                
                if ($fedexMethod->isAvailable($event->getOrder())) {
                    $rates = $fedexMethod->calculateRates($request);
                    $event->addRates('fedex', $rates);
                }
            }
        });
    }

    protected function registerHooks(): void
    {
        // Add FedEx to available shipping methods
        HookSystem::addFilter('checkout.shipping_methods', function ($methods) {
            if ($this->isConfigured()) {
                $enabledServices = $this->getPluginConfig('enabled_services', []);
                
                foreach ($enabledServices as $service) {
                    $methods[] = [
                        'id' => 'fedex_' . strtolower($service),
                        'carrier' => 'FedEx',
                        'name' => $this->getServiceName($service),
                        'code' => $service,
                        'description' => $this->getServiceDescription($service),
                        'icon' => $this->getAssetUrl('images/fedex-logo.png')
                    ];
                }
            }
            return $methods;
        }, 20);

        // Add tracking info to order details
        HookSystem::addAction('order.details.tracking', function ($order) {
            if (strpos($order->shipping_method, 'fedex_') === 0 && $order->tracking_number) {
                $tracking = $this->container->get(FedExTrackingService::class);
                $trackingInfo = $tracking->getTrackingInfo($order->tracking_number);
                include $this->getPluginPath() . '/templates/tracking-info.php';
            }
        });

        // Add shipping options to admin
        HookSystem::addAction('admin.order.shipping_actions', function ($order) {
            if (strpos($order->shipping_method, 'fedex_') === 0) {
                include $this->getPluginPath() . '/templates/admin-shipping-actions.php';
            }
        });
    }

    protected function registerRoutes(): void
    {
        $router = $this->container->get(RouterInterface::class);
        $controller = $this->container->get(FedExApiController::class);

        // Core shipping operations
        $router->post('/api/shipping/fedex/rates', [$controller, 'calculateRates']);
        $router->post('/api/shipping/fedex/rates/bulk', [$controller, 'calculateBulkRates']);
        $router->post('/api/shipping/fedex/rates/compare', [$controller, 'compareRates']);
        
        // Enhanced label operations
        $router->post('/api/shipping/fedex/label', [$controller, 'generateLabel']);
        $router->post('/api/shipping/fedex/label/bulk', [$controller, 'generateBulkLabels']);
        $router->post('/api/shipping/fedex/label/return', [$controller, 'generateReturnLabel']);
        $router->delete('/api/shipping/fedex/label/{id}', [$controller, 'voidLabel']);
        
        // Advanced tracking
        $router->get('/api/shipping/fedex/track/{number}', [$controller, 'trackShipment']);
        $router->post('/api/shipping/fedex/track/bulk', [$controller, 'trackMultipleShipments']);
        $router->get('/api/shipping/fedex/track/{number}/detailed', [$controller, 'getDetailedTracking']);
        $router->get('/api/shipping/fedex/track/{number}/proof-of-delivery', [$controller, 'getProofOfDelivery']);
        
        // Route optimization
        $router->post('/api/shipping/fedex/route/optimize', [$controller, 'optimizeRoute']);
        $router->post('/api/shipping/fedex/route/batch-optimize', [$controller, 'optimizeBatchRoutes']);
        $router->get('/api/shipping/fedex/route/suggestions', [$controller, 'getRouteSuggestions']);
        
        // Pickup management
        $router->post('/api/shipping/fedex/pickup', [$controller, 'schedulePickup']);
        $router->post('/api/shipping/fedex/pickup/optimize', [$controller, 'optimizePickups']);
        $router->put('/api/shipping/fedex/pickup/{id}', [$controller, 'modifyPickup']);
        $router->delete('/api/shipping/fedex/pickup/{id}', [$controller, 'cancelPickup']);
        $router->get('/api/shipping/fedex/pickup/availability', [$controller, 'getPickupAvailability']);
        
        // Address services
        $router->post('/api/shipping/fedex/validate-address', [$controller, 'validateAddress']);
        $router->post('/api/shipping/fedex/validate-address/bulk', [$controller, 'validateBulkAddresses']);
        $router->post('/api/shipping/fedex/standardize-address', [$controller, 'standardizeAddress']);
        $router->get('/api/shipping/fedex/address/suggestions', [$controller, 'getAddressSuggestions']);
        
        // Transit time prediction
        $router->post('/api/shipping/fedex/transit-time', [$controller, 'predictTransitTime']);
        $router->post('/api/shipping/fedex/delivery-commitment', [$controller, 'getDeliveryCommitment']);
        
        // Cost prediction and analysis
        $router->post('/api/shipping/fedex/cost/predict', [$controller, 'predictCost']);
        $router->post('/api/shipping/fedex/cost/forecast', [$controller, 'forecastCosts']);
        $router->get('/api/shipping/fedex/cost/analysis', [$controller, 'getCostAnalysis']);
        
        // Insurance and claims
        $router->post('/api/shipping/fedex/insurance/calculate', [$controller, 'calculateInsurance']);
        $router->post('/api/shipping/fedex/claim', [$controller, 'fileClaim']);
        $router->get('/api/shipping/fedex/claim/{id}', [$controller, 'getClaimStatus']);
        
        // Carbon footprint and sustainability
        $router->post('/api/shipping/fedex/carbon/calculate', [$controller, 'calculateCarbonFootprint']);
        $router->get('/api/shipping/fedex/carbon/report', [$controller, 'getCarbonReport']);
        $router->get('/api/shipping/fedex/sustainable-options', [$controller, 'getSustainableOptions']);
        
        // Service information
        $router->get('/api/shipping/fedex/services', [$controller, 'getServices']);
        $router->get('/api/shipping/fedex/services/zones', [$controller, 'getServiceZones']);
        $router->get('/api/shipping/fedex/services/capabilities', [$controller, 'getServiceCapabilities']);
        
        // Analytics and reporting
        $router->get('/api/shipping/fedex/analytics/overview', [$controller, 'getAnalyticsOverview']);
        $router->get('/api/shipping/fedex/analytics/performance', [$controller, 'getPerformanceMetrics']);
        $router->get('/api/shipping/fedex/analytics/costs', [$controller, 'getCostAnalytics']);
        $router->get('/api/shipping/fedex/analytics/delivery-performance', [$controller, 'getDeliveryPerformance']);
        $router->post('/api/shipping/fedex/reports/generate', [$controller, 'generateReport']);
        
        // Batch operations
        $router->post('/api/shipping/fedex/batch/ship', [$controller, 'batchShip']);
        $router->post('/api/shipping/fedex/batch/void', [$controller, 'batchVoid']);
        $router->get('/api/shipping/fedex/batch/status/{id}', [$controller, 'getBatchStatus']);
        
        // Webhook endpoints
        $router->post('/api/shipping/fedex/webhook/tracking', [$controller, 'handleTrackingWebhook']);
        $router->post('/api/shipping/fedex/webhook/pickup', [$controller, 'handlePickupWebhook']);
        
        // Admin configuration routes
        $router->get('/admin/shipping/fedex/settings', [$controller, 'getSettings']);
        $router->post('/admin/shipping/fedex/settings', [$controller, 'updateSettings']);
        $router->post('/admin/shipping/fedex/test', [$controller, 'testConnection']);
        $router->get('/admin/shipping/fedex/logs', [$controller, 'getLogs']);
        $router->get('/admin/shipping/fedex/health', [$controller, 'getHealthStatus']);
        
        // Rate management
        $router->get('/admin/shipping/fedex/rates/matrix', [$controller, 'getRateMatrix']);
        $router->post('/admin/shipping/fedex/rates/update', [$controller, 'updateRates']);
        $router->get('/admin/shipping/fedex/rates/history', [$controller, 'getRateHistory']);
    }

    protected function registerPermissions(): void
    {
        $permissions = [
            'shipping.fedex.configure' => 'Configure FedEx shipping settings',
            'shipping.fedex.create_label' => 'Create FedEx shipping labels',
            'shipping.fedex.track' => 'Track FedEx shipments',
            'shipping.fedex.schedule_pickup' => 'Schedule FedEx pickups',
            'shipping.fedex.void_shipment' => 'Void FedEx shipments'
        ];

        foreach ($permissions as $key => $description) {
            $this->addPermission($key, $description);
        }
    }

    protected function registerScheduledJobs(): void
    {
        // Update tracking statuses every 15 minutes
        $this->scheduleJob('*/15 * * * *', function () {
            $this->container->get(FedExTrackingService::class)->updateActiveShipments();
        });

        // Clean up old rate cache daily
        $this->scheduleJob('0 3 * * *', function () {
            $this->container->get(FedExShipmentRepository::class)->cleanupRateCache(7);
        });

        // Update service zones weekly
        $this->scheduleJob('0 2 * * 0', function () {
            $this->updateServiceZones();
        });
        
        // Optimize routes for next day shipments every evening
        $this->scheduleJob('0 18 * * *', function () {
            $this->container->get(FedExRouteOptimizer::class)->optimizeNextDayRoutes();
        });
        
        // Update cost prediction models daily
        $this->scheduleJob('0 4 * * *', function () {
            $this->container->get(FedExCostPredictor::class)->updateModels();
        });
        
        // Generate analytics reports hourly
        $this->scheduleJob('0 * * * *', function () {
            $this->container->get(FedExAnalyticsService::class)->generateHourlyReports();
        });
        
        // Sync delivery performance data every 6 hours
        $this->scheduleJob('0 */6 * * *', function () {
            $this->container->get(FedExAnalyticsService::class)->syncDeliveryPerformance();
        });
        
        // Optimize pickup schedules daily
        $this->scheduleJob('0 6 * * *', function () {
            $this->container->get(FedExPickupOptimizer::class)->optimizeDailyPickups();
        });
        
        // Update transit time predictions weekly
        $this->scheduleJob('0 5 * * 1', function () {
            $this->container->get(FedExTransitTimePredictor::class)->updatePredictions();
        });
        
        // Calculate carbon footprint reports monthly
        $this->scheduleJob('0 2 1 * *', function () {
            $this->container->get(FedExCarbonFootprintCalculator::class)->generateMonthlyReport();
        });
        
        // Archive old shipment data monthly
        $this->scheduleJob('0 1 1 * *', function () {
            $this->container->get(FedExShipmentRepository::class)->archiveOldShipments();
        });
        
        // Validate and refresh API credentials monthly
        $this->scheduleJob('0 3 1 * *', function () {
            $this->container->get(FedExApiClient::class)->validateCredentials();
        });
    }

    private function isConfigured(): bool
    {
        return !empty($this->getPluginConfig('account_number')) && 
               !empty($this->getPluginConfig('meter_number')) &&
               !empty($this->getPluginConfig('key')) &&
               !empty($this->getPluginConfig('password'));
    }

    private function createDefaultConfig(): void
    {
        $this->updatePluginConfig([
            // Basic settings
            'environment' => 'sandbox',
            'default_packaging' => 'YOUR_PACKAGING',
            'dropoff_type' => 'REGULAR_PICKUP',
            'enabled_services' => [
                'FEDEX_GROUND',
                'FEDEX_EXPRESS_SAVER',
                'FEDEX_2_DAY',
                'STANDARD_OVERNIGHT',
                'PRIORITY_OVERNIGHT',
                'FIRST_OVERNIGHT',
                'INTERNATIONAL_ECONOMY',
                'INTERNATIONAL_PRIORITY'
            ],
            'insurance_enabled' => true,
            'signature_option' => 'NO_SIGNATURE',
            'label_format' => 'PDF',
            'label_stock_type' => 'PAPER_4X6',
            
            // Performance settings
            'api_timeout' => 30,
            'retry_attempts' => 3,
            'rate_limiting' => true,
            'cache_duration' => 3600,
            'enable_compression' => true,
            
            // Route optimization settings
            'optimization_settings' => [
                'enable_route_optimization' => true,
                'max_stops_per_route' => 25,
                'optimization_algorithm' => 'genetic_algorithm',
                'consider_traffic' => true,
                'consider_delivery_windows' => true,
                'fuel_cost_factor' => 0.15,
                'driver_cost_per_hour' => 25.00
            ],
            
            // Cost prediction settings
            'prediction_settings' => [
                'enable_cost_prediction' => true,
                'prediction_accuracy_threshold' => 0.85,
                'historical_data_months' => 12,
                'seasonal_adjustment' => true,
                'fuel_price_integration' => true
            ],
            
            // Carrier comparison settings
            'comparison_settings' => [
                'enable_multi_carrier_comparison' => true,
                'include_ups' => false,
                'include_usps' => false,
                'include_dhl' => false,
                'comparison_factors' => ['cost', 'speed', 'reliability'],
                'weight_cost' => 0.4,
                'weight_speed' => 0.3,
                'weight_reliability' => 0.3
            ],
            
            // Insurance settings
            'insurance_settings' => [
                'auto_insure_threshold' => 1000,
                'insurance_rate_markup' => 0.1,
                'default_coverage_type' => 'DECLARED_VALUE',
                'max_coverage_amount' => 50000
            ],
            
            // Pickup optimization settings
            'pickup_settings' => [
                'enable_pickup_optimization' => true,
                'consolidate_pickups' => true,
                'max_pickup_window_hours' => 8,
                'preferred_pickup_times' => ['09:00', '13:00', '17:00'],
                'advance_notice_hours' => 4
            ],
            
            // Sustainability settings
            'sustainability_settings' => [
                'enable_carbon_tracking' => true,
                'prefer_ground_shipping' => true,
                'carbon_offset_program' => false,
                'eco_friendly_packaging' => true,
                'green_delivery_options' => true
            ],
            
            // Analytics settings
            'analytics_settings' => [
                'enable_detailed_analytics' => true,
                'track_delivery_performance' => true,
                'monitor_cost_trends' => true,
                'alert_on_delays' => true,
                'performance_benchmarking' => true
            ],
            
            // Notification settings
            'notification_settings' => [
                'webhook_enabled' => true,
                'webhook_url' => '',
                'webhook_events' => ['shipped', 'in_transit', 'delivered', 'exception'],
                'email_notifications' => [
                    'delivery_delays' => true,
                    'delivery_exceptions' => true,
                    'cost_threshold_exceeded' => true
                ],
                'cost_alert_threshold' => 500
            ],
            
            // Advanced features
            'advanced_features' => [
                'enable_batch_processing' => true,
                'enable_white_glove_service' => false,
                'enable_appointment_delivery' => true,
                'enable_saturday_delivery' => true,
                'enable_hold_at_location' => true,
                'enable_dangerous_goods' => false
            ]
        ]);
    }

    private function seedServiceZones(): void
    {
        // Seed default FedEx service zones and transit times
        $zones = [
            ['zone' => 2, 'min_days' => 1, 'max_days' => 2],
            ['zone' => 3, 'min_days' => 2, 'max_days' => 3],
            ['zone' => 4, 'min_days' => 3, 'max_days' => 4],
            ['zone' => 5, 'min_days' => 4, 'max_days' => 5],
            ['zone' => 6, 'min_days' => 5, 'max_days' => 6],
            ['zone' => 7, 'min_days' => 6, 'max_days' => 7],
            ['zone' => 8, 'min_days' => 7, 'max_days' => 8]
        ];

        foreach ($zones as $zone) {
            $this->database->table('fedex_service_zones')->insert($zone);
        }
    }

    private function updateServiceZones(): void
    {
        // This would typically connect to FedEx API to update zone information
        $this->logger->info('Updating FedEx service zones');
    }

    private function getServiceName(string $service): string
    {
        $names = [
            'FEDEX_GROUND' => 'FedEx Ground',
            'FEDEX_EXPRESS_SAVER' => 'FedEx Express Saver',
            'FEDEX_2_DAY' => 'FedEx 2Day',
            'FEDEX_2_DAY_AM' => 'FedEx 2Day A.M.',
            'STANDARD_OVERNIGHT' => 'FedEx Standard Overnight',
            'PRIORITY_OVERNIGHT' => 'FedEx Priority Overnight',
            'FIRST_OVERNIGHT' => 'FedEx First Overnight',
            'INTERNATIONAL_ECONOMY' => 'FedEx International Economy',
            'INTERNATIONAL_PRIORITY' => 'FedEx International Priority'
        ];

        return $names[$service] ?? $service;
    }

    private function getServiceDescription(string $service): string
    {
        $descriptions = [
            'FEDEX_GROUND' => 'Economical ground delivery',
            'FEDEX_EXPRESS_SAVER' => '3 business days',
            'FEDEX_2_DAY' => '2 business days by 4:30 PM',
            'FEDEX_2_DAY_AM' => '2 business days by 10:30 AM',
            'STANDARD_OVERNIGHT' => 'Next business day by 3 PM',
            'PRIORITY_OVERNIGHT' => 'Next business day by 10:30 AM',
            'FIRST_OVERNIGHT' => 'Next business day, first delivery',
            'INTERNATIONAL_ECONOMY' => 'International economy service',
            'INTERNATIONAL_PRIORITY' => 'International priority service'
        ];

        return $descriptions[$service] ?? '';
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
        return '/plugins/shipping-fedex/assets/' . ltrim($path, '/');
    }

    public function getName(): string
    {
        return 'shipping-fedex';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }
}