<?php

declare(strict_types=1);

namespace Shopologic\Core\Security;

use Shopologic\Core\Configuration\ConfigurationManager;

/**
 * Configuration Security Scanner
 * 
 * Scans application configuration for security issues
 */
class ConfigurationScanner
{
    private ConfigurationManager $config;
    
    public function __construct()
    {
        $this->config = new ConfigurationManager();
    }
    
    /**
     * Scan configuration for security issues
     */
    public function scan(): array
    {
        $violations = [];
        
        // Environment configuration checks
        $violations = array_merge($violations, $this->scanEnvironmentConfig());
        
        // Application security settings
        $violations = array_merge($violations, $this->scanAppSecurityConfig());
        
        // Database security settings
        $violations = array_merge($violations, $this->scanDatabaseConfig());
        
        // Session security settings
        $violations = array_merge($violations, $this->scanSessionConfig());
        
        // Cache security settings
        $violations = array_merge($violations, $this->scanCacheConfig());
        
        // File permissions
        $violations = array_merge($violations, $this->scanFilePermissions());
        
        // PHP configuration
        $violations = array_merge($violations, $this->scanPhpConfig());
        
        return $violations;
    }
    
    /**
     * Scan environment configuration
     */
    private function scanEnvironmentConfig(): array
    {
        $violations = [];
        
        // Check if debug mode is enabled in production
        $env = $this->config->get('app.env', 'production');
        $debug = $this->config->get('app.debug', false);
        
        if ($env === 'production' && $debug === true) {
            $violations[] = [
                'type' => 'debug_in_production',
                'severity' => 'high',
                'message' => 'Debug mode enabled in production environment',
                'config_key' => 'app.debug',
                'current_value' => 'true',
                'recommended_value' => 'false',
                'recommendation' => 'Set APP_DEBUG=false in production environment'
            ];
        }
        
        // Check for development environment in production
        if ($env === 'development' || $env === 'dev') {
            $violations[] = [
                'type' => 'dev_env_in_production',
                'severity' => 'medium',
                'message' => 'Development environment detected',
                'config_key' => 'app.env',
                'current_value' => $env,
                'recommended_value' => 'production',
                'recommendation' => 'Set APP_ENV=production for production deployment'
            ];
        }
        
        return $violations;
    }
    
    /**
     * Scan application security configuration
     */
    private function scanAppSecurityConfig(): array
    {
        $violations = [];
        
        // Check encryption key
        $encryptionKey = $this->config->get('security.encryption_key', '');
        if (empty($encryptionKey) || $encryptionKey === 'base64:your-32-byte-key') {
            $violations[] = [
                'type' => 'weak_encryption_key',
                'severity' => 'critical',
                'message' => 'Default or missing encryption key',
                'config_key' => 'security.encryption_key',
                'recommendation' => 'Generate a secure 32-byte encryption key'
            ];
        } elseif (strlen(base64_decode(str_replace('base64:', '', $encryptionKey))) < 32) {
            $violations[] = [
                'type' => 'short_encryption_key',
                'severity' => 'high',
                'message' => 'Encryption key is too short',
                'config_key' => 'security.encryption_key',
                'recommendation' => 'Use a 32-byte encryption key'
            ];
        }
        
        // Check JWT secret
        $jwtSecret = $this->config->get('security.jwt_secret', '');
        if (empty($jwtSecret) || $jwtSecret === 'your-256-bit-secret') {
            $violations[] = [
                'type' => 'weak_jwt_secret',
                'severity' => 'critical',
                'message' => 'Default or missing JWT secret',
                'config_key' => 'security.jwt_secret',
                'recommendation' => 'Generate a secure JWT secret'
            ];
        } elseif (strlen($jwtSecret) < 32) {
            $violations[] = [
                'type' => 'short_jwt_secret',
                'severity' => 'high',
                'message' => 'JWT secret is too short',
                'config_key' => 'security.jwt_secret',
                'recommendation' => 'Use a JWT secret of at least 32 characters'
            ];
        }
        
        // Check password hashing rounds
        $bcryptRounds = $this->config->get('security.bcrypt_rounds', 12);
        if ($bcryptRounds < 10) {
            $violations[] = [
                'type' => 'weak_password_hashing',
                'severity' => 'medium',
                'message' => 'Bcrypt rounds too low',
                'config_key' => 'security.bcrypt_rounds',
                'current_value' => (string)$bcryptRounds,
                'recommended_value' => '12',
                'recommendation' => 'Use at least 10 bcrypt rounds, 12 recommended'
            ];
        }
        
        return $violations;
    }
    
    /**
     * Scan database configuration
     */
    private function scanDatabaseConfig(): array
    {
        $violations = [];
        
        // Check database credentials
        $dbUsername = $this->config->get('database.username', '');
        $dbPassword = $this->config->get('database.password', '');
        
        if ($dbUsername === 'root') {
            $violations[] = [
                'type' => 'privileged_db_user',
                'severity' => 'high',
                'message' => 'Using root database user',
                'config_key' => 'database.username',
                'recommendation' => 'Create a dedicated database user with minimal privileges'
            ];
        }
        
        if (empty($dbPassword)) {
            $violations[] = [
                'type' => 'empty_db_password',
                'severity' => 'critical',
                'message' => 'Database password is empty',
                'config_key' => 'database.password',
                'recommendation' => 'Set a strong database password'
            ];
        } elseif (strlen($dbPassword) < 8) {
            $violations[] = [
                'type' => 'weak_db_password',
                'severity' => 'medium',
                'message' => 'Database password is too short',
                'config_key' => 'database.password',
                'recommendation' => 'Use a password of at least 8 characters'
            ];
        }
        
        // Check SSL usage
        $dbSsl = $this->config->get('database.ssl', false);
        if (!$dbSsl) {
            $violations[] = [
                'type' => 'unencrypted_db_connection',
                'severity' => 'medium',
                'message' => 'Database connection not encrypted',
                'config_key' => 'database.ssl',
                'recommendation' => 'Enable SSL for database connections'
            ];
        }
        
        return $violations;
    }
    
    /**
     * Scan session configuration
     */
    private function scanSessionConfig(): array
    {
        $violations = [];
        
        // Check session security settings
        $sessionSecure = $this->config->get('session.secure', false);
        $sessionHttpOnly = $this->config->get('session.http_only', true);
        $sessionSameSite = $this->config->get('session.same_site', 'lax');
        
        if (!$sessionSecure) {
            $violations[] = [
                'type' => 'insecure_session_cookie',
                'severity' => 'medium',
                'message' => 'Session cookies not marked as secure',
                'config_key' => 'session.secure',
                'recommendation' => 'Set session.secure=true to require HTTPS'
            ];
        }
        
        if (!$sessionHttpOnly) {
            $violations[] = [
                'type' => 'session_accessible_to_js',
                'severity' => 'medium',
                'message' => 'Session cookies accessible to JavaScript',
                'config_key' => 'session.http_only',
                'recommendation' => 'Set session.http_only=true to prevent XSS'
            ];
        }
        
        if ($sessionSameSite === 'none') {
            $violations[] = [
                'type' => 'permissive_session_samesite',
                'severity' => 'low',
                'message' => 'Permissive SameSite policy for session cookies',
                'config_key' => 'session.same_site',
                'recommendation' => 'Use "strict" or "lax" SameSite policy'
            ];
        }
        
        // Check session lifetime
        $sessionLifetime = $this->config->get('session.lifetime', 1440);
        if ($sessionLifetime > 7200) { // 2 hours
            $violations[] = [
                'type' => 'long_session_lifetime',
                'severity' => 'low',
                'message' => 'Session lifetime is very long',
                'config_key' => 'session.lifetime',
                'current_value' => (string)$sessionLifetime,
                'recommendation' => 'Consider shorter session lifetime for security'
            ];
        }
        
        return $violations;
    }
    
    /**
     * Scan cache configuration
     */
    private function scanCacheConfig(): array
    {
        $violations = [];
        
        // Check cache driver security
        $cacheDriver = $this->config->get('cache.driver', 'file');
        
        if ($cacheDriver === 'file') {
            $cachePath = $this->config->get('cache.path', 'storage/cache');
            $fullPath = defined('SHOPOLOGIC_ROOT') ? SHOPOLOGIC_ROOT . '/' . $cachePath : $cachePath;
            
            if (is_dir($fullPath) && is_readable($fullPath)) {
                // Check if cache directory is web accessible
                if (strpos($fullPath, 'public') !== false) {
                    $violations[] = [
                        'type' => 'web_accessible_cache',
                        'severity' => 'medium',
                        'message' => 'Cache directory may be web accessible',
                        'config_key' => 'cache.path',
                        'recommendation' => 'Move cache directory outside web root'
                    ];
                }
            }
        }
        
        return $violations;
    }
    
    /**
     * Scan file permissions
     */
    private function scanFilePermissions(): array
    {
        $violations = [];
        $rootPath = defined('SHOPOLOGIC_ROOT') ? SHOPOLOGIC_ROOT : dirname(__DIR__, 3);
        
        // Check sensitive file permissions
        $sensitiveFiles = [
            '/.env' => 'Environment configuration file',
            '/composer.json' => 'Composer configuration file',
            '/composer.lock' => 'Composer lock file'
        ];
        
        foreach ($sensitiveFiles as $file => $description) {
            $filePath = $rootPath . $file;
            
            if (file_exists($filePath)) {
                $perms = fileperms($filePath);
                $octal = substr(sprintf('%o', $perms), -4);
                
                // Check if file is world-readable
                if ($perms & 0x0004) {
                    $violations[] = [
                        'type' => 'world_readable_file',
                        'severity' => 'medium',
                        'message' => "{$description} is world-readable",
                        'file' => $filePath,
                        'permissions' => $octal,
                        'recommendation' => 'Restrict file permissions (e.g., 600 or 640)'
                    ];
                }
                
                // Check if file is world-writable
                if ($perms & 0x0002) {
                    $violations[] = [
                        'type' => 'world_writable_file',
                        'severity' => 'high',
                        'message' => "{$description} is world-writable",
                        'file' => $filePath,
                        'permissions' => $octal,
                        'recommendation' => 'Remove world-write permissions'
                    ];
                }
            }
        }
        
        // Check directory permissions
        $sensitiveDirectories = [
            '/storage' => 'Storage directory',
            '/storage/logs' => 'Log directory',
            '/storage/cache' => 'Cache directory'
        ];
        
        foreach ($sensitiveDirectories as $dir => $description) {
            $dirPath = $rootPath . $dir;
            
            if (is_dir($dirPath)) {
                $perms = fileperms($dirPath);
                
                // Check if directory is world-writable
                if ($perms & 0x0002) {
                    $violations[] = [
                        'type' => 'world_writable_directory',
                        'severity' => 'medium',
                        'message' => "{$description} is world-writable",
                        'directory' => $dirPath,
                        'recommendation' => 'Restrict directory permissions'
                    ];
                }
            }
        }
        
        return $violations;
    }
    
    /**
     * Scan PHP configuration
     */
    private function scanPhpConfig(): array
    {
        $violations = [];
        
        // Check expose_php
        if (ini_get('expose_php')) {
            $violations[] = [
                'type' => 'php_version_exposed',
                'severity' => 'low',
                'message' => 'PHP version exposed in headers',
                'php_setting' => 'expose_php',
                'recommendation' => 'Set expose_php=Off in php.ini'
            ];
        }
        
        // Check display_errors
        if (ini_get('display_errors')) {
            $violations[] = [
                'type' => 'errors_displayed',
                'severity' => 'medium',
                'message' => 'PHP errors displayed to users',
                'php_setting' => 'display_errors',
                'recommendation' => 'Set display_errors=Off in production'
            ];
        }
        
        // Check log_errors
        if (!ini_get('log_errors')) {
            $violations[] = [
                'type' => 'errors_not_logged',
                'severity' => 'low',
                'message' => 'PHP errors not being logged',
                'php_setting' => 'log_errors',
                'recommendation' => 'Set log_errors=On to log errors'
            ];
        }
        
        // Check allow_url_fopen
        if (ini_get('allow_url_fopen')) {
            $violations[] = [
                'type' => 'url_fopen_enabled',
                'severity' => 'medium',
                'message' => 'Remote file access enabled',
                'php_setting' => 'allow_url_fopen',
                'recommendation' => 'Set allow_url_fopen=Off unless required'
            ];
        }
        
        // Check allow_url_include
        if (ini_get('allow_url_include')) {
            $violations[] = [
                'type' => 'url_include_enabled',
                'severity' => 'high',
                'message' => 'Remote file inclusion enabled',
                'php_setting' => 'allow_url_include',
                'recommendation' => 'Set allow_url_include=Off'
            ];
        }
        
        // Check session.cookie_httponly
        if (!ini_get('session.cookie_httponly')) {
            $violations[] = [
                'type' => 'session_cookie_not_httponly',
                'severity' => 'medium',
                'message' => 'Session cookies accessible to JavaScript',
                'php_setting' => 'session.cookie_httponly',
                'recommendation' => 'Set session.cookie_httponly=1'
            ];
        }
        
        // Check session.cookie_secure
        if (!ini_get('session.cookie_secure')) {
            $violations[] = [
                'type' => 'session_cookie_not_secure',
                'severity' => 'medium',
                'message' => 'Session cookies not marked as secure',
                'php_setting' => 'session.cookie_secure',
                'recommendation' => 'Set session.cookie_secure=1 for HTTPS'
            ];
        }
        
        return $violations;
    }
}