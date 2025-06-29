<?php

namespace AdvancedEmailMarketing;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Hook\HookSystem;
use Shopologic\Core\Container\ContainerInterface;
use Shopologic\Dashboard\WidgetInterface;
use Shopologic\Cron\CronInterface;
use AdvancedEmailMarketing\Services\CampaignServiceInterface;
use AdvancedEmailMarketing\Services\CampaignService;
use AdvancedEmailMarketing\Services\AutomationServiceInterface;
use AdvancedEmailMarketing\Services\AutomationService;
use AdvancedEmailMarketing\Services\PersonalizationServiceInterface;
use AdvancedEmailMarketing\Services\PersonalizationService;
use AdvancedEmailMarketing\Services\DeliverabilityServiceInterface;
use AdvancedEmailMarketing\Services\DeliverabilityService;
use AdvancedEmailMarketing\Services\AnalyticsServiceInterface;
use AdvancedEmailMarketing\Services\AnalyticsService;
use AdvancedEmailMarketing\Repositories\EmailRepositoryInterface;
use AdvancedEmailMarketing\Repositories\EmailRepository;
use AdvancedEmailMarketing\Controllers\EmailApiController;
use AdvancedEmailMarketing\Jobs\SendEmailJob;

/**
 * Advanced Email Marketing Automation Plugin
 * 
 * Comprehensive email marketing solution with behavioral triggers, AI personalization,
 * automated workflows, and advanced deliverability optimization
 */
class AdvancedEmailMarketingPlugin extends AbstractPlugin implements WidgetInterface, CronInterface
{
    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerApiEndpoints();
        $this->registerCronJobs();
        $this->registerPermissions();
        $this->registerWidgets();
    }

    protected function registerServices(): void
    {
        $this->container->bind(CampaignServiceInterface::class, CampaignService::class);
        $this->container->bind(AutomationServiceInterface::class, AutomationService::class);
        $this->container->bind(PersonalizationServiceInterface::class, PersonalizationService::class);
        $this->container->bind(DeliverabilityServiceInterface::class, DeliverabilityService::class);
        $this->container->bind(AnalyticsServiceInterface::class, AnalyticsService::class);
        $this->container->bind(EmailRepositoryInterface::class, EmailRepository::class);

        $this->container->singleton(CampaignService::class, function(ContainerInterface $container) {
            return new CampaignService(
                $container->get(EmailRepositoryInterface::class),
                $container->get('events'),
                $container->get('cache'),
                $this->getConfig()
            );
        });

        $this->container->singleton(AutomationService::class, function(ContainerInterface $container) {
            return new AutomationService(
                $container->get('database'),
                $container->get(CampaignServiceInterface::class),
                $this->getConfig('automation', [])
            );
        });

        $this->container->singleton(PersonalizationService::class, function(ContainerInterface $container) {
            return new PersonalizationService(
                $container->get('database'),
                $container->get('cache'),
                $this->getConfig('personalization', [])
            );
        });

        $this->container->singleton(DeliverabilityService::class, function(ContainerInterface $container) {
            return new DeliverabilityService(
                $container->get('database'),
                $container->get('events'),
                $this->getConfig('deliverability', [])
            );
        });

        $this->container->singleton(AnalyticsService::class, function(ContainerInterface $container) {
            return new AnalyticsService(
                $container->get('database'),
                $container->get('cache'),
                $this->getConfig('analytics', [])
            );
        });
    }

    protected function registerHooks(): void
    {
        // Customer lifecycle triggers
        HookSystem::addAction('customer.registered', [$this, 'triggerWelcomeSequence'], 5);
        HookSystem::addAction('customer.birthday', [$this, 'triggerBirthdayEmail'], 5);
        HookSystem::addAction('customer.anniversary', [$this, 'triggerAnniversaryEmail'], 5);
        
        // Purchase behavior triggers
        HookSystem::addAction('order.completed', [$this, 'triggerPostPurchaseSequence'], 5);
        HookSystem::addAction('order.shipped', [$this, 'triggerShippingNotification'], 5);
        HookSystem::addAction('order.delivered', [$this, 'triggerDeliveryConfirmation'], 5);
        
        // Abandonment triggers
        HookSystem::addAction('cart.abandoned', [$this, 'triggerCartAbandonmentSequence'], 5);
        HookSystem::addAction('browse.abandoned', [$this, 'triggerBrowseAbandonmentEmail'], 5);
        HookSystem::addAction('checkout.abandoned', [$this, 'triggerCheckoutAbandonmentEmail'], 5);
        
        // Engagement triggers
        HookSystem::addAction('product.viewed', [$this, 'trackProductInterest'], 10);
        HookSystem::addAction('product.wishlisted', [$this, 'triggerWishlistReminder'], 5);
        HookSystem::addAction('email.clicked', [$this, 'trackEmailEngagement'], 10);
        HookSystem::addAction('email.opened', [$this, 'trackEmailEngagement'], 10);
        
        // Re-engagement triggers
        HookSystem::addAction('customer.inactive', [$this, 'triggerReengagementCampaign'], 5);
        HookSystem::addAction('subscription.expiring', [$this, 'triggerRenewalEmail'], 5);
        
        // Personalization hooks
        HookSystem::addFilter('email.content', [$this, 'personalizeEmailContent'], 10);
        HookSystem::addFilter('email.subject_line', [$this, 'personalizeSubjectLine'], 10);
        HookSystem::addFilter('email.product_recommendations', [$this, 'addPersonalizedRecommendations'], 10);
        
        // Deliverability optimization
        HookSystem::addAction('email.before_send', [$this, 'optimizeDeliverability'], 5);
        HookSystem::addAction('email.bounced', [$this, 'handleEmailBounce'], 5);
        HookSystem::addAction('email.complained', [$this, 'handleSpamComplaint'], 5);
        
        // A/B testing
        HookSystem::addFilter('email.ab_variant', [$this, 'selectAbTestVariant'], 5);
        HookSystem::addAction('email.ab_result', [$this, 'trackAbTestResult'], 10);
    }

    protected function registerApiEndpoints(): void
    {
        $this->router->group(['prefix' => 'api/v1/email'], function($router) {
            // Campaign management
            $router->get('/campaigns', [EmailApiController::class, 'getCampaigns']);
            $router->post('/campaigns', [EmailApiController::class, 'createCampaign']);
            $router->get('/campaigns/{campaign_id}', [EmailApiController::class, 'getCampaign']);
            $router->put('/campaigns/{campaign_id}', [EmailApiController::class, 'updateCampaign']);
            $router->delete('/campaigns/{campaign_id}', [EmailApiController::class, 'deleteCampaign']);
            
            // Email sending
            $router->post('/send', [EmailApiController::class, 'sendEmail']);
            $router->post('/send-bulk', [EmailApiController::class, 'sendBulkEmail']);
            $router->post('/test', [EmailApiController::class, 'sendTestEmail']);
            $router->post('/preview', [EmailApiController::class, 'previewEmail']);
            
            // Templates
            $router->get('/templates', [EmailApiController::class, 'getTemplates']);
            $router->post('/templates', [EmailApiController::class, 'createTemplate']);
            $router->put('/templates/{template_id}', [EmailApiController::class, 'updateTemplate']);
            
            // Automation workflows
            $router->get('/workflows', [EmailApiController::class, 'getWorkflows']);
            $router->post('/workflows', [EmailApiController::class, 'createWorkflow']);
            $router->put('/workflows/{workflow_id}/activate', [EmailApiController::class, 'activateWorkflow']);
            $router->put('/workflows/{workflow_id}/pause', [EmailApiController::class, 'pauseWorkflow']);
            
            // Subscriber management
            $router->get('/subscribers', [EmailApiController::class, 'getSubscribers']);
            $router->post('/subscribers', [EmailApiController::class, 'addSubscriber']);
            $router->put('/subscribers/{subscriber_id}', [EmailApiController::class, 'updateSubscriber']);
            $router->delete('/subscribers/{subscriber_id}', [EmailApiController::class, 'removeSubscriber']);
            
            // Lists and segments
            $router->get('/lists', [EmailApiController::class, 'getLists']);
            $router->post('/lists', [EmailApiController::class, 'createList']);
            $router->get('/segments', [EmailApiController::class, 'getSegments']);
            $router->post('/segments', [EmailApiController::class, 'createSegment']);
            
            // Analytics
            $router->get('/analytics/overview', [EmailApiController::class, 'getAnalyticsOverview']);
            $router->get('/analytics/campaign/{campaign_id}', [EmailApiController::class, 'getCampaignAnalytics']);
            $router->get('/analytics/deliverability', [EmailApiController::class, 'getDeliverabilityMetrics']);
            $router->get('/analytics/engagement', [EmailApiController::class, 'getEngagementMetrics']);
        });

        // GraphQL schema extension
        $this->graphql->extendSchema([
            'Query' => [
                'emailCampaigns' => [
                    'type' => '[EmailCampaign]',
                    'args' => ['status' => 'String', 'limit' => 'Int'],
                    'resolve' => [$this, 'resolveEmailCampaigns']
                ],
                'emailAnalytics' => [
                    'type' => 'EmailAnalytics',
                    'args' => ['campaignId' => 'ID', 'period' => 'String'],
                    'resolve' => [$this, 'resolveEmailAnalytics']
                ],
                'emailSubscribers' => [
                    'type' => '[EmailSubscriber]',
                    'args' => ['listId' => 'ID', 'segmentId' => 'ID'],
                    'resolve' => [$this, 'resolveEmailSubscribers']
                ]
            ]
        ]);
    }

    protected function registerCronJobs(): void
    {
        // Process email queue every 5 minutes
        $this->cron->schedule('*/5 * * * *', [$this, 'processEmailQueue']);
        
        // Process automated campaigns hourly
        $this->cron->schedule('0 * * * *', [$this, 'processAutomatedCampaigns']);
        
        // Optimize deliverability every 6 hours
        $this->cron->schedule('0 */6 * * *', [$this, 'optimizeDeliverability']);
        
        // Generate email reports daily
        $this->cron->schedule('0 2 * * *', [$this, 'generateEmailReports']);
        
        // Clean up old email data weekly
        $this->cron->schedule('0 3 * * SUN', [$this, 'cleanupOldEmailData']);
    }

    public function getDashboardWidget(): array
    {
        return [
            'id' => 'advanced-email-marketing-widget',
            'title' => 'Email Marketing',
            'position' => 'sidebar',
            'priority' => 20,
            'render' => [$this, 'renderEmailDashboard']
        ];
    }

    protected function registerPermissions(): void
    {
        $this->permissions->register([
            'email.campaigns.view' => 'View email campaigns',
            'email.campaigns.create' => 'Create email campaigns',
            'email.campaigns.send' => 'Send email campaigns',
            'email.templates.manage' => 'Manage email templates',
            'email.subscribers.manage' => 'Manage email subscribers',
            'email.analytics.view' => 'View email analytics',
            'email.workflows.manage' => 'Manage email workflows'
        ]);
    }

    // Hook Implementations

    public function triggerWelcomeSequence(array $data): void
    {
        $customer = $data['customer'];
        $automationService = $this->container->get(AutomationServiceInterface::class);
        
        // Start welcome email sequence
        $automationService->enrollCustomerInWorkflow($customer->id, 'welcome_sequence', [
            'trigger_event' => 'customer_registered',
            'customer_data' => [
                'name' => $customer->name,
                'email' => $customer->email,
                'signup_date' => now(),
                'source' => $data['source'] ?? 'website'
            ]
        ]);
        
        // Track conversion from email signup
        $this->trackEmailConversion('signup', $customer->id);
    }

    public function triggerCartAbandonmentSequence(array $data): void
    {
        $cart = $data['cart'];
        $customer = $data['customer'];
        
        $automationService = $this->container->get(AutomationServiceInterface::class);
        
        // Enroll in cart abandonment workflow
        $automationService->enrollCustomerInWorkflow($customer->id, 'cart_abandonment', [
            'cart_data' => [
                'cart_id' => $cart->id,
                'cart_total' => $cart->total,
                'items' => $cart->items,
                'abandoned_at' => now()
            ],
            'delay_schedule' => [
                ['delay' => '1 hour', 'template' => 'cart_abandonment_1'],
                ['delay' => '24 hours', 'template' => 'cart_abandonment_2'],
                ['delay' => '72 hours', 'template' => 'cart_abandonment_3']
            ]
        ]);
    }

    public function personalizeEmailContent(string $content, array $data): string
    {
        $customer = $data['customer'] ?? null;
        $campaign = $data['campaign'] ?? null;
        
        if (!$customer) {
            return $content;
        }
        
        $personalizationService = $this->container->get(PersonalizationServiceInterface::class);
        
        // Get customer profile for personalization
        $customerProfile = $personalizationService->getCustomerProfile($customer->id);
        
        // Apply dynamic content blocks
        $personalizedContent = $personalizationService->applyPersonalization($content, [
            'customer' => $customer,
            'profile' => $customerProfile,
            'preferences' => $this->getCustomerPreferences($customer->id),
            'purchase_history' => $this->getCustomerPurchaseHistory($customer->id),
            'browsing_behavior' => $this->getCustomerBrowsingBehavior($customer->id)
        ]);
        
        // Add personalized product recommendations
        if (strpos($content, '{{PRODUCT_RECOMMENDATIONS}}') !== false) {
            $recommendations = $personalizationService->getProductRecommendations($customer->id, 4);
            $recommendationsHtml = $this->renderProductRecommendations($recommendations);
            $personalizedContent = str_replace('{{PRODUCT_RECOMMENDATIONS}}', $recommendationsHtml, $personalizedContent);
        }
        
        // Add dynamic pricing based on customer segment
        if (strpos($content, '{{PERSONALIZED_OFFERS}}') !== false) {
            $offers = $personalizationService->getPersonalizedOffers($customer->id);
            $offersHtml = $this->renderPersonalizedOffers($offers);
            $personalizedContent = str_replace('{{PERSONALIZED_OFFERS}}', $offersHtml, $personalizedContent);
        }
        
        return $personalizedContent;
    }

    public function optimizeDeliverability(array $data): void
    {
        $email = $data['email'] ?? null;
        $recipient = $data['recipient'] ?? null;
        
        $deliverabilityService = $this->container->get(DeliverabilityServiceInterface::class);
        
        if ($email && $recipient) {
            // Check sender reputation
            $senderScore = $deliverabilityService->getSenderReputationScore();
            
            // Optimize send time
            $optimalTime = $deliverabilityService->getOptimalSendTime($recipient);
            
            // Check content for spam triggers
            $spamScore = $deliverabilityService->analyzeSpamRisk($email['content']);
            
            // Domain-based optimization
            $domainReputation = $deliverabilityService->getDomainReputation($recipient);
            
            // Apply optimizations
            if ($spamScore > 5) {
                $this->postponeEmail($email['id'], 'High spam score detected');
                return;
            }
            
            if ($senderScore < 70) {
                $this->applySenderOptimizations($email);
            }
            
            // Schedule for optimal delivery time
            if ($optimalTime && $optimalTime !== 'now') {
                $this->rescheduleEmail($email['id'], $optimalTime);
            }
        }
    }

    public function handleEmailBounce(array $data): void
    {
        $email = $data['email'];
        $bounceType = $data['bounce_type']; // hard, soft, complaint
        $recipient = $data['recipient'];
        
        $deliverabilityService = $this->container->get(DeliverabilityServiceInterface::class);
        
        // Record bounce
        $deliverabilityService->recordBounce($email['id'], $recipient, $bounceType, $data['bounce_reason'] ?? '');
        
        // Handle based on bounce type
        switch ($bounceType) {
            case 'hard':
                // Permanently suppress email
                $this->suppressEmail($recipient, 'hard_bounce');
                $this->updateCustomerEmailStatus($recipient, 'invalid');
                break;
                
            case 'soft':
                // Temporary suppression with retry logic
                $this->temporarySuppression($recipient, $data['retry_after'] ?? '24 hours');
                break;
                
            case 'complaint':
                // Handle spam complaint
                $this->suppressEmail($recipient, 'spam_complaint');
                $this->updateCustomerEmailStatus($recipient, 'complained');
                $this->notifyComplianceTeam($recipient, $data);
                break;
        }
        
        // Update sender reputation
        $deliverabilityService->updateSenderReputation($bounceType);
    }

    public function selectAbTestVariant(string $defaultVariant, array $data): string
    {
        $campaign = $data['campaign'];
        $customer = $data['customer'];
        
        // Check if campaign has A/B test configured
        if (!isset($campaign['ab_test']) || !$campaign['ab_test']['enabled']) {
            return $defaultVariant;
        }
        
        // Assign customer to test variant
        $variantAssignment = $this->assignAbTestVariant($campaign['ab_test'], $customer->id);
        
        // Track assignment
        $this->trackAbTestAssignment($campaign['id'], $customer->id, $variantAssignment);
        
        return $variantAssignment;
    }

    public function trackEmailEngagement(array $data): void
    {
        $emailId = $data['email_id'];
        $customerId = $data['customer_id'];
        $eventType = $data['event_type']; // opened, clicked, converted
        
        $analyticsService = $this->container->get(AnalyticsServiceInterface::class);
        
        // Record engagement event
        $analyticsService->recordEngagement($emailId, $customerId, $eventType, [
            'timestamp' => now(),
            'user_agent' => $data['user_agent'] ?? '',
            'ip_address' => $data['ip_address'] ?? '',
            'link_clicked' => $data['link_clicked'] ?? null
        ]);
        
        // Update customer engagement score
        $this->updateCustomerEngagementScore($customerId, $eventType);
        
        // Trigger follow-up actions
        if ($eventType === 'clicked') {
            $this->triggerClickBasedActions($customerId, $data);
        }
    }

    // Cron Job Implementations

    public function processEmailQueue(): void
    {
        $campaignService = $this->container->get(CampaignServiceInterface::class);
        
        // Get emails ready to send
        $queuedEmails = $campaignService->getQueuedEmails([
            'scheduled_before' => now(),
            'status' => 'queued',
            'limit' => 100
        ]);
        
        foreach ($queuedEmails as $email) {
            try {
                // Dispatch email job
                $this->jobs->dispatch(new SendEmailJob($email));
                
                // Update status
                $campaignService->updateEmailStatus($email->id, 'sending');
            } catch (\Exception $e) {
                $this->logger->error('Email queue processing failed', [
                    'email_id' => $email->id,
                    'error' => $e->getMessage()
                ]);
                
                $campaignService->updateEmailStatus($email->id, 'failed');
            }
        }
        
        $this->logger->info('Email queue processed', ['emails_queued' => count($queuedEmails)]);
    }

    public function processAutomatedCampaigns(): void
    {
        $automationService = $this->container->get(AutomationServiceInterface::class);
        
        // Get active automation workflows
        $activeWorkflows = $automationService->getActiveWorkflows();
        
        foreach ($activeWorkflows as $workflow) {
            // Process workflow triggers
            $triggers = $automationService->getWorkflowTriggers($workflow->id);
            
            foreach ($triggers as $trigger) {
                $this->processWorkflowTrigger($workflow, $trigger);
            }
        }
        
        $this->logger->info('Automated campaigns processed', [
            'workflows_processed' => count($activeWorkflows)
        ]);
    }

    public function generateEmailReports(): void
    {
        $analyticsService = $this->container->get(AnalyticsServiceInterface::class);
        
        // Generate daily email performance report
        $report = $analyticsService->generateDailyReport([
            'date' => now()->subDay()->toDateString(),
            'metrics' => [
                'emails_sent',
                'delivery_rate',
                'open_rate',
                'click_rate',
                'conversion_rate',
                'unsubscribe_rate',
                'bounce_rate'
            ]
        ]);
        
        // Store report
        $this->storeEmailReport($report);
        
        // Send to stakeholders if significant changes
        if ($this->hasSignificantChanges($report)) {
            $this->sendReportToStakeholders($report);
        }
        
        $this->logger->info('Email performance report generated');
    }

    // Widget and Dashboard

    public function renderEmailDashboard(): string
    {
        $campaignService = $this->container->get(CampaignServiceInterface::class);
        $analyticsService = $this->container->get(AnalyticsServiceInterface::class);
        
        $data = [
            'campaigns_active' => $campaignService->getActiveCampaignCount(),
            'emails_sent_today' => $analyticsService->getEmailsSentToday(),
            'open_rate_avg' => $analyticsService->getAverageOpenRate('7d'),
            'click_rate_avg' => $analyticsService->getAverageClickRate('7d'),
            'recent_campaigns' => $campaignService->getRecentCampaigns(5),
            'top_performing' => $analyticsService->getTopPerformingCampaigns(3)
        ];
        
        return view('advanced-email-marketing::widgets.dashboard', $data);
    }

    // Helper Methods

    private function assignAbTestVariant(array $abTest, int $customerId): string
    {
        $variants = $abTest['variants'];
        $trafficSplit = $abTest['traffic_split'];
        
        // Use consistent hash to ensure same customer gets same variant
        $hash = md5($customerId . $abTest['id']);
        $hashInt = hexdec(substr($hash, 0, 8));
        $percentage = ($hashInt % 100) + 1;
        
        $cumulativePercentage = 0;
        foreach ($variants as $variant => $split) {
            $cumulativePercentage += $split;
            if ($percentage <= $cumulativePercentage) {
                return $variant;
            }
        }
        
        return array_keys($variants)[0]; // Fallback to first variant
    }

    private function renderProductRecommendations(array $products): string
    {
        $html = '<div class="product-recommendations">';
        
        foreach ($products as $product) {
            $html .= '<div class="recommended-product">';
            $html .= '<img src="' . $product->image . '" alt="' . $product->name . '">';
            $html .= '<h3>' . $product->name . '</h3>';
            $html .= '<p class="price">$' . number_format($product->price, 2) . '</p>';
            $html .= '<a href="' . $product->url . '" class="cta-button">Shop Now</a>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }

    private function getConfig(string $key = null, $default = null)
    {
        $config = [
            'automation' => [
                'enabled' => true,
                'max_emails_per_hour' => 1000,
                'retry_failed_emails' => true,
                'retry_attempts' => 3
            ],
            'personalization' => [
                'enabled' => true,
                'fallback_content' => true,
                'recommendation_engine' => 'collaborative_filtering'
            ],
            'deliverability' => [
                'sender_reputation_threshold' => 70,
                'spam_score_threshold' => 5,
                'bounce_rate_threshold' => 0.05
            ],
            'analytics' => [
                'track_opens' => true,
                'track_clicks' => true,
                'track_conversions' => true,
                'retention_days' => 365
            ],
            'providers' => [
                'default' => 'smtp',
                'backup' => 'sendgrid'
            ]
        ];
        
        return $key ? ($config[$key] ?? $default) : $config;
    }
}