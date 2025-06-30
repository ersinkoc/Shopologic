# 📦 FedEx Shipping Integration Plugin

![Quality Badge](https://img.shields.io/badge/Quality-57%25%20(F)-red)


Enterprise-grade FedEx shipping integration with real-time rates, label generation, tracking, and advanced logistics optimization.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

This plugin is part of the enhanced Shopologic ecosystem featuring cross-plugin integration, real-time events, performance monitoring, and automated testing.

## 🚀 Quick Start

```bash
# Initialize plugin ecosystem
php bootstrap_plugins.php

# Activate FedEx plugin
php cli/plugin.php activate shipping-fedex

# Configure FedEx credentials
php cli/fedex.php configure
```

## ✨ Key Features

### 🚚 Comprehensive Shipping Services
- **Real-Time Rate Calculation** - Live shipping rates for all FedEx services
- **Label Generation** - Automated shipping label creation and printing
- **Package Tracking** - Real-time shipment tracking and notifications
- **Address Validation** - Automatic address verification and correction
- **International Shipping** - Global shipping with customs documentation

### 📊 Advanced Logistics
- **Route Optimization** - AI-powered delivery route optimization
- **Cost Prediction** - Shipping cost forecasting and budgeting
- **Delivery Analytics** - Performance metrics and delivery insights
- **Multi-Package Shipments** - Complex shipment handling
- **Pickup Scheduling** - Automated pickup request management

### 🔄 Automation Features
- **Automatic Label Creation** - Order-triggered label generation
- **Delivery Notifications** - Customer delivery status updates
- **Exception Handling** - Automatic handling of delivery exceptions
- **Return Processing** - Streamlined return label generation
- **Bulk Operations** - Batch processing for high-volume shipping

## 🏗️ Plugin Architecture

### Models
- **`FedExShipment.php`** - Complete shipment lifecycle management

### Services
- **`FedExApiClient.php`** - Core FedEx API communication
- **`FedExRateCalculator.php`** - Real-time shipping rate calculations
- **`FedExLabelGenerator.php`** - Shipping label creation and management
- **`FedExTrackingService.php`** - Package tracking and status updates
- **`FedExAddressValidator.php`** - Address validation and correction
- **`FedExRouteOptimizer.php`** - Delivery route optimization
- **`FedExCostPredictor.php`** - Shipping cost forecasting

### Controllers
- **`FedExApiController.php`** - Shipping management API endpoints

### Repositories
- **`FedExShipmentRepository.php`** - Shipment data access and analytics
- **`FedExTrackingRepository.php`** - Tracking event storage and querying

### Shipping Method
- **`FedExShippingMethod.php`** - Shipping method implementation

## 📦 Shipping Operations

### Rate Calculation

```php
// Real-time rate calculation
$rateCalculator = app(FedExRateCalculator::class);

$rateRequest = [
    'shipper' => [
        'address' => [
            'street' => '1234 Shipping Lane',
            'city' => 'Memphis',
            'state' => 'TN',
            'postal_code' => '38118',
            'country' => 'US'
        ]
    ],
    'recipient' => [
        'address' => [
            'street' => '5678 Customer Ave',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'US'
        ]
    ],
    'packages' => [
        [
            'weight' => 2.5,
            'dimensions' => [
                'length' => 12,
                'width' => 8,
                'height' => 6
            ]
        ]
    ]
];

// Get rates for all available services
$rates = $rateCalculator->getRates($rateRequest);

// Get specific service rate
$expressRate = $rateCalculator->getServiceRate($rateRequest, 'FEDEX_EXPRESS_SAVER');
```

### Label Generation

```php
// Generate shipping labels
$labelGenerator = app(FedExLabelGenerator::class);

$shipmentRequest = [
    'shipper' => $shipperDetails,
    'recipient' => $recipientDetails,
    'packages' => $packageDetails,
    'service_type' => 'FEDEX_GROUND',
    'payment' => [
        'type' => 'SENDER'
    ],
    'label_format' => 'PDF',
    'label_size' => '4x6'
];

// Create shipment and generate label
$shipment = $labelGenerator->createShipment($shipmentRequest);

// Get label data
$labelData = $labelGenerator->getLabel($shipment->tracking_number);

// Save label to file
$labelGenerator->saveLabelToFile($labelData, "/storage/labels/{$shipment->tracking_number}.pdf");
```

### Package Tracking

```php
// Track shipments
$trackingService = app(FedExTrackingService::class);

// Track single package
$trackingInfo = $trackingService->trackPackage('1234567890');

// Track multiple packages
$multipleTracking = $trackingService->trackMultiplePackages([
    '1234567890',
    '0987654321',
    '1122334455'
]);

// Get detailed tracking events
$events = $trackingService->getTrackingEvents('1234567890');

// Set up tracking notifications
$trackingService->setupNotifications('1234567890', [
    'email' => 'customer@example.com',
    'events' => ['DELIVERED', 'EXCEPTION', 'OUT_FOR_DELIVERY']
]);
```

## 🔗 Cross-Plugin Integration

### Shipping Method Interface
Implements `ShippingMethodInterface` for seamless integration:

```php
interface ShippingMethodInterface {
    public function calculateRates(ShippingRequest $request): array;
    public function createShipment(ShipmentRequest $request): Shipment;
    public function trackShipment(string $trackingNumber): TrackingInfo;
    public function generateLabel(string $shipmentId): LabelResponse;
    public function validateAddress(Address $address): AddressValidationResult;
}
```

### Integration Examples

```php
// Use with order processing
$orderService = app(OrderServiceInterface::class);
$shippingMethod = app(ShippingMethodInterface::class);

// Calculate shipping rates for order
$order = $orderService->getOrder(123);
$shippingRequest = new ShippingRequest([
    'origin' => $warehouseAddress,
    'destination' => $order->shipping_address,
    'packages' => $order->getPackages()
]);

$rates = $shippingMethod->calculateRates($shippingRequest);

// Create shipment when order is ready
$shipment = $shippingMethod->createShipment(new ShipmentRequest([
    'order_id' => $order->id,
    'service_type' => $order->shipping_method,
    'packages' => $order->getPackages()
]));

// Update order with tracking information
$orderService->updateShippingInfo($order->id, [
    'tracking_number' => $shipment->tracking_number,
    'carrier' => 'FedEx',
    'service' => $shipment->service_type
]);
```

## 🚚 Advanced Features

### Address Validation

```php
// Validate and correct addresses
$addressValidator = app(FedExAddressValidator::class);

$address = [
    'street' => '123 Main St',
    'city' => 'New York',
    'state' => 'NY',
    'postal_code' => '10001',
    'country' => 'US'
];

// Validate address
$validation = $addressValidator->validateAddress($address);

if ($validation->isValid()) {
    $correctedAddress = $validation->getCorrectedAddress();
    $deliverabilityScore = $validation->getDeliverabilityScore();
} else {
    $errors = $validation->getErrors();
    $suggestions = $validation->getSuggestions();
}

// Batch address validation
$addresses = [$address1, $address2, $address3];
$batchValidation = $addressValidator->validateMultipleAddresses($addresses);
```

### Route Optimization

```php
// Optimize delivery routes
$routeOptimizer = app(FedExRouteOptimizer::class);

$deliveries = [
    ['address' => $address1, 'priority' => 'high'],
    ['address' => $address2, 'priority' => 'normal'],
    ['address' => $address3, 'priority' => 'low']
];

// Optimize route for multiple deliveries
$optimizedRoute = $routeOptimizer->optimizeRoute($deliveries, [
    'start_location' => $warehouseAddress,
    'optimize_for' => 'cost', // or 'time'
    'constraints' => [
        'max_delivery_time' => '2_days',
        'service_types' => ['FEDEX_GROUND', 'FEDEX_EXPRESS_SAVER']
    ]
]);

// Get route recommendations
$recommendations = $routeOptimizer->getRouteRecommendations($optimizedRoute);
```

### Cost Prediction

```php
// Predict shipping costs
$costPredictor = app(FedExCostPredictor::class);

// Predict costs for future shipments
$prediction = $costPredictor->predictShippingCosts([
    'historical_data_months' => 12,
    'projected_volume_increase' => 0.15, // 15% increase
    'service_mix' => [
        'FEDEX_GROUND' => 0.6,
        'FEDEX_EXPRESS_SAVER' => 0.3,
        'FEDEX_OVERNIGHT' => 0.1
    ]
]);

// Get cost optimization suggestions
$optimizations = $costPredictor->getCostOptimizations([
    'current_spending' => 50000,
    'shipment_patterns' => $shipmentData,
    'target_savings' => 0.10 // 10% savings target
]);
```

## ⚡ Real-Time Events

### Tracking Event Processing

```php
// Process tracking updates
$eventDispatcher->listen('fedex.tracking_updated', function($event) {
    $trackingData = $event->getData();
    
    // Update order status based on tracking
    $orderService = app(OrderServiceInterface::class);
    $order = $orderService->findByTrackingNumber($trackingData['tracking_number']);
    
    switch ($trackingData['status']) {
        case 'DELIVERED':
            $orderService->markAsDelivered($order->id, $trackingData['delivery_time']);
            break;
        case 'OUT_FOR_DELIVERY':
            $orderService->updateStatus($order->id, 'out_for_delivery');
            break;
        case 'EXCEPTION':
            $orderService->handleDeliveryException($order->id, $trackingData['exception_details']);
            break;
    }
    
    // Send customer notifications
    $notificationService = app(NotificationService::class);
    $notificationService->sendTrackingUpdate($order->customer_id, $trackingData);
});

// Handle shipment creation
$eventDispatcher->listen('fedex.shipment_created', function($event) {
    $shipmentData = $event->getData();
    
    // Update inventory
    $inventoryProvider = app()->get(InventoryProviderInterface::class);
    foreach ($shipmentData['items'] as $item) {
        $inventoryProvider->updateInventory($item['product_id'], -$item['quantity'], 'shipped');
    }
});
```

## 📊 Analytics & Reporting

### Shipping Analytics

```php
// Comprehensive shipping analytics
$shipmentRepository = app(FedExShipmentRepository::class);

// Get delivery performance metrics
$performance = $shipmentRepository->getDeliveryPerformance([
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31',
    'group_by' => 'service_type'
]);

// Analyze shipping costs
$costAnalysis = $shipmentRepository->getShippingCostAnalysis([
    'period' => 'monthly',
    'breakdown_by' => ['service', 'zone', 'weight_range']
]);

// Get customer shipping preferences
$preferences = $shipmentRepository->getCustomerShippingPreferences();

// Delivery exception analysis
$exceptions = $shipmentRepository->getDeliveryExceptions([
    'period' => 'last_30_days',
    'include_resolution_time' => true
]);
```

## 🧪 Automated Testing

### Test Coverage
- **Unit Tests** - Rate calculation and label generation logic
- **Integration Tests** - FedEx API communication
- **Performance Tests** - Bulk shipping operations
- **Mock Tests** - API response handling

### Example Tests

```php
class FedExShippingTestSuite extends PluginTestSuite
{
    public function getUnitTests(): array
    {
        return [
            'test_rate_calculation' => [$this, 'testRateCalculation'],
            'test_label_generation' => [$this, 'testLabelGeneration'],
            'test_tracking_updates' => [$this, 'testTrackingUpdates']
        ];
    }
    
    public function testRateCalculation(): void
    {
        $calculator = new FedExRateCalculator($mockClient);
        $rates = $calculator->getRates($sampleRequest);
        Assert::assertGreaterThan(0, count($rates));
    }
}
```

## 🛠️ Configuration

### Plugin Settings

```json
{
    "account_number": "123456789",
    "meter_number": "987654321",
    "api_key": "your_api_key",
    "secret_key": "your_secret_key",
    "environment": "production",
    "default_service": "FEDEX_GROUND",
    "label_format": "PDF",
    "label_size": "4x6",
    "address_validation": true,
    "tracking_notifications": true,
    "rate_cache_ttl": 300
}
```

### Database Tables
- `fedex_shipments` - Shipment records and tracking
- `fedex_tracking_events` - Detailed tracking event history

## 📚 API Endpoints

### REST API
- `POST /api/v1/shipping/fedex/rates` - Calculate shipping rates
- `POST /api/v1/shipping/fedex/shipments` - Create shipment
- `GET /api/v1/shipping/fedex/track/{number}` - Track shipment
- `POST /api/v1/shipping/fedex/labels` - Generate shipping label
- `POST /api/v1/shipping/fedex/validate-address` - Validate address

### Usage Examples

```bash
# Calculate shipping rates
curl -X POST /api/v1/shipping/fedex/rates \
  -H "Content-Type: application/json" \
  -d '{"origin": {...}, "destination": {...}, "packages": [...]}'

# Track shipment
curl -X GET /api/v1/shipping/fedex/track/1234567890 \
  -H "Authorization: Bearer {token}"

# Create shipment
curl -X POST /api/v1/shipping/fedex/shipments \
  -H "Content-Type: application/json" \
  -d '{"service": "FEDEX_GROUND", "shipper": {...}, "recipient": {...}}'
```

## 🔧 Installation & Setup

### Requirements
- PHP 8.3+
- FedEx Developer Account
- Valid FedEx Account Number and Meter
- SSL certificate for production

### Installation

```bash
# Activate plugin
php cli/plugin.php activate shipping-fedex

# Run migrations
php cli/migrate.php up

# Configure FedEx credentials
php cli/fedex.php configure --production
```

### FedEx Configuration

```bash
# Test API connection
php cli/fedex.php test-connection

# Validate configuration
php cli/fedex.php validate-setup

# Setup webhook endpoints
php cli/fedex.php setup-webhooks
```

## 📖 Documentation

- **FedEx Integration Guide** - Account setup and API configuration
- **Shipping Workflow** - Order fulfillment and shipping processes
- **Rate Configuration** - Service setup and rate optimization
- **Tracking & Notifications** - Customer communication and updates

## 🚀 Production Ready

This plugin is part of the enhanced Shopologic ecosystem and is production-ready with:
- ✅ Comprehensive shipping functionality
- ✅ Cross-plugin integration via standardized interfaces
- ✅ Real-time tracking and event processing
- ✅ Advanced logistics optimization
- ✅ Automated testing framework
- ✅ Complete documentation and examples

---

**FedEx Shipping Integration** - Enterprise shipping solutions for Shopologic