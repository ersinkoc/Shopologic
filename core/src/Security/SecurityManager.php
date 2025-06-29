<?php

declare(strict_types=1);

namespace Shopologic\Core\Security;

use Shopologic\Core\Configuration\ConfigurationManager;
use Shopologic\Core\Events\EventDispatcher;

/**
 * Security Manager
 * 
 * Centralized security management for the platform
 */
class SecurityManager
{
    private ConfigurationManager $config;
    private EventDispatcher $events;
    private array $scanners = [];
    private array $violations = [];
    
    public function __construct(ConfigurationManager $config, EventDispatcher $events)
    {
        $this->config = $config;
        $this->events = $events;
        $this->initializeScanners();
    }
    
    /**
     * Initialize security scanners
     */
    private function initializeScanners(): void
    {
        $this->scanners = [
            'code' => new CodeScanner(),
            'dependency' => new DependencyScanner(),
            'configuration' => new ConfigurationScanner(),
            'input' => new InputScanner(),
            'file' => new FileScanner()
        ];
    }
    
    /**
     * Run comprehensive security scan
     */
    public function runScan(array $options = []): SecurityReport
    {
        $report = new SecurityReport();
        
        foreach ($this->scanners as $type => $scanner) {
            if (empty($options) || in_array($type, $options)) {
                echo "Running {$type} security scan...\n";
                $results = $scanner->scan();
                $report->addResults($type, $results);
            }
        }
        
        // Dispatch security scan completed event
        $this->events->dispatch(new SecurityScanCompletedEvent($report));
        
        return $report;
    }
    
    /**
     * Scan specific file for vulnerabilities
     */
    public function scanFile(string $filePath): array
    {
        $violations = [];
        
        if (!file_exists($filePath)) {
            return $violations;
        }
        
        $content = file_get_contents($filePath);
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        
        // PHP file specific scans
        if ($extension === 'php') {
            $violations = array_merge($violations, $this->scanPhpFile($content, $filePath));
        }
        
        // General file scans
        $violations = array_merge($violations, $this->scanFileContent($content, $filePath));
        
        return $violations;
    }
    
    /**
     * Scan PHP file for security issues
     */
    private function scanPhpFile(string $content, string $filePath): array
    {
        $violations = [];
        
        // Check for dangerous functions
        $dangerousFunctions = [
            'eval', 'exec', 'system', 'shell_exec', 'passthru',
            'file_get_contents', 'file_put_contents', 'fopen',
            'include', 'require', 'include_once', 'require_once'
        ];
        
        foreach ($dangerousFunctions as $function) {
            if (preg_match('/\b' . preg_quote($function) . '\s*\(/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
                $line = substr_count(substr($content, 0, $matches[0][1]), "\n") + 1;
                $violations[] = [
                    'type' => 'dangerous_function',
                    'severity' => 'high',
                    'message' => "Potentially dangerous function '{$function}' used",
                    'file' => $filePath,
                    'line' => $line,
                    'context' => trim($matches[0][0])
                ];
            }
        }
        
        // Check for SQL injection patterns
        $sqlPatterns = [
            '/\$_(?:GET|POST|REQUEST|COOKIE)\s*\[\s*[\'"][^\'"]*[\'"]\s*\]\s*[;.]/' => 'Direct user input in SQL',
            '/mysql_query\s*\(\s*[\'"][^\'"]*\$/' => 'Dynamic SQL construction',
            '/->query\s*\(\s*[\'"][^\'"]*\$/' => 'Dynamic SQL query',
        ];
        
        foreach ($sqlPatterns as $pattern => $message) {
            if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $line = substr_count(substr($content, 0, $matches[0][1]), "\n") + 1;
                $violations[] = [
                    'type' => 'sql_injection',
                    'severity' => 'critical',
                    'message' => $message,
                    'file' => $filePath,
                    'line' => $line,
                    'context' => trim($matches[0][0])
                ];
            }
        }
        
        // Check for XSS vulnerabilities
        $xssPatterns = [
            '/echo\s+\$_(?:GET|POST|REQUEST)/' => 'Direct echo of user input',
            '/print\s+\$_(?:GET|POST|REQUEST)/' => 'Direct print of user input',
            '/<\?=\s*\$_(?:GET|POST|REQUEST)/' => 'Direct output of user input',
        ];
        
        foreach ($xssPatterns as $pattern => $message) {
            if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $line = substr_count(substr($content, 0, $matches[0][1]), "\n") + 1;
                $violations[] = [
                    'type' => 'xss',
                    'severity' => 'high',
                    'message' => $message,
                    'file' => $filePath,
                    'line' => $line,
                    'context' => trim($matches[0][0])
                ];
            }
        }
        
        // Check for file inclusion vulnerabilities
        if (preg_match('/(?:include|require)(?:_once)?\s*\(\s*\$_(?:GET|POST|REQUEST)/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $line = substr_count(substr($content, 0, $matches[0][1]), "\n") + 1;
            $violations[] = [
                'type' => 'file_inclusion',
                'severity' => 'critical',
                'message' => 'File inclusion with user input',
                'file' => $filePath,
                'line' => $line,
                'context' => trim($matches[0][0])
            ];
        }
        
        return $violations;
    }
    
    /**
     * Scan file content for general security issues
     */
    private function scanFileContent(string $content, string $filePath): array
    {
        $violations = [];
        
        // Check for hardcoded credentials
        $credentialPatterns = [
            '/password\s*=\s*[\'"][^\'"]{3,}[\'"]/' => 'Hardcoded password',
            '/api_key\s*=\s*[\'"][^\'"]{10,}[\'"]/' => 'Hardcoded API key',
            '/secret\s*=\s*[\'"][^\'"]{10,}[\'"]/' => 'Hardcoded secret',
            '/token\s*=\s*[\'"][^\'"]{20,}[\'"]/' => 'Hardcoded token',
        ];
        
        foreach ($credentialPatterns as $pattern => $message) {
            if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $line = substr_count(substr($content, 0, $matches[0][1]), "\n") + 1;
                $violations[] = [
                    'type' => 'hardcoded_credentials',
                    'severity' => 'medium',
                    'message' => $message,
                    'file' => $filePath,
                    'line' => $line,
                    'context' => trim($matches[0][0])
                ];
            }
        }
        
        // Check for debug information
        $debugPatterns = [
            '/var_dump\s*\(/' => 'Debug function var_dump',
            '/print_r\s*\(/' => 'Debug function print_r',
            '/var_export\s*\(/' => 'Debug function var_export',
            '/phpinfo\s*\(/' => 'Information disclosure phpinfo',
        ];
        
        foreach ($debugPatterns as $pattern => $message) {
            if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $line = substr_count(substr($content, 0, $matches[0][1]), "\n") + 1;
                $violations[] = [
                    'type' => 'debug_code',
                    'severity' => 'low',
                    'message' => $message,
                    'file' => $filePath,
                    'line' => $line,
                    'context' => trim($matches[0][0])
                ];
            }
        }
        
        return $violations;
    }
    
    /**
     * Validate configuration security
     */
    public function validateConfiguration(): array
    {
        $issues = [];
        
        // Check if debug mode is enabled in production
        if ($this->config->get('app.env') === 'production' && $this->config->get('app.debug') === true) {
            $issues[] = [
                'type' => 'configuration',
                'severity' => 'high',
                'message' => 'Debug mode enabled in production environment',
                'recommendation' => 'Set APP_DEBUG=false in production'
            ];
        }
        
        // Check encryption key
        $encryptionKey = $this->config->get('security.encryption_key');
        if (!$encryptionKey || $encryptionKey === 'base64:your-32-byte-key') {
            $issues[] = [
                'type' => 'configuration',
                'severity' => 'critical',
                'message' => 'Default or missing encryption key',
                'recommendation' => 'Generate a secure encryption key'
            ];
        }
        
        // Check JWT secret
        $jwtSecret = $this->config->get('security.jwt_secret');
        if (!$jwtSecret || $jwtSecret === 'your-256-bit-secret') {
            $issues[] = [
                'type' => 'configuration',
                'severity' => 'critical',
                'message' => 'Default or missing JWT secret',
                'recommendation' => 'Generate a secure JWT secret'
            ];
        }
        
        // Check HTTPS enforcement
        if ($this->config->get('app.env') === 'production' && !$this->config->get('session.secure')) {
            $issues[] = [
                'type' => 'configuration',
                'severity' => 'medium',
                'message' => 'HTTPS not enforced for sessions in production',
                'recommendation' => 'Set SESSION_SECURE=true in production'
            ];
        }
        
        return $issues;
    }
    
    /**
     * Get security recommendations
     */
    public function getSecurityRecommendations(): array
    {
        return [
            [
                'category' => 'Authentication',
                'recommendations' => [
                    'Enable two-factor authentication for admin users',
                    'Implement strong password policies',
                    'Use secure session management',
                    'Implement account lockout after failed attempts'
                ]
            ],
            [
                'category' => 'Data Protection',
                'recommendations' => [
                    'Encrypt sensitive data at rest',
                    'Use HTTPS for all communications',
                    'Implement proper input validation',
                    'Sanitize all output to prevent XSS'
                ]
            ],
            [
                'category' => 'Server Security',
                'recommendations' => [
                    'Keep PHP and dependencies updated',
                    'Disable unnecessary PHP functions',
                    'Configure proper file permissions',
                    'Use a Web Application Firewall'
                ]
            ],
            [
                'category' => 'Monitoring',
                'recommendations' => [
                    'Enable security logging',
                    'Monitor for suspicious activities',
                    'Implement intrusion detection',
                    'Regular security audits'
                ]
            ]
        ];
    }
    
    /**
     * Generate security headers
     */
    public function getSecurityHeaders(): array
    {
        return [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
            'Content-Security-Policy' => $this->generateCSPHeader(),
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload'
        ];
    }
    
    /**
     * Generate Content Security Policy header
     */
    private function generateCSPHeader(): string
    {
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' *.googleapis.com *.gstatic.com",
            "style-src 'self' 'unsafe-inline' *.googleapis.com",
            "img-src 'self' data: https:",
            "font-src 'self' *.googleapis.com *.gstatic.com",
            "connect-src 'self'",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'"
        ];
        
        return implode('; ', $csp);
    }
}

/**
 * Security Report
 */
class SecurityReport
{
    private array $results = [];
    private array $summary = [];
    
    public function addResults(string $scanType, array $results): void
    {
        $this->results[$scanType] = $results;
        $this->updateSummary($scanType, $results);
    }
    
    private function updateSummary(string $scanType, array $results): void
    {
        $this->summary[$scanType] = [
            'total' => count($results),
            'critical' => count(array_filter($results, fn($r) => ($r['severity'] ?? '') === 'critical')),
            'high' => count(array_filter($results, fn($r) => ($r['severity'] ?? '') === 'high')),
            'medium' => count(array_filter($results, fn($r) => ($r['severity'] ?? '') === 'medium')),
            'low' => count(array_filter($results, fn($r) => ($r['severity'] ?? '') === 'low'))
        ];
    }
    
    public function getResults(): array
    {
        return $this->results;
    }
    
    public function getSummary(): array
    {
        return $this->summary;
    }
    
    public function getTotalIssues(): int
    {
        return array_sum(array_column($this->summary, 'total'));
    }
    
    public function getCriticalIssues(): int
    {
        return array_sum(array_column($this->summary, 'critical'));
    }
    
    public function getHighIssues(): int
    {
        return array_sum(array_column($this->summary, 'high'));
    }
    
    public function getMediumIssues(): int
    {
        return array_sum(array_column($this->summary, 'medium'));
    }
    
    public function getLowIssues(): int
    {
        return array_sum(array_column($this->summary, 'low'));
    }
}

/**
 * Security Event
 */
class SecurityScanCompletedEvent extends \Shopologic\Core\Events\Event
{
    private SecurityReport $report;
    
    public function __construct(SecurityReport $report)
    {
        $this->report = $report;
    }
    
    public function getName(): string
    {
        return 'security.scan.completed';
    }
    
    public function getReport(): SecurityReport
    {
        return $this->report;
    }
}