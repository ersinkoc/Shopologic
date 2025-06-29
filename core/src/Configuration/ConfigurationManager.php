<?php

declare(strict_types=1);

namespace Shopologic\Core\Configuration;

class ConfigurationManager
{
    private array $config = [];
    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? dirname(__DIR__, 3);
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

    private function loadConfiguration(): void
    {
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
    }
    
    private function loadConfigFiles(string $configPath): void
    {
        $files = glob($configPath . '/*.php');
        
        foreach ($files as $file) {
            $key = basename($file, '.php');
            $content = require $file;
            $this->config[$key] = $content;
            
            // Debug: Show what's being loaded
            if (getenv('APP_DEBUG') === 'true') {
                echo "Loaded config: {$key} from {$file}\n";
            }
        }
    }
}