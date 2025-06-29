<?php

declare(strict_types=1);

namespace Shopologic\Core\Plugin;

class PluginRepository
{
    protected string $dataFile;
    protected array $data = [];

    public function __construct(string $dataFile = null)
    {
        $this->dataFile = $dataFile ?? storage_path('plugins/plugins.json');
        $this->load();
    }

    /**
     * Load plugin data from storage
     */
    protected function load(): void
    {
        if (file_exists($this->dataFile)) {
            $content = file_get_contents($this->dataFile);
            $this->data = json_decode($content, true) ?: [];
        }
    }

    /**
     * Save plugin data to storage
     */
    protected function save(): void
    {
        $directory = dirname($this->dataFile);
        
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        file_put_contents($this->dataFile, json_encode($this->data, JSON_PRETTY_PRINT));
    }

    /**
     * Get all activated plugins
     */
    public function getActivated(): array
    {
        $activated = [];
        
        foreach ($this->data as $name => $info) {
            if ($info['activated'] ?? false) {
                $activated[] = $name;
            }
        }
        
        return $activated;
    }

    /**
     * Set plugin activated state
     */
    public function setActivated(string $name, bool $activated): void
    {
        $this->ensurePlugin($name);
        $this->data[$name]['activated'] = $activated;
        $this->save();
    }

    /**
     * Check if plugin is activated
     */
    public function isActivated(string $name): bool
    {
        return $this->data[$name]['activated'] ?? false;
    }

    /**
     * Set plugin installed state
     */
    public function setInstalled(string $name, bool $installed): void
    {
        $this->ensurePlugin($name);
        $this->data[$name]['installed'] = $installed;
        $this->data[$name]['installed_at'] = $installed ? date('Y-m-d H:i:s') : null;
        $this->save();
    }

    /**
     * Check if plugin is installed
     */
    public function isInstalled(string $name): bool
    {
        return $this->data[$name]['installed'] ?? false;
    }

    /**
     * Set plugin version
     */
    public function setVersion(string $name, string $version): void
    {
        $this->ensurePlugin($name);
        $this->data[$name]['version'] = $version;
        $this->data[$name]['updated_at'] = date('Y-m-d H:i:s');
        $this->save();
    }

    /**
     * Get plugin version
     */
    public function getVersion(string $name): ?string
    {
        return $this->data[$name]['version'] ?? null;
    }

    /**
     * Get plugin data
     */
    public function getPluginData(string $name): array
    {
        return $this->data[$name] ?? [];
    }

    /**
     * Set custom plugin data
     */
    public function setPluginData(string $name, string $key, mixed $value): void
    {
        $this->ensurePlugin($name);
        $this->data[$name][$key] = $value;
        $this->save();
    }

    /**
     * Remove plugin data
     */
    public function removePlugin(string $name): void
    {
        unset($this->data[$name]);
        $this->save();
    }

    /**
     * Ensure plugin exists in data
     */
    protected function ensurePlugin(string $name): void
    {
        if (!isset($this->data[$name])) {
            $this->data[$name] = [
                'activated' => false,
                'installed' => false,
                'version' => null,
                'installed_at' => null,
                'updated_at' => null,
            ];
        }
    }
}