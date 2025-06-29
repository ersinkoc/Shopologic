<?php

declare(strict_types=1);

namespace Shopologic\Tests\E2E;

use Shopologic\Tests\E2E\TestFramework\E2ETestCase;
use Shopologic\Tests\E2E\TestFramework\Browser;

/**
 * Performance E2E Test
 * 
 * Tests system performance under various load conditions
 */
class PerformanceTest extends E2ETestCase
{
    private array $performanceMetrics = [];
    
    /**
     * Test page load performance
     */
    public function testPageLoadPerformance(): void
    {
        $browser = $this->createBrowser();
        
        $pages = [
            '/' => 'Homepage',
            '/products' => 'Products listing',
            '/products/laptop-pro' => 'Product detail',
            '/cart' => 'Shopping cart',
            '/checkout' => 'Checkout',
            '/account/dashboard' => 'Account dashboard'
        ];
        
        foreach ($pages as $url => $name) {
            $startTime = microtime(true);
            
            $browser->visit($url);
            
            // Wait for page to be fully loaded
            $this->waitForPageLoad($browser);
            
            $loadTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
            
            $this->performanceMetrics[$name] = [
                'load_time' => round($loadTime, 2),
                'dom_elements' => $browser->executeScript('return document.querySelectorAll("*").length'),
                'images' => $browser->executeScript('return document.images.length'),
                'scripts' => $browser->executeScript('return document.scripts.length'),
                'stylesheets' => $browser->executeScript('return document.styleSheets.length')
            ];
            
            // Assert performance thresholds
            $this->assertLessThan(3000, $loadTime, "$name page load time exceeds 3 seconds");
        }
        
        $this->generatePerformanceReport();
    }
    
    /**
     * Test search performance with large dataset
     */
    public function testSearchPerformance(): void
    {
        $browser = $this->createBrowser();
        
        $browser->visit('/products');
        $browser->waitForElement('.search-box');
        
        $searchTerms = [
            'laptop' => 'Common term',
            'gaming laptop rgb mechanical' => 'Complex query',
            'qwerty123456' => 'No results query',
            'a' => 'Single character',
            'laptop OR desktop AND monitor' => 'Boolean query'
        ];
        
        foreach ($searchTerms as $term => $description) {
            $startTime = microtime(true);
            
            $browser->clear('.search-input');
            $browser->type('.search-input', $term);
            $browser->click('.search-button');
            
            $browser->waitForElement('.search-results');
            
            $searchTime = (microtime(true) - $startTime) * 1000;
            $resultCount = $browser->countElements('.product-card');
            
            $this->performanceMetrics["Search: $description"] = [
                'query' => $term,
                'time_ms' => round($searchTime, 2),
                'results' => $resultCount
            ];
            
            // Search should complete within 1 second
            $this->assertLessThan(1000, $searchTime, "Search for '$term' took too long");
        }
    }
    
    /**
     * Test checkout performance with multiple items
     */
    public function testCheckoutPerformanceWithManyItems(): void
    {
        $browser = $this->createBrowser();
        
        // Add many items to cart
        $itemCounts = [5, 10, 25, 50];
        
        foreach ($itemCounts as $count) {
            // Clear cart first
            $this->clearCart($browser);
            
            $startTime = microtime(true);
            
            // Add items to cart
            for ($i = 0; $i < $count; $i++) {
                $browser->visit('/products');
                $browser->click('.product-card:nth-child(' . (($i % 10) + 1) . ') .add-to-cart');
                $browser->waitForElement('.cart-updated', 5);
            }
            
            // Go to checkout
            $browser->visit('/checkout');
            $browser->waitForElement('.checkout-form');
            
            $checkoutLoadTime = (microtime(true) - $startTime) * 1000;
            
            // Calculate order total
            $browser->click('.calculate-total');
            $browser->waitForElement('.order-total-calculated');
            
            $calculationTime = $browser->executeScript('return window.performance.getEntriesByName("calculate-total")[0].duration');
            
            $this->performanceMetrics["Checkout with $count items"] = [
                'items' => $count,
                'total_time_ms' => round($checkoutLoadTime, 2),
                'calculation_time_ms' => round($calculationTime, 2)
            ];
            
            // Checkout should handle up to 50 items efficiently
            $this->assertLessThan(5000, $checkoutLoadTime, "Checkout with $count items is too slow");
        }
    }
    
    /**
     * Test concurrent user simulation
     */
    public function testConcurrentUserLoad(): void
    {
        $concurrentUsers = 10;
        $browsers = [];
        $results = [];
        
        // Create multiple browser instances
        for ($i = 0; $i < $concurrentUsers; $i++) {
            $browsers[$i] = $this->createBrowser([
                'headless' => true
            ]);
        }
        
        $startTime = microtime(true);
        
        // Simulate concurrent actions
        $actions = [
            function($browser) { $this->browseProducts($browser); },
            function($browser) { $this->searchProducts($browser); },
            function($browser) { $this->addToCart($browser); },
            function($browser) { $this->viewProductDetails($browser); },
            function($browser) { $this->browseCategories($browser); }
        ];
        
        // Execute actions concurrently
        foreach ($browsers as $index => $browser) {
            $action = $actions[$index % count($actions)];
            
            try {
                $actionStart = microtime(true);
                $action($browser);
                $actionTime = (microtime(true) - $actionStart) * 1000;
                
                $results[] = [
                    'user' => $index + 1,
                    'action' => $index % count($actions),
                    'time_ms' => round($actionTime, 2),
                    'success' => true
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'user' => $index + 1,
                    'action' => $index % count($actions),
                    'error' => $e->getMessage(),
                    'success' => false
                ];
            }
        }
        
        $totalTime = (microtime(true) - $startTime) * 1000;
        
        // Calculate success rate
        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $successRate = ($successCount / $concurrentUsers) * 100;
        
        $this->performanceMetrics['Concurrent users'] = [
            'users' => $concurrentUsers,
            'total_time_ms' => round($totalTime, 2),
            'success_rate' => round($successRate, 2),
            'results' => $results
        ];
        
        // System should handle concurrent users with high success rate
        $this->assertGreaterThan(90, $successRate, 'Concurrent user success rate is too low');
        
        // Cleanup browsers
        foreach ($browsers as $browser) {
            $browser->quit();
        }
    }
    
    /**
     * Test image lazy loading performance
     */
    public function testImageLazyLoadingPerformance(): void
    {
        $browser = $this->createBrowser();
        
        // Visit page with many images
        $browser->visit('/products?per_page=100');
        $browser->waitForElement('.products-grid');
        
        // Check initial image load
        $initialImages = $browser->executeScript('
            return Array.from(document.images).filter(img => img.complete).length
        ');
        
        $totalImages = $browser->executeScript('return document.images.length');
        
        $this->performanceMetrics['Initial image load'] = [
            'total_images' => $totalImages,
            'loaded_images' => $initialImages,
            'lazy_loaded' => $totalImages - $initialImages
        ];
        
        // Scroll down and measure lazy loading
        $scrollStart = microtime(true);
        
        $browser->executeScript('window.scrollTo(0, document.body.scrollHeight)');
        $browser->waitFor(function() use ($browser, $totalImages) {
            $loaded = $browser->executeScript('
                return Array.from(document.images).filter(img => img.complete).length
            ');
            return $loaded === $totalImages;
        }, 5);
        
        $lazyLoadTime = (microtime(true) - $scrollStart) * 1000;
        
        $this->performanceMetrics['Lazy loading'] = [
            'time_ms' => round($lazyLoadTime, 2),
            'images_loaded' => $totalImages - $initialImages
        ];
        
        // Lazy loading should be efficient
        $this->assertLessThan(2000, $lazyLoadTime, 'Image lazy loading is too slow');
    }
    
    /**
     * Test API response times
     */
    public function testAPIResponseTimes(): void
    {
        $browser = $this->createBrowser();
        
        $apiEndpoints = [
            '/api/v1/products' => 'Products list',
            '/api/v1/products/1' => 'Single product',
            '/api/v1/categories' => 'Categories',
            '/api/v1/cart' => 'Cart status',
            '/api/v1/search?q=laptop' => 'Search'
        ];
        
        foreach ($apiEndpoints as $endpoint => $name) {
            $startTime = microtime(true);
            
            // Make API request through browser
            $response = $browser->executeScript("
                return fetch('{$endpoint}')
                    .then(r => ({ 
                        status: r.status, 
                        time: performance.now() 
                    }))
            ");
            
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            $this->performanceMetrics["API: $name"] = [
                'endpoint' => $endpoint,
                'time_ms' => round($responseTime, 2),
                'status' => $response['status'] ?? 'unknown'
            ];
            
            // API responses should be fast
            $this->assertLessThan(500, $responseTime, "API endpoint $endpoint is too slow");
        }
    }
    
    /**
     * Test database query performance
     */
    public function testDatabaseQueryPerformance(): void
    {
        $browser = $this->createBrowser();
        
        // Access admin panel for query metrics
        $this->loginAsAdmin($browser);
        
        $browser->visit('/admin/system/performance');
        $browser->waitForElement('.query-metrics');
        
        // Get query performance data
        $queryMetrics = $browser->executeScript('return window.queryMetrics');
        
        if ($queryMetrics) {
            foreach ($queryMetrics as $query) {
                if ($query['time'] > 100) { // Queries over 100ms
                    $this->performanceMetrics['Slow queries'][] = [
                        'query' => substr($query['sql'], 0, 100) . '...',
                        'time_ms' => $query['time'],
                        'calls' => $query['calls']
                    ];
                }
            }
        }
        
        // Check for N+1 queries
        $browser->click('.detect-n-plus-one');
        $browser->waitForElement('.n-plus-one-results');
        
        $nPlusOneCount = $browser->getText('.n-plus-one-count');
        $this->assertEquals('0', $nPlusOneCount, 'N+1 queries detected');
    }
    
    /**
     * Test cache effectiveness
     */
    public function testCacheEffectiveness(): void
    {
        $browser = $this->createBrowser();
        
        // First visit (cold cache)
        $coldStart = microtime(true);
        $browser->visit('/products');
        $browser->waitForElement('.products-grid');
        $coldLoadTime = (microtime(true) - $coldStart) * 1000;
        
        // Second visit (warm cache)
        $warmStart = microtime(true);
        $browser->visit('/products');
        $browser->waitForElement('.products-grid');
        $warmLoadTime = (microtime(true) - $warmStart) * 1000;
        
        // Calculate cache improvement
        $improvement = (($coldLoadTime - $warmLoadTime) / $coldLoadTime) * 100;
        
        $this->performanceMetrics['Cache effectiveness'] = [
            'cold_load_ms' => round($coldLoadTime, 2),
            'warm_load_ms' => round($warmLoadTime, 2),
            'improvement_percent' => round($improvement, 2)
        ];
        
        // Cache should provide significant improvement
        $this->assertGreaterThan(30, $improvement, 'Cache improvement is too low');
    }
    
    /**
     * Test memory usage during long session
     */
    public function testMemoryUsageDuringLongSession(): void
    {
        $browser = $this->createBrowser();
        
        $memoryReadings = [];
        $actions = 50; // Perform 50 actions
        
        for ($i = 0; $i < $actions; $i++) {
            // Perform various actions
            if ($i % 5 === 0) {
                $browser->visit('/products');
            } elseif ($i % 3 === 0) {
                $browser->visit('/products/' . rand(1, 100));
            } else {
                $browser->visit('/');
            }
            
            // Measure memory usage
            if ($i % 10 === 0) {
                $memory = $browser->executeScript('
                    if (performance.memory) {
                        return {
                            used: performance.memory.usedJSHeapSize,
                            total: performance.memory.totalJSHeapSize,
                            limit: performance.memory.jsHeapSizeLimit
                        };
                    }
                    return null;
                ');
                
                if ($memory) {
                    $memoryReadings[] = [
                        'action' => $i,
                        'used_mb' => round($memory['used'] / 1048576, 2),
                        'total_mb' => round($memory['total'] / 1048576, 2)
                    ];
                }
            }
        }
        
        // Check for memory leaks
        if (count($memoryReadings) > 2) {
            $firstReading = $memoryReadings[0]['used_mb'];
            $lastReading = end($memoryReadings)['used_mb'];
            $memoryGrowth = $lastReading - $firstReading;
            
            $this->performanceMetrics['Memory usage'] = [
                'initial_mb' => $firstReading,
                'final_mb' => $lastReading,
                'growth_mb' => $memoryGrowth,
                'readings' => $memoryReadings
            ];
            
            // Memory growth should be reasonable
            $this->assertLessThan(50, $memoryGrowth, 'Excessive memory growth detected');
        }
    }
    
    /**
     * Generate performance report
     */
    private function generatePerformanceReport(): void
    {
        $reportPath = dirname(__DIR__, 2) . '/performance-report.json';
        
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'metrics' => $this->performanceMetrics,
            'summary' => $this->calculateSummary()
        ];
        
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));
        
        echo "\nPerformance Report Summary:\n";
        echo "==========================\n";
        foreach ($report['summary'] as $key => $value) {
            echo "$key: $value\n";
        }
    }
    
    /**
     * Calculate performance summary
     */
    private function calculateSummary(): array
    {
        $loadTimes = [];
        $apiTimes = [];
        
        foreach ($this->performanceMetrics as $metric => $data) {
            if (isset($data['load_time'])) {
                $loadTimes[] = $data['load_time'];
            }
            if (isset($data['time_ms']) && str_contains($metric, 'API:')) {
                $apiTimes[] = $data['time_ms'];
            }
        }
        
        return [
            'average_page_load_ms' => round(array_sum($loadTimes) / count($loadTimes), 2),
            'max_page_load_ms' => max($loadTimes),
            'average_api_response_ms' => round(array_sum($apiTimes) / count($apiTimes), 2),
            'total_metrics' => count($this->performanceMetrics)
        ];
    }
    
    /**
     * Helper: Wait for page load
     */
    private function waitForPageLoad(Browser $browser): void
    {
        $browser->waitFor(function() use ($browser) {
            return $browser->executeScript('return document.readyState') === 'complete';
        }, 10);
    }
    
    /**
     * Helper: Clear cart
     */
    private function clearCart(Browser $browser): void
    {
        $browser->visit('/cart');
        if ($browser->elementExists('.clear-cart-button')) {
            $browser->click('.clear-cart-button');
            $browser->acceptAlert();
            $browser->waitForElement('.cart-empty');
        }
    }
    
    /**
     * Helper: Browse products
     */
    private function browseProducts(Browser $browser): void
    {
        $browser->visit('/products');
        $browser->waitForElement('.products-grid');
        $browser->click('.product-card:nth-child(3)');
        $browser->waitForElement('.product-details');
    }
    
    /**
     * Helper: Search products
     */
    private function searchProducts(Browser $browser): void
    {
        $browser->visit('/');
        $browser->type('.search-input', 'laptop');
        $browser->click('.search-button');
        $browser->waitForElement('.search-results');
    }
    
    /**
     * Helper: Add to cart
     */
    private function addToCart(Browser $browser): void
    {
        $browser->visit('/products');
        $browser->click('.product-card:first-child .add-to-cart');
        $browser->waitForElement('.cart-notification');
    }
    
    /**
     * Helper: View product details
     */
    private function viewProductDetails(Browser $browser): void
    {
        $browser->visit('/products/laptop-pro');
        $browser->waitForElement('.product-details');
        $browser->click('.product-image-thumbnail:nth-child(2)');
    }
    
    /**
     * Helper: Browse categories
     */
    private function browseCategories(Browser $browser): void
    {
        $browser->visit('/categories');
        $browser->waitForElement('.category-list');
        $browser->click('.category-card:nth-child(2)');
        $browser->waitForElement('.category-products');
    }
    
    /**
     * Helper: Login as admin
     */
    private function loginAsAdmin(Browser $browser): void
    {
        $browser->visit('/admin/login');
        $browser->type('#email', 'admin@shopologic.com');
        $browser->type('#password', 'AdminPassword123!');
        $browser->click('.login-button');
        $browser->waitForElement('.admin-dashboard');
    }
}