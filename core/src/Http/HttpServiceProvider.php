<?php

declare(strict_types=1);

namespace Shopologic\Core\Http;

use Shopologic\Core\Container\ServiceProvider;
use Shopologic\Core\Kernel\HttpKernel;
use Shopologic\Core\Kernel\HttpKernelInterface;
use Shopologic\PSR\Http\Message\RequestInterface;
use Shopologic\PSR\Http\Message\ResponseInterface;
use Shopologic\PSR\Http\Message\StreamInterface;
use Shopologic\PSR\Http\Message\UriInterface;

class HttpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->bind(StreamInterface::class, Stream::class);
        $this->bind(UriInterface::class, Uri::class);
        $this->bind(RequestInterface::class, Request::class);
        $this->bind(ResponseInterface::class, Response::class);
        
        $this->singleton(ServerRequestFactory::class);
        
        // Register HttpKernel with dependencies
        $this->singleton(HttpKernelInterface::class, function($container) {
            return new HttpKernel(
                $container->get(\Shopologic\Core\Router\RouterInterface::class),
                $container->get(\Shopologic\Core\Events\EventManager::class)
            );
        });
    }

    public function boot(): void
    {
        // HTTP service provider boot logic
    }
}