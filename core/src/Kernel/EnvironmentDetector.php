<?php

declare(strict_types=1);

namespace Shopologic\Core\Kernel;

/**
 * Environment detector for application configuration
 * 
 * Detects and manages the current application environment based on
 * various sources like environment variables, files, and CLI arguments
 */
class EnvironmentDetector
{
    private const DEFAULT_ENVIRONMENT = 'production';
    private const ENVIRONMENT_FILE = '.env';
    
    private string $environment;
    private array $variables = [];
    private string $basePath;
    
    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        $this->detectEnvironment();
        $this->loadEnvironmentVariables();
    }
    
    /**
     * Get the current environment
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }
    
    /**
     * Get an environment variable
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->variables[$key] ?? $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
    
    /**
     * Set an environment variable
     */
    public function set(string $key, mixed $value): void
    {
        $this->variables[$key] = $value;
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv("{$key}={$value}");
    }
    
    /**
     * Check if running in a specific environment
     */
    public function is(string $environment): bool
    {
        return $this->environment === $environment;
    }
    
    /**
     * Check if running in production
     */
    public function isProduction(): bool
    {
        return $this->is('production');
    }
    
    /**
     * Check if running in development
     */
    public function isDevelopment(): bool
    {
        return $this->is('development');
    }
    
    /**
     * Check if running in testing
     */
    public function isTesting(): bool
    {
        return $this->is('testing');
    }
    
    /**
     * Check if running in staging
     */
    public function isStaging(): bool
    {
        return $this->is('staging');
    }
    
    /**
     * Check if running in local environment
     */
    public function isLocal(): bool
    {
        return $this->is('local') || $this->is('development');
    }
    
    /**
     * Check if running in console/CLI mode
     */
    public function isConsole(): bool
    {
        return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
    }
    
    /**
     * Check if debug mode is enabled
     */
    public function isDebug(): bool
    {
        $debug = $this->get('APP_DEBUG', 'false');
        return filter_var($debug, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * Detect the current environment
     */
    private function detectEnvironment(): void
    {
        // Priority order:
        // 1. CLI argument (--env=production)
        // 2. Environment variable (APP_ENV)
        // 3. .env file
        // 4. Default
        
        // Check CLI arguments
        if ($this->isConsole()) {
            $env = $this->getCliEnvironment();
            if ($env !== null) {
                $this->environment = $env;
                return;
            }
        }
        
        // Check environment variables
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? null;
        if ($env !== null) {
            $this->environment = $env;
            return;
        }
        
        // Check .env file
        $envFile = $this->basePath . '/' . self::ENVIRONMENT_FILE;
        if (file_exists($envFile)) {
            $envContent = file_get_contents($envFile);
            if (preg_match('/^APP_ENV=(.+)$/m', $envContent, $matches)) {
                $this->environment = trim($matches[1], '"\'');
                return;
            }
        }
        
        // Use default
        $this->environment = self::DEFAULT_ENVIRONMENT;
    }
    
    /**
     * Get environment from CLI arguments
     */
    private function getCliEnvironment(): ?string
    {
        global $argv;
        
        if (!isset($argv)) {
            return null;
        }
        
        foreach ($argv as $arg) {
            if (strpos($arg, '--env=') === 0) {
                return substr($arg, 6);
            }
        }
        
        return null;
    }
    
    /**
     * Load environment variables from .env file
     */
    private function loadEnvironmentVariables(): void
    {
        $envFile = $this->basePath . '/' . self::ENVIRONMENT_FILE;
        
        // Also check for environment-specific files
        $envSpecificFile = $this->basePath . '/.env.' . $this->environment;
        
        $files = [];
        if (file_exists($envFile)) {
            $files[] = $envFile;
        }
        if (file_exists($envSpecificFile)) {
            $files[] = $envSpecificFile;
        }
        
        foreach ($files as $file) {
            $this->parseEnvFile($file);
        }
    }
    
    /**
     * Parse .env file and load variables
     */
    private function parseEnvFile(string $file): void
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                    (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                    $value = substr($value, 1, -1);
                }
                
                // Expand variables
                $value = $this->expandVariables($value);
                
                $this->set($key, $value);
            }
        }
    }
    
    /**
     * Expand environment variables in values
     */
    private function expandVariables(string $value): string
    {
        return preg_replace_callback('/\${([A-Z_]+)}/', function ($matches) {
            return $this->get($matches[1], $matches[0]);
        }, $value);
    }
}