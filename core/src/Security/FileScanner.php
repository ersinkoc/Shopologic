<?php

declare(strict_types=1);

namespace Shopologic\Core\Security;

/**
 * File Security Scanner
 * 
 * Scans files and directories for security issues
 */
class FileScanner
{
    private array $dangerousExtensions = [];
    private array $suspiciousPatterns = [];
    private array $webAccessiblePaths = [];
    
    public function __construct()
    {
        $this->initializeDangerousExtensions();
        $this->initializeSuspiciousPatterns();
        $this->initializeWebAccessiblePaths();
    }
    
    /**
     * Initialize dangerous file extensions
     */
    private function initializeDangerousExtensions(): void
    {
        $this->dangerousExtensions = [
            'exe' => 'Executable file',
            'bat' => 'Batch file',
            'cmd' => 'Command file',
            'com' => 'Command file',
            'pif' => 'Program information file',
            'scr' => 'Screen saver file',
            'vbs' => 'Visual Basic script',
            'js' => 'JavaScript file (if in uploads)',
            'jar' => 'Java archive',
            'asp' => 'Active Server Page',
            'aspx' => 'ASP.NET page',
            'jsp' => 'Java Server Page',
            'cgi' => 'CGI script',
            'pl' => 'Perl script',
            'py' => 'Python script (if in uploads)',
            'rb' => 'Ruby script (if in uploads)',
            'sh' => 'Shell script'
        ];
    }
    
    /**
     * Initialize suspicious file patterns
     */
    private function initializeSuspiciousPatterns(): void
    {
        $this->suspiciousPatterns = [
            'shell' => [
                'pattern' => '/(?:c99|r57|b374k|crystal|shell|bypass|exploit)/i',
                'description' => 'Potential web shell'
            ],
            'backdoor' => [
                'pattern' => '/(?:backdoor|trojan|rootkit|keylogger)/i',
                'description' => 'Potential backdoor'
            ],
            'base64_php' => [
                'pattern' => '/eval\s*\(\s*base64_decode\s*\(/i',
                'description' => 'Base64 encoded PHP execution'
            ],
            'obfuscated' => [
                'pattern' => '/(?:\$[a-zA-Z_][a-zA-Z0-9_]*\[\d+\]\s*\.?){5,}/i',
                'description' => 'Heavily obfuscated code'
            ],
            'hex_decode' => [
                'pattern' => '/hex2bin\s*\(|pack\s*\(\s*[\'"]H\*/',
                'description' => 'Hex-encoded content'
            ],
            'suspicious_functions' => [
                'pattern' => '/(?:assert|create_function|call_user_func)\s*\(\s*[\'"]?\$/',
                'description' => 'Suspicious function usage'
            ]
        ];
    }
    
    /**
     * Initialize web-accessible paths to check
     */
    private function initializeWebAccessiblePaths(): void
    {
        $this->webAccessiblePaths = [
            'public',
            'www',
            'web',
            'htdocs',
            'html'
        ];
    }
    
    /**
     * Scan files for security issues
     */
    public function scan(): array
    {
        $violations = [];
        $rootPath = defined('SHOPOLOGIC_ROOT') ? SHOPOLOGIC_ROOT : dirname(__DIR__, 3);
        
        // Scan for dangerous files
        $violations = array_merge($violations, $this->scanDangerousFiles($rootPath));
        
        // Scan for file permission issues
        $violations = array_merge($violations, $this->scanFilePermissions($rootPath));
        
        // Scan for suspicious content
        $violations = array_merge($violations, $this->scanSuspiciousContent($rootPath));
        
        // Scan for web-accessible sensitive files
        $violations = array_merge($violations, $this->scanWebAccessibleFiles($rootPath));
        
        // Scan for backup files
        $violations = array_merge($violations, $this->scanBackupFiles($rootPath));
        
        // Scan upload directories
        $violations = array_merge($violations, $this->scanUploadDirectories($rootPath));
        
        return $violations;
    }
    
    /**
     * Scan for dangerous files
     */
    private function scanDangerousFiles(string $rootPath): array
    {
        $violations = [];
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($rootPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            $extension = strtolower($file->getExtension());
            $filePath = $file->getPathname();
            
            // Skip vendor and common directories
            if ($this->shouldSkipPath($filePath)) {
                continue;
            }
            
            if (isset($this->dangerousExtensions[$extension])) {
                $violations[] = [
                    'type' => 'dangerous_file',
                    'severity' => 'high',
                    'message' => "Dangerous file type: {$this->dangerousExtensions[$extension]}",
                    'file' => $filePath,
                    'extension' => $extension,
                    'recommendation' => 'Remove file or restrict access'
                ];
            }
            
            // Check for suspicious filenames
            $filename = $file->getFilename();
            if ($this->hasSuspiciousFilename($filename)) {
                $violations[] = [
                    'type' => 'suspicious_filename',
                    'severity' => 'medium',
                    'message' => 'Suspicious filename detected',
                    'file' => $filePath,
                    'filename' => $filename,
                    'recommendation' => 'Review file contents and purpose'
                ];
            }
        }
        
        return $violations;
    }
    
    /**
     * Scan for file permission issues
     */
    private function scanFilePermissions(string $rootPath): array
    {
        $violations = [];
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($rootPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            $filePath = $file->getPathname();
            
            if ($this->shouldSkipPath($filePath)) {
                continue;
            }
            
            $perms = $file->getPerms();
            
            // Check for world-writable files
            if ($perms & 0x0002) {
                $violations[] = [
                    'type' => 'world_writable_file',
                    'severity' => 'high',
                    'message' => 'File is world-writable',
                    'file' => $filePath,
                    'permissions' => substr(sprintf('%o', $perms), -4),
                    'recommendation' => 'Remove world-write permission'
                ];
            }
            
            // Check for executable files that shouldn't be
            if (($perms & 0x0040) && $file->getExtension() === 'php') {
                if (strpos($filePath, '/storage/') !== false || strpos($filePath, '/uploads/') !== false) {
                    $violations[] = [
                        'type' => 'executable_in_data_dir',
                        'severity' => 'high',
                        'message' => 'Executable PHP file in data directory',
                        'file' => $filePath,
                        'recommendation' => 'Remove execute permission or move file'
                    ];
                }
            }
        }
        
        return $violations;
    }
    
    /**
     * Scan for suspicious content in files
     */
    private function scanSuspiciousContent(string $rootPath): array
    {
        $violations = [];
        
        $phpFiles = $this->getPhpFiles($rootPath);
        
        foreach ($phpFiles as $filePath) {
            if ($this->shouldSkipPath($filePath)) {
                continue;
            }
            
            $content = file_get_contents($filePath);
            
            foreach ($this->suspiciousPatterns as $type => $pattern) {
                if (preg_match($pattern['pattern'], $content, $matches, PREG_OFFSET_CAPTURE)) {
                    $line = substr_count(substr($content, 0, $matches[0][1]), "\n") + 1;
                    
                    $violations[] = [
                        'type' => 'suspicious_content',
                        'severity' => 'high',
                        'message' => $pattern['description'],
                        'file' => $filePath,
                        'line' => $line,
                        'context' => trim($matches[0][0]),
                        'pattern_type' => $type,
                        'recommendation' => 'Review file for malicious content'
                    ];
                }
            }
            
            // Check for long base64 strings (potential encoded payloads)
            if (preg_match('/[A-Za-z0-9+\/]{200,}={0,2}/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                $line = substr_count(substr($content, 0, $matches[0][1]), "\n") + 1;
                
                $violations[] = [
                    'type' => 'long_base64_string',
                    'severity' => 'medium',
                    'message' => 'Long base64 string detected',
                    'file' => $filePath,
                    'line' => $line,
                    'length' => strlen($matches[0][0]),
                    'recommendation' => 'Review if this is legitimate encoded content'
                ];
            }
            
            // Check for unusual character encoding
            if (!mb_check_encoding($content, 'UTF-8') && !mb_check_encoding($content, 'ASCII')) {
                $violations[] = [
                    'type' => 'unusual_encoding',
                    'severity' => 'low',
                    'message' => 'File contains unusual character encoding',
                    'file' => $filePath,
                    'recommendation' => 'Verify file encoding and content'
                ];
            }
        }
        
        return $violations;
    }
    
    /**
     * Scan for web-accessible sensitive files
     */
    private function scanWebAccessibleFiles(string $rootPath): array
    {
        $violations = [];
        
        $sensitiveFiles = [
            '.env' => 'Environment configuration',
            '.env.local' => 'Local environment configuration',
            '.env.production' => 'Production environment configuration',
            'composer.json' => 'Composer configuration',
            'composer.lock' => 'Composer lock file',
            'package.json' => 'NPM package configuration',
            'package-lock.json' => 'NPM package lock file',
            'config.php' => 'Configuration file',
            'database.php' => 'Database configuration',
            'wp-config.php' => 'WordPress configuration',
            '.htaccess' => 'Apache configuration',
            '.htpasswd' => 'Apache password file',
            'web.config' => 'IIS configuration',
            'phpinfo.php' => 'PHP information file',
            'test.php' => 'Test file',
            'debug.php' => 'Debug file',
            'backup.sql' => 'Database backup',
            'dump.sql' => 'Database dump'
        ];
        
        foreach ($this->webAccessiblePaths as $webPath) {
            $webDir = $rootPath . '/' . $webPath;
            
            if (!is_dir($webDir)) {
                continue;
            }
            
            foreach ($sensitiveFiles as $filename => $description) {
                $filePath = $webDir . '/' . $filename;
                
                if (file_exists($filePath)) {
                    $violations[] = [
                        'type' => 'web_accessible_sensitive_file',
                        'severity' => 'high',
                        'message' => "Sensitive file accessible via web: {$description}",
                        'file' => $filePath,
                        'recommendation' => 'Move file outside web root or add access restrictions'
                    ];
                }
            }
            
            // Scan for any .env files
            $envFiles = glob($webDir . '/.env*');
            foreach ($envFiles as $envFile) {
                if (!in_array(basename($envFile), array_keys($sensitiveFiles))) {
                    $violations[] = [
                        'type' => 'web_accessible_env_file',
                        'severity' => 'high',
                        'message' => 'Environment file accessible via web',
                        'file' => $envFile,
                        'recommendation' => 'Move file outside web root'
                    ];
                }
            }
        }
        
        return $violations;
    }
    
    /**
     * Scan for backup files that might contain sensitive data
     */
    private function scanBackupFiles(string $rootPath): array
    {
        $violations = [];
        
        $backupPatterns = [
            '*.bak',
            '*.backup',
            '*.old',
            '*.orig',
            '*.save',
            '*.tmp',
            '*~',
            '*.sql',
            '*.dump'
        ];
        
        foreach ($backupPatterns as $pattern) {
            $files = glob($rootPath . '/**/' . $pattern, GLOB_BRACE);
            
            foreach ($files as $file) {
                if ($this->shouldSkipPath($file)) {
                    continue;
                }
                
                $violations[] = [
                    'type' => 'backup_file',
                    'severity' => 'medium',
                    'message' => 'Backup file found',
                    'file' => $file,
                    'recommendation' => 'Remove backup file or move outside web root'
                ];
            }
        }
        
        return $violations;
    }
    
    /**
     * Scan upload directories for security issues
     */
    private function scanUploadDirectories(string $rootPath): array
    {
        $violations = [];
        
        $uploadDirs = [
            $rootPath . '/storage/uploads',
            $rootPath . '/public/uploads',
            $rootPath . '/uploads',
            $rootPath . '/files'
        ];
        
        foreach ($uploadDirs as $uploadDir) {
            if (!is_dir($uploadDir)) {
                continue;
            }
            
            // Check if .htaccess exists to prevent PHP execution
            $htaccess = $uploadDir . '/.htaccess';
            if (!file_exists($htaccess)) {
                $violations[] = [
                    'type' => 'missing_upload_protection',
                    'severity' => 'high',
                    'message' => 'Upload directory lacks .htaccess protection',
                    'directory' => $uploadDir,
                    'recommendation' => 'Add .htaccess to prevent script execution'
                ];
            } else {
                // Check .htaccess content
                $htaccessContent = file_get_contents($htaccess);
                if (strpos($htaccessContent, 'php_flag engine off') === false &&
                    strpos($htaccessContent, 'RemoveHandler .php') === false) {
                    $violations[] = [
                        'type' => 'weak_upload_protection',
                        'severity' => 'medium',
                        'message' => 'Upload directory .htaccess may not prevent PHP execution',
                        'file' => $htaccess,
                        'recommendation' => 'Ensure .htaccess prevents script execution'
                    ];
                }
            }
            
            // Scan for PHP files in upload directory
            $phpFiles = glob($uploadDir . '/**/*.php', GLOB_BRACE);
            foreach ($phpFiles as $phpFile) {
                $violations[] = [
                    'type' => 'php_in_upload_dir',
                    'severity' => 'critical',
                    'message' => 'PHP file found in upload directory',
                    'file' => $phpFile,
                    'recommendation' => 'Remove PHP file from upload directory'
                ];
            }
        }
        
        return $violations;
    }
    
    /**
     * Get all PHP files in directory
     */
    private function getPhpFiles(string $directory): array
    {
        $files = [];
        
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
     * Check if path should be skipped
     */
    private function shouldSkipPath(string $path): bool
    {
        $skipPaths = [
            '/vendor/',
            '/node_modules/',
            '/.git/',
            '/storage/cache/',
            '/storage/logs/',
            '/tests/'
        ];
        
        foreach ($skipPaths as $skipPath) {
            if (strpos($path, $skipPath) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if filename is suspicious
     */
    private function hasSuspiciousFilename(string $filename): bool
    {
        $suspiciousNames = [
            'shell',
            'backdoor',
            'trojan',
            'exploit',
            'hack',
            'bypass',
            'webshell',
            'c99',
            'r57',
            'b374k',
            'crystal',
            'adminer',
            'phpmyadmin',
            'mysql',
            'upload',
            'uploader'
        ];
        
        $lowerFilename = strtolower($filename);
        
        foreach ($suspiciousNames as $name) {
            if (strpos($lowerFilename, $name) !== false) {
                return true;
            }
        }
        
        return false;
    }
}