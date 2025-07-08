<?php

declare(strict_types=1);

// Microkernel Architecture Validation Script
// This script validates all components of the Shopologic microkernel

define('SHOPOLOGIC_START', microtime(true));
define('SHOPOLOGIC_ROOT', __DIR__);

require_once SHOPOLOGIC_ROOT . '/core/src/Autoloader.php';

use Shopologic\Core\Autoloader;
use Shopologic\Core\Kernel\Application;

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Color codes for terminal output
$GREEN = "\033[32m";
$RED = "\033[31m";
$YELLOW = "\033[33m";
$BLUE = "\033[34m";
$RESET = "\033[0m";

echo "{$BLUE}╔════════════════════════════════════════════════╗{$RESET}\n";
echo "{$BLUE}║       Shopologic Microkernel Validation        ║{$RESET}\n";
echo "{$BLUE}╚════════════════════════════════════════════════╝{$RESET}\n\n";

// Track test results
$tests = [];
$passed = 0;
$failed = 0;

function runTest(string $name, callable $test, array &$tests, int &$passed, int &$failed): void {
    global $GREEN, $RED, $YELLOW, $RESET;
    
    echo "{$YELLOW}Testing:{$RESET} $name... ";
    
    try {
        $result = $test();
        if ($result === true) {
            echo "{$GREEN}✓ PASSED{$RESET}\n";
            $tests[$name] = 'passed';
            $passed++;
        } else {
            echo "{$RED}✗ FAILED{$RESET} - $result\n";
            $tests[$name] = 'failed';
            $failed++;
        }
    } catch (\Exception $e) {
        echo "{$RED}✗ ERROR{$RESET} - " . $e->getMessage() . "\n";
        $tests[$name] = 'error';
        $failed++;
    }
}

// Register autoloader
$autoloader = new Autoloader();
$autoloader->addNamespace('Shopologic\\Core', SHOPOLOGIC_ROOT . '/core/src');
$autoloader->addNamespace('Shopologic\\PSR', SHOPOLOGIC_ROOT . '/core/src/PSR');
$autoloader->addNamespace('Shopologic\\Plugins', SHOPOLOGIC_ROOT . '/plugins');
$autoloader->register();

// Load helpers
if (file_exists(SHOPOLOGIC_ROOT . '/core/src/helpers.php')) {
    require_once SHOPOLOGIC_ROOT . '/core/src/helpers.php';
}

// 1. Test Application Bootstrap
runTest('Application Bootstrap', function() {
    $app = new Application(SHOPOLOGIC_ROOT);
    $GLOBALS['SHOPOLOGIC_APP'] = $app;
    return $app instanceof Application;
}, $tests, $passed, $failed);

// 2. Test Container
runTest('Container Instance', function() {
    $container = app()->getContainer();
    return $container instanceof \Shopologic\Core\Container\Container;
}, $tests, $passed, $failed);

// 3. Test Core Service Bindings
runTest('Core Service Bindings', function() {
    $container = app()->getContainer();
    $required = [
        Application::class,
        \Shopologic\Core\Container\Container::class,
        \Shopologic\PSR\Container\ContainerInterface::class,
        \Shopologic\Core\Events\EventManager::class,
        \Shopologic\PSR\EventDispatcher\EventDispatcherInterface::class,
        \Shopologic\Core\Configuration\ConfigurationManager::class,
        \Shopologic\Core\Kernel\EnvironmentDetector::class,
    ];
    
    foreach ($required as $service) {
        if (!$container->has($service)) {
            return "Missing binding: $service";
        }
    }
    return true;
}, $tests, $passed, $failed);

// 4. Test Service Provider Registration
runTest('Service Provider Registration', function() {
    $app = app();
    $app->register(\Shopologic\Core\Plugin\PluginServiceProvider::class);
    return true;
}, $tests, $passed, $failed);

// 5. Test Application Boot
runTest('Application Boot', function() {
    $app = app();
    $app->boot();
    
    // Check if providers are loaded
    $reflection = new ReflectionClass($app);
    $property = $reflection->getProperty('loadedProviders');
    $property->setAccessible(true);
    $loadedProviders = $property->getValue($app);
    
    return count($loadedProviders) > 0;
}, $tests, $passed, $failed);

// 6. Test HTTP Kernel
runTest('HTTP Kernel', function() {
    $container = app()->getContainer();
    if (!$container->has(\Shopologic\Core\Kernel\HttpKernelInterface::class)) {
        return "HttpKernelInterface not bound";
    }
    
    $kernel = $container->get(\Shopologic\Core\Kernel\HttpKernelInterface::class);
    return $kernel instanceof \Shopologic\Core\Kernel\HttpKernelInterface;
}, $tests, $passed, $failed);

// 7. Test Request Handling
runTest('Request Handling', function() {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/health';
    $_SERVER['HTTP_HOST'] = 'localhost';
    
    $request = \Shopologic\Core\Http\ServerRequestFactory::fromGlobals();
    $response = app()->handle($request);
    
    return $response->getStatusCode() === 200;
}, $tests, $passed, $failed);

// 8. Test Plugin System
runTest('Plugin System', function() {
    $container = app()->getContainer();
    
    if (!$container->has(\Shopologic\Core\Plugin\PluginManager::class)) {
        return "PluginManager not available";
    }
    
    if (!$container->has(\Shopologic\Core\Plugin\HookSystem::class)) {
        return "HookSystem not available";
    }
    
    return true;
}, $tests, $passed, $failed);

// 9. Test Hook Functions
runTest('Hook Functions', function() {
    $functions = ['add_action', 'do_action', 'add_filter', 'apply_filters', 
                  'remove_action', 'remove_filter', 'has_action', 'has_filter', 
                  'current_filter', 'did_action'];
    
    foreach ($functions as $func) {
        if (!function_exists($func)) {
            return "Missing function: $func";
        }
    }
    
    // Test hook functionality
    $testValue = false;
    add_action('test_hook', function() use (&$testValue) {
        $testValue = true;
    });
    
    do_action('test_hook');
    
    if (!$testValue) {
        return "Hook execution failed";
    }
    
    // Test filter
    add_filter('test_filter', function($value) {
        return $value . '_filtered';
    });
    
    $result = apply_filters('test_filter', 'test');
    if ($result !== 'test_filtered') {
        return "Filter execution failed";
    }
    
    return true;
}, $tests, $passed, $failed);

// 10. Test Helper Functions
runTest('Helper Functions', function() {
    // Test app() helper
    if (app() !== $GLOBALS['SHOPOLOGIC_APP']) {
        return "app() helper returns wrong instance";
    }
    
    // Test app() with service resolution
    $container = app(\Shopologic\Core\Container\Container::class);
    if (!$container instanceof \Shopologic\Core\Container\Container) {
        return "app() service resolution failed";
    }
    
    // Test env() helper
    if (!function_exists('env')) {
        return "env() helper not defined";
    }
    
    return true;
}, $tests, $passed, $failed);

// 11. Test Environment Detection
runTest('Environment Detection', function() {
    $app = app();
    $env = $app->getEnvironment();
    
    if (empty($env)) {
        return "Environment not detected";
    }
    
    // Check environment methods
    $methods = ['isProduction', 'isDevelopment', 'isTesting', 'isDebug'];
    foreach ($methods as $method) {
        if (!method_exists($app, $method)) {
            return "Missing method: $method";
        }
    }
    
    return true;
}, $tests, $passed, $failed);

// 12. Test Dependency Injection
runTest('Dependency Injection', function() {
    $container = app()->getContainer();
    
    // Test binding
    $container->bind('test.binding', function() {
        return new stdClass();
    });
    
    if (!$container->has('test.binding')) {
        return "Binding failed";
    }
    
    $instance1 = $container->get('test.binding');
    $instance2 = $container->get('test.binding');
    
    if ($instance1 === $instance2) {
        return "Non-singleton binding returns same instance";
    }
    
    // Test singleton
    $container->singleton('test.singleton', function() {
        return new stdClass();
    });
    
    $singleton1 = $container->get('test.singleton');
    $singleton2 = $container->get('test.singleton');
    
    if ($singleton1 !== $singleton2) {
        return "Singleton binding returns different instances";
    }
    
    return true;
}, $tests, $passed, $failed);

// Print summary
echo "\n{$BLUE}╔════════════════════════════════════════════════╗{$RESET}\n";
echo "{$BLUE}║                   SUMMARY                      ║{$RESET}\n";
echo "{$BLUE}╚════════════════════════════════════════════════╝{$RESET}\n\n";

echo "Total Tests: " . ($passed + $failed) . "\n";
echo "{$GREEN}Passed: $passed{$RESET}\n";
echo "{$RED}Failed: $failed{$RESET}\n\n";

if ($failed === 0) {
    echo "{$GREEN}✅ All tests passed! The microkernel architecture is working correctly.{$RESET}\n";
} else {
    echo "{$RED}❌ Some tests failed. Please check the errors above.{$RESET}\n";
    echo "\nFailed tests:\n";
    foreach ($tests as $name => $result) {
        if ($result !== 'passed') {
            echo "  - $name\n";
        }
    }
}

// Performance metrics
$elapsed = round((microtime(true) - SHOPOLOGIC_START) * 1000, 2);
$memory = round(memory_get_peak_usage() / 1024 / 1024, 2);

echo "\n{$BLUE}Performance:{$RESET}\n";
echo "  Execution time: {$elapsed}ms\n";
echo "  Peak memory: {$memory}MB\n";

echo "\n";

// Exit with appropriate code
exit($failed > 0 ? 1 : 0);