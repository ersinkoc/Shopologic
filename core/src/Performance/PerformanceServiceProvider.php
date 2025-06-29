<?php

declare(strict_types=1);

namespace Shopologic\Core\Performance;

use Shopologic\Core\Container\ServiceProvider;
use Shopologic\Core\Cache\Advanced\AdvancedCacheManager;
use Shopologic\Core\Cache\Advanced\CacheWarmer;
use Shopologic\Core\Cache\Advanced\CacheInvalidator;
use Shopologic\Core\Queue\QueueManager;
use Shopologic\Core\Queue\Worker;
use Shopologic\Core\Search\SearchEngine;

/**
 * Performance optimization service provider
 */
class PerformanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register Advanced Cache Manager
        $this->container->singleton(AdvancedCacheManager::class, function ($container) {
            $warmer = new CacheWarmer();
            $invalidator = new CacheInvalidator();
            
            return new AdvancedCacheManager(
                $container->get('config')['cache'] ?? [],
                $container->get('events'),
                $warmer,
                $invalidator
            );
        });
        
        // Register Queue Manager
        $this->container->singleton(QueueManager::class, function ($container) {
            return new QueueManager(
                $container->get('config')['queue'] ?? [],
                $container->get('events')
            );
        });
        
        // Register Queue Worker
        $this->container->singleton(Worker::class, function ($container) {
            return new Worker(
                $container->get(QueueManager::class),
                $container->get('events')
            );
        });
        
        // Register Search Engine
        $this->container->singleton(SearchEngine::class, function ($container) {
            return new SearchEngine(
                $container->get('db'),
                $container->get('cache'),
                $container->get('events'),
                $container->get('config')['search'] ?? []
            );
        });
        
        // Register Performance Monitor
        $this->container->singleton(PerformanceMonitor::class, function ($container) {
            return new PerformanceMonitor(
                $container->get('db'),
                $container->get('cache'),
                $container->get('events'),
                $container->get('config')['performance'] ?? []
            );
        });
        
        // Register aliases
        $this->container->alias('cache.advanced', AdvancedCacheManager::class);
        $this->container->alias('queue', QueueManager::class);
        $this->container->alias('search', SearchEngine::class);
        $this->container->alias('performance', PerformanceMonitor::class);
    }
    
    public function boot(): void
    {
        // Setup performance monitoring
        $this->setupPerformanceMonitoring();
        
        // Register cache warmers
        $this->registerCacheWarmers();
        
        // Register search indexers
        $this->registerSearchIndexers();
        
        // Register optimizations
        $this->registerOptimizations();
        
        // Register CLI commands
        $this->registerCommands();
    }
    
    private function setupPerformanceMonitoring(): void
    {
        if (!$this->container->get('config')['performance']['enabled'] ?? true) {
            return;
        }
        
        $monitor = $this->container->get('performance');
        $events = $this->container->get('events');
        
        // Monitor database queries
        $events->listen('db.query', function ($query, $bindings, $time) use ($monitor) {
            $monitor->trackQuery($query, $bindings, $time);
        });
        
        // Monitor cache operations
        $events->listen('cache.hit', function ($key) use ($monitor) {
            $monitor->trackCache('get', $key, true);
        });
        
        $events->listen('cache.miss', function ($key) use ($monitor) {
            $monitor->trackCache('get', $key, false);
        });
        
        // Monitor HTTP requests
        $events->listen('http.request', function ($method, $url, $duration, $statusCode) use ($monitor) {
            $monitor->trackHttpRequest($method, $url, $duration, $statusCode);
        });
        
        // Monitor application lifecycle
        $events->listen('app.terminating', function () use ($monitor) {
            $metrics = $monitor->getMetrics();
            
            // Log summary if needed
            if ($metrics['total_time'] > 1000) { // More than 1 second
                error_log(sprintf(
                    'Slow request: %dms, Memory: %s',
                    $metrics['total_time'],
                    $this->formatBytes($metrics['total_memory'])
                ));
            }
        });
    }
    
    private function registerCacheWarmers(): void
    {
        $cache = $this->container->get('cache.advanced');
        $warmer = $cache->getWarmer();
        
        // Register product cache warmer
        $warmer->registerWarmer('products', function ($cache) {
            $products = $this->container->get('db')
                ->table('products')
                ->where('status', 'active')
                ->limit(100)
                ->get();
            
            foreach ($products as $product) {
                $cache->set('product:' . $product->id, $product, 3600);
            }
        });
        
        // Register category cache warmer
        $warmer->registerWarmer('categories', function ($cache) {
            $categories = $this->container->get('db')
                ->table('categories')
                ->where('status', 'active')
                ->get();
            
            $cache->set('categories:all', $categories, 3600);
            
            foreach ($categories as $category) {
                $cache->set('category:' . $category->id, $category, 3600);
            }
        });
        
        // Register configuration cache warmer
        $warmer->registerWarmer('config', function ($cache) {
            $config = $this->container->get('db')
                ->table('configurations')
                ->get()
                ->pluck('value', 'key')
                ->toArray();
            
            $cache->set('config:all', $config, 86400);
        });
    }
    
    private function registerSearchIndexers(): void
    {
        $search = $this->container->get('search');
        $events = $this->container->get('events');
        
        // Index products
        $events->listen('product.created', function ($product) use ($search) {
            $search->index('product', (string)$product->id, [
                'title' => $product->name,
                'content' => $product->description,
                'tags' => explode(',', $product->tags ?? ''),
                'category' => $product->category->name ?? '',
                'price' => $product->price,
                'sku' => $product->sku,
                '_boost' => $product->featured ? 2.0 : 1.0
            ]);
        });
        
        $events->listen('product.updated', function ($product) use ($search) {
            $search->index('product', (string)$product->id, [
                'title' => $product->name,
                'content' => $product->description,
                'tags' => explode(',', $product->tags ?? ''),
                'category' => $product->category->name ?? '',
                'price' => $product->price,
                'sku' => $product->sku,
                '_boost' => $product->featured ? 2.0 : 1.0
            ]);
        });
        
        $events->listen('product.deleted', function ($product) use ($search) {
            $search->delete('product', (string)$product->id);
        });
        
        // Index categories
        $events->listen('category.created', function ($category) use ($search) {
            $search->index('category', (string)$category->id, [
                'title' => $category->name,
                'content' => $category->description,
                'parent' => $category->parent->name ?? ''
            ]);
        });
        
        $events->listen('category.updated', function ($category) use ($search) {
            $search->index('category', (string)$category->id, [
                'title' => $category->name,
                'content' => $category->description,
                'parent' => $category->parent->name ?? ''
            ]);
        });
        
        $events->listen('category.deleted', function ($category) use ($search) {
            $search->delete('category', (string)$category->id);
        });
    }
    
    private function registerOptimizations(): void
    {
        $events = $this->container->get('events');
        
        // Automatic query optimization
        $events->listen('performance.critical_slowness', function ($metric) {
            if ($metric['type'] === 'query') {
                $this->optimizeSlowQuery($metric);
            }
        });
        
        // Automatic cache optimization
        $events->listen('cache.miss_rate_high', function ($stats) {
            $this->optimizeCacheStrategy($stats);
        });
        
        // Memory optimization
        $events->listen('performance.memory_warning', function ($data) {
            // Clear caches if memory is critical
            if ($data['usage'] > 90) {
                $this->container->get('cache')->clear();
                gc_collect_cycles();
            }
        });
    }
    
    private function registerCommands(): void
    {
        if (!$this->container->has('console')) {
            return;
        }
        
        $console = $this->container->get('console');
        
        // Performance commands
        $console->add(new Commands\CacheWarmCommand($this->container));
        $console->add(new Commands\CacheClearCommand($this->container));
        $console->add(new Commands\QueueWorkCommand($this->container));
        $console->add(new Commands\QueueRetryCommand($this->container));
        $console->add(new Commands\SearchIndexCommand($this->container));
        $console->add(new Commands\SearchReindexCommand($this->container));
        $console->add(new Commands\PerformanceReportCommand($this->container));
        $console->add(new Commands\PerformanceOptimizeCommand($this->container));
    }
    
    private function optimizeSlowQuery(array $metric): void
    {
        $query = $metric['metadata']['query'] ?? '';
        
        // Log for manual review
        error_log("Slow query detected: {$query}");
        
        // Attempt automatic optimization
        if (strpos($query, 'SELECT') === 0) {
            // Check if query result can be cached
            $cacheKey = 'query:' . md5($query . serialize($metric['metadata']['bindings'] ?? []));
            
            // Cache for future requests
            $this->container->get('cache.advanced')->cache($cacheKey, function () use ($query, $metric) {
                // This would execute the query
                return [];
            }, 300); // 5 minutes
        }
    }
    
    private function optimizeCacheStrategy(array $stats): void
    {
        $cache = $this->container->get('cache.advanced');
        
        // Increase TTL for frequently missed keys
        if ($stats['miss_rate'] > 30) {
            // Implement adaptive caching
            error_log("High cache miss rate detected: {$stats['miss_rate']}%");
        }
    }
    
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return sprintf('%.2f %s', $bytes, $units[$i]);
    }
}