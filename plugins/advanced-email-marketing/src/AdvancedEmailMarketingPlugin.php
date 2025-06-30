<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\PluginInterface;
use Shopologic\Core\Plugin\Hook;
use Shopologic\Core\Container\ContainerInterface;
use AdvancedEmailMarketing\Services\{
    EmailMarketingManager,
    AutomationEngine,
    SegmentationService,
    DeliverabilityManager,
    PersonalizationEngine,
    TemplateManager,
    AnalyticsService,
    SubscriberManager,;
    CampaignManager,;
    EmailSender;
};
use AdvancedEmailMarketing\Repositories\{
    SubscriberRepository,
    CampaignRepository,
    TemplateRepository,
    SegmentRepository,
    AutomationRepository,;
    EmailSendRepository,;
    AnalyticsRepository;
};
use AdvancedEmailMarketing\Controllers\{
    CampaignController,
    AutomationController,
    SegmentController,
    TemplateController,
    AnalyticsController,
    DeliverabilityController,;
    SubscriberController,;
    WebhookController;
};

class AdvancedEmailMarketingPlugin extends AbstractPlugin implements PluginInterface
{
    protected string $name = 'advanced-email-marketing';
    protected string $version = '1.0.0';
    protected string $description = 'Advanced email marketing automation platform';
    protected string $author = 'Shopologic Team';
    protected array $dependencies = ['shopologic/commerce', 'shopologic/customers', 'shopologic/analytics'];

    private EmailMarketingManager $emailMarketingManager;
    private AutomationEngine $automationEngine;
    private SegmentationService $segmentationService;
    private DeliverabilityManager $deliverabilityManager;
    private PersonalizationEngine $personalizationEngine;
    private TemplateManager $templateManager;
    private AnalyticsService $analyticsService;
    private SubscriberManager $subscriberManager;
    private CampaignManager $campaignManager;
    private EmailSender $emailSender;

    /**
     * Plugin installation
     */
    public function install(): void
    {
        // Run database migrations
        $this->runMigrations();
        
        // Create default email templates
        $this->createDefaultTemplates();
        
        // Setup default automation workflows
        $this->setupDefaultAutomations();
        
        // Create default segments
        $this->createDefaultSegments();
        
        // Set default configuration
        $this->setDefaultConfiguration();
        
        // Create necessary directories
        $this->createDirectories();
        
        // Initialize email providers
        $this->initializeEmailProviders();
        
        // Setup tracking infrastructure
        $this->setupEmailTracking();
    }

    /**
     * Plugin activation
     */
    public function activate(): void
    {
        // Register services
        $this->registerServices();
        
        // Register hooks and filters
        $this->registerHooks();
        
        // Register API routes
        $this->registerRoutes();
        
        // Schedule background tasks
        $this->scheduleBackgroundTasks();
        
        // Initialize automation engine
        $this->initializeAutomationEngine();
        
        // Setup deliverability monitoring
        $this->setupDeliverabilityMonitoring();
        
        // Initialize personalization engine
        $this->initializePersonalizationEngine();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        // Pause all active automations
        $this->pauseAutomations();
        
        // Unschedule background tasks
        $this->unscheduleBackgroundTasks();
        
        // Clear email queue
        $this->clearEmailQueue();
        
        // Save current state
        $this->saveCurrentState();
    }

    /**
     * Plugin uninstallation
     */
    public function uninstall(): void
    {
        // Note: Database cleanup is optional and user-configurable
        if ($this->getConfig('cleanup_on_uninstall', false)) {
            $this->cleanupDatabase();
        }
        
        // Remove configuration
        $this->removeConfiguration();
        
        // Clean up files
        $this->cleanupFiles();
        
        // Remove email templates
        $this->cleanupTemplates();
        
        // Clear tracking data
        $this->cleanupTrackingData();
    }

    /**
     * Plugin update
     */
    public function update(string $previousVersion): void
    {
        // Run version-specific updates
        if (version_compare($previousVersion, '1.0.0', '<')) {
            $this->updateTo100();
        }
        
        // Update database schema if needed
        $this->runMigrations();
        
        // Update configuration schema
        $this->updateConfiguration();
        
        // Migrate existing email data
        $this->migrateEmailData($previousVersion);
        
        // Update email templates
        $this->updateEmailTemplates();
    }

    /**
     * Plugin boot - called when plugin is loaded
     */
    public function boot(): void
    {
        // Initialize core services
        $this->initializeServices();
        
        // Register event listeners
        $this->registerEventListeners();
        
        // Load plugin configuration
        $this->loadConfiguration();
        
        // Initialize email tracking
        $this->initializeEmailTracking();
        
        // Setup automation triggers
        $this->setupAutomationTriggers();
    }

    /**
     * Register services with the container
     */
    protected function registerServices(): void
    {
        $container = $this->getContainer();
        
        // Register repositories
        $container->singleton(SubscriberRepository::class);
        $container->singleton(CampaignRepository::class);
        $container->singleton(TemplateRepository::class);
        $container->singleton(SegmentRepository::class);
        $container->singleton(AutomationRepository::class);
        $container->singleton(EmailSendRepository::class);
        $container->singleton(AnalyticsRepository::class);
        
        // Register core services
        $container->singleton(SubscriberManager::class, function ($container) {
            return new SubscriberManager(
                $container->get(SubscriberRepository::class),
                $this->getConfig('email_settings', [])
            );
        });
        
        $container->singleton(CampaignManager::class, function ($container) {
            return new CampaignManager(
                $container->get(CampaignRepository::class),
                $container->get(TemplateRepository::class),
                $container->get(SegmentRepository::class),
                $this->getConfig('automation_settings', [])
            );
        });
        
        $container->singleton(AutomationEngine::class, function ($container) {
            return new AutomationEngine(
                $container->get(AutomationRepository::class),
                $container->get(SubscriberRepository::class),
                $container->get(EmailSendRepository::class),
                $this->getConfig('automation_settings', [])
            );
        });
        
        $container->singleton(SegmentationService::class, function ($container) {
            return new SegmentationService(
                $container->get(SegmentRepository::class),
                $container->get(SubscriberRepository::class),
                $this->getConfig('segmentation_settings', [])
            );
        });
        
        $container->singleton(PersonalizationEngine::class, function ($container) {
            return new PersonalizationEngine(
                $container->get(SubscriberRepository::class),
                $this->getConfig('personalization_settings', [])
            );
        });
        
        $container->singleton(TemplateManager::class, function ($container) {
            return new TemplateManager(
                $container->get(TemplateRepository::class),
                $container->get(PersonalizationEngine::class)
            );
        });
        
        $container->singleton(EmailSender::class, function ($container) {
            return new EmailSender(
                $container->get(EmailSendRepository::class),
                $this->getConfig('email_settings', [])
            );
        });
        
        $container->singleton(DeliverabilityManager::class, function ($container) {
            return new DeliverabilityManager(
                $container->get(EmailSendRepository::class),
                $container->get(AnalyticsRepository::class),
                $this->getConfig('deliverability_settings', [])
            );
        });
        
        $container->singleton(AnalyticsService::class, function ($container) {
            return new AnalyticsService(
                $container->get(AnalyticsRepository::class),
                $container->get(EmailSendRepository::class),
                $this->getConfig('analytics_settings', [])
            );
        });
        
        $container->singleton(EmailMarketingManager::class, function ($container) {
            return new EmailMarketingManager(
                $container->get(CampaignManager::class),
                $container->get(AutomationEngine::class),
                $container->get(SegmentationService::class),
                $container->get(SubscriberManager::class),
                $container->get(EmailSender::class)
            );
        });
        
        // Register controllers
        $container->singleton(CampaignController::class);
        $container->singleton(AutomationController::class);
        $container->singleton(SegmentController::class);
        $container->singleton(TemplateController::class);
        $container->singleton(AnalyticsController::class);
        $container->singleton(DeliverabilityController::class);
        $container->singleton(SubscriberController::class);
        $container->singleton(WebhookController::class);
    }

    /**
     * Initialize services
     */
    protected function initializeServices(): void
    {
        $container = $this->getContainer();
        
        $this->emailMarketingManager = $container->get(EmailMarketingManager::class);
        $this->automationEngine = $container->get(AutomationEngine::class);
        $this->segmentationService = $container->get(SegmentationService::class);
        $this->deliverabilityManager = $container->get(DeliverabilityManager::class);
        $this->personalizationEngine = $container->get(PersonalizationEngine::class);
        $this->templateManager = $container->get(TemplateManager::class);
        $this->analyticsService = $container->get(AnalyticsService::class);
        $this->subscriberManager = $container->get(SubscriberManager::class);
        $this->campaignManager = $container->get(CampaignManager::class);
        $this->emailSender = $container->get(EmailSender::class);
    }

    /**
     * Register hooks and filters
     */
    protected function registerHooks(): void
    {
        // Customer lifecycle triggers
        Hook::addAction('customer.registered', [$this, 'triggerWelcomeSequence'], 10);
        Hook::addAction('customer.login', [$this, 'trackCustomerActivity'], 10);
        Hook::addAction('customer.profile_updated', [$this, 'updateSubscriberData'], 10);
        Hook::addAction('customer.birthday', [$this, 'triggerBirthdayEmail'], 10);
        Hook::addAction('customer.inactive', [$this, 'triggerReEngagementSequence'], 10);
        
        // E-commerce triggers
        Hook::addAction('order.completed', [$this, 'triggerOrderConfirmation'], 10);
        Hook::addAction('order.shipped', [$this, 'triggerShippingNotification'], 10);
        Hook::addAction('order.delivered', [$this, 'triggerDeliveryConfirmation'], 10);
        Hook::addAction('cart.abandoned', [$this, 'triggerAbandonedCartSequence'], 10);
        Hook::addAction('cart.updated', [$this, 'trackCartActivity'], 10);
        
        // Product interaction triggers
        Hook::addAction('product.viewed', [$this, 'trackBehavioralEvent'], 10);
        Hook::addAction('product.added_to_cart', [$this, 'trackProductInteraction'], 10);
        Hook::addAction('product.added_to_wishlist', [$this, 'trackWishlistActivity'], 10);
        Hook::addAction('product.reviewed', [$this, 'triggerReviewFollowUp'], 10);
        
        // Email personalization filters
        Hook::addFilter('email.template', [$this, 'personalizeEmailContent'], 10);
        Hook::addFilter('email.subject', [$this, 'personalizeSubjectLine'], 10);
        Hook::addFilter('email.send_time', [$this, 'optimizeSendTime'], 10);
        Hook::addFilter('email.content', [$this, 'injectPersonalizedContent'], 10);
        
        // Segmentation filters
        Hook::addFilter('segment.criteria', [$this, 'enhanceSegmentCriteria'], 10);
        Hook::addFilter('subscriber.data', [$this, 'enrichSubscriberData'], 10);
        
        // Admin hooks
        Hook::addAction('admin_menu', [$this, 'registerAdminMenu']);
        Hook::addAction('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        Hook::addAction('admin_footer', [$this, 'addAdminScripts']);
        
        // Frontend hooks
        Hook::addAction('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
        Hook::addAction('wp_footer', [$this, 'addEmailTrackingScripts']);
        Hook::addAction('wp_head', [$this, 'addEmailTrackingPixel']);
        
        // AJAX hooks
        Hook::addAction('wp_ajax_subscribe_email', [$this, 'handleEmailSubscription']);
        Hook::addAction('wp_ajax_nopriv_subscribe_email', [$this, 'handleEmailSubscription']);
        Hook::addAction('wp_ajax_unsubscribe_email', [$this, 'handleEmailUnsubscribe']);
        Hook::addAction('wp_ajax_nopriv_unsubscribe_email', [$this, 'handleEmailUnsubscribe']);
        Hook::addAction('wp_ajax_update_preferences', [$this, 'handlePreferencesUpdate']);
        Hook::addAction('wp_ajax_track_email_open', [$this, 'handleEmailOpenTracking']);
        Hook::addAction('wp_ajax_nopriv_track_email_open', [$this, 'handleEmailOpenTracking']);
        Hook::addAction('wp_ajax_track_email_click', [$this, 'handleEmailClickTracking']);
        Hook::addAction('wp_ajax_nopriv_track_email_click', [$this, 'handleEmailClickTracking']);
    }

    /**
     * Register API routes
     */
    protected function registerRoutes(): void
    {
        // Campaign routes
        $this->registerRoute('GET', '/api/v1/email-marketing/campaigns', 'CampaignController@index');
        $this->registerRoute('POST', '/api/v1/email-marketing/campaigns', 'CampaignController@create');
        $this->registerRoute('PUT', '/api/v1/email-marketing/campaigns/{id}', 'CampaignController@update');
        $this->registerRoute('DELETE', '/api/v1/email-marketing/campaigns/{id}', 'CampaignController@delete');
        $this->registerRoute('POST', '/api/v1/email-marketing/campaigns/{id}/send', 'CampaignController@send');
        $this->registerRoute('POST', '/api/v1/email-marketing/campaigns/{id}/schedule', 'CampaignController@schedule');
        $this->registerRoute('POST', '/api/v1/email-marketing/campaigns/{id}/test', 'CampaignController@sendTest');
        
        // Automation routes
        $this->registerRoute('GET', '/api/v1/email-marketing/automations', 'AutomationController@index');
        $this->registerRoute('POST', '/api/v1/email-marketing/automations', 'AutomationController@create');
        $this->registerRoute('PUT', '/api/v1/email-marketing/automations/{id}', 'AutomationController@update');
        $this->registerRoute('DELETE', '/api/v1/email-marketing/automations/{id}', 'AutomationController@delete');
        $this->registerRoute('POST', '/api/v1/email-marketing/automations/{id}/activate', 'AutomationController@activate');
        $this->registerRoute('POST', '/api/v1/email-marketing/automations/{id}/deactivate', 'AutomationController@deactivate');
        
        // Segment routes
        $this->registerRoute('GET', '/api/v1/email-marketing/segments', 'SegmentController@index');
        $this->registerRoute('POST', '/api/v1/email-marketing/segments', 'SegmentController@create');
        $this->registerRoute('PUT', '/api/v1/email-marketing/segments/{id}', 'SegmentController@update');
        $this->registerRoute('DELETE', '/api/v1/email-marketing/segments/{id}', 'SegmentController@delete');
        $this->registerRoute('POST', '/api/v1/email-marketing/segments/{id}/calculate', 'SegmentController@calculateSegment');
        
        // Template routes
        $this->registerRoute('GET', '/api/v1/email-marketing/templates', 'TemplateController@index');
        $this->registerRoute('POST', '/api/v1/email-marketing/templates', 'TemplateController@create');
        $this->registerRoute('PUT', '/api/v1/email-marketing/templates/{id}', 'TemplateController@update');
        $this->registerRoute('DELETE', '/api/v1/email-marketing/templates/{id}', 'TemplateController@delete');
        $this->registerRoute('POST', '/api/v1/email-marketing/templates/{id}/test', 'TemplateController@sendTest');
        
        // Subscriber routes
        $this->registerRoute('GET', '/api/v1/email-marketing/subscribers', 'SubscriberController@index');
        $this->registerRoute('POST', '/api/v1/email-marketing/subscribers', 'SubscriberController@create');
        $this->registerRoute('PUT', '/api/v1/email-marketing/subscribers/{id}', 'SubscriberController@update');
        $this->registerRoute('DELETE', '/api/v1/email-marketing/subscribers/{id}', 'SubscriberController@unsubscribe');
        
        // Analytics routes
        $this->registerRoute('GET', '/api/v1/email-marketing/analytics', 'AnalyticsController@overview');
        $this->registerRoute('GET', '/api/v1/email-marketing/analytics/campaigns/{id}', 'AnalyticsController@campaignMetrics');
        $this->registerRoute('GET', '/api/v1/email-marketing/analytics/automations/{id}', 'AnalyticsController@automationMetrics');
        
        // Deliverability routes
        $this->registerRoute('GET', '/api/v1/email-marketing/deliverability', 'DeliverabilityController@status');
        $this->registerRoute('POST', '/api/v1/email-marketing/deliverability/test', 'DeliverabilityController@runTests');
        
        // Webhook routes
        $this->registerRoute('POST', '/api/v1/email-marketing/webhooks/bounce', 'WebhookController@handleBounce');
        $this->registerRoute('POST', '/api/v1/email-marketing/webhooks/complaint', 'WebhookController@handleComplaint');
        $this->registerRoute('POST', '/api/v1/email-marketing/webhooks/delivery', 'WebhookController@handleDelivery');
        $this->registerRoute('POST', '/api/v1/email-marketing/webhooks/open', 'WebhookController@handleOpen');
        $this->registerRoute('POST', '/api/v1/email-marketing/webhooks/click', 'WebhookController@handleClick');
    }

    /**
     * Trigger welcome sequence for new customers
     */
    public function triggerWelcomeSequence($customer): void
    {
        if (!$this->getConfig('automation_settings.enabled', true)) {
            return;
        }

        // Find or create subscriber
        $subscriber = $this->subscriberManager->findOrCreateFromCustomer($customer);
        
        // Trigger welcome automation
        $this->automationEngine->triggerAutomation('welcome', $subscriber, [
            'customer_data' => $customer->toArray(),
            'registration_source' => $customer->getRegistrationSource(),
            'trigger_time' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Trigger order confirmation email
     */
    public function triggerOrderConfirmation($order): void
    {
        $customer = $order->getCustomer();
        if (!$customer) {
            return;
        }

        $subscriber = $this->subscriberManager->findByEmail($customer->getEmail());
        if (!$subscriber) {
            return;
        }

        // Send transactional order confirmation
        $this->emailSender->sendTransactionalEmail(
            $subscriber,
            'order_confirmation',
            [
                'order' => $order->toArray(),
                'customer' => $customer->toArray(),
                'order_items' => $order->getItems()
            ]
        );

        // Also trigger post-purchase automation
        $this->automationEngine->triggerAutomation('post_purchase', $subscriber, [
            'order_data' => $order->toArray(),
            'order_value' => $order->getTotal(),
            'trigger_time' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Trigger abandoned cart sequence
     */
    public function triggerAbandonedCartSequence($cart): void
    {
        if (!$this->getConfig('automation_settings.enabled', true)) {
            return;
        }

        $email = $cart->getCustomerEmail();
        if (!$email) {
            return;
        }

        $subscriber = $this->subscriberManager->findByEmail($email);
        if (!$subscriber || !$subscriber->isSubscribed()) {
            return;
        }

        // Trigger abandoned cart automation
        $this->automationEngine->triggerAutomation('abandoned_cart', $subscriber, [
            'cart_data' => $cart->toArray(),
            'cart_value' => $cart->getTotal(),
            'cart_items' => $cart->getItems(),
            'abandoned_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Trigger birthday email
     */
    public function triggerBirthdayEmail($customer): void
    {
        $subscriber = $this->subscriberManager->findByEmail($customer->getEmail());
        if (!$subscriber || !$subscriber->isSubscribed()) {
            return;
        }

        $this->automationEngine->triggerAutomation('birthday', $subscriber, [
            'customer_data' => $customer->toArray(),
            'birthday_date' => $customer->getBirthday(),
            'trigger_time' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Trigger re-engagement sequence for inactive customers
     */
    public function triggerReEngagementSequence($customer): void
    {
        $subscriber = $this->subscriberManager->findByEmail($customer->getEmail());
        if (!$subscriber || !$subscriber->isSubscribed()) {
            return;
        }

        $this->automationEngine->triggerAutomation('win_back', $subscriber, [
            'customer_data' => $customer->toArray(),
            'last_activity' => $customer->getLastActivityAt(),
            'inactive_days' => $customer->getInactiveDays(),
            'trigger_time' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Track behavioral events for segmentation
     */
    public function trackBehavioralEvent($event, $data = []): void
    {
        if (!$this->getConfig('segmentation_settings.behavioral_tracking', true)) {
            return;
        }

        $email = $data['customer_email'] ?? null;
        if (!$email) {
            return;
        }

        $subscriber = $this->subscriberManager->findByEmail($email);
        if (!$subscriber) {
            return;
        }

        // Update subscriber behavior data
        $this->subscriberManager->trackBehavior($subscriber, $event, $data);
        
        // Update engagement score
        $this->subscriberManager->updateEngagementScore($subscriber);
        
        // Check for automation triggers
        $this->automationEngine->checkBehavioralTriggers($subscriber, $event, $data);
    }

    /**
     * Personalize email content
     */
    public function personalizeEmailContent($content, $subscriber = null, $context = []): string
    {
        if (!$subscriber || !$this->getConfig('personalization_settings.enabled', true)) {
            return $content;
        }

        return $this->personalizationEngine->personalizeContent($content, $subscriber, $context);
    }

    /**
     * Personalize subject line
     */
    public function personalizeSubjectLine($subject, $subscriber = null, $context = []): string
    {
        if (!$subscriber || !$this->getConfig('personalization_settings.enabled', true)) {
            return $subject;
        }

        return $this->personalizationEngine->personalizeSubject($subject, $subscriber, $context);
    }

    /**
     * Optimize send time for subscriber
     */
    public function optimizeSendTime($sendTime, $subscriber = null): string
    {
        if (!$subscriber || !$this->getConfig('personalization_settings.send_time_personalization', true)) {
            return $sendTime;
        }

        return $this->personalizationEngine->optimizeSendTime($subscriber, $sendTime);
    }

    /**
     * Scheduled task: Process automation queue
     */
    public function processAutomationQueue(): void
    {
        if (!$this->getConfig('automation_settings.enabled', true)) {
            return;
        }

        $this->automationEngine->processQueue();
    }

    /**
     * Scheduled task: Update segments
     */
    public function updateSegments(): void
    {
        if (!$this->getConfig('segmentation_settings.real_time_updates', true)) {
            return;
        }

        $this->segmentationService->updateDynamicSegments();
    }

    /**
     * Scheduled task: Process scheduled campaigns
     */
    public function processScheduledCampaigns(): void
    {
        $this->campaignManager->processScheduledCampaigns();
    }

    /**
     * Scheduled task: Clean up old data
     */
    public function cleanupOldData(): void
    {
        $retentionDays = $this->getConfig('analytics_settings.data_retention_days', 365);
        
        $this->analyticsService->cleanupOldTrackingData($retentionDays);
        $this->emailSender->cleanupOldSendData($retentionDays);
    }

    /**
     * Scheduled task: Update engagement scores
     */
    public function updateEngagementScores(): void
    {
        $this->subscriberManager->updateAllEngagementScores();
    }

    /**
     * Scheduled task: Process list hygiene
     */
    public function processListHygiene(): void
    {
        if (!$this->getConfig('deliverability_settings.list_hygiene', true)) {
            return;
        }

        $this->deliverabilityManager->processListHygiene();
    }

    /**
     * Scheduled task: Generate weekly reports
     */
    public function generateWeeklyReports(): void
    {
        $this->analyticsService->generateWeeklyReport();
    }

    /**
     * Scheduled task: Check deliverability health
     */
    public function checkDeliverabilityHealth(): void
    {
        if (!$this->getConfig('deliverability_settings.monitoring_enabled', true)) {
            return;
        }

        $this->deliverabilityManager->checkDeliverabilityHealth();
    }

    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        add_menu_page(
            'Email Marketing',
            'Email Marketing',
            'email_marketing.view',
            'email-marketing',
            [$this, 'renderEmailMarketingDashboard'],
            'dashicons-email-alt',
            26
        );
        
        add_submenu_page(
            'email-marketing',
            'Dashboard',
            'Dashboard',
            'email_marketing.view',
            'email-marketing-dashboard',
            [$this, 'renderEmailMarketingDashboard']
        );
        
        add_submenu_page(
            'email-marketing',
            'Campaigns',
            'Campaigns',
            'campaigns.view',
            'email-campaigns',
            [$this, 'renderCampaigns']
        );
        
        add_submenu_page(
            'email-marketing',
            'Automations',
            'Automations',
            'automations.view',
            'email-automations',
            [$this, 'renderAutomations']
        );
        
        add_submenu_page(
            'email-marketing',
            'Segments',
            'Segments',
            'segments.view',
            'email-segments',
            [$this, 'renderSegments']
        );
        
        add_submenu_page(
            'email-marketing',
            'Templates',
            'Templates',
            'templates.view',
            'email-templates',
            [$this, 'renderTemplates']
        );
        
        add_submenu_page(
            'email-marketing',
            'Subscribers',
            'Subscribers',
            'email_marketing.view',
            'email-subscribers',
            [$this, 'renderSubscribers']
        );
        
        add_submenu_page(
            'email-marketing',
            'Analytics',
            'Analytics',
            'analytics.email_view',
            'email-analytics',
            [$this, 'renderAnalytics']
        );
        
        add_submenu_page(
            'email-marketing',
            'Deliverability',
            'Deliverability',
            'deliverability.view',
            'email-deliverability',
            [$this, 'renderDeliverability']
        );
    }

    /**
     * Set default configuration
     */
    private function setDefaultConfiguration(): void
    {
        $defaults = [
            'email_settings' => [
                'from_name' => get_option('blogname', 'Your Store'),
                'from_email' => get_option('admin_email', 'noreply@yourstore.com'),
                'reply_to_email' => get_option('admin_email', 'support@yourstore.com'),
                'smtp_provider' => 'sendgrid',
                'tracking_enabled' => true,
                'double_opt_in' => true
            ],
            'automation_settings' => [
                'enabled' => true,
                'processing_interval' => '5_minutes',
                'max_sends_per_hour' => 1000,
                'send_time_optimization' => true,
                'timezone_detection' => true
            ],
            'segmentation_settings' => [
                'enabled' => true,
                'real_time_updates' => true,
                'max_segment_size' => 50000,
                'behavioral_tracking' => true
            ],
            'personalization_settings' => [
                'enabled' => true,
                'dynamic_content' => true,
                'product_recommendations' => true,
                'ai_subject_optimization' => false,
                'send_time_personalization' => true
            ],
            'deliverability_settings' => [
                'monitoring_enabled' => true,
                'reputation_tracking' => true,
                'bounce_management' => true,
                'spam_score_checking' => true,
                'list_hygiene' => true
            ],
            'ab_testing' => [
                'enabled' => true,
                'min_sample_size' => 100,
                'confidence_level' => '95',
                'auto_winner_selection' => false
            ],
            'analytics_settings' => [
                'detailed_tracking' => true,
                'click_tracking' => true,
                'open_tracking' => true,
                'revenue_attribution' => true,
                'engagement_scoring' => true
            ]
        ];
        
        foreach ($defaults as $key => $value) {
            if (!$this->hasConfig($key)) {
                $this->setConfig($key, $value);
            }
        }
    }

    /**
     * Create necessary directories
     */
    private function createDirectories(): void
    {
        $dirs = [
            $this->getPluginPath() . '/templates',
            $this->getPluginPath() . '/uploads',
            $this->getPluginPath() . '/logs',
            $this->getPluginPath() . '/cache',
            $this->getPluginPath() . '/exports'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
        }
    }

    /**
     * Register EventListeners
     */
    protected function registerEventListeners(): void
    {
        // TODO: Implement registerEventListeners
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