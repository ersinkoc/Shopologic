<?php
declare(strict_types=1);

namespace EmailMarketing;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\PluginInterface;
use Shopologic\Core\Hook\HookSystem;
use EmailMarketing\Services\CampaignService;
use EmailMarketing\Services\SubscriberService;
use EmailMarketing\Services\AutomationService;
use EmailMarketing\Services\TemplateService;

/**
 * Email Marketing Hub Plugin
 * 
 * Comprehensive email marketing solution with campaign management, automation workflows,
 * abandoned cart recovery, and integration with popular email services
 */
class EmailMarketingPlugin extends AbstractPlugin implements PluginInterface
{
    protected string $name = 'email-marketing';
    protected string $version = '1.0.0';
    
    /**
     * Plugin installation
     */
    public function install(): bool
    {
        $this->runMigrations();
        $this->setDefaultConfig();
        $this->createDefaultTemplates();
        return true;
    }
    
    /**
     * Plugin activation
     */
    public function activate(): bool
    {
        $this->initializeProvider();
        $this->scheduleAutomations();
        return true;
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate(): bool
    {
        $this->pauseAutomations();
        return true;
    }
    
    /**
     * Plugin uninstall
     */
    public function uninstall(): bool
    {
        if ($this->confirmDataRemoval()) {
            $this->dropTables();
            $this->removeConfig();
            $this->removeTemplates();
        }
        return true;
    }
    
    /**
     * Plugin update
     */
    public function update(string $previousVersion): bool
    {
        $this->runUpdateMigrations($previousVersion);
        $this->updateTemplates($previousVersion);
        return true;
    }
    
    /**
     * Plugin boot
     */
    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->registerCronJobs();
        $this->registerWidgets();
    }
    
    /**
     * Register plugin services
     */
    protected function registerServices(): void
    {
        // Campaign service
        $this->container->singleton(CampaignService::class, function ($container) {
            return new CampaignService(
                $container->get('db'),
                $container->get('events'),
                $this->getConfig()
            );
        });
        
        // Subscriber service
        $this->container->singleton(SubscriberService::class, function ($container) {
            return new SubscriberService(
                $container->get('db'),
                $this->getConfig('provider'),
                $this->getConfig('api_key')
            );
        });
        
        // Automation service
        $this->container->singleton(AutomationService::class, function ($container) {
            return new AutomationService(
                $container->get('db'),
                $container->get(CampaignService::class),
                $this->getConfig()
            );
        });
        
        // Template service
        $this->container->singleton(TemplateService::class, function ($container) {
            return new TemplateService(
                $container->get('db'),
                $this->getConfig()
            );
        });
    }
    
    /**
     * Register plugin hooks
     */
    protected function registerHooks(): void
    {
        // Subscriber management
        HookSystem::addAction('user.registered', [$this, 'handleNewSubscriber'], 20);
        
        // Order events
        HookSystem::addAction('order.completed', [$this, 'handleOrderCompleted'], 30);
        
        // Cart events
        HookSystem::addAction('cart.abandoned', [$this, 'handleAbandonedCart'], 10);
        
        // Product events
        HookSystem::addAction('product.back_in_stock', [$this, 'handleBackInStock'], 20);
        
        // Newsletter signup
        HookSystem::addAction('page.footer', [$this, 'injectNewsletterSignup'], 80);
        
        // Email preferences
        HookSystem::addFilter('customer.preferences', [$this, 'addEmailPreferences'], 10);
        
        // Template hooks
        HookSystem::addFilter('email.template', [$this, 'processEmailTemplate'], 10);
    }
    
    /**
     * Register API routes
     */
    protected function registerRoutes(): void
    {
        // Subscriber management
        $this->registerRoute('POST', '/api/v1/email/subscribe', 
            'EmailMarketing\\Controllers\\SubscriberController@subscribe');
        $this->registerRoute('POST', '/api/v1/email/unsubscribe', 
            'EmailMarketing\\Controllers\\SubscriberController@unsubscribe');
        
        // Campaign management
        $this->registerRoute('GET', '/api/v1/email/campaigns', 
            'EmailMarketing\\Controllers\\CampaignController@index');
        $this->registerRoute('POST', '/api/v1/email/campaigns', 
            'EmailMarketing\\Controllers\\CampaignController@create');
        $this->registerRoute('POST', '/api/v1/email/campaigns/{id}/send', 
            'EmailMarketing\\Controllers\\CampaignController@send');
        
        // Automation management
        $this->registerRoute('GET', '/api/v1/email/automations', 
            'EmailMarketing\\Controllers\\AutomationController@index');
        $this->registerRoute('POST', '/api/v1/email/automations', 
            'EmailMarketing\\Controllers\\AutomationController@create');
    }
    
    /**
     * Register cron jobs
     */
    protected function registerCronJobs(): void
    {
        // Process automations every 15 minutes
        $this->scheduleJob('*/15 * * * *', [$this, 'processAutomations']);
        
        // Check abandoned carts hourly
        $this->scheduleJob('0 * * * *', [$this, 'checkAbandonedCarts']);
        
        // Sync subscribers daily at 2 AM
        $this->scheduleJob('0 2 * * *', [$this, 'syncSubscribers']);
        
        // Clean bounces daily at 3 AM
        $this->scheduleJob('0 3 * * *', [$this, 'cleanBounces']);
    }
    
    /**
     * Register dashboard widgets
     */
    protected function registerWidgets(): void
    {
        $this->registerWidget('email_stats', Widgets\EmailStatsWidget::class);
        $this->registerWidget('subscriber_growth', Widgets\SubscriberGrowthWidget::class);
    }
    
    /**
     * Handle new subscriber registration
     */
    public function handleNewSubscriber(array $data): void
    {
        $user = $data['user'];
        $subscriberService = $this->container->get(SubscriberService::class);
        
        // Add to newsletter if opted in
        if ($data['newsletter_optin'] ?? false) {
            $subscriberService->subscribe([
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'user_id' => $user->id
            ]);
        }
        
        // Send welcome email if enabled
        if ($this->getConfig('welcome_email', true)) {
            $this->sendWelcomeEmail($user);
        }
    }
    
    /**
     * Handle completed order
     */
    public function handleOrderCompleted(array $data): void
    {
        $order = $data['order'];
        $automationService = $this->container->get(AutomationService::class);
        
        // Trigger order completion automation
        $automationService->trigger('order_completed', [
            'order' => $order,
            'customer' => $order->customer
        ]);
        
        // Check if this is first purchase
        if ($this->isFirstPurchase($order->customer_id)) {
            $automationService->trigger('first_purchase', [
                'order' => $order,
                'customer' => $order->customer
            ]);
        }
    }
    
    /**
     * Handle abandoned cart
     */
    public function handleAbandonedCart(array $data): void
    {
        if (!$this->getConfig('abandoned_cart_enabled', true)) {
            return;
        }
        
        $cart = $data['cart'];
        $automationService = $this->container->get(AutomationService::class);
        
        // Schedule abandoned cart email series
        $series = $this->getConfig('abandoned_cart_series', []);
        foreach ($series as $index => $email) {
            $automationService->scheduleEmail('abandoned_cart', [
                'cart' => $cart,
                'customer' => $cart->customer,
                'email_index' => $index,
                'discount_percent' => $email['discount_percent'] ?? 0
            ], $email['delay_hours'] * 3600);
        }
    }
    
    /**
     * Handle back in stock notification
     */
    public function handleBackInStock(array $data): void
    {
        $product = $data['product'];
        $subscriberService = $this->container->get(SubscriberService::class);
        
        // Get subscribers waiting for this product
        $waitingSubscribers = $subscriberService->getBackInStockSubscribers($product->id);
        
        foreach ($waitingSubscribers as $subscriber) {
            $this->sendBackInStockEmail($subscriber, $product);
        }
    }
    
    /**
     * Inject newsletter signup form
     */
    public function injectNewsletterSignup(): void
    {
        if (!$this->shouldShowNewsletterSignup()) {
            return;
        }
        
        $templateService = $this->container->get(TemplateService::class);
        echo $templateService->render('newsletter/signup-form', [
            'incentive' => $this->getNewsletterIncentive(),
            'config' => $this->getConfig()
        ]);
    }
    
    /**
     * Process email automations
     */
    public function processAutomations(): void
    {
        $automationService = $this->container->get(AutomationService::class);
        $processed = $automationService->processScheduledEmails();
        
        $this->logger->info('Processed email automations', ['count' => $processed]);
    }
    
    /**
     * Check for abandoned carts
     */
    public function checkAbandonedCarts(): void
    {
        $automationService = $this->container->get(AutomationService::class);
        $found = $automationService->findAbandonedCarts();
        
        $this->logger->info('Found abandoned carts', ['count' => count($found)]);
        
        foreach ($found as $cart) {
            $this->handleAbandonedCart(['cart' => $cart]);
        }
    }
    
    /**
     * Sync subscribers with external provider
     */
    public function syncSubscribers(): void
    {
        if ($this->getConfig('provider') === 'internal') {
            return;
        }
        
        $subscriberService = $this->container->get(SubscriberService::class);
        $synced = $subscriberService->syncWithProvider();
        
        $this->logger->info('Synced subscribers', ['count' => $synced]);
    }
    
    /**
     * Clean email bounces
     */
    public function cleanBounces(): void
    {
        $subscriberService = $this->container->get(SubscriberService::class);
        $cleaned = $subscriberService->cleanBounces();
        
        $this->logger->info('Cleaned email bounces', ['count' => $cleaned]);
    }
    
    /**
     * Send welcome email
     */
    protected function sendWelcomeEmail($user): void
    {
        $campaignService = $this->container->get(CampaignService::class);
        $templateService = $this->container->get(TemplateService::class);
        
        $template = $this->getConfig('welcome_email_template', 'default');
        $content = $templateService->render("welcome/{$template}", [
            'user' => $user,
            'discount_code' => $this->generateWelcomeDiscount($user)
        ]);
        
        $campaignService->sendEmail([
            'to' => $user->email,
            'subject' => 'Welcome to ' . $this->getConfig('from_name'),
            'content' => $content,
            'template' => 'welcome'
        ]);
    }
    
    /**
     * Check if this is customer's first purchase
     */
    protected function isFirstPurchase(int $customerId): bool
    {
        return $this->api->database()->table('orders')
            ->where('customer_id', $customerId)
            ->where('status', 'completed')
            ->count() === 1;
    }
    
    /**
     * Check if should show newsletter signup
     */
    protected function shouldShowNewsletterSignup(): bool
    {
        // Don't show if user is already subscribed
        $user = $this->api->getCurrentUser();
        if ($user) {
            $subscriberService = $this->container->get(SubscriberService::class);
            return !$subscriberService->isSubscribed($user->email);
        }
        
        return true;
    }
    
    /**
     * Get newsletter signup incentive
     */
    protected function getNewsletterIncentive(): ?array
    {
        $incentiveType = $this->getConfig('newsletter_signup_incentive', 'none');
        
        if ($incentiveType === 'none') {
            return null;
        }
        
        return [
            'type' => $incentiveType,
            'amount' => $this->getConfig('incentive_amount', 10),
            'code' => $this->generateIncentiveCode()
        ];
    }
    
    /**
     * Generate welcome discount code
     */
    protected function generateWelcomeDiscount($user): ?string
    {
        $incentive = $this->getNewsletterIncentive();
        return $incentive ? $incentive['code'] : null;
    }
    
    /**
     * Generate incentive discount code
     */
    protected function generateIncentiveCode(): string
    {
        return 'WELCOME' . strtoupper(substr(md5(time() . rand()), 0, 6));
    }
    
    /**
     * Initialize email provider
     */
    protected function initializeProvider(): void
    {
        $subscriberService = $this->container->get(SubscriberService::class);
        $subscriberService->initializeProvider();
    }
    
    /**
     * Schedule automations
     */
    protected function scheduleAutomations(): void
    {
        $automationService = $this->container->get(AutomationService::class);
        $automationService->enableAllAutomations();
    }
    
    /**
     * Pause automations
     */
    protected function pauseAutomations(): void
    {
        $automationService = $this->container->get(AutomationService::class);
        $automationService->pauseAllAutomations();
    }
    
    /**
     * Run database migrations
     */
    protected function runMigrations(): void
    {
        $migrations = [
            'create_email_subscribers_table.php',
            'create_email_lists_table.php',
            'create_email_campaigns_table.php',
            'create_email_campaign_stats_table.php',
            'create_email_automations_table.php',
            'create_email_automation_logs_table.php',
            'create_email_templates_table.php',
            'create_email_bounces_table.php',
            'create_email_preferences_table.php'
        ];
        
        foreach ($migrations as $migration) {
            $this->api->runMigration($this->getPath('migrations/' . $migration));
        }
    }
    
    /**
     * Create default email templates
     */
    protected function createDefaultTemplates(): void
    {
        $templateService = $this->container->get(TemplateService::class);
        $templates = require $this->getPath('config/default_templates.php');
        
        foreach ($templates as $template) {
            $templateService->createTemplate($template);
        }
    }
    
    /**
     * Set default configuration
     */
    protected function setDefaultConfig(): void
    {
        $defaults = [
            'provider' => 'internal',
            'from_name' => 'Shopologic Store',
            'enable_double_optin' => true,
            'welcome_email' => true,
            'welcome_email_template' => 'default',
            'abandoned_cart_enabled' => true,
            'abandoned_cart_delay' => 1,
            'newsletter_signup_incentive' => 'none',
            'segment_sync' => true,
            'product_recommendations' => true,
            'unsubscribe_page' => 'default',
            'bounce_handling' => true,
            'list_cleaning_days' => 365,
            'gdpr_compliance' => false
        ];
        
        foreach ($defaults as $key => $value) {
            if ($this->getConfig($key) === null) {
                $this->setConfig($key, $value);
            }
        }
    }
}