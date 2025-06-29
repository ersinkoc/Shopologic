<?php

declare(strict_types=1);

namespace Shopologic\Core\Marketing;

use Shopologic\Core\Container\ServiceProvider;
use Shopologic\Core\Marketing\Analytics\AnalyticsTracker;
use Shopologic\Core\Marketing\Email\EmailCampaignManager;
use Shopologic\Core\Marketing\Social\SocialMediaManager;
use Shopologic\Core\Marketing\Automation\MarketingAutomation;
use Shopologic\Core\Marketing\ABTesting\ABTestingManager;
use Shopologic\Core\Marketing\Conversion\ConversionTracker;
use Shopologic\Core\Seo\MetaManager;
use Shopologic\Core\Seo\SitemapGenerator;
use Shopologic\Core\Seo\UrlGenerator;
use Shopologic\Core\Seo\RobotsManager;

/**
 * Marketing and SEO service provider
 */
class MarketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register SEO services
        $this->container->singleton(MetaManager::class, function ($container) {
            return new MetaManager(
                $container->get('events'),
                $container->get('config')['seo']['meta'] ?? []
            );
        });
        
        $this->container->singleton(SitemapGenerator::class, function ($container) {
            return new SitemapGenerator(
                $container->get('cache'),
                $container->get('events'),
                $container->get('config')['seo']['sitemap'] ?? []
            );
        });
        
        $this->container->singleton(UrlGenerator::class, function ($container) {
            return new UrlGenerator(
                $container->get('config')['seo']['urls'] ?? []
            );
        });
        
        $this->container->singleton(RobotsManager::class, function ($container) {
            return new RobotsManager(
                $container->get('config')['seo']['robots'] ?? []
            );
        });
        
        // Register Analytics
        $this->container->singleton(AnalyticsTracker::class, function ($container) {
            return new AnalyticsTracker(
                $container->get('events'),
                $container->get('session'),
                $container->get('config')['analytics'] ?? []
            );
        });
        
        // Register Email Campaign Manager
        $this->container->singleton(EmailCampaignManager::class, function ($container) {
            return new EmailCampaignManager(
                $container->get('events'),
                $container->get('queue'),
                $container->get('template'),
                $container->get('config')['email_marketing'] ?? []
            );
        });
        
        // Register Social Media Manager
        $this->container->singleton(SocialMediaManager::class, function ($container) {
            return new SocialMediaManager(
                $container->get('events'),
                $container->get('cache'),
                $container->get('http_client'),
                $container->get('config')['social_media'] ?? []
            );
        });
        
        // Register Marketing Automation
        $this->container->singleton(MarketingAutomation::class, function ($container) {
            return new MarketingAutomation(
                $container->get('events'),
                $container->get('queue')
            );
        });
        
        // Register A/B Testing Manager
        $this->container->singleton(ABTestingManager::class, function ($container) {
            return new ABTestingManager(
                $container->get('cache'),
                $container->get('events'),
                $container->get('config')['ab_testing'] ?? []
            );
        });
        
        // Register Conversion Tracker
        $this->container->singleton(ConversionTracker::class, function ($container) {
            return new ConversionTracker(
                $container->get('events'),
                $container->get('session'),
                $container->get('config')['conversion_tracking'] ?? []
            );
        });
        
        // Register aliases
        $this->container->alias('seo.meta', MetaManager::class);
        $this->container->alias('seo.sitemap', SitemapGenerator::class);
        $this->container->alias('seo.urls', UrlGenerator::class);
        $this->container->alias('seo.robots', RobotsManager::class);
        $this->container->alias('analytics', AnalyticsTracker::class);
        $this->container->alias('email.campaigns', EmailCampaignManager::class);
        $this->container->alias('social', SocialMediaManager::class);
        $this->container->alias('automation', MarketingAutomation::class);
        $this->container->alias('ab_testing', ABTestingManager::class);
        $this->container->alias('conversions', ConversionTracker::class);
    }
    
    public function boot(): void
    {
        // Register marketing routes
        $this->registerRoutes();
        
        // Register event listeners
        $this->registerEventListeners();
        
        // Register template extensions
        $this->registerTemplateExtensions();
        
        // Register CLI commands
        $this->registerCommands();
    }
    
    private function registerRoutes(): void
    {
        $router = $this->container->get('router');
        
        // Analytics tracking endpoints
        $router->get('/track/pixel/{tracking_id}', 'MarketingController@trackPixel');
        $router->get('/track/click/{tracking_id}/{link_id}', 'MarketingController@trackClick');
        
        // Unsubscribe endpoint
        $router->get('/unsubscribe/{token}', 'MarketingController@unsubscribe');
        
        // Social media webhooks
        $router->post('/webhooks/social/{provider}', 'MarketingController@socialWebhook');
        
        // A/B testing endpoints
        $router->post('/api/ab-test/track', 'MarketingController@trackABTest');
        
        // Conversion tracking
        $router->post('/api/conversions/track', 'MarketingController@trackConversion');
    }
    
    private function registerEventListeners(): void
    {
        $events = $this->container->get('events');
        
        // Track order conversions
        $events->listen('order.completed', function ($order) {
            $this->container->get('conversions')->trackConversion('purchase', [
                'value' => $order->total,
                'revenue' => $order->total,
                'order_id' => $order->id
            ]);
        });
        
        // Track user registration
        $events->listen('user.registered', function ($user) {
            $this->container->get('conversions')->trackConversion('registration', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            
            // Trigger automation
            $this->container->get('automation')->handleTrigger('user_registered', [
                'email' => $user->email,
                'name' => $user->name,
                'user_id' => $user->id
            ]);
        });
        
        // Track cart abandonment
        $events->listen('cart.abandoned', function ($cart) {
            $this->container->get('automation')->handleTrigger('cart_abandoned', [
                'cart_id' => $cart->id,
                'email' => $cart->email,
                'value' => $cart->total
            ]);
        });
    }
    
    private function registerTemplateExtensions(): void
    {
        $template = $this->container->get('template');
        
        // SEO extension
        $template->addExtension(new \Shopologic\Core\Template\Extensions\SeoExtension(
            $this->container->get('seo.meta')
        ));
        
        // Analytics extension
        $template->addExtension(new \Shopologic\Core\Template\Extensions\AnalyticsExtension(
            $this->container->get('analytics')
        ));
        
        // A/B testing extension
        $template->addFunction('ab_test', function ($testName) {
            return $this->container->get('ab_testing')->getVariant($testName);
        });
        
        // Social sharing extension
        $template->addFunction('share_urls', function ($url, $title = '', $description = '') {
            return $this->container->get('social')->getShareUrls($url, $title, $description);
        });
    }
    
    private function registerCommands(): void
    {
        if (!$this->container->has('console')) {
            return;
        }
        
        $console = $this->container->get('console');
        
        // Register marketing commands
        $console->add(new Commands\GenerateSitemapCommand($this->container));
        $console->add(new Commands\SendCampaignCommand($this->container));
        $console->add(new Commands\ImportSubscribersCommand($this->container));
        $console->add(new Commands\ExportAnalyticsCommand($this->container));
        $console->add(new Commands\TestABCommand($this->container));
    }
}