<?php

declare(strict_types=1);

namespace Shopologic\Core\Cache;

use Shopologic\Core\Configuration\ConfigurationManager;

class CacheManager
{
    private ConfigurationManager $config;
    private array $stores = [];

    public function __construct(ConfigurationManager $config)
    {
        $this->config = $config;
    }

    public function store(?string $name = null): CacheInterface
    {
        $name = $name ?: $this->getDefaultDriver();

        if (!isset($this->stores[$name])) {
            $this->stores[$name] = $this->createStore($name);
        }

        return $this->stores[$name];
    }

    public function getDefaultDriver(): string
    {
        return $this->config->get('cache.default', 'file');
    }

    protected function createStore(string $name): CacheInterface
    {
        $config = $this->config->get("cache.stores.{$name}");

        if (!$config) {
            throw new \InvalidArgumentException("Cache store [{$name}] is not defined.");
        }

        switch ($config['driver']) {
            case 'array':
                return new ArrayStore();
            case 'file':
                return new FileStore($config['path'] ?? 'storage/cache');
            default:
                throw new \InvalidArgumentException("Cache driver [{$config['driver']}] is not supported.");
        }
    }
}