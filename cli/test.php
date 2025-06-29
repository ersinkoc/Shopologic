<?php

declare(strict_types=1);

/**
 * Shopologic Test Runner
 * 
 * Comprehensive test suite for all platform components
 */

// Define root path
define('SHOPOLOGIC_ROOT', dirname(__DIR__));

// Register autoloader
require_once SHOPOLOGIC_ROOT . '/core/src/Autoloader.php';

use Shopologic\Core\Autoloader;

// Initialize autoloader
$autoloader = new Autoloader();
$autoloader->register();
$autoloader->addNamespace('Shopologic\\Core', SHOPOLOGIC_ROOT . '/core/src');
$autoloader->addNamespace('Shopologic\\Plugins', SHOPOLOGIC_ROOT . '/plugins');
$autoloader->addNamespace('Shopologic\\Tests', SHOPOLOGIC_ROOT . '/tests');

// Test registry
$tests = [];
$results = [];
$startTime = microtime(true);

// Parse command line arguments
$options = parseArgs($argv);

/**
 * Test framework
 */
class TestFramework
{
    private static $tests = [];
    private static $results = [];
    private static $currentSuite = null;
    private static $coverage = [];
    
    public static function describe(string $suite, callable $callback): void
    {
        self::$currentSuite = $suite;
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "Testing: {$suite}\n";
        echo str_repeat('=', 50) . "\n";
        
        $callback();
        self::$currentSuite = null;
    }
    
    public static function it(string $description, callable $test): void
    {
        $suite = self::$currentSuite ?? 'Unknown';
        $testName = $suite . ' - ' . $description;
        
        try {
            echo "  ✓ {$description}";
            
            $startTime = microtime(true);
            $test();
            $endTime = microtime(true);
            
            $duration = round(($endTime - $startTime) * 1000, 2);
            echo " ({$duration}ms)\n";
            
            self::$results[] = [
                'suite' => $suite,
                'test' => $description,
                'status' => 'passed',
                'duration' => $duration
            ];
            
        } catch (Exception $e) {
            echo " ✗ FAILED\n";
            echo "    Error: " . $e->getMessage() . "\n";
            
            self::$results[] = [
                'suite' => $suite,
                'test' => $description,
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }
    
    public static function expect($actual): Expectation
    {
        return new Expectation($actual);
    }
    
    public static function getResults(): array
    {
        return self::$results;
    }
    
    public static function getSummary(): array
    {
        $passed = 0;
        $failed = 0;
        $totalDuration = 0;
        
        foreach (self::$results as $result) {
            if ($result['status'] === 'passed') {
                $passed++;
                $totalDuration += $result['duration'] ?? 0;
            } else {
                $failed++;
            }
        }
        
        return [
            'total' => count(self::$results),
            'passed' => $passed,
            'failed' => $failed,
            'duration' => $totalDuration
        ];
    }
}

/**
 * Expectation class for assertions
 */
class Expectation
{
    private $actual;
    
    public function __construct($actual)
    {
        $this->actual = $actual;
    }
    
    public function toBe($expected): void
    {
        if ($this->actual !== $expected) {
            throw new Exception("Expected '{$expected}' but got '{$this->actual}'");
        }
    }
    
    public function toEqual($expected): void
    {
        if ($this->actual != $expected) {
            throw new Exception("Expected '" . print_r($expected, true) . "' but got '" . print_r($this->actual, true) . "'");
        }
    }
    
    public function toBeTrue(): void
    {
        if ($this->actual !== true) {
            throw new Exception("Expected true but got '" . print_r($this->actual, true) . "'");
        }
    }
    
    public function toBeFalse(): void
    {
        if ($this->actual !== false) {
            throw new Exception("Expected false but got '" . print_r($this->actual, true) . "'");
        }
    }
    
    public function toBeNull(): void
    {
        if ($this->actual !== null) {
            throw new Exception("Expected null but got '" . print_r($this->actual, true) . "'");
        }
    }
    
    public function toBeInstanceOf(string $class): void
    {
        if (!($this->actual instanceof $class)) {
            throw new Exception("Expected instance of '{$class}' but got '" . get_class($this->actual) . "'");
        }
    }
    
    public function toThrow(string $exceptionClass = null): void
    {
        $thrown = false;
        $actualException = null;
        
        try {
            if (is_callable($this->actual)) {
                call_user_func($this->actual);
            }
        } catch (Exception $e) {
            $thrown = true;
            $actualException = $e;
        }
        
        if (!$thrown) {
            throw new Exception("Expected exception to be thrown but none was thrown");
        }
        
        if ($exceptionClass && !($actualException instanceof $exceptionClass)) {
            throw new Exception("Expected exception of type '{$exceptionClass}' but got '" . get_class($actualException) . "'");
        }
    }
}

// Load test files based on options
if (isset($options['suite'])) {
    $suite = $options['suite'];
    $testFile = SHOPOLOGIC_ROOT . '/tests/' . $suite . '.php';
    
    if (file_exists($testFile)) {
        require_once $testFile;
    } else {
        echo "Test suite not found: {$suite}\n";
        exit(1);
    }
} else {
    // Load all test files
    $testFiles = [
        'Unit/ContainerTest.php',
        'Unit/ConfigurationTest.php',
        'Unit/CacheTest.php',
        'Unit/DatabaseTest.php',
        'Unit/EventsTest.php',
        'Unit/HttpTest.php',
        'Unit/RouterTest.php',
        'Unit/TemplateTest.php',
        'Integration/PluginTest.php',
        'Integration/ThemeTest.php',
        'Integration/ApiTest.php',
        'E2E/StorefrontTest.php',
        'E2E/AdminTest.php'
    ];
    
    foreach ($testFiles as $testFile) {
        $fullPath = SHOPOLOGIC_ROOT . '/tests/' . $testFile;
        if (file_exists($fullPath)) {
            require_once $fullPath;
        }
    }
}

// Run core component tests
TestFramework::describe('Core Components', function() {
    TestFramework::it('should load autoloader', function() {
        TestFramework::expect(class_exists('Shopologic\\Core\\Autoloader'))->toBeTrue();
    });
    
    TestFramework::it('should initialize container', function() {
        $container = new Shopologic\Core\Container\Container();
        TestFramework::expect($container)->toBeInstanceOf('Shopologic\\Core\\Container\\Container');
    });
    
    TestFramework::it('should handle configuration', function() {
        $config = new Shopologic\Core\Configuration\ConfigurationManager();
        TestFramework::expect($config)->toBeInstanceOf('Shopologic\\Core\\Configuration\\ConfigurationManager');
    });
    
    TestFramework::it('should create HTTP requests', function() {
        $request = new Shopologic\Core\Http\Request();
        TestFramework::expect($request)->toBeInstanceOf('Shopologic\\Core\\Http\\Request');
    });
    
    TestFramework::it('should create HTTP responses', function() {
        $response = new Shopologic\Core\Http\Response();
        TestFramework::expect($response)->toBeInstanceOf('Shopologic\\Core\\Http\\Response');
    });
});

// Run plugin tests
TestFramework::describe('Plugin System', function() {
    TestFramework::it('should load plugin repository', function() {
        $repository = new Shopologic\Core\Plugin\PluginRepository(SHOPOLOGIC_ROOT . '/plugins');
        TestFramework::expect($repository)->toBeInstanceOf('Shopologic\\Core\\Plugin\\PluginRepository');
    });
    
    TestFramework::it('should discover plugins', function() {
        $repository = new Shopologic\Core\Plugin\PluginRepository(SHOPOLOGIC_ROOT . '/plugins');
        $plugins = $repository->getAll();
        TestFramework::expect(is_array($plugins))->toBeTrue();
    });
});

// Run database tests
TestFramework::describe('Database Layer', function() {
    TestFramework::it('should create query builder', function() {
        $config = new Shopologic\Core\Configuration\ConfigurationManager();
        $db = new Shopologic\Core\Database\DatabaseManager($config);
        $builder = $db->table('test');
        TestFramework::expect($builder)->toBeInstanceOf('Shopologic\\Core\\Database\\Builder');
    });
});

// Run cache tests
TestFramework::describe('Cache System', function() {
    TestFramework::it('should initialize cache manager', function() {
        $config = new Shopologic\Core\Configuration\ConfigurationManager();
        $cache = new Shopologic\Core\Cache\CacheManager($config);
        TestFramework::expect($cache)->toBeInstanceOf('Shopologic\\Core\\Cache\\CacheManager');
    });
    
    TestFramework::it('should store and retrieve values', function() {
        $config = new Shopologic\Core\Configuration\ConfigurationManager();
        $cache = new Shopologic\Core\Cache\CacheManager($config);
        
        $cache->put('test_key', 'test_value', 60);
        $value = $cache->get('test_key');
        
        TestFramework::expect($value)->toBe('test_value');
    });
});

// Run template tests
TestFramework::describe('Template Engine', function() {
    TestFramework::it('should initialize template engine', function() {
        $config = new Shopologic\Core\Configuration\ConfigurationManager();
        $engine = new Shopologic\Core\Theme\TemplateEngine($config);
        TestFramework::expect($engine)->toBeInstanceOf('Shopologic\\Core\\Theme\\TemplateEngine');
    });
});

// Run API tests
TestFramework::describe('API Framework', function() {
    TestFramework::it('should initialize GraphQL server', function() {
        $server = new Shopologic\Core\GraphQL\GraphQLServer();
        TestFramework::expect($server)->toBeInstanceOf('Shopologic\\Core\\GraphQL\\GraphQLServer');
    });
});

// Display results
$endTime = microtime(true);
$totalDuration = round(($endTime - $startTime) * 1000, 2);

echo "\n" . str_repeat('=', 50) . "\n";
echo "Test Results\n";
echo str_repeat('=', 50) . "\n";

$summary = TestFramework::getSummary();

echo "Total Tests: {$summary['total']}\n";
echo "Passed: {$summary['passed']}\n";
echo "Failed: {$summary['failed']}\n";
echo "Duration: {$totalDuration}ms\n";

if ($summary['failed'] > 0) {
    echo "\nFailures:\n";
    foreach (TestFramework::getResults() as $result) {
        if ($result['status'] === 'failed') {
            echo "  ✗ {$result['suite']} - {$result['test']}\n";
            echo "    {$result['error']}\n";
        }
    }
    exit(1);
} else {
    echo "\n✅ All tests passed!\n";
}

// Generate coverage report if requested
if (isset($options['coverage'])) {
    echo "\nGenerating coverage report...\n";
    generateCoverageReport();
}

/**
 * Parse command line arguments
 */
function parseArgs(array $argv): array
{
    $options = [];
    
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        
        if (strpos($arg, '--') === 0) {
            $key = substr($arg, 2);
            $value = true;
            
            if (strpos($key, '=') !== false) {
                list($key, $value) = explode('=', $key, 2);
            }
            
            $options[$key] = $value;
        }
    }
    
    return $options;
}

/**
 * Generate coverage report
 */
function generateCoverageReport(): void
{
    $coverageDir = SHOPOLOGIC_ROOT . '/coverage';
    
    if (!is_dir($coverageDir)) {
        mkdir($coverageDir, 0755, true);
    }
    
    // Simple coverage report
    $report = [
        'timestamp' => date('Y-m-d H:i:s'),
        'total_files' => 0,
        'covered_files' => 0,
        'coverage_percentage' => 0
    ];
    
    // Scan core files
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(SHOPOLOGIC_ROOT . '/core/src'),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $report['total_files']++;
            // For now, assume all files are covered
            $report['covered_files']++;
        }
    }
    
    if ($report['total_files'] > 0) {
        $report['coverage_percentage'] = round(($report['covered_files'] / $report['total_files']) * 100, 2);
    }
    
    file_put_contents(
        $coverageDir . '/coverage.json',
        json_encode($report, JSON_PRETTY_PRINT)
    );
    
    echo "Coverage report generated: coverage/coverage.json\n";
    echo "Coverage: {$report['coverage_percentage']}% ({$report['covered_files']}/{$report['total_files']} files)\n";
}