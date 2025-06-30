<?php

declare(strict_types=1);
namespace Shopologic\Plugins\SubscriptionCommerce;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\Hook;

/**
 * Subscription Commerce Plugin
 * 
 * Complete subscription management with recurring billing and retention analytics
 */
class SubscriptionCommercePlugin extends AbstractPlugin
{
    private $subscriptionManager;
    private $billingEngine;
    private $dunningManager;
    private $retentionAnalytics;

    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->scheduleBillingJobs();
    }

    private function registerServices(): void
    {
        $this->subscriptionManager = new Services\SubscriptionManager($this->api);
        $this->billingEngine = new Services\BillingEngine($this->api);
        $this->dunningManager = new Services\DunningManager($this->api);
        $this->retentionAnalytics = new Services\RetentionAnalytics($this->api);
    }

    private function registerHooks(): void
    {
        // Subscription lifecycle
        Hook::addAction('subscription.created', [$this, 'onSubscriptionCreated'], 10, 1);
        Hook::addAction('subscription.renewed', [$this, 'onSubscriptionRenewed'], 10, 1);
        Hook::addAction('subscription.cancelled', [$this, 'onSubscriptionCancelled'], 10, 2);
        Hook::addAction('subscription.expired', [$this, 'onSubscriptionExpired'], 10, 1);
        
        // Billing events
        Hook::addAction('billing.succeeded', [$this, 'handleSuccessfulBilling'], 10, 2);
        Hook::addAction('billing.failed', [$this, 'handleFailedBilling'], 10, 2);
        Hook::addAction('payment.method_updated', [$this, 'updateSubscriptionPayment'], 10, 2);
        
        // Product integration
        Hook::addFilter('product.subscription_enabled', [$this, 'enableSubscriptionOptions'], 10, 2);
        Hook::addFilter('product.pricing', [$this, 'addSubscriptionPricing'], 10, 2);
        Hook::addFilter('cart.item_added', [$this, 'validateSubscriptionItem'], 10, 2);
        
        // Customer portal
        Hook::addFilter('customer.dashboard', [$this, 'addSubscriptionSection'], 10, 2);
        Hook::addFilter('customer.account_menu', [$this, 'addSubscriptionMenu'], 10, 1);
        
        // Admin interface
        Hook::addAction('admin.subscriptions.dashboard', [$this, 'subscriptionDashboard'], 10, 1);
        Hook::addFilter('admin.product.form', [$this, 'addSubscriptionSettings'], 10, 2);
    }

    public function onSubscriptionCreated($subscription): void
    {
        // Send welcome email
        $this->sendSubscriptionWelcome($subscription);
        
        // Grant subscription benefits
        $this->grantSubscriptionBenefits($subscription);
        
        // Schedule first billing (if not trial)
        if (!$this->isTrialSubscription($subscription)) {
            $this->billingEngine->scheduleNextBilling($subscription);
        } else {
            $this->scheduleTrialEndReminder($subscription);
        }
        
        // Track acquisition
        $this->retentionAnalytics->trackAcquisition($subscription);
        
        // Update customer segments
        Hook::doAction('customer.segment_update', $subscription->customer_id, ['subscriber' => true]);
    }

    public function onSubscriptionRenewed($subscription): void
    {
        // Update subscription period
        $newPeriodEnd = $this->calculateNextPeriodEnd($subscription);
        $this->subscriptionManager->updatePeriod($subscription->id, $newPeriodEnd);
        
        // Send renewal confirmation
        $this->sendRenewalConfirmation($subscription);
        
        // Track retention metrics
        $this->retentionAnalytics->trackRenewal($subscription);
        
        // Check for loyalty rewards
        $this->checkSubscriptionMilestones($subscription);
    }

    public function onSubscriptionCancelled($subscription, $reason): void
    {
        // Determine cancellation type
        $cancellationType = $this->determineCancellationType($subscription);
        
        if ($cancellationType === 'immediate') {
            $this->revokeSubscriptionBenefits($subscription);
        } else {
            // Schedule benefit revocation at period end
            $this->scheduleEndOfPeriodActions($subscription);
        }
        
        // Send cancellation confirmation
        $this->sendCancellationConfirmation($subscription, $cancellationType);
        
        // Track churn
        $this->retentionAnalytics->trackChurn($subscription, $reason);
        
        // Trigger win-back campaign
        $this->triggerWinBackCampaign($subscription, $reason);
    }

    public function handleSuccessfulBilling($subscription, $invoice): void
    {
        // Update subscription status
        $this->subscriptionManager->updateStatus($subscription->id, 'active');
        
        // Clear any dunning status
        $this->dunningManager->clearDunningStatus($subscription->id);
        
        // Send payment receipt
        $this->sendPaymentReceipt($subscription, $invoice);
        
        // Schedule next billing
        $this->billingEngine->scheduleNextBilling($subscription);
        
        // Award loyalty points if applicable
        Hook::doAction('loyalty.points_for_subscription', $subscription->customer_id, $invoice->amount);
    }

    public function handleFailedBilling($subscription, $attempt): void
    {
        $retryCount = $this->dunningManager->getRetryCount($subscription->id);
        $maxRetries = $this->getConfig('failed_payment_retries', 3);
        
        if ($retryCount < $maxRetries) {
            // Schedule retry
            $nextRetry = $this->dunningManager->scheduleRetry($subscription, $retryCount + 1);
            
            // Send dunning email
            $this->sendDunningEmail($subscription, $retryCount + 1, $nextRetry);
            
            // Update subscription status
            $this->subscriptionManager->updateStatus($subscription->id, 'past_due');
        } else {
            // Max retries reached
            $gracePeriod = $this->getConfig('dunning_grace_period', 7);
            
            if ($this->isWithinGracePeriod($subscription, $gracePeriod)) {
                // Still in grace period
                $this->sendFinalWarning($subscription, $gracePeriod);
            } else {
                // Cancel subscription
                $this->subscriptionManager->cancel($subscription->id, 'payment_failed');
            }
        }
        
        // Track payment failure
        $this->retentionAnalytics->trackPaymentFailure($subscription, $attempt);
    }

    public function enableSubscriptionOptions($enabled, $product): bool
    {
        // Check if product supports subscriptions
        if (isset($product->subscription_enabled) && $product->subscription_enabled) {
            return true;
        }
        
        // Check product category
        $subscriptionCategories = $this->getSubscriptionEnabledCategories();
        if (in_array($product->category_id, $subscriptionCategories)) {
            return true;
        }
        
        return $enabled;
    }

    public function addSubscriptionPricing($pricing, $product): array
    {
        if (!$this->isSubscriptionProduct($product)) {
            return $pricing;
        }
        
        $subscriptionPlans = $this->getProductSubscriptionPlans($product->id);
        
        foreach ($subscriptionPlans as $plan) {
            $pricing['subscription_plans'][] = [
                'id' => $plan->id,
                'name' => $plan->name,
                'interval' => $plan->interval,
                'interval_count' => $plan->interval_count,
                'price' => $plan->price,
                'discount_percentage' => $this->calculateSubscriptionDiscount($product->price, $plan->price),
                'trial_days' => $plan->trial_days,
                'benefits' => $plan->benefits
            ];
        }
        
        return $pricing;
    }

    public function addSubscriptionSection($dashboard, $customer): string
    {
        $subscriptions = $this->subscriptionManager->getCustomerSubscriptions($customer->id);
        
        if (empty($subscriptions)) {
            return $dashboard;
        }
        
        $subscriptionWidget = $this->api->view('subscription/dashboard-widget', [
            'subscriptions' => $subscriptions,
            'upcoming_renewals' => $this->getUpcomingRenewals($customer->id),
            'billing_history' => $this->getBillingHistory($customer->id),
            'saved_amount' => $this->calculateTotalSavings($customer->id),
            'can_pause' => true,
            'can_change_plan' => $this->getConfig('enable_plan_changes', true)
        ]);
        
        return $dashboard . $subscriptionWidget;
    }

    public function subscriptionDashboard(): void
    {
        $metrics = [
            'total_subscribers' => $this->subscriptionManager->getTotalActiveSubscriptions(),
            'mrr' => $this->retentionAnalytics->calculateMRR(),
            'arr' => $this->retentionAnalytics->calculateARR(),
            'churn_rate' => $this->retentionAnalytics->getChurnRate(),
            'ltv' => $this->retentionAnalytics->getAverageLTV(),
            'growth_rate' => $this->retentionAnalytics->getGrowthRate(),
            'retention_cohorts' => $this->retentionAnalytics->getRetentionCohorts(),
            'subscription_distribution' => $this->getSubscriptionDistribution(),
            'revenue_forecast' => $this->retentionAnalytics->forecastRevenue(12),
            'at_risk_subscriptions' => $this->identifyAtRiskSubscriptions()
        ];
        
        echo $this->api->view('subscription/admin-dashboard', $metrics);
    }

    public function addSubscriptionSettings($form, $product): string
    {
        $existingPlans = $this->getProductSubscriptionPlans($product->id);
        
        $subscriptionSettings = $this->api->view('subscription/product-settings', [
            'product' => $product,
            'existing_plans' => $existingPlans,
            'intervals' => ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'],
            'enable_trial' => $this->getConfig('enable_trial_periods', true),
            'default_trial_days' => $this->getConfig('default_trial_days', 14)
        ]);
        
        return $form . $subscriptionSettings;
    }

    private function calculateNextPeriodEnd($subscription): string
    {
        $interval = $subscription->billing_interval;
        $intervalCount = $subscription->billing_interval_count;
        
        $periodEnd = strtotime($subscription->current_period_end);
        
        switch ($interval) {
            case 'day':
                $nextEnd = strtotime("+{$intervalCount} days", $periodEnd);
                break;
            case 'week':
                $nextEnd = strtotime("+{$intervalCount} weeks", $periodEnd);
                break;
            case 'month':
                $nextEnd = strtotime("+{$intervalCount} months", $periodEnd);
                break;
            case 'year':
                $nextEnd = strtotime("+{$intervalCount} years", $periodEnd);
                break;
            default:
                $nextEnd = strtotime("+1 month", $periodEnd);
        }
        
        return date('Y-m-d H:i:s', $nextEnd);
    }

    private function checkSubscriptionMilestones($subscription): void
    {
        $renewalCount = $this->subscriptionManager->getRenewalCount($subscription->id);
        $milestones = [3, 6, 12, 24]; // Months
        
        $monthsActive = $renewalCount * $this->getIntervalInMonths($subscription);
        
        if (in_array($monthsActive, $milestones)) {
            $this->grantMilestoneReward($subscription, $monthsActive);
        }
    }

    private function grantMilestoneReward($subscription, $months): void
    {
        $rewards = [
            3 => ['type' => 'discount', 'value' => 10, 'message' => '3-month subscriber! Enjoy 10% off next renewal'],
            6 => ['type' => 'credit', 'value' => 25, 'message' => '6-month loyalty bonus: $25 store credit'],
            12 => ['type' => 'free_month', 'value' => 1, 'message' => 'Happy 1 year! Your next month is on us'],
            24 => ['type' => 'vip_status', 'value' => true, 'message' => '2-year VIP status unlocked!']
        ];
        
        if (isset($rewards[$months])) {
            $reward = $rewards[$months];
            $this->applySubscriptionReward($subscription, $reward);
            
            $this->api->notification()->send($subscription->customer_id, [
                'type' => 'subscription_milestone',
                'title' => '🎉 Subscription Milestone!',
                'message' => $reward['message']
            ]);
        }
    }

    private function identifyAtRiskSubscriptions(): array
    {
        $atRisk = [];
        
        // Recent payment failures
        $failedPayments = $this->dunningManager->getRecentFailures(30);
        foreach ($failedPayments as $subscription) {
            $atRisk[] = [
                'subscription' => $subscription,
                'risk_reason' => 'payment_failures',
                'risk_score' => 0.8
            ];
        }
        
        // Decreased usage
        $lowUsage = $this->retentionAnalytics->getLowUsageSubscriptions();
        foreach ($lowUsage as $subscription) {
            $atRisk[] = [
                'subscription' => $subscription,
                'risk_reason' => 'low_usage',
                'risk_score' => 0.6
            ];
        }
        
        // No recent logins
        $inactive = $this->retentionAnalytics->getInactiveSubscribers(30);
        foreach ($inactive as $subscription) {
            $atRisk[] = [
                'subscription' => $subscription,
                'risk_reason' => 'inactive',
                'risk_score' => 0.7
            ];
        }
        
        return $atRisk;
    }

    private function scheduleBillingJobs(): void
    {
        // Process daily billing
        $this->api->scheduler()->addJob('process_subscription_billing', '0 6 * * *', function() {
            $this->billingEngine->processDailyBilling();
        });
        
        // Process failed payment retries
        $this->api->scheduler()->addJob('retry_failed_payments', '0 */4 * * *', function() {
            $this->dunningManager->processScheduledRetries();
        });
        
        // Send renewal reminders
        $this->api->scheduler()->addJob('send_renewal_reminders', '0 9 * * *', function() {
            $this->sendUpcomingRenewalReminders();
        });
        
        // Update subscription metrics
        $this->api->scheduler()->addJob('update_subscription_metrics', '0 2 * * *', function() {
            $this->retentionAnalytics->updateDailyMetrics();
        });
        
        // Check trial expirations
        $this->api->scheduler()->addJob('check_trial_expirations', '0 10 * * *', function() {
            $this->processTrialExpirations();
        });
    }

    private function sendUpcomingRenewalReminders(): void
    {
        $upcomingRenewals = $this->subscriptionManager->getUpcomingRenewals(7); // 7 days
        
        foreach ($upcomingRenewals as $subscription) {
            $this->api->notification()->send($subscription->customer_id, [
                'type' => 'subscription_renewal_reminder',
                'title' => 'Subscription Renewal Coming Up',
                'message' => "Your {$subscription->plan_name} subscription will renew on " . 
                           date('F j, Y', strtotime($subscription->current_period_end)),
                'email' => true
            ]);
        }
    }

    private function processTrialExpirations(): void
    {
        $expiringTrials = $this->subscriptionManager->getExpiringTrials(1); // Tomorrow
        
        foreach ($expiringTrials as $subscription) {
            // Convert to paid or cancel
            if ($this->hasValidPaymentMethod($subscription)) {
                $this->convertTrialToPaid($subscription);
            } else {
                $this->requestPaymentForTrial($subscription);
            }
        }
    }

    private function registerRoutes(): void
    {
        $this->api->router()->post('/subscriptions/create', 'Controllers\SubscriptionController@create');
        $this->api->router()->get('/subscriptions/{id}', 'Controllers\SubscriptionController@show');
        $this->api->router()->post('/subscriptions/{id}/pause', 'Controllers\SubscriptionController@pause');
        $this->api->router()->post('/subscriptions/{id}/resume', 'Controllers\SubscriptionController@resume');
        $this->api->router()->post('/subscriptions/{id}/cancel', 'Controllers\SubscriptionController@cancel');
        $this->api->router()->post('/subscriptions/{id}/change-plan', 'Controllers\SubscriptionController@changePlan');
        $this->api->router()->get('/subscriptions/customer/{id}', 'Controllers\SubscriptionController@customerSubscriptions');
        
        // Customer portal routes
        $this->api->router()->group(['prefix' => 'account/subscriptions'], function($router) {
            $router->get('/', 'Controllers\CustomerPortalController@index');
            $router->get('/{id}', 'Controllers\CustomerPortalController@show');
            $router->post('/{id}/update-payment', 'Controllers\CustomerPortalController@updatePayment');
            $router->get('/{id}/invoices', 'Controllers\CustomerPortalController@invoices');
        });
    }

    public function install(): void
    {
        $this->runMigrations();
        $this->createDefaultPlans();
        $this->setupBillingConfiguration();
    }

    private function createDefaultPlans(): void
    {
        $defaultPlans = [
            [
                'name' => 'Monthly Basic',
                'interval' => 'month',
                'interval_count' => 1,
                'trial_days' => 14,
                'features' => ['basic_features']
            ],
            [
                'name' => 'Annual Basic',
                'interval' => 'year', 
                'interval_count' => 1,
                'trial_days' => 14,
                'discount' => 20,
                'features' => ['basic_features', 'priority_support']
            ]
        ];

        foreach ($defaultPlans as $plan) {
            $this->api->database()->table('subscription_plans')->insert($plan);
        }
    }

    private function setupBillingConfiguration(): void
    {
        // Configure payment retry schedule
        $retrySchedule = [
            ['attempt' => 1, 'days_after' => 3],
            ['attempt' => 2, 'days_after' => 5],
            ['attempt' => 3, 'days_after' => 7]
        ];
        
        $this->api->config()->set('subscription.retry_schedule', $retrySchedule);
    }

    /**
     * Register Services
     */
    protected function registerServices(): void
    {
        // TODO: Implement registerServices
    }

    /**
     * Register EventListeners
     */
    protected function registerEventListeners(): void
    {
        // TODO: Implement registerEventListeners
    }

    /**
     * Register Hooks
     */
    protected function registerHooks(): void
    {
        // TODO: Implement registerHooks
    }

    /**
     * Register Routes
     */
    protected function registerRoutes(): void
    {
        // TODO: Implement registerRoutes
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