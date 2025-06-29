<?php

declare(strict_types=1);

namespace Shopologic\Tests\E2E\TestFramework;

/**
 * Base E2E Test Case
 * 
 * Provides common functionality for end-to-end tests
 */
abstract class E2ETestCase
{
    protected ?Browser $browser = null;
    protected array $config = [];
    
    /**
     * Set up test environment
     */
    public function setUp(): void
    {
        $this->config = $this->loadConfig();
        $this->resetTestDatabase();
        $this->seedTestData();
    }
    
    /**
     * Tear down test environment
     */
    public function tearDown(): void
    {
        if ($this->browser) {
            $this->browser->quit();
            $this->browser = null;
        }
        
        $this->cleanupTestData();
    }
    
    /**
     * Create browser instance
     */
    protected function createBrowser(array $options = []): Browser
    {
        $defaultOptions = [
            'baseUrl' => $this->config['base_url'] ?? 'http://localhost:8000',
            'headless' => $this->config['headless'] ?? true,
            'viewport' => $options['viewport'] ?? ['width' => 1920, 'height' => 1080],
            'userAgent' => $options['userAgent'] ?? null,
            'timeout' => $this->config['timeout'] ?? 30
        ];
        
        $this->browser = new Browser(array_merge($defaultOptions, $options));
        
        return $this->browser;
    }
    
    /**
     * Assert page contains text
     */
    protected function assertPageContains(Browser $browser, string $text): void
    {
        $pageSource = $browser->getPageSource();
        
        if (strpos($pageSource, $text) === false) {
            throw new \AssertionError("Page does not contain text: {$text}");
        }
    }
    
    /**
     * Assert element exists
     */
    protected function assertElementExists(Browser $browser, string $selector): void
    {
        if (!$browser->elementExists($selector)) {
            throw new \AssertionError("Element does not exist: {$selector}");
        }
    }
    
    /**
     * Assert element does not exist
     */
    protected function assertNotElementExists(Browser $browser, string $selector): void
    {
        if ($browser->elementExists($selector)) {
            throw new \AssertionError("Element should not exist: {$selector}");
        }
    }
    
    /**
     * Assert element is visible
     */
    protected function assertElementVisible(Browser $browser, string $selector): void
    {
        if (!$browser->isElementVisible($selector)) {
            throw new \AssertionError("Element is not visible: {$selector}");
        }
    }
    
    /**
     * Assert element text contains
     */
    protected function assertElementTextContains(Browser $browser, string $selector, string $text): void
    {
        $elementText = $browser->getText($selector);
        
        if (strpos($elementText, $text) === false) {
            throw new \AssertionError("Element {$selector} does not contain text: {$text}. Found: {$elementText}");
        }
    }
    
    /**
     * Assert element count
     */
    protected function assertElementCount(Browser $browser, string $selector, string $operator, int $count): void
    {
        $actualCount = $browser->countElements($selector);
        
        $result = false;
        switch ($operator) {
            case '=':
            case '==':
                $result = $actualCount == $count;
                break;
            case '>':
                $result = $actualCount > $count;
                break;
            case '<':
                $result = $actualCount < $count;
                break;
            case '>=':
                $result = $actualCount >= $count;
                break;
            case '<=':
                $result = $actualCount <= $count;
                break;
        }
        
        if (!$result) {
            throw new \AssertionError("Element count assertion failed: {$actualCount} {$operator} {$count}");
        }
    }
    
    /**
     * Assert element CSS property
     */
    protected function assertElementCss(Browser $browser, string $selector, string $property, string $value): void
    {
        $actualValue = $browser->getCssValue($selector, $property);
        
        if ($actualValue !== $value) {
            throw new \AssertionError("Element {$selector} CSS {$property} is '{$actualValue}', expected '{$value}'");
        }
    }
    
    /**
     * Assert equals
     */
    protected function assertEquals($expected, $actual, string $message = ''): void
    {
        if ($expected != $actual) {
            $message = $message ?: "Expected {$expected}, got {$actual}";
            throw new \AssertionError($message);
        }
    }
    
    /**
     * Load test configuration
     */
    private function loadConfig(): array
    {
        $configFile = dirname(__DIR__, 3) . '/tests/e2e-config.json';
        
        if (!file_exists($configFile)) {
            // Default configuration
            return [
                'base_url' => getenv('E2E_BASE_URL') ?: 'http://localhost:8000',
                'headless' => getenv('E2E_HEADLESS') !== 'false',
                'timeout' => (int)(getenv('E2E_TIMEOUT') ?: 30),
                'database' => [
                    'host' => getenv('DB_HOST') ?: 'localhost',
                    'port' => getenv('DB_PORT') ?: '5432',
                    'database' => getenv('DB_DATABASE') ?: 'shopologic_test',
                    'username' => getenv('DB_USERNAME') ?: 'postgres',
                    'password' => getenv('DB_PASSWORD') ?: ''
                ]
            ];
        }
        
        return json_decode(file_get_contents($configFile), true);
    }
    
    /**
     * Reset test database
     */
    private function resetTestDatabase(): void
    {
        // Run migrations
        exec('php cli/migrate.php fresh --env=test 2>&1', $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new \RuntimeException('Failed to reset test database: ' . implode("\n", $output));
        }
    }
    
    /**
     * Seed test data
     */
    private function seedTestData(): void
    {
        // Run seeders
        exec('php cli/seed.php run --env=test 2>&1', $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new \RuntimeException('Failed to seed test data: ' . implode("\n", $output));
        }
    }
    
    /**
     * Cleanup test data
     */
    private function cleanupTestData(): void
    {
        // Clean up any test files, logs, etc.
        $testUploads = dirname(__DIR__, 3) . '/storage/uploads/test/*';
        array_map('unlink', glob($testUploads));
    }
    
    /**
     * Take screenshot for debugging
     */
    protected function takeScreenshot(string $name): void
    {
        if ($this->browser) {
            $screenshotDir = dirname(__DIR__, 3) . '/tests/screenshots';
            
            if (!is_dir($screenshotDir)) {
                mkdir($screenshotDir, 0755, true);
            }
            
            $filename = $screenshotDir . '/' . date('Y-m-d_H-i-s') . '_' . $name . '.png';
            $this->browser->screenshot($filename);
        }
    }
    
    /**
     * Wait for AJAX requests to complete
     */
    protected function waitForAjax(Browser $browser, int $timeout = 5): void
    {
        $browser->waitFor(function() use ($browser) {
            return $browser->executeScript('return jQuery.active == 0');
        }, $timeout);
    }
    
    /**
     * Execute JavaScript
     */
    protected function executeScript(Browser $browser, string $script)
    {
        return $browser->executeScript($script);
    }
}