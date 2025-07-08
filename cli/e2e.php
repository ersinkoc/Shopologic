<?php

declare(strict_types=1);

/**
 * Shopologic E2E Test Runner
 * 
 * Runs end-to-end tests for the platform
 */

define('SHOPOLOGIC_ROOT', dirname(__DIR__));

require_once SHOPOLOGIC_ROOT . '/core/src/Autoloader.php';
require_once SHOPOLOGIC_ROOT . '/tests/E2E/TestFramework/E2ETestCase.php';
require_once SHOPOLOGIC_ROOT . '/tests/E2E/TestFramework/Browser.php';

use Shopologic\Core\Autoloader;

Autoloader::register();

$command = $argv[1] ?? 'help';

switch ($command) {
    case 'run':
        runTests($argv[2] ?? null, array_slice($argv, 3));
        break;
        
    case 'list':
        listTests();
        break;
        
    case 'setup':
        setupE2E();
        break;
        
    case 'cleanup':
        cleanupE2E();
        break;
        
    case 'report':
        generateReport();
        break;
        
    case 'help':
    default:
        showHelp();
        break;
}

/**
 * Run E2E tests
 */
function runTests(?string $testName = null, array $options = []): void
{
    echo "Running E2E tests...\n\n";
    
    // Parse options
    $headless = !in_array('--headed', $options);
    $parallel = in_array('--parallel', $options);
    $verbose = in_array('--verbose', $options);
    
    // Get test files
    $testDir = SHOPOLOGIC_ROOT . '/tests/E2E';
    $testFiles = [];
    
    if ($testName) {
        // Run specific test
        if (str_ends_with($testName, '.php')) {
            $testFiles[] = $testDir . '/' . $testName;
        } else {
            $testFiles[] = $testDir . '/' . $testName . '.php';
        }
    } else {
        // Run all tests
        $testFiles = glob($testDir . '/*Test.php');
    }
    
    // Validate test files
    $validTestFiles = [];
    foreach ($testFiles as $file) {
        if (file_exists($file)) {
            $validTestFiles[] = $file;
        } else {
            echo "❌ Test file not found: $file\n";
        }
    }
    
    if (empty($validTestFiles)) {
        echo "No test files found.\n";
        exit(1);
    }
    
    // Set up test environment
    setupTestEnvironment($headless);
    
    // Run tests
    $results = [];
    $startTime = microtime(true);
    
    foreach ($validTestFiles as $testFile) {
        $testClass = getTestClass($testFile);
        
        if (!$testClass) {
            echo "❌ Could not load test class from: $testFile\n";
            continue;
        }
        
        $result = runTestClass($testClass, $verbose);
        $results[$testClass] = $result;
    }
    
    $totalTime = microtime(true) - $startTime;
    
    // Display results
    displayResults($results, $totalTime);
    
    // Generate report
    generateTestReport($results);
    
    // Exit with appropriate code
    $hasFailures = array_filter($results, fn($r) => !$r['success']);
    exit(empty($hasFailures) ? 0 : 1);
}

/**
 * List available tests
 */
function listTests(): void
{
    echo "Available E2E Tests:\n";
    echo "===================\n\n";
    
    $testDir = SHOPOLOGIC_ROOT . '/tests/E2E';
    $testFiles = glob($testDir . '/*Test.php');
    
    foreach ($testFiles as $file) {
        $basename = basename($file);
        $testName = str_replace('.php', '', $basename);
        
        echo "• $testName\n";
        
        // Get test methods
        $class = getTestClass($file);
        if ($class) {
            $reflection = new ReflectionClass($class);
            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
            
            foreach ($methods as $method) {
                if (str_starts_with($method->getName(), 'test')) {
                    echo "  - {$method->getName()}\n";
                }
            }
        }
        
        echo "\n";
    }
    
    echo "Run a specific test: php cli/e2e.php run TestName\n";
    echo "Run all tests: php cli/e2e.php run\n";
}

/**
 * Setup E2E testing environment
 */
function setupE2E(): void
{
    echo "Setting up E2E testing environment...\n";
    
    // Create test database
    echo "Creating test database...\n";
    exec('php cli/migrate.php fresh --env=test', $output, $returnCode);
    
    if ($returnCode !== 0) {
        echo "❌ Failed to create test database\n";
        echo implode("\n", $output) . "\n";
        exit(1);
    }
    
    // Seed test data
    echo "Seeding test data...\n";
    exec('php cli/seed.php run --env=test', $output, $returnCode);
    
    if ($returnCode !== 0) {
        echo "❌ Failed to seed test data\n";
        exit(1);
    }
    
    // Create test directories
    $directories = [
        SHOPOLOGIC_ROOT . '/tests/screenshots',
        SHOPOLOGIC_ROOT . '/tests/reports',
        SHOPOLOGIC_ROOT . '/tests/fixtures'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            echo "Created directory: $dir\n";
        }
    }
    
    // Create test configuration
    $configFile = SHOPOLOGIC_ROOT . '/tests/e2e-config.json';
    
    if (!file_exists($configFile)) {
        $config = [
            'base_url' => 'http://localhost:17000',
            'headless' => true,
            'timeout' => 30,
            'viewport' => [
                'width' => 1920,
                'height' => 1080
            ],
            'database' => [
                'host' => 'localhost',
                'port' => 5432,
                'database' => 'shopologic_test',
                'username' => 'postgres',
                'password' => ''
            ]
        ];
        
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
        echo "Created E2E configuration: $configFile\n";
    }
    
    // Create test fixtures
    createTestFixtures();
    
    echo "\n✅ E2E testing environment setup complete!\n";
    echo "Run tests with: php cli/e2e.php run\n";
}

/**
 * Cleanup E2E testing environment
 */
function cleanupE2E(): void
{
    echo "Cleaning up E2E testing environment...\n";
    
    // Clean screenshots
    $screenshots = glob(SHOPOLOGIC_ROOT . '/tests/screenshots/*.png');
    foreach ($screenshots as $file) {
        unlink($file);
    }
    echo "Cleaned " . count($screenshots) . " screenshots\n";
    
    // Clean test uploads
    $uploads = glob(SHOPOLOGIC_ROOT . '/storage/uploads/test/*');
    foreach ($uploads as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    echo "Cleaned " . count($uploads) . " test uploads\n";
    
    // Clean test logs
    $logs = glob(SHOPOLOGIC_ROOT . '/storage/logs/test-*.log');
    foreach ($logs as $file) {
        unlink($file);
    }
    echo "Cleaned " . count($logs) . " test logs\n";
    
    echo "\n✅ Cleanup complete!\n";
}

/**
 * Generate test report
 */
function generateReport(): void
{
    $reportFile = SHOPOLOGIC_ROOT . '/tests/reports/latest.json';
    
    if (!file_exists($reportFile)) {
        echo "No test report found. Run tests first.\n";
        exit(1);
    }
    
    $report = json_decode(file_get_contents($reportFile), true);
    
    echo "E2E Test Report\n";
    echo "===============\n\n";
    echo "Date: {$report['date']}\n";
    echo "Duration: " . round($report['duration'], 2) . "s\n";
    echo "Total Tests: {$report['total_tests']}\n";
    echo "Passed: {$report['passed']}\n";
    echo "Failed: {$report['failed']}\n";
    echo "Success Rate: {$report['success_rate']}%\n\n";
    
    if (!empty($report['failures'])) {
        echo "Failures:\n";
        echo "---------\n";
        foreach ($report['failures'] as $failure) {
            echo "• {$failure['test']}::{$failure['method']}\n";
            echo "  Error: {$failure['error']}\n\n";
        }
    }
    
    // Generate HTML report
    generateHtmlReport($report);
}

/**
 * Show help information
 */
function showHelp(): void
{
    echo "Shopologic E2E Test Runner\n";
    echo "========================\n\n";
    echo "Usage: php cli/e2e.php <command> [options]\n\n";
    echo "Commands:\n";
    echo "  run [test]      Run E2E tests (optionally specify test name)\n";
    echo "  list            List available tests\n";
    echo "  setup           Set up E2E testing environment\n";
    echo "  cleanup         Clean up test artifacts\n";
    echo "  report          Generate test report\n";
    echo "  help            Show this help\n\n";
    echo "Options:\n";
    echo "  --headed        Run tests in headed mode (show browser)\n";
    echo "  --parallel      Run tests in parallel\n";
    echo "  --verbose       Show detailed output\n\n";
    echo "Examples:\n";
    echo "  php cli/e2e.php run                    # Run all tests\n";
    echo "  php cli/e2e.php run CustomerJourneyTest # Run specific test\n";
    echo "  php cli/e2e.php run --headed           # Run with visible browser\n";
}

/**
 * Get test class from file
 */
function getTestClass(string $file): ?string
{
    $content = file_get_contents($file);
    
    if (preg_match('/namespace\s+([^;]+);/', $content, $nsMatch) &&
        preg_match('/class\s+(\w+)/', $content, $classMatch)) {
        
        $namespace = $nsMatch[1];
        $className = $classMatch[1];
        $fullClass = $namespace . '\\' . $className;
        
        require_once $file;
        
        if (class_exists($fullClass)) {
            return $fullClass;
        }
    }
    
    return null;
}

/**
 * Run test class
 */
function runTestClass(string $class, bool $verbose = false): array
{
    $reflection = new ReflectionClass($class);
    $instance = $reflection->newInstance();
    
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    $testMethods = array_filter($methods, fn($m) => str_starts_with($m->getName(), 'test'));
    
    $results = [
        'class' => $class,
        'success' => true,
        'total' => count($testMethods),
        'passed' => 0,
        'failed' => 0,
        'tests' => []
    ];
    
    echo "Running " . basename($class) . "...\n";
    
    foreach ($testMethods as $method) {
        $methodName = $method->getName();
        $startTime = microtime(true);
        
        try {
            // Setup
            if ($reflection->hasMethod('setUp')) {
                $instance->setUp();
            }
            
            // Run test
            $instance->$methodName();
            
            $duration = microtime(true) - $startTime;
            
            $results['tests'][$methodName] = [
                'success' => true,
                'duration' => $duration
            ];
            
            $results['passed']++;
            
            if ($verbose) {
                echo "  ✅ $methodName (" . round($duration, 2) . "s)\n";
            } else {
                echo "  ✅ $methodName\n";
            }
            
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            
            $results['tests'][$methodName] = [
                'success' => false,
                'duration' => $duration,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
            
            $results['failed']++;
            $results['success'] = false;
            
            echo "  ❌ $methodName\n";
            if ($verbose) {
                echo "     Error: " . $e->getMessage() . "\n";
            }
            
            // Take screenshot on failure
            if (property_exists($instance, 'browser') && $instance->browser) {
                $screenshotName = $class . '_' . $methodName . '_failure';
                $instance->takeScreenshot($screenshotName);
            }
            
        } finally {
            // Teardown
            if ($reflection->hasMethod('tearDown')) {
                $instance->tearDown();
            }
        }
    }
    
    echo "\n";
    
    return $results;
}

/**
 * Setup test environment
 */
function setupTestEnvironment(bool $headless): void
{
    // Set environment variables
    putenv('E2E_HEADLESS=' . ($headless ? 'true' : 'false'));
    putenv('APP_ENV=test');
    
    // Ensure test server is running
    $baseUrl = 'http://localhost:17000';
    $ch = curl_init($baseUrl);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 0) {
        echo "⚠️  Test server not running. Start it with: php -S localhost:17000 -t public/\n\n";
    }
}

/**
 * Display test results
 */
function displayResults(array $results, float $totalTime): void
{
    $totalTests = 0;
    $totalPassed = 0;
    $totalFailed = 0;
    
    foreach ($results as $result) {
        $totalTests += $result['total'];
        $totalPassed += $result['passed'];
        $totalFailed += $result['failed'];
    }
    
    echo "\n";
    echo "Test Results\n";
    echo "============\n\n";
    echo "Total Tests: $totalTests\n";
    echo "Passed: $totalPassed\n";
    echo "Failed: $totalFailed\n";
    echo "Duration: " . round($totalTime, 2) . "s\n";
    echo "Success Rate: " . round(($totalPassed / $totalTests) * 100, 2) . "%\n";
    
    if ($totalFailed > 0) {
        echo "\n❌ Tests failed!\n";
    } else {
        echo "\n✅ All tests passed!\n";
    }
}

/**
 * Generate test report
 */
function generateTestReport(array $results): void
{
    $report = [
        'date' => date('Y-m-d H:i:s'),
        'duration' => 0,
        'total_tests' => 0,
        'passed' => 0,
        'failed' => 0,
        'success_rate' => 0,
        'results' => [],
        'failures' => []
    ];
    
    foreach ($results as $class => $result) {
        $report['total_tests'] += $result['total'];
        $report['passed'] += $result['passed'];
        $report['failed'] += $result['failed'];
        
        $report['results'][$class] = $result;
        
        // Collect failures
        foreach ($result['tests'] as $method => $test) {
            if (!$test['success']) {
                $report['failures'][] = [
                    'test' => $class,
                    'method' => $method,
                    'error' => $test['error'] ?? 'Unknown error'
                ];
            }
        }
    }
    
    $report['success_rate'] = $report['total_tests'] > 0 
        ? round(($report['passed'] / $report['total_tests']) * 100, 2)
        : 0;
    
    // Save JSON report
    $reportDir = SHOPOLOGIC_ROOT . '/tests/reports';
    if (!is_dir($reportDir)) {
        mkdir($reportDir, 0755, true);
    }
    
    $reportFile = $reportDir . '/latest.json';
    file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
    
    // Archive with timestamp
    $archiveFile = $reportDir . '/report_' . date('Y-m-d_H-i-s') . '.json';
    copy($reportFile, $archiveFile);
}

/**
 * Generate HTML report
 */
function generateHtmlReport(array $report): void
{
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>E2E Test Report - {$report['date']}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .summary { background: #f0f0f0; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .passed { color: #28a745; }
        .failed { color: #dc3545; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f8f9fa; }
        .test-passed { background: #d4edda; }
        .test-failed { background: #f8d7da; }
    </style>
</head>
<body>
    <h1>E2E Test Report</h1>
    
    <div class="summary">
        <h2>Summary</h2>
        <p>Date: {$report['date']}</p>
        <p>Total Tests: {$report['total_tests']}</p>
        <p class="passed">Passed: {$report['passed']}</p>
        <p class="failed">Failed: {$report['failed']}</p>
        <p>Success Rate: {$report['success_rate']}%</p>
    </div>
    
    <h2>Test Results</h2>
    <table>
        <tr>
            <th>Test Class</th>
            <th>Method</th>
            <th>Status</th>
            <th>Duration</th>
        </tr>
HTML;
    
    foreach ($report['results'] as $class => $result) {
        foreach ($result['tests'] as $method => $test) {
            $status = $test['success'] ? 'PASS' : 'FAIL';
            $rowClass = $test['success'] ? 'test-passed' : 'test-failed';
            $duration = round($test['duration'], 2) . 's';
            
            $html .= <<<HTML
        <tr class="{$rowClass}">
            <td>{$class}</td>
            <td>{$method}</td>
            <td>{$status}</td>
            <td>{$duration}</td>
        </tr>
HTML;
        }
    }
    
    $html .= <<<HTML
    </table>
    
    <h2>Failures</h2>
HTML;
    
    if (empty($report['failures'])) {
        $html .= '<p>No failures!</p>';
    } else {
        foreach ($report['failures'] as $failure) {
            $html .= <<<HTML
    <div style="margin: 20px 0; padding: 10px; background: #f8d7da; border-radius: 5px;">
        <h3>{$failure['test']}::{$failure['method']}</h3>
        <pre>{$failure['error']}</pre>
    </div>
HTML;
        }
    }
    
    $html .= <<<HTML
</body>
</html>
HTML;
    
    $htmlFile = SHOPOLOGIC_ROOT . '/tests/reports/report.html';
    file_put_contents($htmlFile, $html);
    
    echo "\nHTML report generated: $htmlFile\n";
}

/**
 * Create test fixtures
 */
function createTestFixtures(): void
{
    $fixturesDir = SHOPOLOGIC_ROOT . '/tests/fixtures';
    
    // Create sample product image
    $imageFile = $fixturesDir . '/product-image.jpg';
    if (!file_exists($imageFile)) {
        // Create a simple 100x100 white image
        $image = imagecreate(100, 100);
        imagecolorallocate($image, 255, 255, 255);
        imagejpeg($image, $imageFile);
        imagedestroy($image);
        echo "Created test image: $imageFile\n";
    }
    
    // Create sample tax exempt certificate
    $taxFile = $fixturesDir . '/tax-exempt.pdf';
    if (!file_exists($taxFile)) {
        file_put_contents($taxFile, '%PDF-1.4 Sample Tax Exempt Certificate');
        echo "Created test PDF: $taxFile\n";
    }
}