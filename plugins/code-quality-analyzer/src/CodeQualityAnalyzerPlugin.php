<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CodeQualityAnalyzer;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\HookSystem;
use CodeQualityAnalyzer\Services\{
    AnalysisEngine,
    SecurityScanner,
    MetricsCalculator,
    RefactoringEngine,
    ReportGenerator,
    IssueTracker,
    StandardsManager,;
    CIIntegration;
};
use CodeQualityAnalyzer\Analyzers\{
    PHPSyntaxAnalyzer,
    PSRAnalyzer,
    ComplexityAnalyzer,
    SecurityAnalyzer,
    DocumentationAnalyzer,
    DeadCodeAnalyzer,;
    DuplicationDetector;
};

class CodeQualityAnalyzerPlugin extends AbstractPlugin
{
    private AnalysisEngine $analysisEngine;
    private SecurityScanner $securityScanner;
    private MetricsCalculator $metricsCalculator;
    private RefactoringEngine $refactoringEngine;
    private ReportGenerator $reportGenerator;
    private IssueTracker $issueTracker;
    private StandardsManager $standardsManager;
    private CIIntegration $ciIntegration;
    
    private array $analyzers = [];
    private array $currentAnalysis = [];
    
    /**
     * Plugin activation
     */
    public function activate(): void
    {
        // Run migrations
        $this->runMigrations();
        
        // Install default standards and rules
        $this->installDefaultStandards();
        
        // Initialize security database
        $this->initializeSecurityDatabase();
        
        // Set default options
        $this->setDefaultOptions();
        
        // Create required directories
        $this->createDirectories();
        
        // Schedule initial analysis
        $this->scheduleInitialAnalysis();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        // Cancel any running analyses
        $this->cancelRunningAnalyses();
        
        // Save current state
        $this->saveAnalysisState();
    }
    
    /**
     * Register hooks
     */
    protected function registerHooks(): void
    {
        // Initialize services
        HookSystem::addAction('init', [$this, 'initializeServices']);
        
        // File change detection
        HookSystem::addAction('file.saved', [$this, 'analyzeChangedFile']);
        HookSystem::addAction('file.created', [$this, 'analyzeNewFile']);
        
        // Pre-commit hooks
        HookSystem::addFilter('git.pre_commit', [$this, 'preCommitAnalysis']);
        HookSystem::addFilter('deploy.pre_deploy', [$this, 'preDeployAnalysis']);
        
        // Admin interface
        HookSystem::addAction('admin_menu', [$this, 'registerAdminMenu']);
        HookSystem::addAction('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        
        // Developer tools integration
        HookSystem::addAction('developer_tools_menu', [$this, 'addDeveloperToolsMenu']);
        HookSystem::addFilter('ide.code_actions', [$this, 'addCodeActions']);
        
        // API endpoints
        $this->registerApiEndpoints();
        
        // Real-time analysis
        HookSystem::addAction('wp_ajax_analyze_code', [$this, 'handleAjaxAnalysis']);
        HookSystem::addAction('wp_ajax_get_suggestions', [$this, 'handleAjaxSuggestions']);
        
        // Scheduled tasks
        HookSystem::addAction('code_quality_analyze_recent', [$this, 'analyzeRecentChanges']);
        HookSystem::addAction('code_quality_full_analysis', [$this, 'fullCodebaseAnalysis']);
        HookSystem::addAction('code_quality_update_security', [$this, 'updateSecurityDatabase']);
        
        // CI/CD webhooks
        HookSystem::addAction('rest_api_init', [$this, 'registerWebhookEndpoints']);
    }
    
    /**
     * Initialize services
     */
    public function initializeServices(): void
    {
        // Initialize core services
        $this->analysisEngine = new AnalysisEngine($this->container);
        $this->securityScanner = new SecurityScanner($this->container);
        $this->metricsCalculator = new MetricsCalculator($this->container);
        $this->refactoringEngine = new RefactoringEngine($this->container);
        $this->reportGenerator = new ReportGenerator($this->container);
        $this->issueTracker = new IssueTracker($this->container);
        $this->standardsManager = new StandardsManager($this->container);
        $this->ciIntegration = new CIIntegration($this->container);
        
        // Register analyzers
        $this->registerAnalyzers();
        
        // Load custom rules
        $this->loadCustomRules();
    }
    
    /**
     * Register analyzers
     */
    private function registerAnalyzers(): void
    {
        $enabledAnalyzers = $this->getOption('enabled_analyzers', []);
        
        // PHP Syntax Analyzer
        if (in_array('php_syntax', $enabledAnalyzers)) {
            $this->analyzers['php_syntax'] = new PHPSyntaxAnalyzer($this->container);
        }
        
        // PSR Standards Analyzer
        if (in_array('coding_standards', $enabledAnalyzers)) {
            $this->analyzers['psr'] = new PSRAnalyzer(
                $this->container,
                $this->getOption('coding_standards', ['PSR-12'])
            );
        }
        
        // Complexity Analyzer
        if (in_array('complexity', $enabledAnalyzers)) {
            $this->analyzers['complexity'] = new ComplexityAnalyzer(
                $this->container,
                $this->getOption('complexity_thresholds', [])
            );
        }
        
        // Security Analyzer
        if (in_array('security', $enabledAnalyzers)) {
            $this->analyzers['security'] = new SecurityAnalyzer(
                $this->container,
                $this->getOption('security_rules', [])
            );
        }
        
        // Documentation Analyzer
        if (in_array('documentation', $enabledAnalyzers)) {
            $this->analyzers['documentation'] = new DocumentationAnalyzer($this->container);
        }
        
        // Dead Code Analyzer
        if (in_array('dead_code', $enabledAnalyzers)) {
            $this->analyzers['dead_code'] = new DeadCodeAnalyzer($this->container);
        }
        
        // Code Duplication Detector
        if (in_array('duplicates', $enabledAnalyzers)) {
            $this->analyzers['duplicates'] = new DuplicationDetector($this->container);
        }
        
        // Register analyzers with engine
        foreach ($this->analyzers as $name => $analyzer) {
            $this->analysisEngine->registerAnalyzer($name, $analyzer);
        }
    }
    
    /**
     * Analyze changed file
     */
    public function analyzeChangedFile(string $filePath): void
    {
        if (!$this->shouldAnalyzeFile($filePath)) {
            return;
        }
        
        // Start analysis
        $this->currentAnalysis[$filePath] = [
            'started_at' => microtime(true),
            'status' => 'running'
        ];
        
        try {
            // Run analysis
            $results = $this->analysisEngine->analyzeFile($filePath);
            
            // Process results
            $this->processAnalysisResults($filePath, $results);
            
            // Check for auto-fix
            if ($this->getOption('auto_fix', false)) {
                $this->applyAutoFixes($filePath, $results);
            }
            
            // Update metrics
            $this->updateFileMetrics($filePath, $results);
            
            // Track issues
            $this->trackIssues($filePath, $results);
            
            // Notify if critical issues
            if ($this->hasCriticalIssues($results)) {
                $this->notifyCriticalIssues($filePath, $results);
            }
            
        } catch (\RuntimeException $e) {
            $this->log('Analysis failed for ' . $filePath . ': ' . $e->getMessage(), 'error');
        } finally {
            unset($this->currentAnalysis[$filePath]);
        }
    }
    
    /**
     * Pre-commit analysis
     */
    public function preCommitAnalysis(array $files): array
    {
        $results = [
            'passed' => true,
            'issues' => [],
            'metrics' => []
        ];
        
        foreach ($files as $file) {
            if (!$this->shouldAnalyzeFile($file)) {
                continue;
            }
            
            $analysis = $this->analysisEngine->analyzeFile($file);
            
            // Check quality gates
            $qualityGate = $this->checkQualityGate($analysis);
            
            if (!$qualityGate['passed']) {
                $results['passed'] = false;
                $results['issues'][$file] = $qualityGate['issues'];
            }
            
            $results['metrics'][$file] = $analysis['metrics'];
        }
        
        // Generate pre-commit report
        if (!$results['passed']) {
            $this->generatePreCommitReport($results);
        }
        
        return $results;
    }
    
    /**
     * Full codebase analysis
     */
    public function fullCodebaseAnalysis(): void
    {
        $startTime = microtime(true);
        
        // Get all analyzable files
        $files = $this->getAnalyzableFiles();
        
        $this->log('Starting full codebase analysis: ' . count($files) . ' files', 'info');
        
        // Initialize batch analysis
        $batchId = $this->analysisEngine->startBatchAnalysis();
        
        $results = [
            'total_files' => count($files),
            'analyzed_files' => 0,
            'total_issues' => 0,
            'issues_by_severity' => [],
            'metrics' => []
        ];
        
        // Analyze files in batches
        $batchSize = 50;
        $batches = array_chunk($files, $batchSize);
        
        foreach ($batches as $batch) {
            $batchResults = $this->analysisEngine->analyzeBatch($batch, $batchId);
            
            // Aggregate results
            $results['analyzed_files'] += count($batchResults);
            
            foreach ($batchResults as $filePath => $fileResults) {
                $results['total_issues'] += count($fileResults['issues'] ?? []);
                
                // Count issues by severity
                foreach ($fileResults['issues'] ?? [] as $issue) {
                    $severity = $issue['severity'];
                    $results['issues_by_severity'][$severity] = 
                        ($results['issues_by_severity'][$severity] ?? 0) + 1;
                }
                
                // Aggregate metrics
                foreach ($fileResults['metrics'] ?? [] as $metric => $value) {
                    if (!isset($results['metrics'][$metric])) {
                        $results['metrics'][$metric] = [];
                    }
                    $results['metrics'][$metric][] = $value;
                }
            }
        }
        
        // Calculate aggregate metrics
        $results['quality_score'] = $this->calculateOverallQualityScore($results);
        $results['analysis_time'] = microtime(true) - $startTime;
        
        // Save results
        $reportId = $this->reportGenerator->generateFullReport($results, $batchId);
        
        // Update baseline
        $this->updateQualityBaseline($results);
        
        // Send notifications
        $this->sendAnalysisComplete($reportId, $results);
        
        $this->log('Full codebase analysis completed in ' . round($results['analysis_time'], 2) . 's', 'info');
    }
    
    /**
     * Check quality gate
     */
    private function checkQualityGate(array $analysis): array
    {
        $qualityGate = $this->getOption('quality_gate', []);
        $passed = true;
        $failures = [];
        
        // Check quality score
        if (isset($qualityGate['min_quality_score'])) {
            $score = $analysis['quality_score'] ?? 0;
            if ($score < $qualityGate['min_quality_score']) {
                $passed = false;
                $failures[] = sprintf(
                    'Quality score %.1f%% is below threshold %.1f%%',
                    $score,
                    $qualityGate['min_quality_score']
                );
            }
        }
        
        // Check critical issues
        if (isset($qualityGate['max_critical_issues'])) {
            $criticalCount = $this->countIssuesBySeverity($analysis['issues'] ?? [], 'critical');
            if ($criticalCount > $qualityGate['max_critical_issues']) {
                $passed = false;
                $failures[] = sprintf(
                    '%d critical issues exceed maximum of %d',
                    $criticalCount,
                    $qualityGate['max_critical_issues']
                );
            }
        }
        
        // Check major issues
        if (isset($qualityGate['max_major_issues'])) {
            $majorCount = $this->countIssuesBySeverity($analysis['issues'] ?? [], 'major');
            if ($majorCount > $qualityGate['max_major_issues']) {
                $passed = false;
                $failures[] = sprintf(
                    '%d major issues exceed maximum of %d',
                    $majorCount,
                    $qualityGate['max_major_issues']
                );
            }
        }
        
        // Check test coverage
        if (isset($qualityGate['min_test_coverage'])) {
            $coverage = $analysis['metrics']['test_coverage'] ?? 0;
            if ($coverage < $qualityGate['min_test_coverage']) {
                $passed = false;
                $failures[] = sprintf(
                    'Test coverage %.1f%% is below threshold %.1f%%',
                    $coverage,
                    $qualityGate['min_test_coverage']
                );
            }
        }
        
        return [
            'passed' => $passed,
            'issues' => $failures
        ];
    }
    
    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        add_menu_page(
            'Code Quality',
            'Code Quality',
            'code_quality.access',
            'code-quality-analyzer',
            [$this, 'renderDashboard'],
            'dashicons-code-standards',
            75
        );
        
        add_submenu_page(
            'code-quality-analyzer',
            'Analysis',
            'Analysis',
            'code_quality.analyze',
            'code-quality-analyze',
            [$this, 'renderAnalysis']
        );
        
        add_submenu_page(
            'code-quality-analyzer',
            'Issues',
            'Issues',
            'code_quality.view_reports',
            'code-quality-issues',
            [$this, 'renderIssues']
        );
        
        add_submenu_page(
            'code-quality-analyzer',
            'Reports',
            'Reports',
            'code_quality.view_reports',
            'code-quality-reports',
            [$this, 'renderReports']
        );
        
        add_submenu_page(
            'code-quality-analyzer',
            'Standards',
            'Standards',
            'code_quality.manage_standards',
            'code-quality-standards',
            [$this, 'renderStandards']
        );
        
        add_submenu_page(
            'code-quality-analyzer',
            'Settings',
            'Settings',
            'code_quality.configure_rules',
            'code-quality-settings',
            [$this, 'renderSettings']
        );
    }
    
    /**
     * Install default standards
     */
    private function installDefaultStandards(): void
    {
        $standards = [
            'PSR-1' => 'PSR-1 Basic Coding Standard',
            'PSR-2' => 'PSR-2 Coding Style Guide',
            'PSR-4' => 'PSR-4 Autoloading Standard',
            'PSR-12' => 'PSR-12 Extended Coding Style',
            'WordPress' => 'WordPress Coding Standards',
            'Shopologic' => 'Shopologic Internal Standards'
        ];
        
        foreach ($standards as $code => $name) {
            $this->standardsManager->installStandard($code, $name);
        }
    }
    
    /**
     * Initialize security database
     */
    private function initializeSecurityDatabase(): void
    {
        // Download latest security advisories
        $this->securityScanner->updateAdvisoryDatabase();
        
        // Load OWASP rules
        $this->securityScanner->loadOWASPRules();
        
        // Load CWE database
        $this->securityScanner->loadCWEDatabase();
    }
    
    /**
     * Create required directories
     */
    private function createDirectories(): void
    {
        $dirs = [
            $this->getPluginPath() . '/reports',
            $this->getPluginPath() . '/cache',
            $this->getPluginPath() . '/temp',
            $this->getPluginPath() . '/baselines'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
        }
    }

    /**
     * Register Services
     */
    protected function registerServices(): void
    {
        // TODO: Implement registerServices
    }

    /**
     * Register EventListeners
     */
    protected function registerEventListeners(): void
    {
        // TODO: Implement registerEventListeners
    }

    /**
     * Register Routes
     */
    protected function registerRoutes(): void
    {
        // TODO: Implement registerRoutes
    }

    /**
     * Register Permissions
     */
    protected function registerPermissions(): void
    {
        // TODO: Implement registerPermissions
    }

    /**
     * Register ScheduledJobs
     */
    protected function registerScheduledJobs(): void
    {
        // TODO: Implement registerScheduledJobs
    }
}