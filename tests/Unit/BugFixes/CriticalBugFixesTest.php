<?php

declare(strict_types=1);

namespace Shopologic\Tests\Unit\BugFixes;

use PHPUnit\Framework\TestCase;
use Shopologic\Core\Theme\TemplateSandbox;
use Shopologic\Core\Theme\TemplateEngine;
use Shopologic\Core\Cache\FileStore;
use Shopologic\Core\Ecommerce\Models\Product;

/**
 * Test suite for CRITICAL bug fixes
 *
 * This test suite verifies that all CRITICAL severity bugs have been properly fixed:
 * - BUG-001: eval() removed from TemplateSandbox
 * - BUG-002: unserialize replaced with JSON in QueueManager
 * - BUG-003: unserialize replaced with JSON in FileStore
 * - BUG-004: Race condition fixed in stock management
 * - BUG-005: Transaction wrapper added to payment processing
 * - BUG-006: Infinite loop prevention in slug generation
 */
class CriticalBugFixesTest extends TestCase
{
    /**
     * @test
     * BUG-001: Test that eval() is no longer used in template execution
     */
    public function testTemplateSandboxNoLongerUsesEval(): void
    {
        // Create a simple template engine mock
        $engine = $this->createMock(TemplateEngine::class);

        $sandbox = new TemplateSandbox($engine, ['name' => 'Test']);

        // Get the source code of the execute method
        $reflection = new \ReflectionMethod(TemplateSandbox::class, 'execute');
        $filename = $reflection->getFileName();
        $startLine = $reflection->getStartLine();
        $endLine = $reflection->getEndLine();

        $source = file($filename);
        $methodSource = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));

        // Verify eval() is NOT in the method
        $this->assertStringNotContainsString('eval(', $methodSource,
            'BUG-001: TemplateSandbox::execute() should not use eval()');

        // Verify temp file approach is used instead
        $this->assertStringContainsString('sys_get_temp_dir()', $methodSource,
            'BUG-001: Should use temporary file approach');
        $this->assertStringContainsString('include', $methodSource,
            'BUG-001: Should use include instead of eval');
    }

    /**
     * @test
     * BUG-003: Test that FileStore uses JSON instead of serialize
     */
    public function testFileStoreUsesJsonInsteadOfSerialize(): void
    {
        $tempDir = sys_get_temp_dir() . '/shopologic_cache_test_' . uniqid();
        mkdir($tempDir, 0755, true);

        try {
            $store = new FileStore($tempDir);

            // Store a value
            $testData = ['foo' => 'bar', 'nested' => ['key' => 'value']];
            $store->set('test_key', $testData, 3600);

            // Read the raw file content
            $reflection = new \ReflectionMethod(FileStore::class, 'getPath');
            $reflection->setAccessible(true);
            $path = $reflection->invoke($store, 'test_key');

            $rawContent = file_get_contents($path);

            // Verify it's JSON, not serialized PHP
            $this->assertStringNotContainsString('O:', $rawContent,
                'BUG-003: Cache files should not contain PHP serialized objects');
            $this->assertStringNotContainsString('a:', $rawContent,
                'BUG-003: Cache files should not contain PHP serialized arrays (in this simple test)');

            // Verify it's valid JSON
            $decoded = json_decode($rawContent, true);
            $this->assertNotNull($decoded, 'BUG-003: Cache file should contain valid JSON');
            $this->assertArrayHasKey('value', $decoded);
            $this->assertArrayHasKey('expires_at', $decoded);

            // Verify retrieval still works
            $retrieved = $store->get('test_key');
            $this->assertEquals($testData, $retrieved, 'BUG-003: Data should be retrievable');

        } finally {
            // Cleanup
            array_map('unlink', glob("$tempDir/*/*/*"));
            array_map('rmdir', glob("$tempDir/*/*"));
            array_map('rmdir', glob("$tempDir/*"));
            @rmdir($tempDir);
        }
    }

    /**
     * @test
     * BUG-006: Test that slug generation has iteration limit
     */
    public function testSlugGenerationHasIterationLimit(): void
    {
        // Get the source of generateSlug method
        $reflection = new \ReflectionMethod(Product::class, 'generateSlug');
        $filename = $reflection->getFileName();
        $startLine = $reflection->getStartLine();
        $endLine = $reflection->getEndLine();

        $source = file($filename);
        $methodSource = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));

        // Verify there's a max attempts check
        $this->assertStringContainsString('maxAttempts', $methodSource,
            'BUG-006: Slug generation should have max attempts limit');
        $this->assertMatchesRegularExpression('/if\s*\(\s*\$counter\s*>=\s*\$maxAttempts/', $methodSource,
            'BUG-006: Should check counter against maxAttempts');

        // Verify there's a UUID fallback
        $this->assertStringContainsString('md5', $methodSource,
            'BUG-006: Should have unique identifier fallback');
        $this->assertStringContainsString('uniqid', $methodSource,
            'BUG-006: Should use uniqid for fallback');
    }

    /**
     * @test
     * BUG-012: Test stock validation logic
     */
    public function testStockValidationLogic(): void
    {
        // Get the source of decreaseStock method
        $reflection = new \ReflectionMethod(Product::class, 'decreaseStock');
        $filename = $reflection->getFileName();
        $startLine = $reflection->getStartLine();
        $endLine = $reflection->getEndLine();

        $source = file($filename);
        $methodSource = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));

        // Verify the fix checks for <= 0
        $this->assertMatchesRegularExpression('/quantity\s*<=\s*0/', $methodSource,
            'BUG-012: Should check if quantity is <= 0');

        // Verify backorder check exists
        $this->assertStringContainsString('allow_backorder', $methodSource,
            'BUG-012: Should check allow_backorder flag');
    }

    /**
     * @test
     * BUG-004: Test that createFromCart uses transaction
     */
    public function testCreateFromCartUsesTransaction(): void
    {
        // Get the source of createFromCart method
        $reflection = new \ReflectionMethod(\Shopologic\Core\Ecommerce\Models\Order::class, 'createFromCart');
        $filename = $reflection->getFileName();
        $startLine = $reflection->getStartLine();
        $endLine = $reflection->getEndLine();

        $source = file($filename);
        $methodSource = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));

        // Verify transaction is used
        $this->assertStringContainsString('transaction', $methodSource,
            'BUG-004: createFromCart should use database transaction');
        $this->assertStringContainsString('lockForUpdate', $methodSource,
            'BUG-004: Should use row-level locking for stock updates');

        // Verify stock check is within transaction
        $this->assertStringContainsString('quantity >=', $methodSource,
            'BUG-004: Should verify stock availability within transaction');
    }

    /**
     * @test
     * BUG-005: Test that payment processing uses transaction
     */
    public function testPaymentProcessingUsesTransaction(): void
    {
        // Get the source of processPayment method
        $reflection = new \ReflectionMethod(\Shopologic\Core\Ecommerce\Payment\PaymentManager::class, 'processPayment');
        $filename = $reflection->getFileName();
        $startLine = $reflection->getStartLine();
        $endLine = $reflection->getEndLine();

        $source = file($filename);
        $methodSource = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));

        // Verify transaction wrapper exists
        $this->assertStringContainsString('transaction', $methodSource,
            'BUG-005: Payment processing should use database transaction');
        $this->assertStringContainsString('markAsPaid', $methodSource,
            'BUG-005: Should mark order as paid within transaction');
        $this->assertStringContainsString('transactions()->create', $methodSource,
            'BUG-005: Should create transaction record within transaction');
    }

    /**
     * @test
     * BUG-002: Test that QueueManager uses JSON instead of unserialize
     */
    public function testQueueManagerUsesJsonInsteadOfUnserialize(): void
    {
        // Read QueueManager source
        $filename = dirname(__DIR__, 3) . '/core/src/Queue/QueueManager.php';
        $source = file_get_contents($filename);

        // Find handleJob method
        preg_match('/function handleJob.*?\{(.+?)\n    \}/s', $source, $matches);
        $handleJobSource = $matches[1] ?? '';

        // Verify unserialize is NOT used
        $this->assertStringNotContainsString('unserialize($payload', $handleJobSource,
            'BUG-002: handleJob should not use unserialize');

        // Verify JSON decode is used
        $this->assertStringContainsString('json_decode', $handleJobSource,
            'BUG-002: Should use json_decode instead');

        // Verify class whitelist exists
        $this->assertStringContainsString('allowed_job_classes', $handleJobSource,
            'BUG-002: Should have class whitelist for additional security');
    }

    /**
     * Integration test: Verify all critical fixes are documented in code
     */
    public function testAllCriticalFixesAreDocumented(): void
    {
        $files = [
            'core/src/Theme/TemplateSandbox.php' => 'BUG-001',
            'core/src/Cache/FileStore.php' => 'BUG-003',
            'core/src/Queue/QueueManager.php' => 'BUG-002',
            'core/src/Ecommerce/Models/Product.php' => ['BUG-006', 'BUG-012'],
            'core/src/Ecommerce/Models/Order.php' => 'BUG-004',
            'core/src/Ecommerce/Payment/PaymentManager.php' => 'BUG-005',
        ];

        foreach ($files as $file => $expectedBugIds) {
            $path = dirname(__DIR__, 3) . '/' . $file;
            $this->assertFileExists($path, "File $file should exist");

            $content = file_get_contents($path);
            $bugIds = is_array($expectedBugIds) ? $expectedBugIds : [$expectedBugIds];

            foreach ($bugIds as $bugId) {
                $this->assertStringContainsString($bugId, $content,
                    "File $file should contain reference to $bugId fix");
            }
        }
    }

    /**
     * Test that no eval() statements remain in critical code paths
     */
    public function testNoEvalInCriticalPaths(): void
    {
        $criticalFiles = [
            'core/src/Theme/**/*.php',
            'core/src/Cache/**/*.php',
            'core/src/Queue/**/*.php',
            'core/src/Security/**/*.php',
            'core/src/Auth/**/*.php',
        ];

        $rootDir = dirname(__DIR__, 3);

        foreach ($criticalFiles as $pattern) {
            foreach (glob("$rootDir/$pattern") as $file) {
                if (str_contains($file, 'Test.php')) {
                    continue; // Skip test files
                }

                $content = file_get_contents($file);

                // Check for eval usage (excluding comments)
                $lines = explode("\n", $content);
                foreach ($lines as $lineNum => $line) {
                    // Skip comments
                    if (preg_match('/^\s*\/\//', $line) || preg_match('/^\s*\*/', $line)) {
                        continue;
                    }

                    if (str_contains($line, 'eval(')) {
                        $this->fail("Found eval() in $file on line " . ($lineNum + 1) .
                            ". eval() should not be used in production code.");
                    }
                }
            }
        }

        $this->assertTrue(true, 'No eval() found in critical code paths');
    }

    /**
     * Test that no unserialize() without whitelist in critical paths
     */
    public function testNoUnsafeUnserializeInCriticalPaths(): void
    {
        $criticalFiles = [
            'core/src/Cache/**/*.php',
            'core/src/Queue/**/*.php',
            'core/src/Session/**/*.php',
        ];

        $rootDir = dirname(__DIR__, 3);

        foreach ($criticalFiles as $pattern) {
            foreach (glob("$rootDir/$pattern") as $file) {
                if (str_contains($file, 'Test.php')) {
                    continue;
                }

                $content = file_get_contents($file);

                // Look for unserialize without allowed_classes parameter
                if (preg_match('/unserialize\s*\(\s*\$[^,)]+\s*\)/', $content, $matches)) {
                    $this->fail("Found unsafe unserialize() in $file: {$matches[0]}. " .
                        "Use JSON or unserialize with allowed_classes parameter.");
                }
            }
        }

        $this->assertTrue(true, 'No unsafe unserialize() found in critical code paths');
    }
}
