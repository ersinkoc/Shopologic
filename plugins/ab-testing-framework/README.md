# 🧪 A/B Testing Framework Plugin

![Quality Badge](https://img.shields.io/badge/Quality-86%25%20(B+)-green)


Comprehensive A/B testing and experimentation platform for optimizing conversion rates and user experience across all e-commerce touchpoints.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

This plugin is part of the enhanced Shopologic ecosystem featuring cross-plugin integration, real-time events, performance monitoring, and automated testing.

## 🚀 Quick Start

```bash
# Initialize plugin ecosystem
php bootstrap_plugins.php

# Activate A/B Testing Framework
php cli/plugin.php activate ab-testing-framework
```

## ✨ Key Features

### 🔬 Advanced Experimentation
- **Multi-variate Testing** - Test multiple variables simultaneously
- **Statistical Significance** - Automated statistical analysis and confidence intervals
- **Segment-Based Testing** - Target specific customer segments
- **Progressive Rollouts** - Gradual traffic allocation with safety controls
- **Cross-Device Tracking** - Consistent experiences across devices

### 📊 Comprehensive Analytics
- **Real-time Results** - Live experiment performance monitoring
- **Conversion Tracking** - Multiple conversion goals and funnels
- **Revenue Impact** - Direct revenue attribution and ROI calculations
- **User Behavior Analysis** - Detailed user interaction tracking
- **Performance Metrics** - Page load time and technical performance impact

### 🎯 Smart Targeting
- **Customer Segmentation** - Target by demographics, behavior, and purchase history
- **Geographic Targeting** - Location-based experiment allocation
- **Device Targeting** - Mobile, desktop, and tablet-specific tests
- **Time-based Rules** - Schedule experiments for optimal timing
- **Custom Attributes** - Flexible targeting based on any customer data

## 🏗️ Plugin Architecture

### Main Plugin Class
- **`ABTestingFrameworkPlugin.php`** - Core A/B testing engine and management

### Services
- **Experiment Manager** - Test creation, execution, and management
- **Statistical Engine** - Significance testing and confidence calculations
- **Targeting Engine** - Audience segmentation and allocation
- **Analytics Processor** - Performance measurement and reporting
- **Variation Renderer** - Dynamic content and experience delivery

### Controllers
- **Experiment API** - RESTful endpoints for experiment management
- **Analytics API** - Performance data and reporting endpoints
- **Admin Interface** - Dashboard for experiment configuration

## 🔬 Experiment Management

### Creating A/B Tests

```php
// Create a new A/B test
$experimentManager = app(ExperimentManager::class);

$experiment = $experimentManager->createExperiment([
    'name' => 'Product Page Layout Test',
    'description' => 'Testing new product page layout for higher conversions',
    'type' => 'ab_test',
    'target_page' => '/products/*',
    'allocation_method' => 'random',
    'traffic_allocation' => 50, // 50% of traffic
    'variations' => [
        [
            'name' => 'Control',
            'allocation' => 50,
            'changes' => []
        ],
        [
            'name' => 'New Layout',
            'allocation' => 50,
            'changes' => [
                'template' => 'product-page-v2',
                'css_class' => 'layout-variant-b'
            ]
        ]
    ],
    'goals' => [
        [
            'name' => 'Add to Cart',
            'type' => 'event',
            'event' => 'cart.item_added',
            'primary' => true
        ],
        [
            'name' => 'Purchase',
            'type' => 'event',
            'event' => 'order.completed',
            'primary' => false
        ]
    ],
    'targeting' => [
        'segments' => ['new_visitors'],
        'devices' => ['desktop', 'mobile'],
        'countries' => ['US', 'CA', 'UK']
    ]
]);

// Start the experiment
$experimentManager->startExperiment($experiment->id);
```

### Multi-variate Testing

```php
// Create multivariate test
$mvtExperiment = $experimentManager->createExperiment([
    'name' => 'Checkout Page Optimization',
    'type' => 'multivariate',
    'variables' => [
        [
            'name' => 'button_color',
            'values' => ['blue', 'green', 'orange']
        ],
        [
            'name' => 'form_layout',
            'values' => ['single_column', 'two_column']
        ],
        [
            'name' => 'trust_signals',
            'values' => ['badges', 'testimonials', 'security_icons']
        ]
    ],
    'traffic_allocation' => 30,
    'min_sample_size' => 1000
]);
```

## 🎯 Advanced Targeting

### Segment-Based Testing

```php
// Target specific customer segments
$targetingEngine = app(TargetingEngine::class);

$experiment = $experimentManager->createExperiment([
    'name' => 'VIP Customer Pricing Test',
    'targeting' => [
        'segments' => [
            $targetingEngine->createSegment([
                'name' => 'High Value Customers',
                'criteria' => [
                    'lifetime_value' => ['operator' => '>', 'value' => 1000],
                    'purchase_frequency' => ['operator' => '>', 'value' => 3],
                    'last_purchase_days' => ['operator' => '<', 'value' => 90]
                ]
            ])
        ],
        'allocation_strategy' => 'weighted',
        'weights' => [
            'High Value Customers' => 80,
            'Other' => 20
        ]
    ]
]);
```

### Progressive Rollouts

```php
// Gradual rollout with safety controls
$rolloutManager = app(RolloutManager::class);

$rollout = $rolloutManager->createProgressiveRollout([
    'experiment_id' => $experiment->id,
    'initial_allocation' => 5, // Start with 5%
    'max_allocation' => 100,
    'increment_percentage' => 10,
    'increment_interval' => '24 hours',
    'safety_rules' => [
        'min_conversion_rate' => 0.02,
        'max_bounce_rate' => 0.60,
        'min_confidence_level' => 0.95
    ],
    'auto_promote' => true,
    'auto_promote_threshold' => 0.99
]);
```

## 📊 Analytics & Reporting

### Real-time Performance Monitoring

```php
// Get experiment performance
$analyticsProcessor = app(AnalyticsProcessor::class);

$performance = $analyticsProcessor->getExperimentPerformance($experiment->id);

// Statistical significance testing
$significance = $analyticsProcessor->calculateSignificance([
    'control_conversions' => $performance['control']['conversions'],
    'control_visitors' => $performance['control']['visitors'],
    'variation_conversions' => $performance['variation']['conversions'],
    'variation_visitors' => $performance['variation']['visitors'],
    'confidence_level' => 0.95
]);

// Revenue impact analysis
$revenueImpact = $analyticsProcessor->calculateRevenueImpact([
    'experiment_id' => $experiment->id,
    'time_period' => '30_days',
    'include_projections' => true
]);
```

### Advanced Analytics

```php
// Detailed performance breakdown
$analytics = $analyticsProcessor->getDetailedAnalytics($experiment->id, [
    'group_by' => ['device', 'traffic_source', 'day_of_week'],
    'metrics' => [
        'conversion_rate',
        'average_order_value',
        'bounce_rate',
        'time_on_page',
        'page_load_time'
    ],
    'include_segments' => true,
    'date_range' => [
        'start' => '2024-01-01',
        'end' => '2024-01-31'
    ]
]);

// Cohort analysis
$cohortAnalysis = $analyticsProcessor->getCohortAnalysis($experiment->id, [
    'cohort_type' => 'weekly',
    'retention_period' => '8_weeks',
    'metric' => 'repeat_purchase_rate'
]);
```

## 🔗 Cross-Plugin Integration

### Integration with Analytics

```php
// Track experiment events in analytics
$analyticsProvider = app()->get(AnalyticsProviderInterface::class);

// Track experiment assignment
$analyticsProvider->trackEvent('experiment_assigned', [
    'experiment_id' => $experiment->id,
    'experiment_name' => $experiment->name,
    'variation' => $assignedVariation,
    'customer_id' => $customerId
]);

// Track goal conversions
$analyticsProvider->trackEvent('experiment_goal_conversion', [
    'experiment_id' => $experiment->id,
    'goal_name' => 'add_to_cart',
    'variation' => $variation,
    'conversion_value' => $orderValue
]);
```

### Integration with Email Marketing

```php
// Test email campaigns
$marketingProvider = app()->get(MarketingProviderInterface::class);

$emailExperiment = $experimentManager->createExperiment([
    'name' => 'Welcome Email Subject Line Test',
    'type' => 'email_campaign',
    'campaign_id' => 'welcome_series_001',
    'variations' => [
        ['subject' => 'Welcome to our store!'],
        ['subject' => 'Your exclusive 10% discount awaits'],
        ['subject' => 'Start shopping with free shipping']
    ],
    'goals' => [
        ['name' => 'Email Open', 'type' => 'email_opened'],
        ['name' => 'Click Through', 'type' => 'email_clicked'],
        ['name' => 'Purchase', 'type' => 'order_completed']
    ]
]);
```

## ⚡ Real-Time Events

### Experiment Event Processing

```php
// Process experiment events
$eventDispatcher = PluginEventDispatcher::getInstance();

$eventDispatcher->listen('experiment.goal_achieved', function($event) {
    $data = $event->getData();
    
    // Update experiment statistics
    $analyticsProcessor = app(AnalyticsProcessor::class);
    $analyticsProcessor->recordGoalConversion($data['experiment_id'], $data['variation'], $data['goal']);
    
    // Check for statistical significance
    $significance = $analyticsProcessor->checkSignificance($data['experiment_id']);
    if ($significance['is_significant']) {
        event(new ExperimentSignificanceReached($data['experiment_id'], $significance));
    }
});

$eventDispatcher->listen('experiment.significance_reached', function($event) {
    $data = $event->getData();
    
    // Auto-promote winning variation if configured
    $experimentManager = app(ExperimentManager::class);
    $experiment = $experimentManager->getExperiment($data['experiment_id']);
    
    if ($experiment->auto_promote && $data['confidence'] > $experiment->auto_promote_threshold) {
        $experimentManager->promoteWinningVariation($experiment->id);
    }
});
```

## 🧪 Testing Framework Integration

### Test Coverage

```php
class ABTestingFrameworkTestSuite extends PluginTestSuite
{
    public function getUnitTests(): array
    {
        return [
            'test_experiment_creation' => [$this, 'testExperimentCreation'],
            'test_traffic_allocation' => [$this, 'testTrafficAllocation'],
            'test_significance_calculation' => [$this, 'testSignificanceCalculation']
        ];
    }
    
    public function testSignificanceCalculation(): void
    {
        $analyticsProcessor = new AnalyticsProcessor();
        $result = $analyticsProcessor->calculateSignificance([
            'control_conversions' => 100,
            'control_visitors' => 1000,
            'variation_conversions' => 120,
            'variation_visitors' => 1000,
            'confidence_level' => 0.95
        ]);
        
        Assert::assertTrue($result['is_significant']);
        Assert::assertGreaterThan(0.95, $result['confidence']);
    }
}
```

## 🛠️ Configuration

### Plugin Settings

```json
{
    "default_confidence_level": 0.95,
    "min_sample_size": 100,
    "max_experiments_per_page": 5,
    "auto_stop_losing_variations": true,
    "statistical_engine": "bayesian",
    "tracking_cookie_duration": "30_days",
    "enable_cross_device_tracking": true,
    "performance_monitoring": true
}
```

## 📚 API Endpoints

### REST API
- `GET /api/v1/experiments` - List experiments
- `POST /api/v1/experiments` - Create experiment
- `PUT /api/v1/experiments/{id}/start` - Start experiment
- `PUT /api/v1/experiments/{id}/stop` - Stop experiment
- `GET /api/v1/experiments/{id}/performance` - Get performance data
- `POST /api/v1/experiments/{id}/goals` - Track goal conversion

### Usage Examples

```bash
# Create experiment
curl -X POST /api/v1/experiments \
  -H "Content-Type: application/json" \
  -d '{"name": "Button Color Test", "variations": [...]}'

# Get performance data
curl -X GET /api/v1/experiments/123/performance \
  -H "Authorization: Bearer {token}"
```

## 🔧 Installation

### Requirements
- PHP 8.3+
- Statistical computing capabilities
- JavaScript tracking library

### Setup

```bash
# Activate plugin
php cli/plugin.php activate ab-testing-framework

# Run setup
php cli/experiments.php setup

# Install tracking script
php cli/experiments.php install-tracking
```

## 📖 Best Practices

- **Statistical Power** - Ensure adequate sample sizes
- **Test Duration** - Run tests for full business cycles
- **Single Variable** - Test one element at a time for A/B tests
- **Pre-planned Analysis** - Define success metrics before starting
- **Segment Analysis** - Analyze results across different user segments

## 🚀 Production Ready

This plugin is part of the enhanced Shopologic ecosystem and is production-ready with:
- ✅ Advanced statistical analysis capabilities
- ✅ Cross-plugin integration for comprehensive testing
- ✅ Real-time performance monitoring
- ✅ Automated significance testing
- ✅ Progressive rollout safety controls
- ✅ Complete testing framework integration

---

**A/B Testing Framework** - Data-driven optimization for Shopologic