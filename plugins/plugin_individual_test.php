<?php

/**
 * Individual Plugin Testing Suite
 * Tests each plugin one by one with comprehensive validation
 */

declare(strict_types=1);

class IndividualPluginTest
{
    private string $pluginsDir;
    private array $plugins = [];
    private array $testResults = [];
    private int $totalPlugins = 0;
    private int $passedPlugins = 0;
    private int $failedPlugins = 0;
    private array $criticalFailures = [];
    
    public function __construct()
    {
        $this->pluginsDir = __DIR__;
    }
    
    public function testAllPluginsIndividually(): void
    {
        echo "🧪 Shopologic Individual Plugin Testing Suite\n";
        echo "============================================\n\n";
        echo "📋 Testing each plugin individually for complete validation\n\n";
        
        $this->discoverPlugins();
        $this->testEachPlugin();
        $this->generateDetailedReport();
    }
    
    private function discoverPlugins(): void
    {
        $directories = glob($this->pluginsDir . '/*', GLOB_ONLYDIR);
        
        foreach ($directories as $dir) {
            $pluginName = basename($dir);
            if ($pluginName === 'shared') continue;
            
            $pluginJsonPath = $dir . '/plugin.json';
            if (file_exists($pluginJsonPath)) {
                $manifest = json_decode(file_get_contents($pluginJsonPath), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->plugins[$pluginName] = [
                        'name' => $pluginName,
                        'path' => $dir,
                        'manifest' => $manifest
                    ];
                }
            }
        }
        
        $this->totalPlugins = count($this->plugins);
        echo "📦 Found {$this->totalPlugins} plugins to test individually\n";
        echo "═══════════════════════════════════════════════\n\n";
    }
    
    private function testEachPlugin(): void
    {
        $pluginNumber = 0;
        
        foreach ($this->plugins as $pluginName => $plugin) {
            $pluginNumber++;
            echo "🔍 [{$pluginNumber}/{$this->totalPlugins}] Testing: {$pluginName}\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            
            $testResult = [
                'plugin' => $pluginName,
                'timestamp' => date('Y-m-d H:i:s'),
                'tests' => [],
                'passed' => 0,
                'failed' => 0,
                'score' => 0,
                'status' => 'unknown'
            ];
            
            // 1. Manifest Validation
            $manifestTest = $this->testManifest($plugin);
            $testResult['tests']['manifest'] = $manifestTest;
            $this->displayTestResult('Manifest Validation', $manifestTest);
            
            // 2. File Structure Test
            $structureTest = $this->testFileStructure($plugin);
            $testResult['tests']['structure'] = $structureTest;
            $this->displayTestResult('File Structure', $structureTest);
            
            // 3. Bootstrap Test
            $bootstrapTest = $this->testBootstrap($plugin);
            $testResult['tests']['bootstrap'] = $bootstrapTest;
            $this->displayTestResult('Bootstrap File', $bootstrapTest);
            
            // 4. PHP Syntax Test
            $syntaxTest = $this->testPhpSyntax($plugin);
            $testResult['tests']['syntax'] = $syntaxTest;
            $this->displayTestResult('PHP Syntax Check', $syntaxTest);
            
            // 5. Namespace Test
            $namespaceTest = $this->testNamespaces($plugin);
            $testResult['tests']['namespace'] = $namespaceTest;
            $this->displayTestResult('Namespace Compliance', $namespaceTest);
            
            // 6. Security Test
            $securityTest = $this->testSecurity($plugin);
            $testResult['tests']['security'] = $securityTest;
            $this->displayTestResult('Security Scan', $securityTest);
            
            // 7. Documentation Test
            $docTest = $this->testDocumentation($plugin);
            $testResult['tests']['documentation'] = $docTest;
            $this->displayTestResult('Documentation', $docTest);
            
            // 8. Dependencies Test
            $depsTest = $this->testDependencies($plugin);
            $testResult['tests']['dependencies'] = $depsTest;
            $this->displayTestResult('Dependencies', $depsTest);
            
            // 9. Assets Test
            $assetsTest = $this->testAssets($plugin);
            $testResult['tests']['assets'] = $assetsTest;
            $this->displayTestResult('Assets & Resources', $assetsTest);
            
            // 10. Code Quality Test
            $qualityTest = $this->testCodeQuality($plugin);
            $testResult['tests']['quality'] = $qualityTest;
            $this->displayTestResult('Code Quality', $qualityTest);
            
            // Calculate results
            foreach ($testResult['tests'] as $test) {
                if ($test['passed']) {
                    $testResult['passed']++;
                } else {
                    $testResult['failed']++;
                }
            }
            
            $totalTests = $testResult['passed'] + $testResult['failed'];
            $testResult['score'] = $totalTests > 0 ? round(($testResult['passed'] / $totalTests) * 100, 1) : 0;
            
            // Determine status
            if ($testResult['score'] >= 90) {
                $testResult['status'] = 'excellent';
                $statusIcon = '🟢';
            } elseif ($testResult['score'] >= 75) {
                $testResult['status'] = 'good';
                $statusIcon = '🟡';
            } elseif ($testResult['score'] >= 60) {
                $testResult['status'] = 'acceptable';
                $statusIcon = '🟠';
            } else {
                $testResult['status'] = 'needs_improvement';
                $statusIcon = '🔴';
                $this->criticalFailures[] = $pluginName;
            }
            
            echo "\n📊 Plugin Test Summary:\n";
            echo "   {$statusIcon} Score: {$testResult['score']}% ({$testResult['status']})\n";
            echo "   ✅ Passed: {$testResult['passed']}/{$totalTests} tests\n";
            
            if ($testResult['failed'] > 0) {
                echo "   ❌ Failed: {$testResult['failed']} tests\n";
            }
            
            if ($testResult['score'] >= 60) {
                $this->passedPlugins++;
            } else {
                $this->failedPlugins++;
            }
            
            $this->testResults[$pluginName] = $testResult;
            
            echo "\n";
            echo "═══════════════════════════════════════════════\n\n";
        }
    }
    
    private function testManifest(array $plugin): array
    {
        $manifest = $plugin['manifest'];
        $issues = [];
        
        // Required fields
        $requiredFields = ['name', 'version', 'description', 'author', 'license'];
        foreach ($requiredFields as $field) {
            if (!isset($manifest[$field]) || empty($manifest[$field])) {
                $issues[] = "Missing required field: $field";
            }
        }
        
        // Version format
        if (isset($manifest['version']) && !preg_match('/^\d+\.\d+\.\d+$/', $manifest['version'])) {
            $issues[] = "Invalid version format (should be x.y.z)";
        }
        
        // Dependencies format
        if (isset($manifest['dependencies']) && !is_array($manifest['dependencies'])) {
            $issues[] = "Dependencies must be an array";
        }
        
        return [
            'passed' => empty($issues),
            'issues' => $issues,
            'message' => empty($issues) ? 'Manifest is valid' : implode(', ', $issues)
        ];
    }
    
    private function testFileStructure(array $plugin): array
    {
        $requiredDirs = ['src', 'migrations', 'tests'];
        $requiredFiles = ['plugin.json', 'bootstrap.php', 'README.md'];
        $missing = [];
        
        foreach ($requiredDirs as $dir) {
            if (!is_dir($plugin['path'] . '/' . $dir)) {
                $missing[] = "$dir/";
            }
        }
        
        foreach ($requiredFiles as $file) {
            if (!file_exists($plugin['path'] . '/' . $file)) {
                $missing[] = $file;
            }
        }
        
        return [
            'passed' => empty($missing),
            'issues' => $missing,
            'message' => empty($missing) ? 'File structure is complete' : 'Missing: ' . implode(', ', $missing)
        ];
    }
    
    private function testBootstrap(array $plugin): array
    {
        $bootstrapPath = $plugin['path'] . '/bootstrap.php';
        
        if (!file_exists($bootstrapPath)) {
            return [
                'passed' => false,
                'issues' => ['Bootstrap file not found'],
                'message' => 'Bootstrap file missing'
            ];
        }
        
        // Check syntax
        $output = [];
        $returnCode = 0;
        exec("php -l $bootstrapPath 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            return [
                'passed' => false,
                'issues' => ['PHP syntax error in bootstrap.php'],
                'message' => 'Bootstrap has syntax errors'
            ];
        }
        
        // Check for plugin class
        $content = file_get_contents($bootstrapPath);
        $pluginClass = $this->getExpectedPluginClass($plugin['name']);
        
        if (strpos($content, "class $pluginClass") === false) {
            return [
                'passed' => false,
                'issues' => ["Plugin class $pluginClass not found"],
                'message' => "Missing plugin class definition"
            ];
        }
        
        return [
            'passed' => true,
            'issues' => [],
            'message' => 'Bootstrap file is valid'
        ];
    }
    
    private function testPhpSyntax(array $plugin): array
    {
        $errors = [];
        $phpFiles = $this->getPhpFiles($plugin['path']);
        
        foreach ($phpFiles as $file) {
            $output = [];
            $returnCode = 0;
            exec("php -l $file 2>&1", $output, $returnCode);
            
            if ($returnCode !== 0) {
                $relativePath = str_replace($plugin['path'] . '/', '', $file);
                $errors[] = $relativePath;
            }
        }
        
        return [
            'passed' => empty($errors),
            'issues' => $errors,
            'message' => empty($errors) ? 'All PHP files have valid syntax' : 'Syntax errors in: ' . count($errors) . ' files'
        ];
    }
    
    private function testNamespaces(array $plugin): array
    {
        $issues = [];
        $expectedNamespace = $this->getExpectedNamespace($plugin['name']);
        $phpFiles = glob($plugin['path'] . '/src/**/*.php');
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
                $actualNamespace = $matches[1];
                if (!str_starts_with($actualNamespace, $expectedNamespace)) {
                    $relativePath = str_replace($plugin['path'] . '/', '', $file);
                    $issues[] = "$relativePath has incorrect namespace";
                }
            }
        }
        
        return [
            'passed' => empty($issues),
            'issues' => $issues,
            'message' => empty($issues) ? 'Namespace compliance verified' : 'Namespace issues in ' . count($issues) . ' files'
        ];
    }
    
    private function testSecurity(array $plugin): array
    {
        $issues = [];
        $phpFiles = $this->getPhpFiles($plugin['path']);
        
        $dangerousPatterns = [
            '/\beval\s*\(/' => 'eval() usage detected',
            '/\bexec\s*\(/' => 'exec() usage detected',
            '/\bshell_exec\s*\(/' => 'shell_exec() usage detected',
            '/\bsystem\s*\(/' => 'system() usage detected',
            '/\$_GET\s*\[[^\]]+\]\s*;/' => 'Unvalidated $_GET usage',
            '/\$_POST\s*\[[^\]]+\]\s*;/' => 'Unvalidated $_POST usage',
            '/file_get_contents\s*\(\s*[\'"]https?:\/\//' => 'External URL access'
        ];
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            foreach ($dangerousPatterns as $pattern => $message) {
                if (preg_match($pattern, $content)) {
                    $relativePath = str_replace($plugin['path'] . '/', '', $file);
                    $issues[] = "$relativePath: $message";
                }
            }
        }
        
        return [
            'passed' => empty($issues),
            'issues' => $issues,
            'message' => empty($issues) ? 'No security issues detected' : 'Security concerns: ' . count($issues)
        ];
    }
    
    private function testDocumentation(array $plugin): array
    {
        $issues = [];
        $requiredDocs = ['README.md'];
        
        foreach ($requiredDocs as $doc) {
            $docPath = $plugin['path'] . '/' . $doc;
            if (!file_exists($docPath)) {
                $issues[] = "$doc not found";
            } else {
                $content = file_get_contents($docPath);
                if (strlen($content) < 100) {
                    $issues[] = "$doc appears incomplete (too short)";
                }
            }
        }
        
        return [
            'passed' => empty($issues),
            'issues' => $issues,
            'message' => empty($issues) ? 'Documentation is complete' : implode(', ', $issues)
        ];
    }
    
    private function testDependencies(array $plugin): array
    {
        $issues = [];
        
        if (isset($plugin['manifest']['dependencies'])) {
            foreach ($plugin['manifest']['dependencies'] as $dep) {
                if (!isset($this->plugins[$dep])) {
                    $issues[] = "Missing dependency: $dep";
                }
            }
        }
        
        return [
            'passed' => empty($issues),
            'issues' => $issues,
            'message' => empty($issues) ? 'All dependencies satisfied' : implode(', ', $issues)
        ];
    }
    
    private function testAssets(array $plugin): array
    {
        $assetsDir = $plugin['path'] . '/assets';
        
        if (!is_dir($assetsDir)) {
            return [
                'passed' => true,
                'issues' => [],
                'message' => 'No assets directory (optional)'
            ];
        }
        
        $issues = [];
        $allowedExtensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico'];
        
        $files = glob($assetsDir . '/**/*.*');
        foreach ($files as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExtensions)) {
                $issues[] = "Unexpected file type: .$ext";
            }
        }
        
        return [
            'passed' => empty($issues),
            'issues' => $issues,
            'message' => empty($issues) ? 'Assets validated' : 'Asset issues: ' . implode(', ', $issues)
        ];
    }
    
    private function testCodeQuality(array $plugin): array
    {
        $srcPath = $plugin['path'] . '/src';
        
        if (!is_dir($srcPath)) {
            return [
                'passed' => false,
                'issues' => ['No src directory found'],
                'message' => 'Source directory missing'
            ];
        }
        
        $issues = [];
        $phpFiles = $this->getPhpFiles($srcPath);
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            
            // Check for PSR-12 compliance indicators
            if (!preg_match('/declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/', $content)) {
                $relativePath = str_replace($plugin['path'] . '/', '', $file);
                $issues[] = "$relativePath: Missing strict_types declaration";
            }
        }
        
        return [
            'passed' => count($issues) < 3, // Allow up to 2 minor issues
            'issues' => $issues,
            'message' => empty($issues) ? 'Code quality excellent' : 'Quality issues: ' . count($issues)
        ];
    }
    
    private function getPhpFiles(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }
        
        $files = [];
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        } catch (Exception $e) {
            // Directory doesn't exist or other error
            return [];
        }
        
        return $files;
    }
    
    private function getExpectedPluginClass(string $pluginName): string
    {
        $parts = explode('-', $pluginName);
        return implode('', array_map('ucfirst', $parts)) . 'Plugin';
    }
    
    private function getExpectedNamespace(string $pluginName): string
    {
        $parts = explode('-', $pluginName);
        return 'Shopologic\\Plugins\\' . implode('', array_map('ucfirst', $parts));
    }
    
    private function displayTestResult(string $testName, array $result): void
    {
        $icon = $result['passed'] ? '✅' : '❌';
        $message = $result['message'];
        echo "   $icon $testName: $message\n";
    }
    
    private function generateDetailedReport(): void
    {
        echo "\n📊 COMPREHENSIVE PLUGIN TEST REPORT\n";
        echo "=====================================\n\n";
        
        $overallScore = $this->totalPlugins > 0 
            ? round(($this->passedPlugins / $this->totalPlugins) * 100, 1) 
            : 0;
        
        echo "📈 OVERALL RESULTS:\n";
        echo "- Total plugins tested: {$this->totalPlugins}\n";
        echo "- Passed (60%+ score): {$this->passedPlugins}\n";
        echo "- Failed (<60% score): {$this->failedPlugins}\n";
        echo "- Overall pass rate: $overallScore%\n\n";
        
        // Grade distribution
        $grades = ['excellent' => 0, 'good' => 0, 'acceptable' => 0, 'needs_improvement' => 0];
        foreach ($this->testResults as $result) {
            $grades[$result['status']]++;
        }
        
        echo "🎯 GRADE DISTRIBUTION:\n";
        echo "- 🟢 Excellent (90-100%): {$grades['excellent']} plugins\n";
        echo "- 🟡 Good (75-89%): {$grades['good']} plugins\n";
        echo "- 🟠 Acceptable (60-74%): {$grades['acceptable']} plugins\n";
        echo "- 🔴 Needs Improvement (<60%): {$grades['needs_improvement']} plugins\n\n";
        
        // Top performers
        $topPlugins = array_filter($this->testResults, fn($r) => $r['score'] >= 90);
        uasort($topPlugins, fn($a, $b) => $b['score'] <=> $a['score']);
        
        if (!empty($topPlugins)) {
            echo "🏆 TOP PERFORMING PLUGINS:\n";
            $count = 0;
            foreach ($topPlugins as $name => $result) {
                echo "   {$result['score']}% - $name\n";
                if (++$count >= 5) break;
            }
            echo "\n";
        }
        
        // Critical failures
        if (!empty($this->criticalFailures)) {
            echo "🚨 CRITICAL FAILURES (Require immediate attention):\n";
            foreach ($this->criticalFailures as $plugin) {
                $score = $this->testResults[$plugin]['score'];
                echo "   - $plugin ({$score}%)\n";
            }
            echo "\n";
        }
        
        // Common issues
        $allIssues = [];
        foreach ($this->testResults as $result) {
            foreach ($result['tests'] as $testName => $test) {
                if (!$test['passed']) {
                    $allIssues[$testName] = ($allIssues[$testName] ?? 0) + 1;
                }
            }
        }
        
        if (!empty($allIssues)) {
            arsort($allIssues);
            echo "📋 MOST COMMON ISSUES:\n";
            foreach ($allIssues as $issue => $count) {
                $percentage = round(($count / $this->totalPlugins) * 100, 1);
                echo "   - $issue: $count plugins ($percentage%)\n";
            }
            echo "\n";
        }
        
        // Recommendations
        echo "💡 RECOMMENDATIONS:\n";
        if ($overallScore >= 90) {
            echo "✅ Excellent! The plugin ecosystem is in great shape.\n";
            echo "   - Continue regular monitoring and updates\n";
            echo "   - Consider adding more advanced features\n";
        } elseif ($overallScore >= 75) {
            echo "🟡 Good overall, but some improvements needed:\n";
            echo "   - Focus on plugins scoring below 75%\n";
            echo "   - Address common issues identified above\n";
            echo "   - Run batch refactoring tools\n";
        } else {
            echo "🔴 Significant improvements required:\n";
            echo "   - Prioritize critical failures\n";
            echo "   - Use automated tools to fix common issues\n";
            echo "   - Consider comprehensive refactoring\n";
        }
        
        // Save detailed report
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'summary' => [
                'total_plugins' => $this->totalPlugins,
                'passed_plugins' => $this->passedPlugins,
                'failed_plugins' => $this->failedPlugins,
                'overall_score' => $overallScore
            ],
            'grade_distribution' => $grades,
            'test_results' => $this->testResults,
            'critical_failures' => $this->criticalFailures,
            'common_issues' => $allIssues
        ];
        
        file_put_contents(
            $this->pluginsDir . '/INDIVIDUAL_PLUGIN_TEST_REPORT.json',
            json_encode($report, JSON_PRETTY_PRINT)
        );
        
        echo "\n💾 Detailed report saved: INDIVIDUAL_PLUGIN_TEST_REPORT.json\n";
        echo "\n🎊 INDIVIDUAL PLUGIN TESTING COMPLETE!\n";
    }
}

// Execute individual plugin tests
$tester = new IndividualPluginTest();
$tester->testAllPluginsIndividually();