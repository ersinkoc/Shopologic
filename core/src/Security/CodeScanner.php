<?php

declare(strict_types=1);

namespace Shopologic\Core\Security;

/**
 * Code Security Scanner
 * 
 * Scans source code for security vulnerabilities
 */
class CodeScanner
{
    private array $dangerousPatterns = [];
    private array $excludedPaths = [];
    
    public function __construct()
    {
        $this->initializeDangerousPatterns();
        $this->excludedPaths = [
            '/vendor/',
            '/node_modules/',
            '/tests/',
            '/.git/',
            '/storage/cache/',
            '/storage/logs/'
        ];
    }
    
    /**
     * Initialize dangerous code patterns
     */
    private function initializeDangerousPatterns(): void
    {
        $this->dangerousPatterns = [
            // Command injection
            'command_injection' => [
                '/\b(?:exec|system|shell_exec|passthru|popen|proc_open)\s*\(\s*\$/' => 'Command injection vulnerability',
                '/\b(?:exec|system|shell_exec|passthru)\s*\(\s*[\'"][^\'"]*(.*?)\$/' => 'Dynamic command execution',
            ],
            
            // SQL injection
            'sql_injection' => [
                '/\$_(?:GET|POST|REQUEST|COOKIE)\s*\[\s*[\'"][^\'"]*[\'"]\s*\]\s*[;.]/' => 'Direct user input in query',
                '/mysql_query\s*\(\s*[\'"][^\'"]*\$/' => 'Dynamic SQL construction',
                '/->query\s*\(\s*[\'"][^\'"]*\$/' => 'Unsafe SQL query',
                '/WHERE\s+[a-zA-Z_][a-zA-Z0-9_]*\s*=\s*\$_/' => 'Unsafe WHERE clause',
            ],
            
            // XSS vulnerabilities
            'xss' => [
                '/echo\s+\$_(?:GET|POST|REQUEST|COOKIE)/' => 'Direct echo of user input',
                '/print\s+\$_(?:GET|POST|REQUEST|COOKIE)/' => 'Direct print of user input',
                '/<\?=\s*\$_(?:GET|POST|REQUEST|COOKIE)/' => 'Direct output of user input',
                '/innerHTML\s*=\s*[\'"][^\'"]*\$/' => 'Dynamic HTML injection',
            ],
            
            // File inclusion
            'file_inclusion' => [
                '/(?:include|require)(?:_once)?\s*\(\s*\$_(?:GET|POST|REQUEST)/' => 'File inclusion with user input',
                '/file_get_contents\s*\(\s*\$_(?:GET|POST|REQUEST)/' => 'Remote file inclusion',
                '/fopen\s*\(\s*\$_(?:GET|POST|REQUEST)/' => 'File access with user input',
            ],
            
            // Deserialization
            'deserialization' => [
                '/unserialize\s*\(\s*\$_(?:GET|POST|REQUEST|COOKIE)/' => 'Unsafe deserialization',
                '/json_decode\s*\(\s*\$_(?:GET|POST|REQUEST)/' => 'JSON deserialization without validation',
            ],
            
            // Code injection
            'code_injection' => [
                '/eval\s*\(\s*\$/' => 'Code injection via eval',
                '/create_function\s*\(/' => 'Dynamic function creation',
                '/preg_replace\s*\([^,]*\/[^,]*e[^,]*,/' => 'Code execution via regex',
            ],
            
            // Path traversal
            'path_traversal' => [
                '/\$_(?:GET|POST|REQUEST)\[[^\]]+\].*?\.\./' => 'Path traversal attempt',
                '/file_get_contents\s*\([^)]*\.\./' => 'File access with path traversal',
            ],
            
            // Information disclosure
            'info_disclosure' => [
                '/phpinfo\s*\(/' => 'Information disclosure via phpinfo',
                '/var_dump\s*\(\s*\$_/' => 'Variable dump of user input',
                '/print_r\s*\(\s*\$_/' => 'Print_r of user input',
                '/error_reporting\s*\(\s*E_ALL/' => 'Full error reporting enabled',
            ],
            
            // Weak cryptography
            'weak_crypto' => [
                '/md5\s*\(\s*\$/' => 'Weak MD5 hashing',
                '/sha1\s*\(\s*\$/' => 'Weak SHA1 hashing',
                '/crypt\s*\(\s*\$[^,]*,\s*[\'"][^\'"]{0,7}[\'"]/' => 'Weak crypt salt',
            ],
            
            // Authentication bypass
            'auth_bypass' => [
                '/\$_SESSION\[[^\]]+\]\s*=\s*true/' => 'Direct session manipulation',
                '/if\s*\(\s*\$_(?:GET|POST)\[[^\]]+\]\s*==\s*[\'"]admin[\'"]/' => 'Weak authentication check',
            ]
        ];
    }
    
    /**
     * Scan source code for vulnerabilities
     */
    public function scan(): array
    {
        $violations = [];
        $rootPath = defined('SHOPOLOGIC_ROOT') ? SHOPOLOGIC_ROOT : dirname(__DIR__, 3);
        
        $files = $this->getPhpFiles($rootPath);
        
        foreach ($files as $file) {
            if ($this->shouldExcludeFile($file)) {
                continue;
            }
            
            $fileViolations = $this->scanFile($file);
            $violations = array_merge($violations, $fileViolations);
        }
        
        return $violations;
    }
    
    /**
     * Get all PHP files in directory
     */
    private function getPhpFiles(string $directory): array
    {
        $files = [];
        
        if (!is_dir($directory)) {
            return $files;
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    /**
     * Check if file should be excluded from scan
     */
    private function shouldExcludeFile(string $filePath): bool
    {
        foreach ($this->excludedPaths as $excludedPath) {
            if (strpos($filePath, $excludedPath) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Scan individual file
     */
    private function scanFile(string $filePath): array
    {
        $violations = [];
        
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return $violations;
        }
        
        $content = file_get_contents($filePath);
        
        foreach ($this->dangerousPatterns as $category => $patterns) {
            foreach ($patterns as $pattern => $description) {
                if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                        
                        $violations[] = [
                            'type' => $category,
                            'severity' => $this->getSeverityForCategory($category),
                            'message' => $description,
                            'file' => $filePath,
                            'line' => $line,
                            'context' => trim($match[0]),
                            'pattern' => $pattern
                        ];
                    }
                }
            }
        }
        
        // Additional checks
        $violations = array_merge($violations, $this->checkHardcodedCredentials($content, $filePath));
        $violations = array_merge($violations, $this->checkDebugCode($content, $filePath));
        $violations = array_merge($violations, $this->checkUnsafeFileOperations($content, $filePath));
        
        return $violations;
    }
    
    /**
     * Check for hardcoded credentials
     */
    private function checkHardcodedCredentials(string $content, string $filePath): array
    {
        $violations = [];
        
        $credentialPatterns = [
            '/(?:password|pwd|pass)\s*[=:]\s*[\'"][^\'"]{3,}[\'"]/' => 'Hardcoded password',
            '/(?:api_key|apikey)\s*[=:]\s*[\'"][^\'"]{10,}[\'"]/' => 'Hardcoded API key',
            '/(?:secret|secret_key)\s*[=:]\s*[\'"][^\'"]{10,}[\'"]/' => 'Hardcoded secret',
            '/(?:token|access_token)\s*[=:]\s*[\'"][^\'"]{20,}[\'"]/' => 'Hardcoded token',
            '/(?:private_key|privatekey)\s*[=:]\s*[\'"][^\'"]{50,}[\'"]/' => 'Hardcoded private key',
        ];
        
        foreach ($credentialPatterns as $pattern => $description) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                    
                    // Skip if it's a placeholder or example
                    if (preg_match('/(?:example|placeholder|your_|test_|dummy)/', $match[0])) {
                        continue;
                    }
                    
                    $violations[] = [
                        'type' => 'hardcoded_credentials',
                        'severity' => 'high',
                        'message' => $description,
                        'file' => $filePath,
                        'line' => $line,
                        'context' => trim($match[0])
                    ];
                }
            }
        }
        
        return $violations;
    }
    
    /**
     * Check for debug code
     */
    private function checkDebugCode(string $content, string $filePath): array
    {
        $violations = [];
        
        $debugPatterns = [
            '/var_dump\s*\(/' => 'Debug function var_dump',
            '/print_r\s*\(/' => 'Debug function print_r',
            '/var_export\s*\(/' => 'Debug function var_export',
            '/debug_backtrace\s*\(/' => 'Debug function debug_backtrace',
            '/console\.log\s*\(/' => 'JavaScript console.log',
            '/alert\s*\(/' => 'JavaScript alert',
        ];
        
        foreach ($debugPatterns as $pattern => $description) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                    
                    $violations[] = [
                        'type' => 'debug_code',
                        'severity' => 'low',
                        'message' => $description,
                        'file' => $filePath,
                        'line' => $line,
                        'context' => trim($match[0])
                    ];
                }
            }
        }
        
        return $violations;
    }
    
    /**
     * Check for unsafe file operations
     */
    private function checkUnsafeFileOperations(string $content, string $filePath): array
    {
        $violations = [];
        
        $filePatterns = [
            '/file_put_contents\s*\([^,]*\$_/' => 'File write with user input',
            '/fwrite\s*\([^,]*,\s*\$_/' => 'File write with user input',
            '/move_uploaded_file\s*\([^,]*,\s*\$_/' => 'File upload without validation',
            '/unlink\s*\(\s*\$_/' => 'File deletion with user input',
            '/rmdir\s*\(\s*\$_/' => 'Directory deletion with user input',
        ];
        
        foreach ($filePatterns as $pattern => $description) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                    
                    $violations[] = [
                        'type' => 'unsafe_file_operation',
                        'severity' => 'medium',
                        'message' => $description,
                        'file' => $filePath,
                        'line' => $line,
                        'context' => trim($match[0])
                    ];
                }
            }
        }
        
        return $violations;
    }
    
    /**
     * Get severity level for category
     */
    private function getSeverityForCategory(string $category): string
    {
        $severityMap = [
            'command_injection' => 'critical',
            'sql_injection' => 'critical',
            'code_injection' => 'critical',
            'file_inclusion' => 'critical',
            'xss' => 'high',
            'deserialization' => 'high',
            'path_traversal' => 'high',
            'auth_bypass' => 'high',
            'info_disclosure' => 'medium',
            'weak_crypto' => 'medium',
            'unsafe_file_operation' => 'medium',
            'hardcoded_credentials' => 'high',
            'debug_code' => 'low'
        ];
        
        return $severityMap[$category] ?? 'medium';
    }
}