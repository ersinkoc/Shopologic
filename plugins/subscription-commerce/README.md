# 💳 Subscription Commerce Plugin

![Quality Badge](https://img.shields.io/badge/Quality-71%25%20(C)-yellow)


Comprehensive subscription management platform enabling recurring billing, flexible subscription models, automated renewals, and complete subscription lifecycle management for modern subscription-based businesses.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

This plugin is part of the enhanced Shopologic ecosystem featuring cross-plugin integration, real-time events, performance monitoring, and automated testing.

## 🚀 Quick Start

```bash
# Initialize plugin ecosystem
php bootstrap_plugins.php

# Activate Subscription Commerce
php cli/plugin.php activate subscription-commerce
```

## ✨ Key Features

### 📅 Flexible Subscription Models
- **Multiple Billing Frequencies** - Daily, weekly, monthly, quarterly, yearly options
- **Tiered Subscriptions** - Multiple pricing tiers with feature differentiation
- **Usage-Based Billing** - Metered billing based on consumption
- **Hybrid Models** - Combination of fixed and usage-based pricing
- **Trial Periods** - Configurable trial periods with automatic conversion

### 💰 Advanced Billing Management
- **Automated Recurring Billing** - Seamless payment processing and retry logic
- **Proration Handling** - Automatic proration for plan changes
- **Dunning Management** - Intelligent failed payment recovery
- **Multiple Payment Methods** - Support for various payment sources
- **Invoice Generation** - Automated invoice creation and delivery

### 🔄 Subscription Lifecycle
- **Flexible Plan Changes** - Upgrades, downgrades, and lateral moves
- **Pause/Resume Functionality** - Temporary subscription holds
- **Cancellation Management** - Retention workflows and win-back campaigns
- **Renewal Optimization** - Smart renewal timing and notifications
- **Churn Prevention** - Predictive analytics for retention

## 🏗️ Plugin Architecture

### Main Plugin Class
- **`SubscriptionCommercePlugin.php`** - Core subscription management and lifecycle

### Services
- **Subscription Manager** - Core subscription lifecycle management
- **Billing Engine** - Recurring billing and payment processing
- **Plan Manager** - Subscription plan creation and management
- **Dunning Service** - Failed payment recovery and retry logic
- **Analytics Engine** - Subscription metrics and insights

### Models
- **Subscription** - Customer subscription records and status
- **SubscriptionPlan** - Plan definitions and pricing structures
- **BillingCycle** - Billing period management and scheduling
- **SubscriptionInvoice** - Invoice generation and tracking
- **PaymentAttempt** - Payment processing and retry history

### Controllers
- **Subscription API** - RESTful endpoints for subscription management
- **Customer Portal** - Self-service subscription management interface
- **Admin Dashboard** - Subscription administration and analytics

## 💳 Subscription Management Implementation

### Advanced Subscription Plans

```php
// Sophisticated subscription plan management
$subscriptionManager = app(SubscriptionManager::class);

// Create comprehensive subscription plans
$subscriptionPlan = $subscriptionManager->createSubscriptionPlan([
    'plan_id' => 'PREMIUM_MONTHLY_2024',
    'plan_name' => 'Premium Monthly Subscription',
    'plan_description' => 'Full access to premium features with priority support',
    'pricing_model' => [
        'type' => 'hybrid', // fixed_recurring, usage_based, hybrid
        'base_price' => [
            'amount' => 49.99,
            'currency' => 'USD',
            'interval' => 'month',
            'interval_count' => 1
        ],
        'usage_components' => [
            [
                'component_id' => 'api_calls',
                'component_name' => 'API Calls',
                'pricing_scheme' => 'tiered',
                'tiers' => [
                    ['up_to' => 1000, 'unit_price' => 0],
                    ['up_to' => 5000, 'unit_price' => 0.01],
                    ['up_to' => 20000, 'unit_price' => 0.008],
                    ['up_to' => null, 'unit_price' => 0.005]
                ],
                'aggregate_usage' => 'sum'
            ],
            [
                'component_id' => 'storage_gb',
                'component_name' => 'Storage',
                'pricing_scheme' => 'per_unit',
                'unit_price' => 0.10,
                'included_units' => 100
            ]
        ]
    ],
    'features' => [
        'included_features' => [
            'priority_support' => true,
            'advanced_analytics' => true,
            'api_access' => true,
            'custom_branding' => true,
            'team_collaboration' => ['max_users' => 10]
        ],
        'add_on_features' => [
            'additional_users' => ['price_per_user' => 5.00],
            'white_label' => ['monthly_price' => 99.00],
            'dedicated_support' => ['monthly_price' => 299.00]
        ]
    ],
    'trial_configuration' => [
        'trial_period_days' => 14,
        'trial_type' => 'full_access', // limited_access, credit_based
        'credit_card_required' => false,
        'auto_convert_to_paid' => true,
        'trial_limitations' => [
            'api_calls_limit' => 1000,
            'storage_limit_gb' => 10
        ]
    ],
    'billing_configuration' => [
        'proration_enabled' => true,
        'proration_method' => 'immediate', // immediate, next_billing_cycle
        'minimum_charge' => 1.00,
        'tax_inclusive' => false,
        'setup_fee' => 0.00
    ]
]);

// Create tiered subscription plans
$tieredPlans = $subscriptionManager->createTieredSubscriptionStructure([
    'product_family' => 'SaaS Platform Access',
    'tiers' => [
        [
            'tier_name' => 'Starter',
            'tier_level' => 1,
            'monthly_price' => 19.99,
            'annual_price' => 199.99, // Discounted annual
            'features' => [
                'users' => 3,
                'projects' => 5,
                'storage_gb' => 10,
                'support_level' => 'email'
            ],
            'upgrade_path' => ['Professional', 'Enterprise']
        ],
        [
            'tier_name' => 'Professional',
            'tier_level' => 2,
            'monthly_price' => 79.99,
            'annual_price' => 799.99,
            'features' => [
                'users' => 15,
                'projects' => 50,
                'storage_gb' => 100,
                'support_level' => 'priority',
                'advanced_features' => ['analytics', 'integrations', 'api_access']
            ],
            'upgrade_path' => ['Enterprise'],
            'downgrade_path' => ['Starter']
        ],
        [
            'tier_name' => 'Enterprise',
            'tier_level' => 3,
            'monthly_price' => 299.99,
            'annual_price' => 2999.99,
            'features' => [
                'users' => 'unlimited',
                'projects' => 'unlimited',
                'storage_gb' => 1000,
                'support_level' => 'dedicated',
                'enterprise_features' => ['sso', 'audit_logs', 'custom_contracts', 'sla']
            ],
            'custom_pricing_available' => true,
            'downgrade_path' => ['Professional']
        ]
    ],
    'tier_comparison_matrix' => true,
    'automatic_recommendations' => true,
    'usage_based_tier_suggestions' => true
]);
```

### Customer Subscription Lifecycle

```php
// Advanced subscription lifecycle management
$subscriptionLifecycle = app(SubscriptionLifecycleManager::class);

// Create new subscription with comprehensive setup
$newSubscription = $subscriptionLifecycle->createSubscription([
    'customer_id' => 'CUST_12345',
    'plan_id' => 'PREMIUM_MONTHLY_2024',
    'subscription_details' => [
        'start_date' => now()->toISOString(),
        'trial_end_date' => now()->addDays(14)->toISOString(),
        'billing_cycle_anchor' => now()->day(1)->toISOString(), // First of month
        'payment_method_id' => 'pm_1234567890',
        'metadata' => [
            'source' => 'website_signup',
            'campaign' => 'summer_promo_2024',
            'referrer' => 'partner_xyz'
        ]
    ],
    'initial_configuration' => [
        'add_ons' => ['white_label', 'additional_users' => 5],
        'usage_alerts' => [
            'api_calls' => ['threshold' => 4000, 'notification' => 'email'],
            'storage_gb' => ['threshold' => 90, 'notification' => 'dashboard']
        ],
        'billing_preferences' => [
            'invoice_delivery' => 'email',
            'payment_retry_schedule' => 'intelligent', // intelligent, aggressive, conservative
            'auto_collection' => true
        ]
    ],
    'activation_workflow' => [
        'send_welcome_email' => true,
        'provision_resources' => true,
        'setup_onboarding' => true,
        'assign_customer_success_manager' => false
    ]
]);

// Handle subscription upgrades/downgrades
$planChange = $subscriptionLifecycle->changePlan([
    'subscription_id' => $newSubscription->id,
    'new_plan_id' => 'ENTERPRISE_MONTHLY_2024',
    'change_configuration' => [
        'change_timing' => 'immediate', // immediate, next_billing_cycle, specific_date
        'proration_handling' => [
            'calculate_proration' => true,
            'credit_unused_time' => true,
            'charge_upgrade_difference' => true,
            'minimum_charge_threshold' => 1.00
        ],
        'feature_migration' => [
            'preserve_customizations' => true,
            'migrate_usage_data' => true,
            'notify_feature_changes' => true,
            'grandfathering_rules' => [
                'preserve_pricing' => false,
                'preserve_features' => ['custom_domain']
            ]
        ],
        'communication' => [
            'send_confirmation_email' => true,
            'update_invoice_immediately' => true,
            'notify_team_members' => true
        ]
    ]
]);

// Implement pause/resume functionality
$pausedSubscription = $subscriptionLifecycle->pauseSubscription([
    'subscription_id' => $newSubscription->id,
    'pause_configuration' => [
        'pause_type' => 'billing_pause', // billing_pause, access_pause, partial_pause
        'pause_duration' => [
            'start_date' => now()->toISOString(),
            'end_date' => now()->addMonths(3)->toISOString(),
            'max_pause_duration' => 90 // days
        ],
        'pause_behavior' => [
            'preserve_data' => true,
            'allow_read_only_access' => true,
            'pause_billing_immediately' => true,
            'prorate_current_period' => true
        ],
        'resume_configuration' => [
            'auto_resume' => true,
            'resume_notification_days' => [7, 3, 1],
            'resume_with_current_plan' => true,
            'offer_plan_change_on_resume' => true
        ]
    ],
    'pause_reason' => [
        'category' => 'temporary_budget_constraint',
        'feedback' => 'Seasonal business slowdown',
        'likelihood_to_resume' => 0.85
    ]
]);
```

### Billing and Payment Processing

```php
// Advanced billing engine implementation
$billingEngine = app(BillingEngine::class);

// Process recurring billing cycle
$billingCycle = $billingEngine->processBillingCycle([
    'subscription_id' => $newSubscription->id,
    'billing_period' => [
        'start_date' => '2024-06-01',
        'end_date' => '2024-06-30',
        'billing_date' => '2024-07-01'
    ],
    'billing_components' => [
        'base_subscription' => [
            'amount' => 49.99,
            'description' => 'Premium Monthly Subscription',
            'tax_applicable' => true
        ],
        'usage_charges' => [
            'api_calls' => [
                'usage_quantity' => 3500,
                'billable_quantity' => 2500, // After included 1000
                'unit_price' => 0.01,
                'total_charge' => 25.00
            ],
            'storage_gb' => [
                'usage_quantity' => 150,
                'billable_quantity' => 50, // After included 100
                'unit_price' => 0.10,
                'total_charge' => 5.00
            ]
        ],
        'add_ons' => [
            'white_label' => 99.00,
            'additional_users' => 25.00 // 5 users × $5
        ],
        'credits_and_adjustments' => [
            'promotional_credit' => -10.00,
            'service_credit' => -5.00,
            'referral_bonus' => -20.00
        ]
    ],
    'tax_calculation' => [
        'tax_rate' => 0.0875, // 8.75%
        'tax_jurisdiction' => 'NY',
        'tax_exempt' => false,
        'tax_amount' => 16.74
    ],
    'invoice_generation' => [
        'generate_pdf' => true,
        'include_usage_details' => true,
        'branding_customization' => true,
        'payment_terms' => 'due_upon_receipt'
    ]
]);

// Implement intelligent dunning management
$dunningService = app(DunningService::class);

$dunningCampaign = $dunningService->createDunningCampaign([
    'failed_payment' => [
        'subscription_id' => $newSubscription->id,
        'payment_attempt_id' => 'pa_failed_123',
        'failure_reason' => 'insufficient_funds',
        'failure_amount' => 168.73
    ],
    'dunning_strategy' => [
        'retry_schedule' => [
            ['days_after_failure' => 3, 'retry_type' => 'smart_retry'],
            ['days_after_failure' => 5, 'retry_type' => 'different_time'],
            ['days_after_failure' => 7, 'retry_type' => 'reduced_amount'],
            ['days_after_failure' => 10, 'retry_type' => 'final_attempt']
        ],
        'communication_sequence' => [
            [
                'timing' => 'immediate',
                'channel' => 'email',
                'template' => 'payment_failed_soft',
                'tone' => 'understanding'
            ],
            [
                'timing' => 'day_3',
                'channel' => 'email',
                'template' => 'payment_retry_reminder',
                'include_update_payment_link' => true
            ],
            [
                'timing' => 'day_7',
                'channel' => 'in_app_notification',
                'template' => 'account_at_risk',
                'urgency_level' => 'medium'
            ],
            [
                'timing' => 'day_10',
                'channel' => 'email',
                'template' => 'final_notice',
                'include_retention_offer' => true
            ]
        ],
        'smart_retry_features' => [
            'optimal_retry_time' => true, // ML-based optimal charging time
            'payment_method_update_prompts' => true,
            'alternative_payment_methods' => true,
            'partial_payment_acceptance' => true,
            'payment_plan_offers' => true
        ]
    ],
    'retention_tactics' => [
        'offer_temporary_downgrade' => true,
        'extend_grace_period' => ['max_days' => 30],
        'provide_one_time_discount' => ['percentage' => 25],
        'pause_instead_of_cancel' => true
    ]
]);
```

### Subscription Analytics and Insights

```php
// Advanced subscription analytics
$analyticsEngine = app(SubscriptionAnalyticsEngine::class);

// Calculate comprehensive subscription metrics
$subscriptionMetrics = $analyticsEngine->calculateMetrics([
    'time_period' => '2024-Q2',
    'metrics_categories' => [
        'revenue_metrics' => [
            'mrr' => true, // Monthly Recurring Revenue
            'arr' => true, // Annual Recurring Revenue
            'arpu' => true, // Average Revenue Per User
            'ltv' => true, // Lifetime Value
            'revenue_growth_rate' => true,
            'revenue_churn' => true,
            'expansion_revenue' => true
        ],
        'customer_metrics' => [
            'total_subscribers' => true,
            'new_subscribers' => true,
            'churned_subscribers' => true,
            'churn_rate' => true,
            'retention_rate' => true,
            'reactivation_rate' => true,
            'trial_conversion_rate' => true
        ],
        'operational_metrics' => [
            'failed_payment_rate' => true,
            'dunning_recovery_rate' => true,
            'average_subscription_length' => true,
            'upgrade_rate' => true,
            'downgrade_rate' => true,
            'pause_rate' => true
        ],
        'cohort_analysis' => [
            'revenue_cohorts' => true,
            'retention_cohorts' => true,
            'ltv_cohorts' => true,
            'behavior_cohorts' => true
        ]
    ],
    'segmentation' => [
        'by_plan' => true,
        'by_billing_period' => true,
        'by_acquisition_channel' => true,
        'by_customer_segment' => true,
        'by_geography' => true
    ],
    'predictive_analytics' => [
        'churn_prediction' => true,
        'ltv_prediction' => true,
        'upgrade_likelihood' => true,
        'payment_failure_risk' => true
    ]
]);

// Generate actionable insights
$subscriptionInsights = $analyticsEngine->generateInsights([
    'metrics_data' => $subscriptionMetrics,
    'insight_categories' => [
        'revenue_optimization' => [
            'pricing_optimization_opportunities',
            'plan_performance_analysis',
            'add_on_attachment_rates',
            'discount_impact_analysis'
        ],
        'churn_reduction' => [
            'churn_risk_factors',
            'retention_lever_effectiveness',
            'engagement_correlation',
            'feature_usage_impact'
        ],
        'growth_opportunities' => [
            'expansion_revenue_potential',
            'cross_sell_opportunities',
            'market_segment_penetration',
            'geographic_expansion_potential'
        ]
    ],
    'recommendation_engine' => [
        'automated_recommendations' => true,
        'priority_scoring' => true,
        'impact_estimation' => true,
        'implementation_roadmap' => true
    ]
]);
```

## 🔗 Cross-Plugin Integration

### Integration with Payment Processing

```php
// Payment gateway integration for subscriptions
$paymentProvider = app()->get(PaymentGatewayInterface::class);

// Setup subscription payment methods
$subscriptionPaymentSetup = $paymentProvider->setupSubscriptionPayments([
    'customer_id' => 'CUST_12345',
    'subscription_id' => $newSubscription->id,
    'payment_configuration' => [
        'primary_payment_method' => [
            'type' => 'credit_card',
            'method_id' => 'pm_card_visa_1234',
            'is_default' => true
        ],
        'backup_payment_methods' => [
            [
                'type' => 'bank_account',
                'method_id' => 'pm_bank_checking_5678',
                'use_for_retry' => true
            ]
        ],
        'payment_routing_rules' => [
            'use_backup_on_failure' => true,
            'smart_retry_enabled' => true,
            'multi_currency_support' => true
        ]
    ],
    'tokenization' => [
        'vault_payment_methods' => true,
        'pci_compliance_level' => 'saq_a',
        'network_tokenization' => true
    ]
]);

// Process subscription payment
$paymentResult = $paymentProvider->processSubscriptionPayment([
    'subscription_id' => $newSubscription->id,
    'amount' => $billingCycle->total_amount,
    'currency' => 'USD',
    'payment_metadata' => [
        'billing_period' => $billingCycle->period,
        'invoice_id' => $billingCycle->invoice_id,
        'retry_attempt' => 0
    ]
]);
```

### Integration with Customer Analytics

```php
// Customer behavior analytics integration
$analyticsProvider = app()->get(AnalyticsProviderInterface::class);

// Track subscription lifecycle events
$subscriptionTracking = $analyticsProvider->trackSubscriptionEvents([
    'customer_id' => 'CUST_12345',
    'subscription_events' => [
        'subscription_created' => [
            'plan_name' => $subscriptionPlan->name,
            'plan_value' => $subscriptionPlan->price,
            'trial_period' => $subscriptionPlan->trial_days,
            'acquisition_channel' => 'organic_search'
        ],
        'trial_started' => [
            'trial_length' => 14,
            'features_accessed' => ['dashboard', 'api', 'reports'],
            'engagement_score' => 0.75
        ],
        'subscription_converted' => [
            'conversion_time' => 'day_10',
            'conversion_trigger' => 'feature_limit_reached',
            'initial_payment' => 49.99
        ]
    ],
    'behavioral_signals' => [
        'feature_usage_frequency' => 'daily',
        'api_call_volume' => 'high',
        'support_ticket_count' => 0,
        'satisfaction_score' => 9
    ]
]);

// Churn prediction integration
$churnPrediction = $analyticsProvider->predictSubscriptionChurn([
    'subscription_id' => $newSubscription->id,
    'prediction_factors' => [
        'usage_decline' => false,
        'support_tickets' => 0,
        'failed_payments' => 0,
        'competitor_research_detected' => false,
        'engagement_score' => 0.85
    ]
]);
```

## ⚡ Real-Time Subscription Events

### Subscription Event Processing

```php
// Process subscription lifecycle events
$eventDispatcher = PluginEventDispatcher::getInstance();

$eventDispatcher->listen('subscription.created', function($event) {
    $subscriptionData = $event->getData();
    
    // Initialize subscription resources
    $resourceProvisioner = app(ResourceProvisioner::class);
    $resourceProvisioner->provisionSubscriptionResources([
        'subscription_id' => $subscriptionData['subscription_id'],
        'plan_features' => $subscriptionData['plan_features'],
        'immediate_activation' => true
    ]);
    
    // Start onboarding workflow
    $onboardingService = app(OnboardingService::class);
    $onboardingService->startSubscriptionOnboarding([
        'customer_id' => $subscriptionData['customer_id'],
        'subscription_type' => $subscriptionData['plan_type'],
        'personalized_journey' => true
    ]);
});

$eventDispatcher->listen('subscription.payment_failed', function($event) {
    $failureData = $event->getData();
    
    // Initiate dunning process
    $dunningService = app(DunningService::class);
    $dunningService->handlePaymentFailure([
        'subscription_id' => $failureData['subscription_id'],
        'failure_reason' => $failureData['reason'],
        'failure_count' => $failureData['attempt_count'],
        'intelligent_retry' => true
    ]);
    
    // Update customer risk profile
    $riskAssessment = app(CustomerRiskAssessment::class);
    $riskAssessment->updatePaymentRisk([
        'customer_id' => $failureData['customer_id'],
        'risk_event' => 'payment_failure',
        'risk_score_adjustment' => 0.1
    ]);
});

$eventDispatcher->listen('subscription.churned', function($event) {
    $churnData = $event->getData();
    
    // Analyze churn reasons
    $churnAnalytics = app(ChurnAnalyticsService::class);
    $churnAnalysis = $churnAnalytics->analyzeChurnEvent([
        'subscription_id' => $churnData['subscription_id'],
        'churn_reason' => $churnData['reason'],
        'customer_feedback' => $churnData['feedback'],
        'usage_patterns' => $churnData['usage_data']
    ]);
    
    // Initiate win-back campaign
    $winBackService = app(WinBackCampaignService::class);
    $winBackService->createWinBackCampaign([
        'customer_id' => $churnData['customer_id'],
        'churn_analysis' => $churnAnalysis,
        'personalized_offers' => true,
        'campaign_duration' => '90_days'
    ]);
});
```

## 🧪 Testing Framework Integration

### Subscription Commerce Test Coverage

```php
class SubscriptionCommerceTestSuite extends PluginTestSuite
{
    public function getUnitTests(): array
    {
        return [
            'test_subscription_creation' => [$this, 'testSubscriptionCreation'],
            'test_billing_cycle_processing' => [$this, 'testBillingCycleProcessing'],
            'test_plan_change_proration' => [$this, 'testPlanChangeProration'],
            'test_dunning_recovery' => [$this, 'testDunningRecovery']
        ];
    }
    
    public function testSubscriptionCreation(): void
    {
        $subscriptionManager = new SubscriptionManager();
        $subscription = $subscriptionManager->createSubscription([
            'customer_id' => 'TEST_CUSTOMER',
            'plan_id' => 'TEST_PLAN'
        ]);
        
        Assert::assertNotNull($subscription->id);
        Assert::assertEquals('active', $subscription->status);
        Assert::assertNotNull($subscription->next_billing_date);
    }
    
    public function testBillingCycleProcessing(): void
    {
        $billingEngine = new BillingEngine();
        $billingResult = $billingEngine->processBillingCycle([
            'subscription_id' => 'TEST_SUBSCRIPTION',
            'amount' => 99.99
        ]);
        
        Assert::assertTrue($billingResult->success);
        Assert::assertNotNull($billingResult->invoice_id);
        Assert::assertEquals('paid', $billingResult->payment_status);
    }
}
```

## 🛠️ Configuration

### Subscription Commerce Settings

```json
{
    "subscription_settings": {
        "billing_cycles": ["daily", "weekly", "monthly", "quarterly", "yearly"],
        "trial_enabled": true,
        "default_trial_days": 14,
        "proration_enabled": true,
        "auto_renewal": true
    },
    "billing_configuration": {
        "payment_retry_attempts": 4,
        "retry_interval_days": [3, 5, 7, 10],
        "dunning_enabled": true,
        "grace_period_days": 10,
        "failed_payment_notifications": true
    },
    "plan_management": {
        "allow_plan_changes": true,
        "immediate_downgrades": false,
        "upgrade_proration": true,
        "grandfathering_enabled": true
    },
    "analytics_tracking": {
        "mrr_calculation": true,
        "churn_tracking": true,
        "cohort_analysis": true,
        "predictive_analytics": true
    }
}
```

### Database Tables
- `subscriptions` - Customer subscription records
- `subscription_plans` - Plan definitions and pricing
- `billing_cycles` - Billing period management
- `subscription_invoices` - Invoice generation and tracking
- `payment_attempts` - Payment processing history

## 📚 API Endpoints

### REST API
- `POST /api/v1/subscriptions` - Create new subscription
- `GET /api/v1/subscriptions/{id}` - Get subscription details
- `PUT /api/v1/subscriptions/{id}/plan` - Change subscription plan
- `POST /api/v1/subscriptions/{id}/cancel` - Cancel subscription
- `POST /api/v1/subscriptions/{id}/pause` - Pause subscription
- `GET /api/v1/subscriptions/analytics` - Get subscription metrics

### Usage Examples

```bash
# Create subscription
curl -X POST /api/v1/subscriptions \
  -H "Content-Type: application/json" \
  -d '{"customer_id": "CUST123", "plan_id": "PREMIUM_MONTHLY"}'

# Change plan
curl -X PUT /api/v1/subscriptions/SUB123/plan \
  -H "Content-Type: application/json" \
  -d '{"new_plan_id": "ENTERPRISE_MONTHLY", "change_timing": "immediate"}'

# Get analytics
curl -X GET /api/v1/subscriptions/analytics \
  -H "Authorization: Bearer {token}"
```

## 🔧 Installation

### Requirements
- PHP 8.3+
- Recurring payment processing capability
- Advanced billing system support
- Analytics infrastructure

### Setup

```bash
# Activate plugin
php cli/plugin.php activate subscription-commerce

# Run migrations
php cli/migrate.php up

# Configure billing engine
php cli/subscription.php setup-billing

# Initialize analytics
php cli/subscription.php setup-analytics
```

## 📖 Documentation

- **Subscription Setup Guide** - Creating and managing subscription plans
- **Billing Configuration** - Setting up recurring billing and dunning
- **Plan Migration** - Handling upgrades, downgrades, and changes
- **Analytics & Metrics** - Understanding subscription business metrics

## 🚀 Production Ready

This plugin is part of the enhanced Shopologic ecosystem and is production-ready with:
- ✅ Comprehensive subscription lifecycle management
- ✅ Cross-plugin integration for complete commerce solution
- ✅ Advanced billing and dunning capabilities
- ✅ Sophisticated analytics and insights
- ✅ Complete testing framework integration
- ✅ Enterprise-grade subscription architecture

---

**Subscription Commerce** - Complete subscription management for Shopologic