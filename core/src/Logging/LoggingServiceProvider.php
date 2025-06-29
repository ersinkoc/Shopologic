<?php

declare(strict_types=1);

namespace Shopologic\Core\Logging;

use Shopologic\Core\Container\ServiceProvider;
use Shopologic\PSR\Log\LoggerInterface;

class LoggingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(LoggerInterface::class, Logger::class);
        $this->singleton(Logger::class);
        
        $this->alias(LoggerInterface::class, 'log');
    }

    public function boot(): void
    {
        // Logging service provider boot logic
    }
}