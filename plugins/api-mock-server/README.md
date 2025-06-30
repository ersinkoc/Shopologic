# 🔧 API Mock Server Plugin

![Quality Badge](https://img.shields.io/badge/Quality-57%25%20(F)-red)


Advanced API mocking and testing framework for simulating external services, testing integrations, and developing applications without dependencies on external APIs.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

This plugin is part of the enhanced Shopologic ecosystem featuring cross-plugin integration, real-time events, performance monitoring, and automated testing.

## 🚀 Quick Start

```bash
# Initialize plugin ecosystem
php bootstrap_plugins.php

# Activate API Mock Server
php cli/plugin.php activate api-mock-server
```

## ✨ Key Features

### 🔄 Advanced API Mocking
- **Dynamic Response Generation** - Context-aware responses based on request parameters
- **Stateful Mocking** - Maintain state across multiple API calls for realistic testing
- **Response Templating** - Flexible response templates with variable substitution
- **Data Generation** - Automatic generation of realistic test data
- **Conditional Logic** - Complex response logic based on request conditions

### 🧪 Testing Framework Integration
- **Automated Test Scenarios** - Pre-defined test scenarios for common use cases
- **Performance Testing** - Load testing and performance validation of mock services
- **Contract Testing** - API contract validation and schema enforcement
- **Regression Testing** - Automated regression testing with mock services
- **Integration Testing** - End-to-end testing with mocked external dependencies

### 📊 Monitoring and Analytics
- **Request Analytics** - Detailed analytics on mock API usage and patterns
- **Performance Metrics** - Response time and throughput monitoring
- **Error Simulation** - Controlled error injection for resilience testing
- **Usage Tracking** - Track API usage across different environments
- **Health Monitoring** - Mock service health and availability monitoring

## 🏗️ Plugin Architecture

### Main Plugin Class
- **`ApiMockServerPlugin.php`** - Core mock server engine and management

### Services
- **Mock Engine** - Core API mocking and response generation
- **Request Router** - Smart request routing and endpoint matching
- **Response Generator** - Dynamic response creation with templating
- **State Manager** - Stateful mock management and persistence
- **Data Generator** - Realistic test data generation

### Models
- **MockEndpoint** - API endpoint definitions and configurations
- **MockResponse** - Response templates and generation rules
- **MockScenario** - Test scenario definitions and workflows
- **RequestLog** - Request tracking and analytics
- **MockState** - Stateful mock data and session management

### Controllers
- **Mock Server API** - RESTful endpoints for mock management
- **Testing Interface** - Test scenario execution and validation
- **Analytics Dashboard** - Mock usage analytics and monitoring

## 🔄 Advanced Mock Configuration

### Dynamic Endpoint Creation

```php
// Create sophisticated mock endpoints
$mockEngine = app(MockEngine::class);

// E-commerce payment gateway mock
$paymentGatewayMock = $mockEngine->createMockEndpoint([
    'name' => 'Payment Gateway API',
    'base_url' => '/api/mock/payment-gateway',
    'endpoints' => [
        [
            'method' => 'POST',
            'path' => '/charges',
            'response_template' => [
                'id' => '{{faker.uuid}}',
                'amount' => '{{request.amount}}',
                'currency' => '{{request.currency}}',
                'status' => '{{conditional.payment_status}}',
                'created' => '{{now.iso8601}}',
                'payment_method' => [
                    'id' => '{{faker.uuid}}',
                    'type' => '{{request.payment_method.type}}',
                    'last4' => '{{request.payment_method.number | last4}}',
                    'brand' => '{{credit_card.brand}}'
                ]
            ],
            'conditional_logic' => [
                'payment_status' => [
                    'conditions' => [
                        ['field' => 'amount', 'operator' => '>', 'value' => 100000, 'result' => 'failed'],
                        ['field' => 'payment_method.number', 'operator' => 'starts_with', 'value' => '4000000000000002', 'result' => 'failed'],
                        ['default' => 'succeeded']
                    ]
                ]
            ],
            'validation' => [
                'required_fields' => ['amount', 'currency', 'payment_method'],
                'amount_range' => ['min' => 50, 'max' => 999999],
                'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD']
            ],
            'response_delay' => [
                'min' => 500, // milliseconds
                'max' => 2000,
                'distribution' => 'normal'
            ]
        ],
        [
            'method' => 'GET',
            'path' => '/charges/{charge_id}',
            'response_template' => [
                'id' => '{{path.charge_id}}',
                'amount' => '{{state.charges[path.charge_id].amount}}',
                'status' => '{{state.charges[path.charge_id].status}}',
                'created' => '{{state.charges[path.charge_id].created}}',
                'refunded' => false,
                'refunds' => [
                    'object' => 'list',
                    'data' => [],
                    'total_count' => 0
                ]
            ],
            'state_management' => [
                'read_from_state' => true,
                'state_key' => 'charges.{{path.charge_id}}'
            ]
        ]
    ]
]);

// Shipping API mock with complex logic
$shippingApiMock = $mockEngine->createMockEndpoint([
    'name' => 'Shipping Rate Calculator',
    'base_url' => '/api/mock/shipping',
    'endpoints' => [
        [
            'method' => 'POST',
            'path' => '/rates',
            'response_template' => [
                'rates' => '{{shipping.calculate_rates}}',
                'delivery_estimates' => '{{shipping.delivery_estimates}}',
                'service_availability' => '{{shipping.service_availability}}'
            ],
            'custom_generators' => [
                'shipping.calculate_rates' => function($request) {
                    $origin = $request['origin'];
                    $destination = $request['destination'];
                    $weight = $request['weight'];
                    
                    $distance = $this->calculateDistance($origin, $destination);
                    $baseRate = $weight * 0.5 + $distance * 0.1;
                    
                    return [
                        [
                            'service' => 'standard',
                            'rate' => round($baseRate, 2),
                            'currency' => 'USD',
                            'delivery_days' => ceil($distance / 500) + 2
                        ],
                        [
                            'service' => 'express',
                            'rate' => round($baseRate * 2.5, 2),
                            'currency' => 'USD',
                            'delivery_days' => ceil($distance / 1000) + 1
                        ]
                    ];
                }
            ]
        ]
    ]
]);
```

### Stateful Mock Scenarios

```php
// Create complex stateful scenarios
$scenarioManager = app(MockScenarioManager::class);

// E-commerce order processing scenario
$orderProcessingScenario = $scenarioManager->createScenario([
    'name' => 'Complete Order Processing Workflow',
    'description' => 'Simulates the entire order lifecycle from creation to delivery',
    'initial_state' => [
        'orders' => [],
        'inventory' => [
            'PROD123' => ['quantity' => 100, 'reserved' => 0],
            'PROD456' => ['quantity' => 50, 'reserved' => 0]
        ],
        'warehouse_locations' => ['NY', 'CA', 'TX']
    ],
    'workflow_steps' => [
        [
            'step' => 'create_order',
            'endpoint' => 'POST /orders',
            'state_changes' => [
                'orders.{{response.order_id}}' => '{{request.order_data}}',
                'inventory.{{request.product_id}}.reserved' => '{{increment_by(request.quantity)}}'
            ],
            'conditions' => [
                'inventory_check' => 'inventory.{{request.product_id}}.quantity >= request.quantity'
            ]
        ],
        [
            'step' => 'process_payment',
            'endpoint' => 'POST /payments',
            'state_changes' => [
                'orders.{{request.order_id}}.payment_status' => 'paid',
                'orders.{{request.order_id}}.payment_id' => '{{response.payment_id}}'
            ],
            'failure_scenarios' => [
                'insufficient_funds' => {
                    'probability' => 0.05,
                    'response' => ['status' => 'failed', 'error' => 'insufficient_funds']
                }
            ]
        ],
        [
            'step' => 'allocate_inventory',
            'endpoint' => 'POST /inventory/allocate',
            'state_changes' => [
                'inventory.{{request.product_id}}.quantity' => '{{decrement_by(request.quantity)}}',
                'inventory.{{request.product_id}}.reserved' => '{{decrement_by(request.quantity)}}',
                'orders.{{request.order_id}}.allocation_status' => 'allocated'
            ]
        ],
        [
            'step' => 'ship_order',
            'endpoint' => 'POST /shipping/create-shipment',
            'state_changes' => [
                'orders.{{request.order_id}}.shipping_status' => 'shipped',
                'orders.{{request.order_id}}.tracking_number' => '{{faker.tracking_number}}'
            ],
            'response_template' => [
                'shipment_id' => '{{faker.uuid}}',
                'tracking_number' => '{{faker.tracking_number}}',
                'estimated_delivery' => '{{date.add_days(3)}}'
            ]
        ]
    ]
]);

// Customer journey simulation
$customerJourneyScenario = $scenarioManager->createScenario([
    'name' => 'Customer Registration and First Purchase',
    'workflow_steps' => [
        [
            'step' => 'register_customer',
            'endpoint' => 'POST /customers',
            'state_changes' => [
                'customers.{{response.customer_id}}' => [
                    'registration_date' => '{{now.iso8601}}',
                    'tier' => 'bronze',
                    'total_spent' => 0,
                    'order_count' => 0
                ]
            ]
        ],
        [
            'step' => 'browse_products',
            'endpoint' => 'GET /products',
            'behavior_simulation' => [
                'view_count' => '{{random.int(3, 10)}}',
                'time_between_views' => '{{random.int(5, 30)}}', // seconds
                'categories_browsed' => '{{random.array(categories, 2, 4)}}'
            ]
        ],
        [
            'step' => 'add_to_cart',
            'endpoint' => 'POST /cart/items',
            'state_changes' => [
                'customers.{{request.customer_id}}.cart_items' => '{{append(request.item)}}'
            ]
        ]
    ]
]);
```

## 🔗 Cross-Plugin Integration

### Integration with Testing Framework

```php
// Advanced testing integration
$testFramework = app()->get(PluginTestFramework::class);

// Register mock server for automated testing
$testFramework->registerMockProvider('api_mock_server', [
    'setup_callback' => function($testConfig) {
        $mockEngine = app(MockEngine::class);
        
        // Setup test-specific mocks
        foreach ($testConfig['mock_endpoints'] as $endpoint) {
            $mockEngine->createMockEndpoint($endpoint);
        }
        
        // Initialize test state
        $mockEngine->setState($testConfig['initial_state'] ?? []);
        
        return $mockEngine->getServerUrl();
    },
    'teardown_callback' => function() {
        $mockEngine = app(MockEngine::class);
        $mockEngine->clearAllMocks();
        $mockEngine->resetState();
    }
]);

// Create integration tests with mocks
$integrationTest = $testFramework->createIntegrationTest([
    'test_name' => 'Payment Processing Integration Test',
    'mock_dependencies' => [
        'payment_gateway' => [
            'type' => 'api_mock_server',
            'endpoints' => $paymentGatewayMock,
            'scenarios' => ['successful_payment', 'failed_payment', 'timeout']
        ],
        'inventory_service' => [
            'type' => 'api_mock_server',
            'endpoints' => $inventoryServiceMock,
            'initial_state' => ['products' => $testProducts]
        ]
    ],
    'test_scenarios' => [
        'successful_order_flow',
        'payment_failure_handling',
        'inventory_shortage_handling'
    ]
]);
```

### Integration with Performance Monitoring

```php
// Performance testing with mocks
$performanceMonitor = app(PluginHealthMonitor::class);

// Mock service performance simulation
$performanceScenario = $mockEngine->createPerformanceScenario([
    'name' => 'Payment Gateway Load Test',
    'base_response_time' => 200, // milliseconds
    'load_patterns' => [
        'normal_load' => [
            'requests_per_second' => 100,
            'response_time_variance' => 50,
            'error_rate' => 0.01
        ],
        'high_load' => [
            'requests_per_second' => 500,
            'response_time_variance' => 200,
            'error_rate' => 0.05
        ],
        'overload' => [
            'requests_per_second' => 1000,
            'response_time_variance' => 1000,
            'error_rate' => 0.15,
            'timeout_rate' => 0.1
        ]
    ],
    'degradation_simulation' => [
        'circuit_breaker_threshold' => 0.1,
        'backpressure_simulation' => true,
        'service_degradation' => true
    ]
]);

// Monitor mock service performance
$performanceMonitor->trackMockServicePerformance([
    'service_name' => 'payment_gateway_mock',
    'metrics' => [
        'response_time',
        'throughput',
        'error_rate',
        'availability'
    ],
    'alerting_thresholds' => [
        'response_time_p95' => 1000,
        'error_rate' => 0.05,
        'availability' => 0.99
    ]
]);
```

## ⚡ Real-Time Mock Management

### Dynamic Mock Updates

```php
// Real-time mock configuration
$eventDispatcher = PluginEventDispatcher::getInstance();

$eventDispatcher->listen('mock.configuration_changed', function($event) {
    $configData = $event->getData();
    
    // Update mock endpoints dynamically
    $mockEngine = app(MockEngine::class);
    $mockEngine->updateEndpointConfiguration([
        'endpoint_id' => $configData['endpoint_id'],
        'new_configuration' => $configData['configuration'],
        'reload_existing_mocks' => true
    ]);
    
    // Notify dependent services
    $notificationService = app(NotificationService::class);
    $notificationService->notifyMockUpdate($configData);
});

$eventDispatcher->listen('mock.scenario_started', function($event) {
    $scenarioData = $event->getData();
    
    // Initialize scenario state
    $stateManager = app(MockStateManager::class);
    $stateManager->initializeScenarioState([
        'scenario_id' => $scenarioData['scenario_id'],
        'initial_state' => $scenarioData['initial_state'],
        'cleanup_on_complete' => true
    ]);
    
    // Track scenario execution
    $analyticsProvider = app()->get(AnalyticsProviderInterface::class);
    $analyticsProvider->trackEvent('mock.scenario_execution', [
        'scenario_id' => $scenarioData['scenario_id'],
        'scenario_name' => $scenarioData['scenario_name'],
        'execution_context' => $scenarioData['context']
    ]);
});
```

## 🧪 Testing Framework Integration

### Mock Server Test Coverage

```php
class ApiMockServerTestSuite extends PluginTestSuite
{
    public function getUnitTests(): array
    {
        return [
            'test_mock_endpoint_creation' => [$this, 'testMockEndpointCreation'],
            'test_stateful_mock_scenarios' => [$this, 'testStatefulMockScenarios'],
            'test_response_generation' => [$this, 'testResponseGeneration'],
            'test_conditional_logic' => [$this, 'testConditionalLogic']
        ];
    }
    
    public function testMockEndpointCreation(): void
    {
        $mockEngine = new MockEngine();
        $endpoint = $mockEngine->createMockEndpoint([
            'name' => 'Test API',
            'base_url' => '/api/test',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/users',
                    'response_template' => ['users' => []]
                ]
            ]
        ]);
        
        Assert::assertNotNull($endpoint->id);
        Assert::assertEquals('Test API', $endpoint->name);
    }
    
    public function testStatefulMockScenarios(): void
    {
        $scenarioManager = new MockScenarioManager();
        $scenario = $scenarioManager->createScenario([
            'name' => 'Test Scenario',
            'initial_state' => ['counter' => 0],
            'workflow_steps' => [
                [
                    'step' => 'increment',
                    'endpoint' => 'POST /increment',
                    'state_changes' => ['counter' => '{{increment}}']
                ]
            ]
        ]);
        
        Assert::assertNotNull($scenario->id);
        Assert::assertEquals(0, $scenario->getCurrentState()['counter']);
    }
}
```

## 🛠️ Configuration

### Mock Server Settings

```json
{
    "mock_server": {
        "port": 8080,
        "host": "0.0.0.0",
        "max_concurrent_requests": 1000,
        "request_timeout": 30000,
        "response_compression": true,
        "cors_enabled": true
    },
    "response_generation": {
        "default_delay_ms": 100,
        "faker_locale": "en_US",
        "template_cache_enabled": true,
        "state_persistence": "memory"
    },
    "logging": {
        "log_all_requests": true,
        "log_request_bodies": false,
        "log_response_bodies": false,
        "retention_days": 7
    },
    "performance": {
        "enable_metrics": true,
        "metrics_retention_hours": 24,
        "performance_simulation": true,
        "load_testing_support": true
    }
}
```

### Database Tables
- `mock_endpoints` - Mock endpoint definitions and configurations
- `mock_responses` - Response templates and generation rules
- `mock_scenarios` - Test scenario definitions and workflows
- `mock_request_logs` - Request tracking and analytics
- `mock_state` - Stateful mock data and session management

## 📚 API Endpoints

### REST API
- `POST /api/v1/mock/endpoints` - Create mock endpoints
- `GET /api/v1/mock/endpoints` - List mock endpoints
- `POST /api/v1/mock/scenarios` - Create test scenarios
- `PUT /api/v1/mock/scenarios/{id}/start` - Start scenario execution
- `GET /api/v1/mock/analytics` - Get mock usage analytics

### Usage Examples

```bash
# Create mock endpoint
curl -X POST /api/v1/mock/endpoints \
  -H "Content-Type: application/json" \
  -d '{"name": "Test API", "base_url": "/api/test", "endpoints": [...]}'

# Start scenario
curl -X PUT /api/v1/mock/scenarios/123/start \
  -H "Authorization: Bearer {token}"

# Get analytics
curl -X GET /api/v1/mock/analytics \
  -H "Authorization: Bearer {token}"
```

## 🔧 Installation

### Requirements
- PHP 8.3+
- Advanced templating support
- State management capabilities
- Performance monitoring tools

### Setup

```bash
# Activate plugin
php cli/plugin.php activate api-mock-server

# Run migrations
php cli/migrate.php up

# Start mock server
php cli/mock-server.php start

# Load sample mocks
php cli/mock-server.php load-samples
```

## 📖 Documentation

- **Mock Configuration Guide** - Setting up realistic API mocks
- **Testing Best Practices** - Using mocks for comprehensive testing
- **Performance Testing** - Load testing with mock services
- **Integration Patterns** - Common mock integration patterns

## 🚀 Production Ready

This plugin is part of the enhanced Shopologic ecosystem and is production-ready with:
- ✅ Advanced API mocking and simulation capabilities
- ✅ Cross-plugin integration for comprehensive testing
- ✅ Real-time mock management and updates
- ✅ Performance testing and monitoring
- ✅ Complete testing framework integration
- ✅ Scalable mock server architecture

---

**API Mock Server** - Advanced API mocking and testing for Shopologic