<?php

declare(strict_types=1);

/**
 * Shopologic Comprehensive Test Suite
 * Tests microkernel architecture with real plugin loading
 */

define('SHOPOLOGIC_START', microtime(true));
define('SHOPOLOGIC_ROOT', __DIR__);

require_once SHOPOLOGIC_ROOT . '/core/src/Autoloader.php';

use Shopologic\Core\Autoloader;
use Shopologic\Core\Kernel\Application;
use Shopologic\Core\Plugin\AbstractPlugin;

// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Terminal colors
class Colors {
    const GREEN = "\033[32m";
    const RED = "\033[31m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const CYAN = "\033[36m";
    const RESET = "\033[0m";
    const BOLD = "\033[1m";
}

// Test result tracking
class TestRunner {
    private array $results = [];
    private int $passed = 0;
    private int $failed = 0;
    private int $warnings = 0;
    
    public function test(string $name, callable $test): void {
        echo Colors::YELLOW . "Testing: " . Colors::RESET . $name . "... ";
        
        $start = microtime(true);
        
        try {
            $result = $test();
            $elapsed = round((microtime(true) - $start) * 1000, 2);
            
            if ($result === true) {
                echo Colors::GREEN . "✓ PASSED" . Colors::RESET . " ({$elapsed}ms)\n";
                $this->results[$name] = ['status' => 'passed', 'time' => $elapsed];
                $this->passed++;
            } elseif (is_string($result) && strpos($result, 'WARNING:') === 0) {
                echo Colors::YELLOW . "⚠ WARNING" . Colors::RESET . " - " . substr($result, 8) . " ({$elapsed}ms)\n";
                $this->results[$name] = ['status' => 'warning', 'message' => $result, 'time' => $elapsed];
                $this->warnings++;
            } else {
                echo Colors::RED . "✗ FAILED" . Colors::RESET . " - $result ({$elapsed}ms)\n";
                $this->results[$name] = ['status' => 'failed', 'message' => $result, 'time' => $elapsed];
                $this->failed++;
            }
        } catch (\Exception $e) {
            $elapsed = round((microtime(true) - $start) * 1000, 2);
            echo Colors::RED . "✗ ERROR" . Colors::RESET . " - " . $e->getMessage() . " ({$elapsed}ms)\n";
            if (getenv('DEBUG')) {
                echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n";
                echo "  Trace:\n";
                $trace = array_slice($e->getTrace(), 0, 3);
                foreach ($trace as $i => $frame) {
                    echo "    #{$i} " . ($frame['file'] ?? 'unknown') . ":" . ($frame['line'] ?? '?') . "\n";
                }
            }
            $this->results[$name] = ['status' => 'error', 'message' => $e->getMessage(), 'time' => $elapsed];
            $this->failed++;
        }
    }
    
    public function group(string $name, callable $tests): void {
        echo "\n" . Colors::BLUE . Colors::BOLD . "═══ $name ═══" . Colors::RESET . "\n\n";
        $tests($this);
    }
    
    public function summary(): void {
        $total = $this->passed + $this->failed + $this->warnings;
        $totalTime = array_sum(array_column($this->results, 'time'));
        
        echo "\n" . Colors::BLUE . "╔════════════════════════════════════════════════╗" . Colors::RESET . "\n";
        echo Colors::BLUE . "║                   SUMMARY                      ║" . Colors::RESET . "\n";
        echo Colors::BLUE . "╚════════════════════════════════════════════════╝" . Colors::RESET . "\n\n";
        
        echo "Total Tests: $total\n";
        echo Colors::GREEN . "Passed: $this->passed" . Colors::RESET . "\n";
        echo Colors::YELLOW . "Warnings: $this->warnings" . Colors::RESET . "\n";
        echo Colors::RED . "Failed: $this->failed" . Colors::RESET . "\n";
        echo "Total Time: " . round($totalTime, 2) . "ms\n";
        
        if ($this->failed > 0) {
            echo "\n" . Colors::RED . "Failed tests:" . Colors::RESET . "\n";
            foreach ($this->results as $name => $result) {
                if ($result['status'] === 'failed' || $result['status'] === 'error') {
                    echo "  - $name: " . ($result['message'] ?? 'Unknown error') . "\n";
                }
            }
        }
        
        if ($this->warnings > 0) {
            echo "\n" . Colors::YELLOW . "Warnings:" . Colors::RESET . "\n";
            foreach ($this->results as $name => $result) {
                if ($result['status'] === 'warning') {
                    echo "  - $name: " . substr($result['message'], 8) . "\n";
                }
            }
        }
        
        echo "\n";
        exit($this->failed > 0 ? 1 : 0);
    }
}

// Banner
echo Colors::CYAN . "
╔════════════════════════════════════════════════╗
║     Shopologic Comprehensive Test Suite        ║
║           Microkernel + Plugins                ║
╚════════════════════════════════════════════════╝
" . Colors::RESET . "\n";

// Initialize autoloader
$autoloader = new Autoloader();
$autoloader->addNamespace('Shopologic\\Core', SHOPOLOGIC_ROOT . '/core/src');
$autoloader->addNamespace('Shopologic\\PSR', SHOPOLOGIC_ROOT . '/core/src/PSR');
$autoloader->addNamespace('Shopologic\\Plugins', SHOPOLOGIC_ROOT . '/plugins');
$autoloader->register();

// Load helper functions
if (file_exists(SHOPOLOGIC_ROOT . '/core/src/helpers.php')) {
    require_once SHOPOLOGIC_ROOT . '/core/src/helpers.php';
}

// Initialize test runner
$runner = new TestRunner();

// Initialize application once
$app = null;

$runner->group('Core Bootstrap', function($runner) use (&$app) {
    $runner->test('Application initialization', function() use (&$app) {
        $app = new Application(SHOPOLOGIC_ROOT);
        $GLOBALS['SHOPOLOGIC_APP'] = $app;
        return $app instanceof Application;
    });
    
    $runner->test('Container availability', function() use ($app) {
        $container = $app->getContainer();
        return $container instanceof \Shopologic\Core\Container\Container;
    });
    
    $runner->test('Core service bindings', function() use ($app) {
        $container = $app->getContainer();
        $services = [
            \Shopologic\Core\Kernel\Application::class,
            \Shopologic\Core\Container\Container::class,
            \Shopologic\Core\Events\EventManager::class,
            \Shopologic\Core\Configuration\ConfigurationManager::class,
        ];
        
        foreach ($services as $service) {
            if (!$container->has($service)) {
                return "Missing: $service";
            }
        }
        return true;
    });
    
    $runner->test('Plugin service provider registration', function() use ($app) {
        $app->register(\Shopologic\Core\Plugin\PluginServiceProvider::class);
        return true;
    });
    
    $runner->test('Application boot', function() use ($app) {
        $app->boot();
        $reflection = new ReflectionClass($app);
        $property = $reflection->getProperty('booted');
        $property->setAccessible(true);
        return $property->getValue($app) === true;
    });
});

$runner->group('Plugin System', function($runner) use ($app) {
    $runner->test('PluginManager availability', function() use ($app) {
        $container = $app->getContainer();
        return $container->has(\Shopologic\Core\Plugin\PluginManager::class);
    });
    
    $runner->test('HookSystem availability', function() use ($app) {
        $container = $app->getContainer();
        return $container->has(\Shopologic\Core\Plugin\HookSystem::class);
    });
    
    $runner->test('Hook functions exist', function() {
        $functions = ['add_action', 'do_action', 'add_filter', 'apply_filters'];
        foreach ($functions as $func) {
            if (!function_exists($func)) {
                return "Missing: $func";
            }
        }
        return true;
    });
    
    $runner->test('Hook execution', function() {
        $executed = false;
        add_action('test_hook', function() use (&$executed) {
            $executed = true;
        });
        do_action('test_hook');
        return $executed;
    });
    
    $runner->test('Filter execution', function() {
        add_filter('test_filter', function($value) {
            return $value . '_modified';
        });
        $result = apply_filters('test_filter', 'original');
        return $result === 'original_modified';
    });
});

$runner->group('Plugin Loading', function($runner) use ($app) {
    $runner->test('Plugin directory exists', function() {
        $pluginDir = SHOPOLOGIC_ROOT . '/plugins';
        return is_dir($pluginDir);
    });
    
    $runner->test('Core-commerce plugin exists', function() {
        $pluginPath = SHOPOLOGIC_ROOT . '/plugins/core-commerce';
        return is_dir($pluginPath) && file_exists($pluginPath . '/plugin.json');
    });
    
    $runner->test('Plugin manifest parsing', function() {
        $manifestPath = SHOPOLOGIC_ROOT . '/plugins/core-commerce/plugin.json';
        $manifest = json_decode(file_get_contents($manifestPath), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return "JSON parse error: " . json_last_error_msg();
        }
        
        $required = ['name', 'version', 'description', 'bootstrap'];
        foreach ($required as $field) {
            if (!isset($manifest[$field])) {
                return "Missing field: $field";
            }
        }
        
        return true;
    });
    
    $runner->test('Plugin class autoloading', function() {
        // The autoloader should be able to find the plugin class
        return class_exists('\\Shopologic\\Plugins\\CoreCommerce\\CoreCommercePlugin');
    });
});

$runner->group('Core Commerce Plugin', function($runner) use ($app) {
    $plugin = null;
    
    $runner->test('Plugin instantiation', function() use ($app, &$plugin) {
        $container = $app->getContainer();
        $pluginPath = SHOPOLOGIC_ROOT . '/plugins/core-commerce';
        
        // Check if we can create the plugin instance
        $pluginClass = '\\Shopologic\\Plugins\\CoreCommerce\\CoreCommercePlugin';
        if (!class_exists($pluginClass)) {
            return "Plugin class not found: $pluginClass";
        }
        
        $plugin = new $pluginClass($container, $pluginPath);
        return $plugin instanceof \Shopologic\Core\Plugin\AbstractPlugin;
    });
    
    $runner->test('Plugin metadata', function() use ($plugin) {
        if (!$plugin) return "Plugin not instantiated";
        
        $name = $plugin->getName();
        $version = $plugin->getVersion();
        
        if (empty($name)) return "Plugin name is empty";
        if (empty($version)) return "Plugin version is empty";
        
        return true;
    });
    
    $runner->test('Plugin register method', function() use ($plugin) {
        if (!$plugin) return "Plugin not instantiated";
        
        // The register method should work without errors
        $plugin->register();
        return true;
    });
    
    $runner->test('Service registration', function() use ($app, $plugin) {
        if (!$plugin) return "Plugin not instantiated";
        
        $container = $app->getContainer();
        
        // Check if the plugin registered its services
        $services = [
            '\\Shopologic\\Plugins\\CoreCommerce\\Contracts\\ProductRepositoryInterface',
            '\\Shopologic\\Plugins\\CoreCommerce\\Contracts\\CategoryRepositoryInterface',
            '\\Shopologic\\Plugins\\CoreCommerce\\Contracts\\CartServiceInterface',
            '\\Shopologic\\Plugins\\CoreCommerce\\Contracts\\OrderServiceInterface',
            '\\Shopologic\\Plugins\\CoreCommerce\\Contracts\\CustomerServiceInterface',
        ];
        
        $missing = [];
        foreach ($services as $service) {
            if (!$container->has($service)) {
                $missing[] = basename(str_replace('\\', '/', $service));
            }
        }
        
        if (!empty($missing)) {
            return "WARNING: Missing services: " . implode(', ', $missing) . " (services may not be registered yet)";
        }
        
        return true;
    });
    
    $runner->test('Plugin boot method', function() use ($plugin) {
        if (!$plugin) return "Plugin not instantiated";
        
        // The boot method should work without errors
        $plugin->boot();
        return true;
    });
});

$runner->group('Integration Tests', function($runner) use ($app) {
    $runner->test('Request handling with plugins', function() use ($app) {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/health';
        $_SERVER['HTTP_HOST'] = 'localhost';
        
        $request = \Shopologic\Core\Http\ServerRequestFactory::fromGlobals();
        $response = $app->handle($request);
        
        return $response->getStatusCode() === 200;
    });
    
    $runner->test('Hook integration', function() {
        $results = [];
        
        // Register multiple hooks
        add_action('integration_test', function() use (&$results) {
            $results[] = 'action1';
        }, 10);
        
        add_action('integration_test', function() use (&$results) {
            $results[] = 'action2';
        }, 20);
        
        add_filter('integration_filter', function($value) {
            return $value . '_first';
        }, 10);
        
        add_filter('integration_filter', function($value) {
            return $value . '_second';
        }, 20);
        
        // Execute hooks
        do_action('integration_test');
        $filtered = apply_filters('integration_filter', 'test');
        
        // Verify results
        if (count($results) !== 2) return "Action hooks failed";
        if ($results[0] !== 'action1' || $results[1] !== 'action2') return "Action order incorrect";
        if ($filtered !== 'test_first_second') return "Filter chain failed";
        
        return true;
    });
    
    $runner->test('Service resolution chain', function() use ($app) {
        $container = $app->getContainer();
        
        // Test nested dependency resolution
        $container->bind('test.nested.c', function() {
            return (object)['name' => 'ServiceC'];
        });
        
        $container->bind('test.nested.b', function($container) {
            return (object)[
                'name' => 'ServiceB',
                'dependency' => $container->get('test.nested.c')
            ];
        });
        
        $container->bind('test.nested.a', function($container) {
            return (object)[
                'name' => 'ServiceA',
                'dependency' => $container->get('test.nested.b')
            ];
        });
        
        $serviceA = $container->get('test.nested.a');
        
        if ($serviceA->name !== 'ServiceA') return "ServiceA incorrect";
        if ($serviceA->dependency->name !== 'ServiceB') return "ServiceB incorrect";
        if ($serviceA->dependency->dependency->name !== 'ServiceC') return "ServiceC incorrect";
        
        return true;
    });
});

$runner->group('Performance Tests', function($runner) use ($app) {
    $runner->test('Container resolution speed', function() use ($app) {
        $container = $app->getContainer();
        
        // Register 100 services
        for ($i = 0; $i < 100; $i++) {
            $container->bind("perf.service.$i", function() use ($i) {
                return new stdClass();
            });
        }
        
        $start = microtime(true);
        
        // Resolve each service
        for ($i = 0; $i < 100; $i++) {
            $container->get("perf.service.$i");
        }
        
        $elapsed = (microtime(true) - $start) * 1000;
        
        if ($elapsed > 50) {
            return "WARNING: Slow resolution: {$elapsed}ms for 100 services";
        }
        
        return true;
    });
    
    $runner->test('Hook execution speed', function() {
        // Register 100 hooks
        for ($i = 0; $i < 100; $i++) {
            add_action("perf_hook", function() {
                // Do nothing
            });
        }
        
        $start = microtime(true);
        
        // Execute hook 100 times
        for ($i = 0; $i < 100; $i++) {
            do_action("perf_hook");
        }
        
        $elapsed = (microtime(true) - $start) * 1000;
        
        if ($elapsed > 50) {
            return "WARNING: Slow hook execution: {$elapsed}ms for 100x100 calls";
        }
        
        return true;
    });
});

// Show performance metrics
$elapsed = round((microtime(true) - SHOPOLOGIC_START) * 1000, 2);
$memory = round(memory_get_peak_usage() / 1024 / 1024, 2);

echo "\n" . Colors::CYAN . "Performance Metrics:" . Colors::RESET . "\n";
echo "  Total execution time: {$elapsed}ms\n";
echo "  Peak memory usage: {$memory}MB\n";

// Show summary
$runner->summary();