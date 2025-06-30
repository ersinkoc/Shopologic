<?php

/**
 * Final Plugin Validation Tool
 * Comprehensive validation of all standardized plugins
 */

declare(strict_types=1);

class FinalPluginValidator
{
    private string $pluginsDir;
    private array $plugins = [];
    private array $validationResults = [];
    private int $totalTests = 0;
    private int $passedTests = 0;
    private int $failedTests = 0;
    
    public function __construct()
    {
        $this->pluginsDir = __DIR__;
    }
    
    public function validateAll(): void
    {
        echo "🔍 Final Plugin Validation Suite\n";
        echo "================================\n\n";
        
        $this->discoverPlugins();
        $this->runValidationSuite();
        $this->generateValidationReport();
        $this->createQualityBadges();
    }
    
    private function discoverPlugins(): void
    {
        $directories = glob($this->pluginsDir . '/*', GLOB_ONLYDIR);
        
        foreach ($directories as $dir) {
            $pluginName = basename($dir);
            if ($pluginName === 'shared') continue;
            
            $pluginJsonPath = $dir . '/plugin.json';
            if (file_exists($pluginJsonPath)) {
                $this->plugins[$pluginName] = [
                    'path' => $dir,
                    'manifest' => json_decode(file_get_contents($pluginJsonPath), true)
                ];
            }
        }
        
        echo "🎯 Validating " . count($this->plugins) . " plugins\n\n";
    }
    
    private function runValidationSuite(): void
    {
        foreach ($this->plugins as $pluginName => $plugin) {
            echo "🔬 Validating: $pluginName\n";
            
            $results = [
                'plugin' => $pluginName,
                'tests' => [],
                'score' => 0,
                'grade' => 'F',
                'issues' => []
            ];
            
            // Test Suite
            $results['tests']['structure'] = $this->validateStructure($pluginName, $plugin);
            $results['tests']['bootstrap'] = $this->validateBootstrap($pluginName, $plugin);
            $results['tests']['namespace'] = $this->validateNamespace($pluginName, $plugin);
            $results['tests']['documentation'] = $this->validateDocumentation($pluginName, $plugin);
            $results['tests']['security'] = $this->validateSecurity($pluginName, $plugin);
            $results['tests']['quality'] = $this->validateCodeQuality($pluginName, $plugin);
            $results['tests']['manifest'] = $this->validateManifest($pluginName, $plugin);
            
            // Calculate score
            $totalChecks = count($results['tests']);
            $passedChecks = count(array_filter($results['tests'], fn($test) => $test['passed']));
            $results['score'] = round(($passedChecks / $totalChecks) * 100);
            $results['grade'] = $this->calculateGrade($results['score']);
            
            // Collect issues
            foreach ($results['tests'] as $testName => $test) {
                if (!$test['passed']) {
                    $results['issues'][] = $test['message'];
                }
            }
            
            $this->validationResults[$pluginName] = $results;
            
            $statusIcon = $results['score'] >= 90 ? '✅' : ($results['score'] >= 70 ? '⚠️' : '❌');
            echo "   $statusIcon Score: {$results['score']}% (Grade: {$results['grade']})\n\n";
            
            $this->totalTests += $totalChecks;
            $this->passedTests += $passedChecks;
            $this->failedTests += ($totalChecks - $passedChecks);
        }
    }
    
    private function validateStructure(string $pluginName, array $plugin): array
    {
        $requiredDirs = [
            'src', 'src/Controllers', 'src/Models', 'src/Services', 'src/Repositories',
            'templates', 'assets', 'assets/css', 'assets/js', 'assets/images',
            'migrations', 'tests', 'docs'
        ];
        
        $missing = [];
        foreach ($requiredDirs as $dir) {
            if (!is_dir($plugin['path'] . '/' . $dir)) {
                $missing[] = $dir;
            }
        }
        
        return [
            'passed' => empty($missing),
            'message' => empty($missing) ? 'All required directories present' : 'Missing directories: ' . implode(', ', $missing)
        ];
    }
    
    private function validateBootstrap(string $pluginName, array $plugin): array
    {
        $manifest = $plugin['manifest'];
        
        if (!isset($manifest['bootstrap'])) {
            return ['passed' => false, 'message' => 'No bootstrap configuration'];
        }
        
        $bootstrapFile = $plugin['path'] . '/' . $manifest['bootstrap']['file'];
        if (!file_exists($bootstrapFile)) {
            return ['passed' => false, 'message' => 'Bootstrap file not found'];
        }
        
        $content = file_get_contents($bootstrapFile);
        
        // Check for strict types
        if (!preg_match('/declare\s*\(\s*strict_types\s*=\s*1\s*\)/', $content)) {
            return ['passed' => false, 'message' => 'Missing strict_types declaration'];
        }
        
        // Check for AbstractPlugin extension
        if (!preg_match('/extends\s+AbstractPlugin/', $content)) {
            return ['passed' => false, 'message' => 'Does not extend AbstractPlugin'];
        }
        
        // Check for required methods
        $requiredMethods = [
            'registerServices', 'registerEventListeners', 'registerHooks',
            'registerRoutes', 'registerPermissions', 'registerScheduledJobs'
        ];
        
        foreach ($requiredMethods as $method) {
            if (!preg_match("/protected\s+function\s+$method/", $content)) {
                return ['passed' => false, 'message' => "Missing method: $method"];
            }
        }
        
        return ['passed' => true, 'message' => 'Bootstrap class properly implemented'];
    }
    
    private function validateNamespace(string $pluginName, array $plugin): array
    {
        $expectedNamespace = $this->getExpectedNamespace($pluginName);
        $manifest = $plugin['manifest'];
        
        // Check autoload configuration
        if (!isset($manifest['autoload']['psr-4'])) {
            return ['passed' => false, 'message' => 'Missing PSR-4 autoload configuration'];
        }
        
        $expectedNsKey = $expectedNamespace . '\\';
        if (!isset($manifest['autoload']['psr-4'][$expectedNsKey])) {
            return ['passed' => false, 'message' => "Missing namespace: $expectedNsKey"];
        }
        
        // Check bootstrap class namespace
        $bootstrapFile = $plugin['path'] . '/' . $manifest['bootstrap']['file'];
        if (file_exists($bootstrapFile)) {
            $content = file_get_contents($bootstrapFile);
            if (!preg_match("/namespace\s+$expectedNamespace/", $content)) {
                return ['passed' => false, 'message' => "Incorrect namespace in bootstrap file"];
            }
        }
        
        return ['passed' => true, 'message' => 'Namespace configuration correct'];
    }
    
    private function validateDocumentation(string $pluginName, array $plugin): array
    {
        $requiredDocs = ['README.md', 'API.md', 'HOOKS.md'];
        $missing = [];
        
        foreach ($requiredDocs as $doc) {
            if (!file_exists($plugin['path'] . '/' . $doc)) {
                $missing[] = $doc;
            }
        }
        
        return [
            'passed' => empty($missing),
            'message' => empty($missing) ? 'All documentation files present' : 'Missing docs: ' . implode(', ', $missing)
        ];
    }
    
    private function validateSecurity(string $pluginName, array $plugin): array
    {
        $phpFiles = $this->findPhpFiles($plugin['path']);
        $securityIssues = [];
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            
            // Check for direct superglobal access
            if (preg_match('/\$_(GET|POST|REQUEST|COOKIE|SERVER)\s*\[/', $content)) {
                $securityIssues[] = "Direct superglobal access in " . basename($file);
            }
            
            // Check for eval usage
            if (preg_match('/\beval\s*\(/', $content)) {
                $securityIssues[] = "eval() usage in " . basename($file);
            }
            
            // Check for SQL without preparation
            if (preg_match('/\$\w+\s*\.\s*["\']SELECT|INSERT|UPDATE|DELETE/', $content)) {
                $securityIssues[] = "Potential SQL injection in " . basename($file);
            }
        }
        
        return [
            'passed' => empty($securityIssues),
            'message' => empty($securityIssues) ? 'No security issues detected' : implode(', ', $securityIssues)
        ];
    }
    
    private function validateCodeQuality(string $pluginName, array $plugin): array
    {
        $phpFiles = $this->findPhpFiles($plugin['path']);
        $qualityIssues = [];
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            
            // Check for strict types
            if (!preg_match('/declare\s*\(\s*strict_types\s*=\s*1\s*\)/', $content)) {
                $qualityIssues[] = "Missing strict_types in " . basename($file);
                break; // Only report once per plugin
            }
            
            // Check for generic Exception catches
            if (preg_match('/catch\s*\(\s*\\\\?Exception\s+/', $content)) {
                $qualityIssues[] = "Generic Exception catch in " . basename($file);
            }
        }
        
        return [
            'passed' => empty($qualityIssues),
            'message' => empty($qualityIssues) ? 'Code quality standards met' : implode(', ', $qualityIssues)
        ];
    }
    
    private function validateManifest(string $pluginName, array $plugin): array
    {
        $manifest = $plugin['manifest'];
        $required = ['name', 'version', 'description', 'bootstrap', 'autoload'];
        $missing = [];
        
        foreach ($required as $field) {
            if (!isset($manifest[$field])) {
                $missing[] = $field;
            }
        }
        
        return [
            'passed' => empty($missing),
            'message' => empty($missing) ? 'Manifest complete' : 'Missing fields: ' . implode(', ', $missing)
        ];
    }
    
    private function calculateGrade(float $score): string
    {
        if ($score >= 95) return 'A+';
        if ($score >= 90) return 'A';
        if ($score >= 85) return 'B+';
        if ($score >= 80) return 'B';
        if ($score >= 75) return 'C+';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }
    
    private function getExpectedNamespace(string $pluginName): string
    {
        $parts = explode('-', $pluginName);
        $parts = array_map('ucfirst', $parts);
        return 'Shopologic\\Plugins\\' . implode('', $parts);
    }
    
    private function findPhpFiles(string $dir): array
    {
        $files = [];
        if (!is_dir($dir)) return $files;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    private function generateValidationReport(): void
    {
        echo "\n📊 VALIDATION REPORT\n";
        echo "====================\n\n";
        
        // Overall statistics
        $totalPlugins = count($this->validationResults);
        $aGradePlugins = count(array_filter($this->validationResults, fn($r) => in_array($r['grade'], ['A+', 'A'])));
        $bGradePlugins = count(array_filter($this->validationResults, fn($r) => in_array($r['grade'], ['B+', 'B'])));
        $failingPlugins = count(array_filter($this->validationResults, fn($r) => $r['score'] < 70));
        
        $averageScore = round(array_sum(array_column($this->validationResults, 'score')) / $totalPlugins);
        $passRate = round(($this->passedTests / $this->totalTests) * 100, 1);
        
        echo "📈 SUMMARY STATISTICS:\n";
        echo "- Total plugins validated: $totalPlugins\n";
        echo "- Average score: $averageScore%\n";
        echo "- Overall pass rate: $passRate%\n";
        echo "- A-grade plugins: $aGradePlugins\n";
        echo "- B-grade plugins: $bGradePlugins\n";
        echo "- Failing plugins: $failingPlugins\n\n";
        
        // Grade distribution
        $gradeCount = [];
        foreach ($this->validationResults as $result) {
            $grade = $result['grade'];
            $gradeCount[$grade] = ($gradeCount[$grade] ?? 0) + 1;
        }
        
        echo "📊 GRADE DISTRIBUTION:\n";
        foreach (['A+', 'A', 'B+', 'B', 'C+', 'C', 'D', 'F'] as $grade) {
            $count = $gradeCount[$grade] ?? 0;
            $percentage = round(($count / $totalPlugins) * 100);
            echo "- $grade: $count plugins ($percentage%)\n";
        }
        echo "\n";
        
        // Plugins with issues
        $pluginsWithIssues = array_filter($this->validationResults, fn($r) => !empty($r['issues']));
        if (!empty($pluginsWithIssues)) {
            echo "⚠️ PLUGINS REQUIRING ATTENTION:\n";
            foreach ($pluginsWithIssues as $pluginName => $result) {
                echo "- $pluginName (Score: {$result['score']}%):\n";
                foreach ($result['issues'] as $issue) {
                    echo "  • $issue\n";
                }
                echo "\n";
            }
        } else {
            echo "🎉 ALL PLUGINS PASSED VALIDATION!\n\n";
        }
        
        // Save detailed report
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'summary' => [
                'total_plugins' => $totalPlugins,
                'average_score' => $averageScore,
                'pass_rate' => $passRate,
                'grade_distribution' => $gradeCount
            ],
            'plugins' => $this->validationResults
        ];
        
        file_put_contents($this->pluginsDir . '/VALIDATION_REPORT.json', json_encode($report, JSON_PRETTY_PRINT));
        echo "💾 Detailed validation report saved to: VALIDATION_REPORT.json\n";
    }
    
    private function createQualityBadges(): void
    {
        echo "\n🏆 GENERATING QUALITY BADGES\n";
        echo "============================\n\n";
        
        $badges = [];
        
        foreach ($this->validationResults as $pluginName => $result) {
            $score = $result['score'];
            $grade = $result['grade'];
            
            // Determine badge color
            if ($score >= 90) {
                $color = 'brightgreen';
                $status = 'excellent';
            } elseif ($score >= 80) {
                $color = 'green';
                $status = 'good';
            } elseif ($score >= 70) {
                $color = 'yellow';
                $status = 'acceptable';
            } else {
                $color = 'red';
                $status = 'needs-work';
            }
            
            $badges[$pluginName] = [
                'score' => $score,
                'grade' => $grade,
                'status' => $status,
                'color' => $color,
                'badge_url' => "https://img.shields.io/badge/Quality-$score%25%20($grade)-$color"
            ];
            
            // Update plugin README with badge
            $readmePath = $this->plugins[$pluginName]['path'] . '/README.md';
            if (file_exists($readmePath)) {
                $readme = file_get_contents($readmePath);
                $badgeMarkdown = "![Quality Badge](https://img.shields.io/badge/Quality-$score%25%20($grade)-$color)\n";
                
                // Add badge at the top if not already present
                if (!preg_match('/!\[Quality Badge\]/', $readme)) {
                    $lines = explode("\n", $readme);
                    array_splice($lines, 1, 0, ['', $badgeMarkdown]);
                    file_put_contents($readmePath, implode("\n", $lines));
                }
            }
        }
        
        // Save badges summary
        file_put_contents($this->pluginsDir . '/QUALITY_BADGES.json', json_encode($badges, JSON_PRETTY_PRINT));
        echo "✅ Quality badges generated and added to README files\n";
        echo "💾 Badge data saved to: QUALITY_BADGES.json\n\n";
        
        echo "🎯 VALIDATION COMPLETE!\n";
        echo "All plugins have been thoroughly validated and quality badges generated.\n";
    }
}

// Run the validation
$validator = new FinalPluginValidator();
$validator->validateAll();