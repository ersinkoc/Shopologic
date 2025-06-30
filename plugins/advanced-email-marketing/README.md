# 📧 Advanced Email Marketing Automation Plugin

![Quality Badge](https://img.shields.io/badge/Quality-57%25%20(F)-red)


Sophisticated email marketing platform with advanced automation, segmentation, personalization, and comprehensive analytics for enterprise e-commerce operations.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

This plugin is part of the enhanced Shopologic ecosystem featuring 47 advanced models, cross-plugin integration, real-time events, performance monitoring, and automated testing.

## 🚀 Quick Start

```bash
# Initialize plugin ecosystem
php bootstrap_plugins.php

# Run with integration demonstration
php bootstrap_plugins.php --demo
```

## ✨ Key Features

### 🤖 Advanced Automation
- **Multi-Step Workflows** - Complex automation sequences with conditional logic
- **Trigger-Based Campaigns** - Behavior, event, and time-based triggers
- **A/B Testing** - Automated testing with statistical significance
- **Dynamic Content** - Personalized content based on customer data
- **Smart Timing** - AI-powered send time optimization

### 🎯 Intelligent Segmentation
- **Dynamic Segments** - Real-time segmentation based on customer behavior
- **Predictive Segmentation** - ML-powered customer lifetime value prediction
- **Cross-Channel Integration** - Unified customer profiles across touchpoints
- **Behavioral Triggers** - Advanced engagement and abandonment detection
- **Custom Attributes** - Flexible customer property management

### 📊 Comprehensive Analytics
- **Campaign Performance** - Detailed metrics with ROI tracking
- **Deliverability Monitoring** - Real-time deliverability and reputation tracking
- **Engagement Analytics** - Heat maps, click tracking, and engagement scoring
- **Revenue Attribution** - Direct revenue tracking from email campaigns
- **Predictive Analytics** - Engagement and churn prediction models

## 🏗️ Plugin Architecture

### Models
- **`Campaign.php`** - Comprehensive campaign management with analytics
- **`Template.php`** - Advanced template system with dynamic content
- **`Subscriber.php`** - Detailed subscriber profiles with engagement tracking
- **`Segment.php`** - Dynamic segmentation with real-time updates
- **`Automation.php`** - Multi-step automation workflow management
- **`AutomationStep.php`** - Individual automation step configuration
- **`EmailSend.php`** - Detailed send tracking with deliverability metrics
- **`SubscriberAutomation.php`** - Subscriber journey tracking through automations

### Services
- **`EmailMarketingManager.php`** - Central orchestration for all email operations
- **`CampaignManager.php`** - Campaign creation, execution, and optimization
- **`AutomationEngine.php`** - Workflow processing and execution
- **`SegmentationService.php`** - Dynamic segmentation and targeting
- **`EmailSender.php`** - Delivery optimization and deliverability management
- **`SubscriberManager.php`** - Subscriber lifecycle and engagement management

### Controllers
- **`CampaignController.php`** - Campaign management API endpoints
- **`AutomationController.php`** - Automation workflow management
- **`SubscriberController.php`** - Subscriber and segmentation management
- **`TemplateController.php`** - Template design and management
- **`AnalyticsController.php`** - Performance and engagement analytics
- **`DeliverabilityController.php`** - Deliverability monitoring and optimization
- **`SegmentController.php`** - Dynamic segmentation management
- **`WebhookController.php`** - External integration and webhook handling

### Repositories
- **`CampaignRepository.php`** - Campaign data access and analytics
- **`SubscriberRepository.php`** - Subscriber data and segmentation queries
- **`AutomationRepository.php`** - Automation workflow data management
- **`TemplateRepository.php`** - Template storage and versioning
- **`EmailSendRepository.php`** - Send tracking and deliverability data
- **`SegmentRepository.php`** - Segment definition and member management
- **`AnalyticsRepository.php`** - Performance metrics and reporting data

## 🔗 Cross-Plugin Integration

### Provider Interface
Implements `MarketingProviderInterface` for seamless integration:

```php
interface MarketingProviderInterface {
    public function sendTransactionalEmail(string $template, array $recipient, array $data = []): bool;
    public function triggerAutomation(string $automationKey, int $customerId, array $data = []): bool;
    public function addSubscriber(array $subscriberData): bool;
    public function createSegment(array $segmentConfig): Segment;
    public function trackEngagement(string $emailId, string $eventType, array $metadata = []): void;
}
```

### Integration Examples

```php
// Get marketing provider
$marketingProvider = $integrationManager->getMarketingProvider();

// Send transactional emails
$marketingProvider->sendTransactionalEmail('order_confirmation', 
    ['email' => 'customer@example.com'], 
    ['order_id' => 'ORD-123', 'total' => 299.99]
);

// Trigger automation workflows
$marketingProvider->triggerAutomation('welcome_series', 12345, [
    'signup_source' => 'newsletter',
    'interests' => ['electronics', 'gadgets']
]);

// Add subscribers with segmentation
$marketingProvider->addSubscriber([
    'email' => 'new@customer.com',
    'first_name' => 'John',
    'preferences' => ['weekly_deals', 'new_products']
]);
```

## 📧 Advanced Features

### Sophisticated Campaign Management

```php
// Create advanced campaigns with A/B testing
$campaign = Campaign::create([
    'name' => 'Black Friday Sale 2024',
    'type' => 'promotional',
    'send_method' => 'scheduled',
    'scheduled_at' => '2024-11-29 09:00:00',
    'ab_testing_enabled' => true,
    'ab_testing_config' => [
        'test_type' => 'subject_line',
        'test_percentage' => 20,
        'winner_criteria' => 'open_rate',
        'test_duration_hours' => 4
    ]
]);

// Execute campaigns with optimization
$campaign->send([
    'segment_ids' => [1, 2, 3],
    'personalization' => true,
    'send_time_optimization' => true,
    'deliverability_checks' => true
]);

// Track performance and optimization
$performance = $campaign->getPerformanceMetrics();
$roi = $campaign->calculateROI();
$recommendations = $campaign->getOptimizationRecommendations();
```

### Dynamic Automation Workflows

```php
// Create complex automation workflows
$automation = Automation::create([
    'name' => 'Abandoned Cart Recovery',
    'trigger_type' => 'event',
    'trigger_config' => [
        'event' => 'cart_abandoned',
        'delay_minutes' => 60
    ],
    'is_active' => true
]);

// Add workflow steps with conditional logic
$automation->addStep([
    'step_type' => 'email',
    'template_id' => 'abandoned_cart_1',
    'delay_hours' => 0,
    'conditions' => [
        'cart_value' => ['operator' => '>', 'value' => 50]
    ]
]);

$automation->addStep([
    'step_type' => 'email',
    'template_id' => 'abandoned_cart_2',
    'delay_hours' => 24,
    'conditions' => [
        'email_opened' => false,
        'cart_still_active' => true
    ]
]);

// Track subscriber journey through automation
$journey = SubscriberAutomation::getJourney($subscriberId, $automationId);
$conversion = $journey->getConversionMetrics();
```

### Intelligent Segmentation

```php
// Create dynamic segments with real-time updates
$segment = Segment::create([
    'name' => 'High-Value Customers',
    'type' => 'dynamic',
    'criteria' => [
        'total_spent' => ['operator' => '>', 'value' => 1000],
        'last_purchase_days' => ['operator' => '<', 'value' => 90],
        'email_engagement' => ['operator' => '>', 'value' => 0.3]
    ],
    'auto_refresh' => true,
    'refresh_frequency' => 'daily'
]);

// Advanced segmentation with cross-plugin data
$segment->addCriteria([
    'loyalty_tier' => ['operator' => 'in', 'value' => ['gold', 'platinum']],
    'average_order_value' => ['operator' => '>', 'value' => 150],
    'product_categories' => ['operator' => 'contains', 'value' => 'electronics']
]);

// Get segment analytics and insights
$insights = $segment->getSegmentInsights();
$growth = $segment->getGrowthTrend(30); // 30-day trend
$overlap = $segment->analyzeOverlapWith($otherSegmentId);
```

## ⚡ Real-Time Events

### Event Listeners

```php
// Order completion triggers
$eventDispatcher->listen('order.completed', function($event) {
    $orderData = $event->getData();
    // Trigger post-purchase automation
    $marketingProvider = app()->get(MarketingProviderInterface::class);
    $marketingProvider->triggerAutomation('post_purchase_sequence', 
        $orderData['customer_id'], $orderData);
});

// Customer tier upgrades
$eventDispatcher->listen('loyalty.tier_upgraded', function($event) {
    $data = $event->getData();
    // Send tier upgrade congratulations
    $marketingProvider = app()->get(MarketingProviderInterface::class);
    $marketingProvider->sendTransactionalEmail('tier_upgrade_congratulations',
        ['customer_id' => $data['customer_id']], $data);
});
```

### Event Dispatching

```php
// Dispatch email marketing events
$eventDispatcher->dispatch('email.campaign_sent', [
    'campaign_id' => 123,
    'campaign_name' => 'Black Friday Sale',
    'recipient_count' => 15000,
    'send_completed_at' => now()->toISOString()
]);

$eventDispatcher->dispatch('email.automation_triggered', [
    'automation_id' => 456,
    'trigger_event' => 'cart_abandoned',
    'subscriber_id' => 789,
    'trigger_data' => ['cart_value' => 199.99]
]);
```

## 📈 Performance Monitoring

### Health Checks

```php
// Register email marketing health checks
$healthMonitor->registerHealthCheck('email_marketing', 'deliverability_rate', function() {
    // Check overall deliverability performance
    return $this->checkDeliverabilityHealth();
});

$healthMonitor->registerHealthCheck('email_marketing', 'automation_processing', function() {
    // Verify automation workflow processing
    return $this->checkAutomationProcessingHealth();
});
```

### Metrics Tracking

```php
// Record email marketing performance metrics
$healthMonitor->recordResponseTime('email_marketing', 'campaign_send', 245.8);
$healthMonitor->recordMemoryUsage('email_marketing', 22.1);
$healthMonitor->recordDatabaseQueryTime('email_marketing', 'SELECT * FROM campaigns', 18.5);
```

## 🧪 Automated Testing

### Test Coverage
- **Unit Tests** - Email template rendering and segmentation logic
- **Integration Tests** - Cross-plugin automation workflows
- **Performance Tests** - Large-scale campaign sending benchmarks
- **Security Tests** - Data privacy and consent management

### Example Tests

```php
class EmailMarketingTestSuite extends PluginTestSuite
{
    public function getUnitTests(): array
    {
        return [
            'test_template_rendering' => [$this, 'testTemplateRendering'],
            'test_segmentation_criteria' => [$this, 'testSegmentationCriteria'],
            'test_automation_triggers' => [$this, 'testAutomationTriggers']
        ];
    }
    
    public function testTemplateRendering(): void
    {
        $template = new Template(['content' => 'Hello {{first_name}}!']);
        $rendered = $template->render(['first_name' => 'John']);
        Assert::assertEquals('Hello John!', $rendered);
    }
}
```

## 🛠️ Configuration

### Plugin Settings

```json
{
    "smtp_settings": {
        "host": "smtp.mailgun.org",
        "port": 587,
        "encryption": "tls",
        "authentication": true
    },
    "default_from_email": "noreply@example.com",
    "default_from_name": "Shopologic Store",
    "bounce_handling": true,
    "unsubscribe_handling": "automatic",
    "double_opt_in": true,
    "send_rate_limit": 1000,
    "analytics_tracking": true,
    "gdpr_compliance": true
}
```

### Database Tables
- `email_campaigns` - Campaign definitions and settings
- `email_templates` - Template content and configuration
- `email_subscribers` - Subscriber profiles and preferences
- `email_segments` - Segment definitions and criteria
- `email_automations` - Automation workflow configurations
- `email_automation_steps` - Individual automation step definitions
- `email_sends` - Send tracking and deliverability data
- `subscriber_automations` - Subscriber journey through automations

## 📚 API Endpoints

### REST API
- `GET /api/v1/email/campaigns` - List campaigns
- `POST /api/v1/email/campaigns` - Create campaign
- `POST /api/v1/email/campaigns/{id}/send` - Send campaign
- `GET /api/v1/email/subscribers` - List subscribers
- `POST /api/v1/email/subscribers` - Add subscriber
- `GET /api/v1/email/segments` - List segments
- `POST /api/v1/email/automations/trigger` - Trigger automation
- `GET /api/v1/email/analytics/{id}` - Get campaign analytics

### Usage Examples

```bash
# Create campaign
curl -X POST /api/v1/email/campaigns \
  -H "Content-Type: application/json" \
  -d '{"name": "Newsletter", "template_id": 123, "segment_ids": [1,2]}'

# Trigger automation
curl -X POST /api/v1/email/automations/trigger \
  -H "Content-Type: application/json" \
  -d '{"automation_key": "welcome_series", "customer_id": 456}'

# Get campaign analytics
curl -X GET /api/v1/email/analytics/123 \
  -H "Authorization: Bearer {token}"
```

## 🔧 Installation & Setup

### Requirements
- PHP 8.3+
- PostgreSQL database
- Shopologic Core Framework
- SMTP server or email service provider

### Installation

```bash
# Activate plugin
php cli/plugin.php activate advanced-email-marketing

# Run migrations
php cli/migrate.php up

# Initialize plugin ecosystem
php bootstrap_plugins.php
```

### Email Provider Setup

```bash
# Configure SMTP settings
php cli/email.php configure --provider=mailgun
php cli/email.php test-connection
php cli/email.php setup-webhooks
```

## 📖 Documentation

- **Campaign Creation Guide** - Design and optimization best practices
- **Automation Workflows** - Advanced automation setup and management
- **Segmentation Strategies** - Customer segmentation and targeting
- **Deliverability Guide** - Maintaining high deliverability rates
- **Analytics & Reporting** - Performance measurement and optimization

## 🚀 Production Ready

This plugin is part of the enhanced Shopologic ecosystem and is production-ready with:
- ✅ Comprehensive model layer with sophisticated business logic
- ✅ Cross-plugin integration via standardized interfaces
- ✅ Real-time event system with middleware support
- ✅ Performance monitoring and health checks
- ✅ Automated testing framework
- ✅ Complete documentation and examples

---

**Advanced Email Marketing Automation** - Enterprise email marketing for Shopologic