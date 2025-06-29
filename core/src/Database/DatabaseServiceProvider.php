<?php

declare(strict_types=1);

namespace Shopologic\Core\Database;

use Shopologic\Core\Container\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(DatabaseManager::class);
        
        $this->singleton(ConnectionInterface::class, function($container) {
            return $container->get(DatabaseManager::class)->connection();
        });

        $this->alias(DatabaseManager::class, 'db');
    }

    public function boot(): void
    {
        // Database service provider boot logic
    }
}