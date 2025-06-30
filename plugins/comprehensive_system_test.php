<?php

/**
 * Comprehensive System Test Suite for Shopologic Plugin Infrastructure
 * Tests all development tools, monitoring systems, and infrastructure components
 */

declare(strict_types=1);

class ComprehensiveSystemTest
{
    private string $pluginsDir;
    private array $testResults = [];
    private int $totalTests = 0;
    private int $passedTests = 0;
    private int $failedTests = 0;
    private array $criticalErrors = [];
    
    public function __construct()
    {
        $this->pluginsDir = __DIR__;
    }
    
    public function executeSystemTests(): void
    {
        echo "🧪 Shopologic Plugin Infrastructure - Comprehensive System Test\n";
        echo "==============================================================\n\n";
        echo "⚠️  Testing all infrastructure components (excluding individual plugins)\n\n";
        
        $this->testDevelopmentTools();
        $this->testQualityAssuranceTools();
        $this->testMonitoringSystems();
        $this->testTestingFramework();
        $this->testMarketplaceTools();
        $this->testDocumentationSystem();
        $this->testIntegrationSystems();
        $this->testCleanupTools();
        $this->testScriptsAndAutomation();
        $this->testDashboardsAndUI();
        $this->generateTestReport();
    }
    
    private function testDevelopmentTools(): void
    {
        echo "🔧 TESTING DEVELOPMENT TOOLS\n";
        echo "============================\n\n";
        
        // Test 1: Development Tools PHP
        $this->runTest(
            'Development Tools (development_tools.php)',
            function() {
                $file = $this->pluginsDir . '/development_tools.php';
                if (!file_exists($file)) {
                    return ['passed' => false, 'error' => 'File not found'];
                }
                
                // Syntax check
                $output = [];
                $returnCode = 0;
                exec("php -l $file 2>&1", $output, $returnCode);
                
                if ($returnCode !== 0) {
                    return ['passed' => false, 'error' => 'PHP syntax error: ' . implode(' ', $output)];
                }
                
                // Check if file contains expected class
                $content = file_get_contents($file);
                if (strpos($content, 'class PluginDevelopmentTools') === false) {
                    return ['passed' => false, 'error' => 'PluginDevelopmentTools class not found'];
                }
                
                return ['passed' => true, 'message' => 'Development tools validated successfully'];
            }
        );
        
        // Test 2: Plugin Development Wizard
        $this->runTest(
            'Plugin Development Wizard (plugin_development_wizard.sh)',
            function() {
                $file = $this->pluginsDir . '/plugin_development_wizard.sh';
                if (!file_exists($file)) {
                    return ['passed' => false, 'error' => 'File not found'];
                }
                
                // Check if executable
                if (!is_executable($file)) {
                    return ['passed' => false, 'error' => 'Script is not executable'];
                }
                
                // Check shebang
                $firstLine = fgets(fopen($file, 'r'));
                if (strpos($firstLine, '#!/bin/bash') === false) {
                    return ['passed' => false, 'error' => 'Invalid shebang line'];
                }
                
                return ['passed' => true, 'message' => 'Development wizard validated successfully'];
            }
        );
        
        echo "\n";
    }
    
    private function testQualityAssuranceTools(): void
    {
        echo "📊 TESTING QUALITY ASSURANCE TOOLS\n";
        echo "==================================\n\n";
        
        $qaTools = [
            'plugin_analyzer.php' => 'PluginAnalyzer',
            'batch_refactor.php' => 'BatchPluginRefactor',
            'final_validator.php' => 'FinalPluginValidator',
            'namespace_fixer.php' => 'NamespaceFixer'
        ];
        
        foreach ($qaTools as $file => $expectedClass) {
            $this->runTest(
                "Quality Tool: $file",
                function() use ($file, $expectedClass) {
                    $filePath = $this->pluginsDir . '/' . $file;
                    if (!file_exists($filePath)) {
                        return ['passed' => false, 'error' => 'File not found'];
                    }
                    
                    // Syntax check
                    $output = [];
                    $returnCode = 0;
                    exec("php -l $filePath 2>&1", $output, $returnCode);
                    
                    if ($returnCode !== 0) {
                        return ['passed' => false, 'error' => 'PHP syntax error'];
                    }
                    
                    // Check for expected class
                    $content = file_get_contents($filePath);
                    if (strpos($content, "class $expectedClass") === false) {
                        return ['passed' => false, 'error' => "$expectedClass class not found"];
                    }
                    
                    return ['passed' => true, 'message' => 'Tool validated successfully'];
                }
            );
        }
        
        echo "\n";
    }
    
    private function testMonitoringSystems(): void
    {
        echo "🏥 TESTING MONITORING SYSTEMS\n";
        echo "=============================\n\n";
        
        // Test Plugin Monitor
        $this->runTest(
            'Plugin Monitor (plugin_monitor.php)',
            function() {
                $file = $this->pluginsDir . '/plugin_monitor.php';
                if (!file_exists($file)) {
                    return ['passed' => false, 'error' => 'File not found'];
                }
                
                // Syntax check
                $output = [];
                $returnCode = 0;
                exec("php -l $file 2>&1", $output, $returnCode);
                
                if ($returnCode !== 0) {
                    return ['passed' => false, 'error' => 'PHP syntax error'];
                }
                
                // Check for monitoring class
                $content = file_get_contents($file);
                if (strpos($content, 'class PluginHealthMonitor') === false) {
                    return ['passed' => false, 'error' => 'PluginHealthMonitor class not found'];
                }
                
                // Check if monitoring functions exist
                $requiredFunctions = ['assessPluginHealth', 'generateHealthReport', 'monitorAllPlugins'];
                $missingFunctions = [];
                
                foreach ($requiredFunctions as $func) {
                    if (strpos($content, "function $func") === false) {
                        $missingFunctions[] = $func;
                    }
                }
                
                if (!empty($missingFunctions)) {
                    return ['passed' => false, 'error' => 'Missing functions: ' . implode(', ', $missingFunctions)];
                }
                
                return ['passed' => true, 'message' => 'Monitor system validated successfully'];
            }
        );
        
        // Test Monitor Script
        $this->runTest(
            'Monitor Shell Script (monitor.sh)',
            function() {
                $file = $this->pluginsDir . '/monitor.sh';
                if (!file_exists($file)) {
                    return ['passed' => false, 'error' => 'File not found'];
                }
                
                // Check if executable
                if (!is_executable($file)) {
                    return ['passed' => false, 'error' => 'Script is not executable'];
                }
                
                return ['passed' => true, 'message' => 'Monitor script validated'];
            }
        );
        
        echo "\n";
    }
    
    private function testTestingFramework(): void
    {
        echo "🧪 TESTING TESTING FRAMEWORK\n";
        echo "============================\n\n";
        
        // Test Framework PHP
        $this->runTest(
            'Test Framework (test_framework.php)',
            function() {
                $file = $this->pluginsDir . '/test_framework.php';
                if (!file_exists($file)) {
                    return ['passed' => false, 'error' => 'File not found'];
                }
                
                // Syntax check
                $output = [];
                $returnCode = 0;
                exec("php -l $file 2>&1", $output, $returnCode);
                
                if ($returnCode !== 0) {
                    return ['passed' => false, 'error' => 'PHP syntax error'];
                }
                
                // Check for test framework class
                $content = file_get_contents($file);
                if (strpos($content, 'class PluginTestFramework') === false) {
                    return ['passed' => false, 'error' => 'PluginTestFramework class not found'];
                }
                
                // Check test generation methods
                $testMethods = ['generateTestSuites', 'createUnitTests', 'createIntegrationTests'];
                foreach ($testMethods as $method) {
                    if (strpos($content, "function $method") === false) {
                        return ['passed' => false, 'error' => "Missing method: $method"];
                    }
                }
                
                return ['passed' => true, 'message' => 'Test framework validated successfully'];
            }
        );
        
        // Test Runner Script
        $this->runTest(
            'Test Runner Script (run_tests.sh)',
            function() {
                $file = $this->pluginsDir . '/run_tests.sh';
                if (!file_exists($file)) {
                    return ['passed' => false, 'error' => 'File not found'];
                }
                
                if (!is_executable($file)) {
                    return ['passed' => false, 'error' => 'Script is not executable'];
                }
                
                // Check for PHPUnit configuration
                $content = file_get_contents($file);
                if (strpos($content, 'phpunit') === false) {
                    return ['passed' => false, 'error' => 'PHPUnit command not found in script'];
                }
                
                return ['passed' => true, 'message' => 'Test runner validated'];
            }
        );
        
        // PHPUnit Configuration
        $this->runTest(
            'PHPUnit Configuration (phpunit.xml)',
            function() {
                $file = $this->pluginsDir . '/phpunit.xml';
                if (!file_exists($file)) {
                    return ['passed' => false, 'error' => 'File not found'];
                }
                
                // Validate XML
                $xml = @simplexml_load_file($file);
                if ($xml === false) {
                    return ['passed' => false, 'error' => 'Invalid XML format'];
                }
                
                // Check for test suites
                if (!isset($xml->testsuites)) {
                    return ['passed' => false, 'error' => 'No test suites defined'];
                }
                
                return ['passed' => true, 'message' => 'PHPUnit config validated'];
            }
        );
        
        echo "\n";
    }
    
    private function testMarketplaceTools(): void
    {
        echo "🏪 TESTING MARKETPLACE TOOLS\n";
        echo "============================\n\n";
        
        // Test Marketplace Preparation
        $this->runTest(
            'Marketplace Preparation (marketplace_preparation.php)',
            function() {
                $file = $this->pluginsDir . '/marketplace_preparation.php';
                if (!file_exists($file)) {
                    return ['passed' => false, 'error' => 'File not found'];
                }
                
                // Syntax check
                $output = [];
                $returnCode = 0;
                exec("php -l $file 2>&1", $output, $returnCode);
                
                if ($returnCode !== 0) {
                    return ['passed' => false, 'error' => 'PHP syntax error'];
                }
                
                // Check for marketplace class
                $content = file_get_contents($file);
                if (strpos($content, 'class PluginMarketplacePreparation') === false) {
                    return ['passed' => false, 'error' => 'PluginMarketplacePreparation class not found'];
                }
                
                return ['passed' => true, 'message' => 'Marketplace tools validated'];
            }
        );
        
        // Test Marketplace Packages Directory
        $this->runTest(
            'Marketplace Packages Directory',
            function() {
                $dir = $this->pluginsDir . '/marketplace-packages';
                if (!is_dir($dir)) {
                    return ['passed' => false, 'error' => 'Directory not found'];
                }
                
                // Check for packages
                $packages = glob($dir . '/*.zip');
                if (empty($packages)) {
                    return ['passed' => false, 'error' => 'No packages found'];
                }
                
                return ['passed' => true, 'message' => count($packages) . ' packages found'];
            }
        );
        
        echo "\n";
    }
    
    private function testDocumentationSystem(): void
    {
        echo "📚 TESTING DOCUMENTATION SYSTEM\n";
        echo "===============================\n\n";
        
        $requiredDocs = [
            'PLUGIN_DEVELOPMENT_GUIDELINES.md' => 'Development Guidelines',
            'ECOSYSTEM_SHOWCASE.md' => 'Ecosystem Showcase',
            'FINAL_PROJECT_STATUS.md' => 'Project Status',
            'NEXT_STEPS_GUIDE.md' => 'Next Steps Guide',
            'QUICK_REFERENCE.md' => 'Quick Reference',
            'MARKETPLACE_SUBMISSION_CHECKLIST.md' => 'Marketplace Checklist'
        ];
        
        foreach ($requiredDocs as $file => $description) {
            $this->runTest(
                "Documentation: $description",
                function() use ($file) {
                    $filePath = $this->pluginsDir . '/' . $file;
                    if (!file_exists($filePath)) {
                        return ['passed' => false, 'error' => 'File not found'];
                    }
                    
                    // Check if file has content
                    $content = file_get_contents($filePath);
                    if (strlen($content) < 100) {
                        return ['passed' => false, 'error' => 'File appears to be empty or too small'];
                    }
                    
                    // Check for markdown headers
                    if (strpos($content, '#') === false) {
                        return ['passed' => false, 'error' => 'No markdown headers found'];
                    }
                    
                    return ['passed' => true, 'message' => 'Documentation validated'];
                }
            );
        }
        
        echo "\n";
    }
    
    private function testIntegrationSystems(): void
    {
        echo "🔗 TESTING INTEGRATION SYSTEMS\n";
        echo "==============================\n\n";
        
        // Test Performance Benchmark
        $this->runTest(
            'Performance Benchmark (performance_benchmark.php)',
            function() {
                $file = $this->pluginsDir . '/performance_benchmark.php';
                if (!file_exists($file)) {
                    return ['passed' => false, 'error' => 'File not found'];
                }
                
                // Syntax check
                $output = [];
                $returnCode = 0;
                exec("php -l $file 2>&1", $output, $returnCode);
                
                if ($returnCode !== 0) {
                    return ['passed' => false, 'error' => 'PHP syntax error'];
                }
                
                // Check for benchmark class
                $content = file_get_contents($file);
                if (strpos($content, 'class PluginPerformanceBenchmark') === false) {
                    return ['passed' => false, 'error' => 'PluginPerformanceBenchmark class not found'];
                }
                
                return ['passed' => true, 'message' => 'Performance benchmark validated'];
            }
        );
        
        // Test Integration Test Suite
        $this->runTest(
            'Integration Test Suite (integration_test_suite.php)',
            function() {
                $file = $this->pluginsDir . '/integration_test_suite.php';
                if (!file_exists($file)) {
                    return ['passed' => false, 'error' => 'File not found'];
                }
                
                // Syntax check
                $output = [];
                $returnCode = 0;
                exec("php -l $file 2>&1", $output, $returnCode);
                
                if ($returnCode !== 0) {
                    return ['passed' => false, 'error' => 'PHP syntax error'];
                }
                
                return ['passed' => true, 'message' => 'Integration test suite validated'];
            }
        );
        
        // Test Shared Directory
        $this->runTest(
            'Shared Services Directory',
            function() {
                $dir = $this->pluginsDir . '/shared';
                if (!is_dir($dir)) {
                    return ['passed' => false, 'error' => 'Directory not found'];
                }
                
                // Check for shared files
                $sharedFiles = glob($dir . '/*.php');
                if (empty($sharedFiles)) {
                    return ['passed' => false, 'error' => 'No shared services found'];
                }
                
                return ['passed' => true, 'message' => count($sharedFiles) . ' shared services found'];
            }
        );
        
        echo "\n";
    }
    
    private function testCleanupTools(): void
    {
        echo "🧹 TESTING CLEANUP TOOLS\n";
        echo "========================\n\n";
        
        // Test Cleanup Analyzer
        $this->runTest(
            'Cleanup Analyzer (cleanup_analyzer.php)',
            function() {
                $file = $this->pluginsDir . '/cleanup_analyzer.php';
                if (!file_exists($file)) {
                    return ['passed' => false, 'error' => 'File not found'];
                }
                
                // Syntax check
                $output = [];
                $returnCode = 0;
                exec("php -l $file 2>&1", $output, $returnCode);
                
                if ($returnCode !== 0) {
                    return ['passed' => false, 'error' => 'PHP syntax error'];
                }
                
                // Check for cleanup class
                $content = file_get_contents($file);
                if (strpos($content, 'class ProjectCleanupAnalyzer') === false) {
                    return ['passed' => false, 'error' => 'ProjectCleanupAnalyzer class not found'];
                }
                
                return ['passed' => true, 'message' => 'Cleanup analyzer validated'];
            }
        );
        
        // Test Cleanup Report
        $this->runTest(
            'Cleanup Report (CLEANUP_REPORT.json)',
            function() {
                $file = $this->pluginsDir . '/CLEANUP_REPORT.json';
                if (!file_exists($file)) {
                    return ['passed' => false, 'error' => 'File not found - cleanup may not have been run'];
                }
                
                // Validate JSON
                $json = json_decode(file_get_contents($file), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return ['passed' => false, 'error' => 'Invalid JSON format'];
                }
                
                return ['passed' => true, 'message' => 'Cleanup report validated'];
            }
        );
        
        echo "\n";
    }
    
    private function testScriptsAndAutomation(): void
    {
        echo "🔧 TESTING SCRIPTS AND AUTOMATION\n";
        echo "=================================\n\n";
        
        // Test Optimize Plugins Script
        $this->runTest(
            'Optimize Plugins Script (optimize_plugins.php)',
            function() {
                $file = $this->pluginsDir . '/optimize_plugins.php';
                if (!file_exists($file)) {
                    return ['passed' => false, 'error' => 'File not found'];
                }
                
                // Syntax check
                $output = [];
                $returnCode = 0;
                exec("php -l $file 2>&1", $output, $returnCode);
                
                if ($returnCode !== 0) {
                    return ['passed' => false, 'error' => 'PHP syntax error'];
                }
                
                return ['passed' => true, 'message' => 'Optimization script validated'];
            }
        );
        
        // Test Bootstrap Plugins
        $this->runTest(
            'Bootstrap Plugins (bootstrap_plugins.php)',
            function() {
                $file = $this->pluginsDir . '/bootstrap_plugins.php';
                if (!file_exists($file)) {
                    return ['passed' => false, 'error' => 'File not found'];
                }
                
                // Syntax check
                $output = [];
                $returnCode = 0;
                exec("php -l $file 2>&1", $output, $returnCode);
                
                if ($returnCode !== 0) {
                    return ['passed' => false, 'error' => 'PHP syntax error'];
                }
                
                return ['passed' => true, 'message' => 'Bootstrap script validated'];
            }
        );
        
        echo "\n";
    }
    
    private function testDashboardsAndUI(): void
    {
        echo "📊 TESTING DASHBOARDS AND UI\n";
        echo "============================\n\n";
        
        $dashboards = [
            'health_dashboard.html' => 'Health Dashboard',
            'performance_dashboard.html' => 'Performance Dashboard',
            'marketplace-website.html' => 'Marketplace Website'
        ];
        
        foreach ($dashboards as $file => $description) {
            $this->runTest(
                "Dashboard: $description",
                function() use ($file) {
                    $filePath = $this->pluginsDir . '/' . $file;
                    if (!file_exists($filePath)) {
                        return ['passed' => false, 'error' => 'File not found'];
                    }
                    
                    // Check if valid HTML
                    $content = file_get_contents($filePath);
                    if (strpos($content, '<!DOCTYPE html>') === false) {
                        return ['passed' => false, 'error' => 'Invalid HTML - missing DOCTYPE'];
                    }
                    
                    // Check for basic HTML structure
                    $requiredTags = ['<html', '<head>', '<body>', '</html>'];
                    foreach ($requiredTags as $tag) {
                        if (strpos($content, $tag) === false) {
                            return ['passed' => false, 'error' => "Missing required tag: $tag"];
                        }
                    }
                    
                    // Check for JavaScript
                    if (strpos($content, '<script') === false) {
                        return ['passed' => false, 'error' => 'No JavaScript found'];
                    }
                    
                    return ['passed' => true, 'message' => 'Dashboard validated'];
                }
            );
        }
        
        echo "\n";
    }
    
    private function runTest(string $testName, callable $testFunction): void
    {
        $this->totalTests++;
        
        try {
            $result = $testFunction();
            
            if ($result['passed']) {
                $this->passedTests++;
                echo "✅ $testName: {$result['message']}\n";
                
                $this->testResults[] = [
                    'test' => $testName,
                    'status' => 'passed',
                    'message' => $result['message']
                ];
            } else {
                $this->failedTests++;
                $error = $result['error'] ?? 'Unknown error';
                echo "❌ $testName: $error\n";
                
                $this->testResults[] = [
                    'test' => $testName,
                    'status' => 'failed',
                    'error' => $error
                ];
                
                // Track critical errors
                if (strpos($testName, 'Development Tools') !== false ||
                    strpos($testName, 'Plugin Monitor') !== false ||
                    strpos($testName, 'Test Framework') !== false) {
                    $this->criticalErrors[] = "$testName: $error";
                }
            }
        } catch (Exception $e) {
            $this->failedTests++;
            echo "❌ $testName: Exception - " . $e->getMessage() . "\n";
            
            $this->testResults[] = [
                'test' => $testName,
                'status' => 'failed',
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
    
    private function generateTestReport(): void
    {
        echo "📊 COMPREHENSIVE SYSTEM TEST REPORT\n";
        echo "===================================\n\n";
        
        $passRate = $this->totalTests > 0 ? round(($this->passedTests / $this->totalTests) * 100, 1) : 0;
        
        echo "📈 OVERALL TEST RESULTS:\n";
        echo "- Total system tests: {$this->totalTests}\n";
        echo "- Tests passed: {$this->passedTests}\n";
        echo "- Tests failed: {$this->failedTests}\n";
        echo "- Pass rate: $passRate%\n\n";
        
        // Category summary
        $categories = [
            'Development Tools' => 0,
            'Quality Assurance' => 0,
            'Monitoring' => 0,
            'Testing' => 0,
            'Marketplace' => 0,
            'Documentation' => 0,
            'Integration' => 0,
            'Cleanup' => 0,
            'Automation' => 0,
            'UI/Dashboards' => 0
        ];
        
        $categoryPassed = $categories;
        
        foreach ($this->testResults as $result) {
            foreach ($categories as $category => $count) {
                if (strpos($result['test'], $category) !== false ||
                    (strpos($result['test'], 'Dashboard') !== false && $category === 'UI/Dashboards') ||
                    (strpos($result['test'], 'Script') !== false && $category === 'Automation')) {
                    $categories[$category]++;
                    if ($result['status'] === 'passed') {
                        $categoryPassed[$category]++;
                    }
                    break;
                }
            }
        }
        
        echo "🎯 CATEGORY BREAKDOWN:\n";
        foreach ($categories as $category => $total) {
            if ($total > 0) {
                $passed = $categoryPassed[$category];
                $rate = round(($passed / $total) * 100, 1);
                $status = $rate === 100 ? '✅' : ($rate >= 75 ? '🟡' : '❌');
                echo "$status $category: $passed/$total tests passed ($rate%)\n";
            }
        }
        
        echo "\n";
        
        // Critical errors
        if (!empty($this->criticalErrors)) {
            echo "🚨 CRITICAL ERRORS FOUND:\n";
            foreach ($this->criticalErrors as $error) {
                echo "- $error\n";
            }
            echo "\n";
        }
        
        // Failed tests summary
        $failedTests = array_filter($this->testResults, fn($r) => $r['status'] === 'failed');
        if (!empty($failedTests)) {
            echo "❌ FAILED TESTS DETAILS:\n";
            foreach ($failedTests as $test) {
                echo "- {$test['test']}: {$test['error']}\n";
            }
            echo "\n";
        }
        
        // Overall system status
        echo "🏁 SYSTEM STATUS ASSESSMENT:\n";
        if ($passRate === 100) {
            echo "✅ EXCELLENT: All infrastructure components are working perfectly!\n";
        } elseif ($passRate >= 90) {
            echo "🟢 VERY GOOD: Infrastructure is fully functional with minor issues.\n";
        } elseif ($passRate >= 75) {
            echo "🟡 GOOD: Infrastructure is mostly functional but needs attention.\n";
        } elseif ($passRate >= 60) {
            echo "🟠 ACCEPTABLE: Infrastructure has several issues that should be addressed.\n";
        } else {
            echo "❌ CRITICAL: Infrastructure has significant issues requiring immediate attention.\n";
        }
        
        // Recommendations
        echo "\n📋 RECOMMENDATIONS:\n";
        if ($this->failedTests === 0) {
            echo "✅ All systems operational - ready for plugin development!\n";
        } else {
            echo "⚠️  Address the failed tests before proceeding with development:\n";
            
            $recommendations = [];
            foreach ($failedTests as $test) {
                if (strpos($test['error'], 'not found') !== false) {
                    $recommendations['missing_files'] = true;
                }
                if (strpos($test['error'], 'syntax error') !== false) {
                    $recommendations['syntax_errors'] = true;
                }
                if (strpos($test['error'], 'not executable') !== false) {
                    $recommendations['permissions'] = true;
                }
            }
            
            if (isset($recommendations['missing_files'])) {
                echo "- Some files are missing - check if all tools were properly created\n";
            }
            if (isset($recommendations['syntax_errors'])) {
                echo "- Fix PHP syntax errors in the affected files\n";
            }
            if (isset($recommendations['permissions'])) {
                echo "- Make shell scripts executable: chmod +x *.sh\n";
            }
        }
        
        // Save test report
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'summary' => [
                'total_tests' => $this->totalTests,
                'passed_tests' => $this->passedTests,
                'failed_tests' => $this->failedTests,
                'pass_rate' => $passRate
            ],
            'categories' => array_map(function($category, $total) use ($categoryPassed) {
                return [
                    'total' => $total,
                    'passed' => $categoryPassed[$category],
                    'rate' => $total > 0 ? round(($categoryPassed[$category] / $total) * 100, 1) : 0
                ];
            }, array_keys($categories), $categories),
            'critical_errors' => $this->criticalErrors,
            'test_results' => $this->testResults
        ];
        
        file_put_contents($this->pluginsDir . '/SYSTEM_TEST_REPORT.json', json_encode($report, JSON_PRETTY_PRINT));
        
        echo "\n💾 Detailed test report saved: SYSTEM_TEST_REPORT.json\n";
        echo "\n🎊 COMPREHENSIVE SYSTEM TESTING COMPLETE!\n";
    }
}

// Execute comprehensive system tests
$systemTest = new ComprehensiveSystemTest();
$systemTest->executeSystemTests();