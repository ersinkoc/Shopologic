# 📊 Google Analytics Plugin

![Quality Badge](https://img.shields.io/badge/Quality-71%25%20(C)-yellow)


Comprehensive Google Analytics integration with advanced e-commerce tracking, custom event management, and enhanced reporting capabilities for deep business insights.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

This plugin is part of the enhanced Shopologic ecosystem featuring cross-plugin integration, real-time events, performance monitoring, and automated testing.

## 🚀 Quick Start

```bash
# Initialize plugin ecosystem
php bootstrap_plugins.php

# Activate Google Analytics plugin
php cli/plugin.php activate analytics-google
```

## ✨ Key Features

### 📈 Advanced E-commerce Tracking
- **Enhanced E-commerce Events** - Complete customer journey tracking from impression to purchase
- **Revenue Attribution** - Multi-channel revenue attribution and conversion path analysis
- **Product Performance Analytics** - Detailed product-level performance metrics
- **Customer Lifetime Value Tracking** - Long-term customer value measurement
- **Funnel Analysis** - Comprehensive conversion funnel tracking and optimization

### 🎯 Custom Event Management
- **Business-Specific Events** - Custom event tracking for unique business requirements
- **User Interaction Tracking** - Detailed user behavior and engagement analytics
- **Content Performance** - Page and content effectiveness measurement
- **Campaign Attribution** - Marketing campaign performance and ROI tracking
- **Cross-Device Analytics** - Unified user journey across multiple devices

### 📊 Advanced Reporting
- **Real-Time Dashboards** - Live business performance monitoring
- **Custom Reports** - Tailored reports for specific business needs
- **Automated Insights** - AI-powered insights and anomaly detection
- **Data Export** - Flexible data export for external analysis
- **Goal Tracking** - Comprehensive goal setup and conversion tracking

## 🏗️ Plugin Architecture

### Main Plugin Class
- **`GoogleAnalyticsPlugin.php`** - Core Google Analytics integration and management

### Services
- **Analytics Tracker** - Core event tracking and data collection
- **E-commerce Tracker** - Specialized e-commerce event tracking
- **Report Generator** - Custom report creation and data visualization
- **Goal Manager** - Conversion goal setup and tracking
- **Data Processor** - Analytics data processing and enrichment

### Models
- **AnalyticsEvent** - Custom event definitions and tracking
- **EcommerceTransaction** - E-commerce transaction tracking
- **ConversionGoal** - Goal setup and performance tracking
- **AnalyticsReport** - Custom report configurations
- **UserSession** - Session tracking and user journey analysis

### Controllers
- **Analytics API** - RESTful endpoints for analytics management
- **Tracking Interface** - Event tracking and data collection endpoints
- **Reports Dashboard** - Analytics reporting and visualization interface

## 📈 E-commerce Tracking Implementation

### Enhanced E-commerce Events

```php
// Advanced e-commerce event tracking
$ecommerceTracker = app(EcommerceTracker::class);

// Track product impressions
$ecommerceTracker->trackProductImpressions([
    'products' => [
        [
            'item_id' => 'PROD123',
            'item_name' => 'Premium Wireless Headphones',
            'item_category' => 'Electronics',
            'item_category2' => 'Audio',
            'item_category3' => 'Headphones',
            'item_brand' => 'AudioTech',
            'price' => 199.99,
            'currency' => 'USD',
            'quantity' => 1,
            'item_list_name' => 'Featured Products',
            'item_list_id' => 'featured_homepage',
            'index' => 1
        ]
    ],
    'context' => [
        'page_location' => '/homepage',
        'page_title' => 'Homepage - Premium Audio Store',
        'user_id' => $userId,
        'session_id' => $sessionId
    ]
]);

// Track product views with detailed context
$ecommerceTracker->trackProductView([
    'product' => [
        'item_id' => 'PROD123',
        'item_name' => 'Premium Wireless Headphones',
        'item_category' => 'Electronics',
        'item_brand' => 'AudioTech',
        'price' => 199.99,
        'currency' => 'USD',
        'value' => 199.99
    ],
    'context' => [
        'content_type' => 'product',
        'content_id' => 'PROD123',
        'user_properties' => [
            'customer_tier' => 'premium',
            'lifetime_value' => 2500,
            'purchase_frequency' => 'monthly'
        ]
    ],
    'enhanced_data' => [
        'product_rating' => 4.8,
        'review_count' => 245,
        'stock_status' => 'in_stock',
        'discount_percentage' => 15
    ]
]);

// Track add to cart with detailed attribution
$ecommerceTracker->trackAddToCart([
    'products' => [
        [
            'item_id' => 'PROD123',
            'item_name' => 'Premium Wireless Headphones',
            'item_category' => 'Electronics',
            'price' => 199.99,
            'quantity' => 1,
            'currency' => 'USD',
            'value' => 199.99
        ]
    ],
    'attribution' => [
        'source' => 'product_page',
        'medium' => 'website',
        'campaign' => 'summer_sale_2024',
        'content' => 'product_recommendations'
    ],
    'user_context' => [
        'device_category' => 'mobile',
        'browser' => 'Chrome',
        'operating_system' => 'iOS',
        'screen_resolution' => '375x812'
    ]
]);
```

### Purchase Tracking and Attribution

```php
// Comprehensive purchase tracking
$ecommerceTracker->trackPurchase([
    'transaction_id' => 'TXN-2024-001234',
    'transaction_data' => [
        'value' => 249.98,
        'currency' => 'USD',
        'tax' => 20.00,
        'shipping' => 9.99,
        'coupon' => 'SUMMER15',
        'affiliation' => 'Online Store'
    ],
    'items' => [
        [
            'item_id' => 'PROD123',
            'item_name' => 'Premium Wireless Headphones',
            'item_category' => 'Electronics',
            'item_brand' => 'AudioTech',
            'price' => 199.99,
            'quantity' => 1,
            'currency' => 'USD',
            'discount' => 29.99
        ],
        [
            'item_id' => 'PROD456',
            'item_name' => 'Headphone Case',
            'item_category' => 'Accessories',
            'price' => 19.99,
            'quantity' => 1,
            'currency' => 'USD'
        ]
    ],
    'customer_data' => [
        'customer_id' => $customerId,
        'customer_tier' => 'premium',
        'first_purchase' => false,
        'lifetime_value' => 2749.98,
        'acquisition_channel' => 'google_ads'
    ],
    'attribution_data' => [
        'first_click_source' => 'google',
        'first_click_medium' => 'cpc',
        'first_click_campaign' => 'brand_awareness',
        'last_click_source' => 'email',
        'last_click_medium' => 'newsletter',
        'last_click_campaign' => 'weekly_deals',
        'days_to_conversion' => 7,
        'touchpoint_count' => 5
    ]
]);

// Track refunds and returns
$ecommerceTracker->trackRefund([
    'transaction_id' => 'TXN-2024-001234',
    'refund_data' => [
        'value' => 199.99,
        'currency' => 'USD',
        'refund_reason' => 'customer_request',
        'refund_type' => 'partial'
    ],
    'items' => [
        [
            'item_id' => 'PROD123',
            'quantity' => 1,
            'price' => 199.99
        ]
    ]
]);
```

## 🎯 Custom Event Tracking

### Business-Specific Event Management

```php
// Advanced custom event tracking
$analyticsTracker = app(AnalyticsTracker::class);

// Track newsletter subscriptions
$analyticsTracker->trackCustomEvent([
    'event_name' => 'newsletter_subscription',
    'event_parameters' => [
        'subscription_type' => 'weekly_deals',
        'user_tier' => 'new_customer',
        'signup_location' => 'checkout_page',
        'incentive_offered' => '10_percent_discount'
    ],
    'value' => 5.00, // Estimated value of newsletter subscriber
    'currency' => 'USD'
]);

// Track content engagement
$analyticsTracker->trackContentEngagement([
    'content_type' => 'blog_article',
    'content_id' => 'BLOG-2024-001',
    'content_title' => 'The Future of Wireless Audio',
    'engagement_metrics' => [
        'time_on_page' => 180, // seconds
        'scroll_depth' => 85, // percentage
        'social_shares' => 3,
        'comments' => 1,
        'rating' => 5
    ],
    'user_context' => [
        'traffic_source' => 'organic_search',
        'device_type' => 'desktop',
        'user_type' => 'returning_visitor'
    ]
]);

// Track search behavior
$analyticsTracker->trackSiteSearch([
    'search_term' => 'wireless bluetooth headphones',
    'search_category' => 'electronics',
    'search_results' => [
        'total_results' => 24,
        'results_per_page' => 12,
        'page_number' => 1,
        'top_result_clicked' => true,
        'click_position' => 3
    ],
    'search_refinements' => [
        'filters_applied' => ['brand', 'price_range'],
        'sort_order' => 'price_low_to_high',
        'search_refinement_count' => 2
    ]
]);

// Track user interactions
$analyticsTracker->trackUserInteraction([
    'interaction_type' => 'video_play',
    'element_id' => 'product_demo_video',
    'interaction_data' => [
        'video_title' => 'Headphone Demo - Noise Cancellation',
        'video_duration' => 120, // seconds
        'play_time' => 45, // seconds watched
        'completion_rate' => 0.375,
        'quality_selected' => '1080p',
        'volume_level' => 0.8
    ],
    'context' => [
        'page_type' => 'product_detail',
        'product_id' => 'PROD123',
        'user_session_duration' => 300
    ]
]);
```

### Campaign and Marketing Attribution

```php
// Advanced campaign tracking
$campaignTracker = app(CampaignTracker::class);

// Track marketing campaign performance
$campaignTracker->trackCampaignInteraction([
    'campaign_id' => 'SUMMER_SALE_2024',
    'campaign_name' => 'Summer Electronics Sale',
    'campaign_type' => 'seasonal_promotion',
    'interaction_type' => 'banner_click',
    'creative_details' => [
        'creative_id' => 'BANNER_001',
        'creative_name' => 'Summer Sale Hero Banner',
        'creative_format' => 'display_banner',
        'creative_size' => '728x90',
        'placement' => 'homepage_header'
    ],
    'targeting_data' => [
        'audience_segment' => 'electronics_enthusiasts',
        'demographic_targeting' => 'age_25_45',
        'behavioral_targeting' => 'frequent_shoppers',
        'geographic_targeting' => 'US_west_coast'
    ]
]);

// Track affiliate and referral performance
$campaignTracker->trackAffiliateActivity([
    'affiliate_id' => 'AFF_TECH_BLOGGER_001',
    'affiliate_name' => 'TechReview Pro',
    'referral_source' => 'blog_review',
    'commission_rate' => 0.08,
    'activity_type' => 'click',
    'landing_page' => '/products/headphones',
    'conversion_value' => 0, // Will be updated on conversion
    'tracking_data' => [
        'click_timestamp' => now(),
        'user_agent' => $userAgent,
        'ip_address' => $ipAddress,
        'referrer_url' => 'https://techreviewpro.com/headphone-reviews'
    ]
]);
```

## 🔗 Cross-Plugin Integration

### Integration with Advanced Analytics

```php
// Enhanced analytics integration
$analyticsProvider = app()->get(AnalyticsProviderInterface::class);

// Sync data between internal analytics and Google Analytics
$dataSync = $analyticsProvider->syncWithGoogleAnalytics([
    'sync_direction' => 'bidirectional',
    'data_types' => [
        'ecommerce_events',
        'custom_events',
        'user_properties',
        'conversion_goals'
    ],
    'sync_frequency' => 'real_time',
    'data_validation' => true
]);

// Enhanced reporting with cross-platform data
$combinedReport = $analyticsProvider->generateCombinedReport([
    'google_analytics_data' => $gaData,
    'internal_analytics_data' => $internalData,
    'report_type' => 'comprehensive_performance',
    'metrics' => [
        'traffic_acquisition',
        'user_behavior',
        'conversion_performance',
        'revenue_attribution'
    ]
]);
```

### Integration with Email Marketing

```php
// Marketing campaign attribution
$marketingProvider = app()->get(MarketingProviderInterface::class);

// Track email campaign performance in Google Analytics
$emailCampaignTracking = $analyticsTracker->trackEmailCampaign([
    'campaign_id' => 'EMAIL_WEEKLY_001',
    'campaign_name' => 'Weekly Deals Newsletter',
    'email_data' => [
        'subject_line' => 'Save 20% on Premium Audio Gear',
        'send_date' => '2024-06-15',
        'recipient_count' => 15000,
        'segment' => 'electronics_enthusiasts'
    ],
    'performance_metrics' => [
        'open_rate' => 0.28,
        'click_rate' => 0.045,
        'conversion_rate' => 0.012,
        'unsubscribe_rate' => 0.002
    ],
    'attribution_window' => '7_days'
]);

// Track marketing automation workflows
$marketingProvider->trackAutomationWorkflow([
    'workflow_id' => 'ABANDON_CART_001',
    'workflow_name' => 'Cart Abandonment Recovery',
    'trigger_event' => 'cart_abandonment',
    'ga_tracking' => [
        'campaign_source' => 'email',
        'campaign_medium' => 'automation',
        'campaign_name' => 'cart_recovery',
        'campaign_content' => 'reminder_email_1'
    ]
]);
```

## ⚡ Real-Time Analytics Events

### Live Event Processing

```php
// Process real-time analytics events
$eventDispatcher = PluginEventDispatcher::getInstance();

$eventDispatcher->listen('analytics.user_interaction', function($event) {
    $interactionData = $event->getData();
    
    // Send real-time event to Google Analytics
    $analyticsTracker = app(AnalyticsTracker::class);
    $analyticsTracker->trackRealTimeEvent([
        'event_name' => $interactionData['event_type'],
        'event_parameters' => $interactionData['parameters'],
        'user_id' => $interactionData['user_id'],
        'timestamp' => $interactionData['timestamp']
    ]);
    
    // Update real-time dashboard
    $dashboardService = app(RealTimeDashboard::class);
    $dashboardService->updateMetrics($interactionData);
});

$eventDispatcher->listen('ecommerce.conversion', function($event) {
    $conversionData = $event->getData();
    
    // Enhanced conversion tracking
    $ecommerceTracker = app(EcommerceTracker::class);
    $ecommerceTracker->trackConversionComplete([
        'conversion_data' => $conversionData,
        'attribution_analysis' => true,
        'customer_journey_tracking' => true,
        'cross_device_tracking' => true
    ]);
    
    // Update conversion goals
    $goalManager = app(GoalManager::class);
    $goalManager->updateGoalProgress($conversionData);
});
```

## 📊 Advanced Reporting and Insights

### Custom Report Generation

```php
// Generate comprehensive analytics reports
$reportGenerator = app(ReportGenerator::class);

// E-commerce performance report
$ecommerceReport = $reportGenerator->generateEcommerceReport([
    'date_range' => ['start' => '2024-01-01', 'end' => '2024-06-30'],
    'metrics' => [
        'revenue',
        'transactions',
        'average_order_value',
        'ecommerce_conversion_rate',
        'product_performance',
        'category_performance'
    ],
    'dimensions' => [
        'source_medium',
        'device_category',
        'geographic_location',
        'customer_segment'
    ],
    'advanced_analysis' => [
        'cohort_analysis' => true,
        'attribution_modeling' => 'data_driven',
        'predictive_insights' => true
    ]
]);

// Customer journey analysis
$journeyReport = $reportGenerator->generateCustomerJourneyReport([
    'analysis_period' => '90_days',
    'journey_mapping' => [
        'touchpoint_analysis' => true,
        'path_analysis' => true,
        'conversion_paths' => true,
        'drop_off_analysis' => true
    ],
    'segmentation' => [
        'new_vs_returning',
        'device_category',
        'traffic_source',
        'geographic_region'
    ]
]);

// Real-time performance dashboard
$realTimeDashboard = $reportGenerator->generateRealTimeDashboard([
    'refresh_interval' => 30, // seconds
    'widgets' => [
        'active_users',
        'real_time_conversions',
        'top_pages',
        'traffic_sources',
        'goal_completions',
        'revenue_tracking'
    ],
    'alerts' => [
        'traffic_spike' => ['threshold' => '200%', 'notification' => true],
        'conversion_drop' => ['threshold' => '-50%', 'notification' => true],
        'goal_achievement' => ['threshold' => '100%', 'notification' => true]
    ]
]);
```

## 🧪 Testing Framework Integration

### Analytics Tracking Test Coverage

```php
class GoogleAnalyticsTestSuite extends PluginTestSuite
{
    public function getUnitTests(): array
    {
        return [
            'test_ecommerce_tracking' => [$this, 'testEcommerceTracking'],
            'test_custom_event_tracking' => [$this, 'testCustomEventTracking'],
            'test_goal_tracking' => [$this, 'testGoalTracking'],
            'test_attribution_tracking' => [$this, 'testAttributionTracking']
        ];
    }
    
    public function testEcommerceTracking(): void
    {
        $tracker = new EcommerceTracker();
        $result = $tracker->trackPurchase([
            'transaction_id' => 'TEST_TXN_001',
            'value' => 199.99,
            'currency' => 'USD',
            'items' => $this->getMockPurchaseItems()
        ]);
        
        Assert::assertTrue($result->success);
        Assert::assertNotEmpty($result->tracking_id);
    }
    
    public function testCustomEventTracking(): void
    {
        $tracker = new AnalyticsTracker();
        $result = $tracker->trackCustomEvent([
            'event_name' => 'newsletter_signup',
            'event_parameters' => ['source' => 'homepage']
        ]);
        
        Assert::assertTrue($result->success);
        Assert::assertEquals('newsletter_signup', $result->event_name);
    }
}
```

## 🛠️ Configuration

### Google Analytics Settings

```json
{
    "google_analytics": {
        "measurement_id": "G-XXXXXXXXXX",
        "api_secret": "your_measurement_protocol_secret",
        "tracking_mode": "gtag",
        "enhanced_ecommerce": true,
        "real_time_tracking": true,
        "cross_domain_tracking": false
    },
    "ecommerce_tracking": {
        "purchase_tracking": true,
        "refund_tracking": true,
        "product_impressions": true,
        "product_clicks": true,
        "checkout_steps": true,
        "promotion_tracking": true
    },
    "custom_events": {
        "newsletter_signup": {"value": 5.0},
        "video_engagement": {"value": 2.0},
        "content_download": {"value": 3.0},
        "social_share": {"value": 1.0}
    },
    "data_retention": {
        "user_data_retention": "26_months",
        "event_data_retention": "14_months",
        "reset_on_new_activity": true
    }
}
```

### Database Tables
- `ga_events` - Custom event tracking and metadata
- `ga_ecommerce_transactions` - E-commerce transaction records
- `ga_goals` - Conversion goal configurations
- `ga_campaigns` - Campaign tracking and attribution data
- `ga_reports` - Custom report configurations and schedules

## 📚 API Endpoints

### REST API
- `POST /api/v1/analytics/track-event` - Track custom events
- `POST /api/v1/analytics/track-purchase` - Track e-commerce purchases
- `GET /api/v1/analytics/reports` - Generate custom reports
- `POST /api/v1/analytics/goals` - Configure conversion goals
- `GET /api/v1/analytics/real-time` - Real-time analytics data

### Usage Examples

```bash
# Track custom event
curl -X POST /api/v1/analytics/track-event \
  -H "Content-Type: application/json" \
  -d '{"event_name": "newsletter_signup", "parameters": {"source": "homepage"}}'

# Track purchase
curl -X POST /api/v1/analytics/track-purchase \
  -H "Content-Type: application/json" \
  -d '{"transaction_id": "TXN123", "value": 199.99, "items": [...]}'

# Get real-time data
curl -X GET /api/v1/analytics/real-time \
  -H "Authorization: Bearer {token}"
```

## 🔧 Installation

### Requirements
- PHP 8.3+
- Google Analytics 4 account
- Measurement Protocol API access
- SSL certificate for secure tracking

### Setup

```bash
# Activate plugin
php cli/plugin.php activate analytics-google

# Configure Google Analytics
php cli/analytics.php configure --measurement-id=G-XXXXXXXXXX

# Setup enhanced e-commerce
php cli/analytics.php setup-ecommerce

# Test tracking setup
php cli/analytics.php test-tracking
```

## 📖 Documentation

- **Google Analytics Setup Guide** - Complete configuration walkthrough
- **E-commerce Tracking Manual** - Enhanced e-commerce implementation
- **Custom Event Guide** - Business-specific event tracking
- **Attribution Modeling** - Multi-channel attribution analysis

## 🚀 Production Ready

This plugin is part of the enhanced Shopologic ecosystem and is production-ready with:
- ✅ Comprehensive Google Analytics 4 integration
- ✅ Cross-plugin data synchronization
- ✅ Real-time event tracking and reporting
- ✅ Advanced e-commerce and attribution tracking
- ✅ Complete testing framework integration
- ✅ GDPR-compliant data collection

---

**Google Analytics Plugin** - Advanced analytics integration for Shopologic