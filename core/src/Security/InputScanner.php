<?php

declare(strict_types=1);

namespace Shopologic\Core\Security;

/**
 * Input Security Scanner
 * 
 * Scans for input validation and sanitization issues
 */
class InputScanner
{
    private array $inputSources = [];
    private array $sanitizationFunctions = [];
    private array $validationPatterns = [];
    
    public function __construct()
    {
        $this->initializeInputSources();
        $this->initializeSanitizationFunctions();
        $this->initializeValidationPatterns();
    }
    
    /**
     * Initialize input sources to monitor
     */
    private function initializeInputSources(): void
    {
        $this->inputSources = [
            '$_GET',
            '$_POST',
            '$_REQUEST',
            '$_COOKIE',
            '$_SERVER',
            '$_ENV',
            '$_FILES',
            'file_get_contents',
            'fgets',
            'fread'
        ];
    }
    
    /**
     * Initialize sanitization functions
     */
    private function initializeSanitizationFunctions(): void
    {
        $this->sanitizationFunctions = [
            'htmlspecialchars',
            'htmlentities',
            'strip_tags',
            'addslashes',
            'mysqli_real_escape_string',
            'filter_var',
            'filter_input',
            'trim',
            'preg_replace',
            'str_replace'
        ];
    }
    
    /**
     * Initialize validation patterns
     */
    private function initializeValidationPatterns(): void
    {
        $this->validationPatterns = [
            'email' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/',
            'url' => '/^https?:\/\/[^\s]+$/',
            'numeric' => '/^\d+$/',
            'alphanumeric' => '/^[a-zA-Z0-9]+$/',
            'alpha' => '/^[a-zA-Z]+$/'
        ];
    }
    
    /**
     * Scan for input validation issues
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
        $excludedPaths = [
            '/vendor/',
            '/node_modules/',
            '/tests/',
            '/.git/',
            '/storage/cache/',
            '/storage/logs/'
        ];
        
        foreach ($excludedPaths as $excludedPath) {
            if (strpos($filePath, $excludedPath) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Scan individual file for input issues
     */
    private function scanFile(string $filePath): array
    {
        $violations = [];
        
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return $violations;
        }
        
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);
        
        // Scan for unsanitized input usage
        $violations = array_merge($violations, $this->scanUnsanitizedInput($content, $filePath));
        
        // Scan for missing input validation
        $violations = array_merge($violations, $this->scanMissingValidation($content, $filePath));
        
        // Scan for SQL injection vulnerabilities
        $violations = array_merge($violations, $this->scanSqlInjection($content, $filePath));
        
        // Scan for XSS vulnerabilities
        $violations = array_merge($violations, $this->scanXssVulnerabilities($content, $filePath));
        
        // Scan for command injection
        $violations = array_merge($violations, $this->scanCommandInjection($content, $filePath));
        
        // Scan for file inclusion vulnerabilities
        $violations = array_merge($violations, $this->scanFileInclusion($content, $filePath));
        
        return $violations;
    }
    
    /**
     * Scan for unsanitized input usage
     */
    private function scanUnsanitizedInput(string $content, string $filePath): array
    {
        $violations = [];
        
        // Pattern for direct usage of user input without sanitization
        $patterns = [
            '/echo\s+\$_(?:GET|POST|REQUEST|COOKIE)\[/' => 'Direct echo of user input',
            '/print\s+\$_(?:GET|POST|REQUEST|COOKIE)\[/' => 'Direct print of user input',
            '/<\?=\s*\$_(?:GET|POST|REQUEST|COOKIE)\[/' => 'Direct output of user input',
            '/\$\w+\s*=\s*\$_(?:GET|POST|REQUEST|COOKIE)\[[^\]]+\];\s*echo\s+\$\w+/' => 'Unsanitized variable output'
        ];
        
        foreach ($patterns as $pattern => $description) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                    
                    $violations[] = [
                        'type' => 'unsanitized_input',
                        'severity' => 'high',
                        'message' => $description,
                        'file' => $filePath,
                        'line' => $line,
                        'context' => trim($match[0]),
                        'recommendation' => 'Sanitize user input before output'
                    ];
                }
            }
        }
        
        return $violations;
    }
    
    /**
     * Scan for missing input validation
     */
    private function scanMissingValidation(string $content, string $filePath): array
    {
        $violations = [];
        
        // Look for direct assignment from user input without validation
        $patterns = [
            '/\$\w+\s*=\s*\$_(?:GET|POST|REQUEST)\[[^\]]+\];\s*(?!if|while|for)/' => 'Assignment without validation',
            '/function\s+\w+\([^)]*\$_(?:GET|POST|REQUEST)/' => 'Function parameter from user input',
        ];
        
        foreach ($patterns as $pattern => $description) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                    
                    // Check if validation is present nearby
                    $hasValidation = $this->hasNearbyValidation($content, $match[1]);
                    
                    if (!$hasValidation) {
                        $violations[] = [
                            'type' => 'missing_validation',
                            'severity' => 'medium',
                            'message' => $description,
                            'file' => $filePath,
                            'line' => $line,
                            'context' => trim($match[0]),
                            'recommendation' => 'Add input validation before processing'
                        ];
                    }
                }
            }
        }
        
        return $violations;
    }
    
    /**
     * Scan for SQL injection vulnerabilities
     */
    private function scanSqlInjection(string $content, string $filePath): array
    {
        $violations = [];
        
        $patterns = [
            '/(?:SELECT|INSERT|UPDATE|DELETE).*?\$_(?:GET|POST|REQUEST)/' => 'SQL query with user input',
            '/mysql_query\s*\([^)]*\$_(?:GET|POST|REQUEST)/' => 'MySQL query with user input',
            '/mysqli_query\s*\([^)]*\$_(?:GET|POST|REQUEST)/' => 'MySQLi query with user input',
            '/->query\s*\([^)]*\$_(?:GET|POST|REQUEST)/' => 'Database query with user input',
            '/WHERE\s+\w+\s*=\s*[\'"]?\$_(?:GET|POST|REQUEST)/' => 'WHERE clause with user input'
        ];
        
        foreach ($patterns as $pattern => $description) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                    
                    $violations[] = [
                        'type' => 'sql_injection',
                        'severity' => 'critical',
                        'message' => $description,
                        'file' => $filePath,
                        'line' => $line,
                        'context' => trim($match[0]),
                        'recommendation' => 'Use prepared statements or parameterized queries'
                    ];
                }
            }
        }
        
        return $violations;
    }
    
    /**
     * Scan for XSS vulnerabilities
     */
    private function scanXssVulnerabilities(string $content, string $filePath): array
    {
        $violations = [];
        
        // JavaScript context patterns
        $jsPatterns = [
            '/<script[^>]*>\s*.*?\$_(?:GET|POST|REQUEST).*?<\/script>/s' => 'User input in JavaScript',
            '/document\.write\s*\([^)]*\$_(?:GET|POST|REQUEST)/' => 'User input in document.write',
            '/innerHTML\s*=\s*[\'"][^\'"]*\$_(?:GET|POST|REQUEST)/' => 'User input in innerHTML'
        ];
        
        foreach ($jsPatterns as $pattern => $description) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                    
                    $violations[] = [
                        'type' => 'xss_vulnerability',
                        'severity' => 'high',
                        'message' => $description,
                        'file' => $filePath,
                        'line' => $line,
                        'context' => trim($match[0]),
                        'recommendation' => 'Escape user input for JavaScript context'
                    ];
                }
            }
        }
        
        // HTML attribute patterns
        $attrPatterns = [
            '/<\w+[^>]*\s+\w+=[\'"]?[^\'"]*\$_(?:GET|POST|REQUEST)/' => 'User input in HTML attribute',
            '/<\w+[^>]*\s+on\w+=[\'"]?[^\'"]*\$_(?:GET|POST|REQUEST)/' => 'User input in event handler'
        ];
        
        foreach ($attrPatterns as $pattern => $description) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                    
                    $violations[] = [
                        'type' => 'xss_vulnerability',
                        'severity' => 'high',
                        'message' => $description,
                        'file' => $filePath,
                        'line' => $line,
                        'context' => trim($match[0]),
                        'recommendation' => 'Escape user input for HTML attribute context'
                    ];
                }
            }
        }
        
        return $violations;
    }
    
    /**
     * Scan for command injection vulnerabilities
     */
    private function scanCommandInjection(string $content, string $filePath): array
    {
        $violations = [];
        
        $patterns = [
            '/(?:exec|system|shell_exec|passthru)\s*\([^)]*\$_(?:GET|POST|REQUEST)/' => 'Command execution with user input',
            '/`[^`]*\$_(?:GET|POST|REQUEST)[^`]*`/' => 'Backtick command execution with user input',
            '/popen\s*\([^)]*\$_(?:GET|POST|REQUEST)/' => 'Process execution with user input'
        ];
        
        foreach ($patterns as $pattern => $description) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                    
                    $violations[] = [
                        'type' => 'command_injection',
                        'severity' => 'critical',
                        'message' => $description,
                        'file' => $filePath,
                        'line' => $line,
                        'context' => trim($match[0]),
                        'recommendation' => 'Validate and sanitize input, use whitelisting'
                    ];
                }
            }
        }
        
        return $violations;
    }
    
    /**
     * Scan for file inclusion vulnerabilities
     */
    private function scanFileInclusion(string $content, string $filePath): array
    {
        $violations = [];
        
        $patterns = [
            '/(?:include|require)(?:_once)?\s*\([^)]*\$_(?:GET|POST|REQUEST)/' => 'File inclusion with user input',
            '/file_get_contents\s*\([^)]*\$_(?:GET|POST|REQUEST)/' => 'File reading with user input',
            '/fopen\s*\([^)]*\$_(?:GET|POST|REQUEST)/' => 'File opening with user input'
        ];
        
        foreach ($patterns as $pattern => $description) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                    
                    $violations[] = [
                        'type' => 'file_inclusion',
                        'severity' => 'critical',
                        'message' => $description,
                        'file' => $filePath,
                        'line' => $line,
                        'context' => trim($match[0]),
                        'recommendation' => 'Use whitelist validation for file paths'
                    ];
                }
            }
        }
        
        return $violations;
    }
    
    /**
     * Check if validation is present near the match
     */
    private function hasNearbyValidation(string $content, int $offset): bool
    {
        // Get a window around the match
        $start = max(0, $offset - 500);
        $end = min(strlen($content), $offset + 500);
        $window = substr($content, $start, $end - $start);
        
        // Check for validation patterns
        $validationPatterns = [
            '/filter_var\s*\(/',
            '/filter_input\s*\(/',
            '/is_numeric\s*\(/',
            '/is_string\s*\(/',
            '/is_array\s*\(/',
            '/preg_match\s*\(/',
            '/strlen\s*\([^)]+\)\s*[<>]/',
            '/if\s*\([^)]*empty\s*\(/',
            '/if\s*\([^)]*isset\s*\(/',
            '/htmlspecialchars\s*\(/',
            '/htmlentities\s*\(/',
            '/strip_tags\s*\(/'
        ];
        
        foreach ($validationPatterns as $pattern) {
            if (preg_match($pattern, $window)) {
                return true;
            }
        }
        
        return false;
    }
}