<?php

declare(strict_types=1);

namespace Shopologic\Core\Analytics;

use Shopologic\Core\Container\ServiceProvider;
use Shopologic\Core\Analytics\Collectors\PageViewCollector;
use Shopologic\Core\Analytics\Collectors\EcommerceCollector;
use Shopologic\Core\Analytics\Collectors\UserBehaviorCollector;

/**
 * Analytics and reporting service provider
 */
class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register Analytics Engine
        $this->container->singleton(AnalyticsEngine::class, function ($container) {
            $engine = new AnalyticsEngine(
                $container->get('db'),
                $container->get('cache'),
                $container->get('events'),
                $container->get('config')['analytics'] ?? []
            );
            
            // Register collectors
            $engine->registerCollector('page_view', new PageViewCollector($container->get('db')));
            $engine->registerCollector('ecommerce', new EcommerceCollector($container->get('db')));
            $engine->registerCollector('user_behavior', new UserBehaviorCollector($container->get('db')));
            
            return $engine;
        });
        
        // Register Aggregation Processor
        $this->container->singleton(AggregationProcessor::class, function ($container) {
            return new AggregationProcessor($container->get('db'));
        });
        
        // Register Report Generator
        $this->container->singleton(ReportGenerator::class, function ($container) {
            return new ReportGenerator(
                $container->get('db'),
                $container->get('template'),
                $container->get('export'),
                $container->get(AnalyticsEngine::class),
                $container->get('config')['analytics']['reports'] ?? []
            );
        });
        
        // Register aliases
        $this->container->alias('analytics', AnalyticsEngine::class);
        $this->container->alias('analytics.reports', ReportGenerator::class);
        $this->container->alias('analytics.aggregation', AggregationProcessor::class);
    }
    
    public function boot(): void
    {
        // Register routes
        $this->registerRoutes();
        
        // Register event listeners
        $this->registerEventListeners();
        
        // Register scheduled tasks
        $this->registerScheduledTasks();
        
        // Register template functions
        $this->registerTemplateFunctions();
        
        // Register CLI commands
        $this->registerCommands();
    }
    
    private function registerRoutes(): void
    {
        $router = $this->container->get('router');
        
        // Analytics tracking endpoint
        $router->post('/api/analytics/track', 'AnalyticsController@track');
        
        // Analytics API endpoints
        $router->get('/api/analytics/realtime', 'AnalyticsController@realtime');
        $router->get('/api/analytics/metrics', 'AnalyticsController@metrics');
        $router->get('/api/analytics/reports', 'AnalyticsController@reports');
        $router->post('/api/analytics/reports', 'AnalyticsController@generateReport');
        
        // Dashboard endpoint
        $router->get('/api/analytics/dashboard', 'AnalyticsController@dashboard');
        
        // Export endpoints
        $router->get('/api/analytics/export/{format}', 'AnalyticsController@export');
    }
    
    private function registerEventListeners(): void
    {
        $events = $this->container->get('events');
        $analytics = $this->container->get('analytics');
        
        // Track page views
        $events->listen('page.viewed', function ($data) use ($analytics) {
            $analytics->track('page_view', [
                'page' => $data['page'],
                'title' => $data['title'] ?? '',
                'referrer' => $_SERVER['HTTP_REFERER'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ], $data['user_id'] ?? null);
        });
        
        // Track e-commerce events
        $events->listen('product.viewed', function ($product) use ($analytics) {
            $analytics->track('view_item', [
                'items' => [[
                    'item_id' => $product->id,
                    'item_name' => $product->name,
                    'item_category' => $product->category->name ?? null,
                    'price' => $product->price
                ]]
            ]);
        });
        
        $events->listen('cart.item_added', function ($item) use ($analytics) {
            $analytics->track('add_to_cart', [
                'items' => [[
                    'item_id' => $item->product_id,
                    'item_name' => $item->product->name,
                    'price' => $item->price,
                    'quantity' => $item->quantity
                ]],
                'value' => $item->price * $item->quantity
            ]);
        });
        
        $events->listen('order.completed', function ($order) use ($analytics) {
            $items = [];
            foreach ($order->items as $item) {
                $items[] = [
                    'item_id' => $item->product_id,
                    'item_name' => $item->product->name,
                    'price' => $item->price,
                    'quantity' => $item->quantity
                ];
            }
            
            $analytics->track('purchase', [
                'transaction_id' => $order->id,
                'value' => $order->total,
                'tax' => $order->tax,
                'shipping' => $order->shipping,
                'currency' => $order->currency,
                'items' => $items
            ], $order->user_id);
        });
        
        // Track user events
        $events->listen('user.logged_in', function ($user) use ($analytics) {
            $analytics->track('login', [
                'method' => 'email'
            ], $user->id);
        });
        
        $events->listen('user.registered', function ($user) use ($analytics) {
            $analytics->track('sign_up', [
                'method' => 'email'
            ], $user->id);
        });
        
        // Track search
        $events->listen('search.performed', function ($data) use ($analytics) {
            $analytics->track('search', [
                'search_term' => $data['query'],
                'results_count' => $data['results_count'] ?? 0
            ]);
        });
    }
    
    private function registerScheduledTasks(): void
    {
        if (!$this->container->has('scheduler')) {
            return;
        }
        
        $scheduler = $this->container->get('scheduler');
        $analytics = $this->container->get('analytics');
        $aggregator = $this->container->get('analytics.aggregation');
        $reportGenerator = $this->container->get('analytics.reports');
        
        // Process aggregations
        $scheduler->call(function () use ($aggregator) {
            $aggregator->processHourlyAggregations();
        })->hourly();
        
        $scheduler->call(function () use ($aggregator) {
            $aggregator->processDailyAggregations();
        })->dailyAt('01:00');
        
        $scheduler->call(function () use ($aggregator) {
            $aggregator->processWeeklyAggregations();
        })->weekly();
        
        $scheduler->call(function () use ($aggregator) {
            $aggregator->processMonthlyAggregations();
        })->monthly();
        
        // Process scheduled reports
        $scheduler->call(function () use ($reportGenerator) {
            $reportGenerator->processScheduledReports();
        })->hourly();
        
        // Clean up old data
        $scheduler->call(function () use ($analytics) {
            $analytics->cleanup();
        })->daily();
    }
    
    private function registerTemplateFunctions(): void
    {
        $template = $this->container->get('template');
        $analytics = $this->container->get('analytics');
        
        // Analytics tracking function
        $template->addFunction('analytics_track', function ($event, $properties = []) use ($analytics) {
            $analytics->track($event, $properties);
        });
        
        // Get real-time metrics
        $template->addFunction('analytics_realtime', function () use ($analytics) {
            return $analytics->getRealTimeMetrics();
        });
        
        // Get metric value
        $template->addFunction('analytics_metric', function ($metric, $period = 'today') use ($analytics) {
            list($start, $end) = $this->parsePeriod($period);
            $metrics = $analytics->getMetrics($start, $end, [$metric]);
            return $metrics[0][$metric] ?? 0;
        });
    }
    
    private function registerCommands(): void
    {
        if (!$this->container->has('console')) {
            return;
        }
        
        $console = $this->container->get('console');
        
        // Register analytics commands
        $console->add(new Commands\ProcessAggregationsCommand($this->container));
        $console->add(new Commands\GenerateReportCommand($this->container));
        $console->add(new Commands\ExportAnalyticsCommand($this->container));
        $console->add(new Commands\CleanupAnalyticsCommand($this->container));
        $console->add(new Commands\ImportAnalyticsCommand($this->container));
    }
    
    private function parsePeriod(string $period): array
    {
        $end = new \DateTime();
        $start = clone $end;
        
        switch ($period) {
            case 'today':
                $start->setTime(0, 0, 0);
                break;
            case 'yesterday':
                $start->modify('-1 day')->setTime(0, 0, 0);
                $end->modify('-1 day')->setTime(23, 59, 59);
                break;
            case 'week':
                $start->modify('-7 days');
                break;
            case 'month':
                $start->modify('-30 days');
                break;
            case 'year':
                $start->modify('-365 days');
                break;
        }
        
        return [$start, $end];
    }
}