<?php

declare(strict_types=1);

namespace Shopologic\Core\Cache;

use Shopologic\Core\Container\ServiceProvider;

class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(CacheManager::class);
        
        $this->singleton(CacheInterface::class, function($container) {
            return $container->get(CacheManager::class)->store();
        });

        $this->alias(CacheManager::class, 'cache');
    }

    public function boot(): void
    {
        // Cache service provider boot logic
    }
}