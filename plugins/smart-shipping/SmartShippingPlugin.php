<?php
namespace SmartShipping;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\Hook;

/**
 * Smart Shipping Calculator Plugin
 * 
 * Intelligent shipping with real-time rates, delivery predictions, and carbon tracking
 */
class SmartShippingPlugin extends AbstractPlugin
{
    private $rateCalculator;
    private $deliveryPredictor;
    private $carbonCalculator;
    private $routeOptimizer;

    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->initializeShippingZones();
    }

    private function registerServices(): void
    {
        $this->rateCalculator = new Services\RateCalculator($this->api);
        $this->deliveryPredictor = new Services\DeliveryPredictor($this->api);
        $this->carbonCalculator = new Services\CarbonCalculator($this->api);
        $this->routeOptimizer = new Services\RouteOptimizer($this->api);
    }

    private function registerHooks(): void
    {
        // Checkout integration
        Hook::addFilter('checkout.shipping_methods', [$this, 'calculateShippingRates'], 10, 2);
        Hook::addFilter('checkout.shipping_display', [$this, 'enhanceShippingDisplay'], 10, 2);
        Hook::addAction('checkout.shipping_selected', [$this, 'trackShippingSelection'], 10, 2);
        
        // Order processing
        Hook::addAction('order.placed', [$this, 'optimizeShippingRoute'], 10, 1);
        Hook::addAction('order.shipped', [$this, 'trackShipment'], 10, 2);
        Hook::addFilter('order.tracking_info', [$this, 'enhanceTrackingInfo'], 10, 2);
        
        // Cart updates
        Hook::addAction('cart.updated', [$this, 'updateShippingEstimates'], 10, 1);
        Hook::addFilter('cart.shipping_preview', [$this, 'showShippingPreview'], 10, 2);
        
        // Rate calculation
        Hook::addFilter('shipping.rate_calculated', [$this, 'applyShippingRules'], 10, 3);
        Hook::addFilter('shipping.free_threshold', [$this, 'calculateFreeShippingThreshold'], 10, 2);
        
        // Admin features
        Hook::addAction('admin.shipping.dashboard', [$this, 'shippingDashboard'], 10);
        Hook::addFilter('admin.order.shipping_options', [$this, 'addAdminShippingOptions'], 10, 2);
    }

    public function calculateShippingRates($methods, $address): array
    {
        if (!$this->getConfig('enable_real_time_rates', true)) {
            return $methods;
        }

        $cart = $this->api->service('CartService')->getCurrentCart();
        $packages = $this->createShippingPackages($cart);
        
        foreach ($packages as $package) {
            // Calculate package dimensions and weight
            $packageData = $this->calculatePackageData($package);
            
            // Get available carriers
            $carriers = $this->getEnabledCarriers();
            
            foreach ($carriers as $carrier) {
                $rates = $this->rateCalculator->getRates($carrier, $packageData, $address);
                
                foreach ($rates as $rate) {
                    $method = [
                        'id' => $carrier['id'] . '_' . $rate['service_code'],
                        'name' => $carrier['name'] . ' - ' . $rate['service_name'],
                        'cost' => $rate['total_cost'],
                        'delivery_estimate' => $this->deliveryPredictor->predict($carrier, $rate, $address),
                        'carrier' => $carrier['id'],
                        'service' => $rate['service_code'],
                        'package_id' => $package['id']
                    ];
                    
                    // Add carbon footprint if enabled
                    if ($this->getConfig('enable_carbon_tracking', true)) {
                        $method['carbon_footprint'] = $this->carbonCalculator->calculate($packageData, $address, $carrier);
                    }
                    
                    // Add eco-friendly option flag
                    if ($this->isEcoFriendlyOption($method)) {
                        $method['is_eco_friendly'] = true;
                        $method['eco_badge'] = 'Low Carbon';
                    }
                    
                    $methods[] = $method;
                }
            }
        }
        
        // Apply free shipping rules
        $methods = $this->applyFreeShippingRules($methods, $cart);
        
        // Sort methods by preference
        $methods = $this->sortShippingMethods($methods);
        
        return $methods;
    }

    public function enhanceShippingDisplay($display, $method): string
    {
        $enhancements = [];
        
        // Add delivery prediction
        if ($this->getConfig('show_delivery_prediction', true) && isset($method['delivery_estimate'])) {
            $estimate = $method['delivery_estimate'];
            $enhancements['delivery'] = $this->api->view('shipping/delivery-estimate', [
                'earliest' => $estimate['earliest_date'],
                'latest' => $estimate['latest_date'],
                'confidence' => $estimate['confidence'],
                'is_guaranteed' => $estimate['is_guaranteed'] ?? false
            ]);
        }
        
        // Add carbon footprint
        if (isset($method['carbon_footprint'])) {
            $enhancements['carbon'] = $this->api->view('shipping/carbon-info', [
                'emissions' => $method['carbon_footprint']['total_kg_co2'],
                'comparison' => $this->getCarbonComparison($method['carbon_footprint']['total_kg_co2']),
                'offset_available' => true,
                'offset_cost' => $this->calculateCarbonOffsetCost($method['carbon_footprint']['total_kg_co2'])
            ]);
        }
        
        // Add tracking features
        $enhancements['tracking'] = $this->api->view('shipping/tracking-features', [
            'real_time_tracking' => $method['features']['real_time_tracking'] ?? false,
            'delivery_notifications' => $method['features']['delivery_notifications'] ?? false,
            'signature_required' => $method['features']['signature_required'] ?? false
        ]);
        
        return $display . implode('', $enhancements);
    }

    public function optimizeShippingRoute($order): void
    {
        if (!$this->shouldOptimizeRoute($order)) {
            return;
        }
        
        $shipments = $this->groupOrderItemsForShipment($order);
        
        foreach ($shipments as $shipment) {
            $optimizedRoute = $this->routeOptimizer->optimize([
                'origin' => $shipment['origin'],
                'destination' => $order->shipping_address,
                'items' => $shipment['items'],
                'carrier' => $shipment['carrier'],
                'service' => $shipment['service'],
                'constraints' => [
                    'delivery_date' => $shipment['promised_delivery'],
                    'cost_limit' => $shipment['max_cost']
                ]
            ]);
            
            if ($optimizedRoute['savings'] > 0) {
                $this->applyOptimizedRoute($order, $shipment, $optimizedRoute);
            }
        }
    }

    public function trackShipment($order, $trackingData): void
    {
        $shipment = [
            'order_id' => $order->id,
            'tracking_number' => $trackingData['tracking_number'],
            'carrier' => $trackingData['carrier'],
            'shipped_at' => date('Y-m-d H:i:s'),
            'origin' => $trackingData['origin'],
            'destination' => $order->shipping_address,
            'estimated_delivery' => $trackingData['estimated_delivery']
        ];
        
        $this->api->database()->table('shipment_tracking')->insert($shipment);
        
        // Calculate actual shipping metrics
        $this->updateShippingMetrics($order, $trackingData);
        
        // Set up delivery prediction monitoring
        $this->deliveryPredictor->monitorShipment($trackingData['tracking_number']);
        
        // Track carbon emissions
        if ($this->getConfig('enable_carbon_tracking', true)) {
            $this->trackCarbonEmissions($order, $trackingData);
        }
    }

    public function showShippingPreview($preview, $cart): string
    {
        $estimates = $this->estimateShippingCosts($cart);
        
        $previewWidget = $this->api->view('shipping/cart-preview', [
            'estimated_shipping' => $estimates['lowest_rate'],
            'delivery_estimate' => $estimates['fastest_delivery'],
            'free_shipping_progress' => $this->calculateFreeShippingProgress($cart),
            'available_methods' => count($estimates['all_methods']),
            'eco_option_available' => $estimates['has_eco_option']
        ]);
        
        return $preview . $previewWidget;
    }

    public function shippingDashboard(): void
    {
        $metrics = [
            'shipping_performance' => $this->getShippingPerformanceMetrics(),
            'carrier_comparison' => $this->compareCarrierPerformance(),
            'delivery_accuracy' => $this->getDeliveryAccuracyStats(),
            'carbon_footprint' => $this->getTotalCarbonFootprint(),
            'cost_analysis' => $this->getShippingCostAnalysis(),
            'zone_distribution' => $this->getZoneDistribution(),
            'popular_methods' => $this->getPopularShippingMethods(),
            'optimization_savings' => $this->getOptimizationSavings()
        ];
        
        echo $this->api->view('shipping/admin-dashboard', $metrics);
    }

    private function createShippingPackages($cart): array
    {
        $items = $cart->getItems();
        $packages = [];
        
        // Group items by shipping class
        $groupedItems = $this->groupItemsByShippingClass($items);
        
        foreach ($groupedItems as $shippingClass => $classItems) {
            // Apply packing algorithm
            $packedBoxes = $this->packItems($classItems);
            
            foreach ($packedBoxes as $box) {
                $packages[] = [
                    'id' => uniqid('pkg_'),
                    'items' => $box['items'],
                    'dimensions' => $box['dimensions'],
                    'weight' => $box['weight'],
                    'shipping_class' => $shippingClass,
                    'value' => $this->calculatePackageValue($box['items'])
                ];
            }
        }
        
        return $packages;
    }

    private function packItems($items): array
    {
        $boxes = $this->getAvailableBoxSizes();
        $packedBoxes = [];
        
        // Sort items by size (largest first)
        usort($items, function($a, $b) {
            $volumeA = $a->length * $a->width * $a->height;
            $volumeB = $b->length * $b->width * $b->height;
            return $volumeB <=> $volumeA;
        });
        
        // First-fit decreasing algorithm
        foreach ($items as $item) {
            $packed = false;
            
            foreach ($packedBoxes as &$box) {
                if ($this->canFitInBox($item, $box)) {
                    $box['items'][] = $item;
                    $box['remaining_space'] = $this->updateRemainingSpace($box, $item);
                    $box['weight'] += $item->weight * $item->quantity;
                    $packed = true;
                    break;
                }
            }
            
            if (!$packed) {
                // Find best fitting new box
                $bestBox = $this->findBestBox($item, $boxes);
                $packedBoxes[] = [
                    'box_type' => $bestBox,
                    'items' => [$item],
                    'dimensions' => $bestBox['dimensions'],
                    'remaining_space' => $this->calculateRemainingSpace($bestBox, $item),
                    'weight' => $item->weight * $item->quantity
                ];
            }
        }
        
        return $packedBoxes;
    }

    private function applyFreeShippingRules($methods, $cart): array
    {
        $threshold = $this->getConfig('free_shipping_threshold', 50);
        $cartTotal = $cart->getSubtotal();
        
        if ($cartTotal >= $threshold) {
            foreach ($methods as &$method) {
                if ($this->isEligibleForFreeShipping($method)) {
                    $method['original_cost'] = $method['cost'];
                    $method['cost'] = 0;
                    $method['free_shipping_applied'] = true;
                    $method['name'] .= ' (FREE)';
                }
            }
        }
        
        return $methods;
    }

    private function isEcoFriendlyOption($method): bool
    {
        if (!$this->getConfig('enable_eco_shipping', true)) {
            return false;
        }
        
        // Check carbon emissions
        if (isset($method['carbon_footprint'])) {
            $avgEmissions = $this->getAverageCarbonEmissions($method['carrier']);
            if ($method['carbon_footprint']['total_kg_co2'] < $avgEmissions * 0.8) {
                return true;
            }
        }
        
        // Check for eco-certified carriers
        $ecoCarriers = ['usps', 'carbon_neutral_express'];
        if (in_array($method['carrier'], $ecoCarriers)) {
            return true;
        }
        
        // Check for ground shipping (generally more eco-friendly)
        if (strpos(strtolower($method['name']), 'ground') !== false) {
            return true;
        }
        
        return false;
    }

    private function calculateFreeShippingProgress($cart): array
    {
        $threshold = $this->getConfig('free_shipping_threshold', 50);
        $currentTotal = $cart->getSubtotal();
        
        return [
            'current' => $currentTotal,
            'threshold' => $threshold,
            'remaining' => max(0, $threshold - $currentTotal),
            'percentage' => min(100, ($currentTotal / $threshold) * 100),
            'qualified' => $currentTotal >= $threshold
        ];
    }

    private function getShippingPerformanceMetrics(): array
    {
        $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
        
        return [
            'on_time_delivery_rate' => $this->calculateOnTimeDeliveryRate($thirtyDaysAgo),
            'average_shipping_cost' => $this->getAverageShippingCost($thirtyDaysAgo),
            'average_delivery_time' => $this->getAverageDeliveryTime($thirtyDaysAgo),
            'damage_rate' => $this->getDamageRate($thirtyDaysAgo),
            'customer_satisfaction' => $this->getShippingSatisfactionScore($thirtyDaysAgo)
        ];
    }

    private function trackCarbonEmissions($order, $trackingData): void
    {
        $distance = $this->calculateShippingDistance(
            $trackingData['origin'],
            $order->shipping_address
        );
        
        $emissions = $this->carbonCalculator->calculateActual([
            'carrier' => $trackingData['carrier'],
            'service' => $trackingData['service'],
            'distance' => $distance,
            'weight' => $trackingData['weight'],
            'transport_mode' => $this->getTransportMode($trackingData)
        ]);
        
        $this->api->database()->table('shipping_carbon_footprint')->insert([
            'order_id' => $order->id,
            'carrier' => $trackingData['carrier'],
            'distance_km' => $distance,
            'emissions_kg_co2' => $emissions['total_kg_co2'],
            'transport_mode' => $emissions['transport_mode'],
            'calculation_method' => 'actual',
            'tracked_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function initializeShippingZones(): void
    {
        // Set up shipping zone calculations
        $this->api->scheduler()->addJob('update_shipping_zones', '0 3 * * *', function() {
            $this->updateShippingZoneRates();
        });
        
        // Update carrier rates
        $this->api->scheduler()->addJob('update_carrier_rates', '0 4 * * 1', function() {
            $this->rateCalculator->updateCarrierRates();
        });
        
        // Calculate delivery performance
        $this->api->scheduler()->addJob('calculate_delivery_performance', '0 2 * * *', function() {
            $this->deliveryPredictor->updatePredictionModels();
        });
    }

    private function registerRoutes(): void
    {
        $this->api->router()->post('/shipping/calculate', 'Controllers\ShippingController@calculateRates');
        $this->api->router()->get('/shipping/tracking/{id}', 'Controllers\ShippingController@trackShipment');
        $this->api->router()->get('/shipping/carbon-footprint', 'Controllers\ShippingController@getCarbonFootprint');
        $this->api->router()->post('/shipping/optimize-routes', 'Controllers\ShippingController@optimizeRoutes');
        $this->api->router()->get('/shipping/zones', 'Controllers\ShippingController@getShippingZones');
        $this->api->router()->post('/shipping/carbon-offset', 'Controllers\ShippingController@purchaseCarbonOffset');
    }

    public function install(): void
    {
        $this->runMigrations();
        $this->createDefaultShippingZones();
        $this->setupCarriers();
    }

    private function createDefaultShippingZones(): void
    {
        $zones = [
            ['name' => 'Domestic', 'countries' => ['US'], 'is_default' => true],
            ['name' => 'North America', 'countries' => ['CA', 'MX']],
            ['name' => 'International', 'countries' => ['*'], 'is_fallback' => true]
        ];

        foreach ($zones as $zone) {
            $this->api->database()->table('shipping_zones')->insert($zone);
        }
    }

    private function setupCarriers(): void
    {
        $carriers = [
            ['code' => 'usps', 'name' => 'USPS', 'supports_tracking' => true],
            ['code' => 'ups', 'name' => 'UPS', 'supports_tracking' => true],
            ['code' => 'fedex', 'name' => 'FedEx', 'supports_tracking' => true],
            ['code' => 'dhl', 'name' => 'DHL', 'supports_tracking' => true]
        ];

        foreach ($carriers as $carrier) {
            $this->api->database()->table('shipping_carriers')->insert($carrier);
        }
    }
}