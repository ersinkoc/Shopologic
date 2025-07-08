<?php

declare(strict_types=1);

namespace Shopologic\Core\Configuration;

class ConfigurationManager
{
    private array $config = [];
    private string $basePath;
    private string $environment;
    private array $loadedFiles = [];
    private bool $cached = false;
    private string $cacheFile;

    public function __construct(?string $basePath = null, ?string $environment = null)
    {
        $this->basePath = $basePath ?? dirname(__DIR__, 3);
        $this->environment = $environment ?? $_ENV['APP_ENV'] ?? 'production';
        $this->cacheFile = $this->basePath . '/storage/cache/config.php';
        $this->loadConfiguration();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    public function has(string $key): bool
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return false;
            }
            $value = $value[$k];
        }

        return true;
    }

    public function all(): array
    {
        return $this->config;
    }

    public function reload(): void
    {
        $this->config = [];
        $this->loadedFiles = [];
        $this->cached = false;
        $this->loadConfiguration();
    }

    public function merge(array $config): void
    {
        $this->config = array_replace_recursive($this->config, $config);
    }

    public function getLoadedFiles(): array
    {
        return $this->loadedFiles;
    }

    public function cache(): void
    {
        if (!is_dir(dirname($this->cacheFile))) {
            mkdir(dirname($this->cacheFile), 0755, true);
        }
        
        $export = var_export($this->config, true);
        $content = "<?php\n\nreturn {$export};\n";
        file_put_contents($this->cacheFile, $content);
    }

    public function clearCache(): void
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    private function loadConfiguration(): void
    {
        // Try to load from cache first in production
        if ($this->environment === 'production' && file_exists($this->cacheFile)) {
            $this->config = require $this->cacheFile;
            $this->cached = true;
            return;
        }
        
        // Load from core config directory first (defaults)
        $coreConfigPath = $this->basePath . '/core/config';
        if (is_dir($coreConfigPath)) {
            $this->loadConfigFiles($coreConfigPath);
        }
        
        // Load from main config directory (overrides)
        $configPath = $this->basePath . '/config';
        if (is_dir($configPath)) {
            $this->loadConfigFiles($configPath);
        }
        
        // Load environment-specific config
        $envConfigPath = $this->basePath . '/config/' . $this->environment;
        if (is_dir($envConfigPath)) {
            $this->loadConfigFiles($envConfigPath);
        }
        
        // Process environment variable overrides
        $this->processEnvironmentOverrides();
    }
    
    private function loadConfigFiles(string $configPath): void
    {
        $files = glob($configPath . '/*.php');
        
        foreach ($files as $file) {
            $key = basename($file, '.php');
            $content = require $file;
            
            // If config already exists, merge recursively
            if (isset($this->config[$key]) && is_array($this->config[$key]) && is_array($content)) {
                $this->config[$key] = array_replace_recursive($this->config[$key], $content);
            } else {
                $this->config[$key] = $content;
            }
            
            $this->loadedFiles[] = $file;
            
            // Debug: Show what's being loaded (commented out to reduce output)
            // if (getenv('APP_DEBUG') === 'true') {
            //     echo "Loaded config: {$key} from {$file}\n";
            // }
        }
    }
    
    private function processEnvironmentOverrides(): void
    {
        // Allow environment variables to override config values
        // Format: CONFIG_FILE_KEY=value (e.g., CONFIG_DATABASE_HOST=localhost)
        foreach ($_ENV as $key => $value) {
            if (strpos($key, 'CONFIG_') === 0) {
                $configKey = strtolower(str_replace('_', '.', substr($key, 7)));
                $this->set($configKey, $this->parseValue($value));
            }
        }
    }
    
    private function parseValue(string $value): mixed
    {
        // Handle boolean values
        if (strtolower($value) === 'true') return true;
        if (strtolower($value) === 'false') return false;
        
        // Handle null
        if (strtolower($value) === 'null') return null;
        
        // Handle numeric values
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float)$value : (int)$value;
        }
        
        // Handle JSON arrays/objects
        if ((strpos($value, '[') === 0 || strpos($value, '{') === 0)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        
        return $value;
    }
}