<?php

declare(strict_types=1);

namespace Shopologic\Core\Router;

use Shopologic\Core\Container\ServiceProvider;

class RouterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(RouterInterface::class, Router::class);
        $this->singleton(Router::class);
        $this->singleton(RouteCompiler::class);
    }

    public function boot(): void
    {
        $router = $this->container->get(RouterInterface::class);
        
        // Register basic routes
        $this->registerRoutes($router);
    }

    private function registerRoutes(RouterInterface $router): void
    {
        // Homepage
        $router->get('/', function() {
            $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
            $body->write('<h1>Welcome to Shopologic</h1><p>Your enterprise e-commerce platform is running!</p>');
            return new \Shopologic\Core\Http\Response(200, ['Content-Type' => 'text/html'], $body);
        })->name('home');

        // Health check endpoint
        $router->get('/health', function() {
            return new \Shopologic\Core\Http\Response(200, ['Content-Type' => 'application/json'], 
                new \Shopologic\Core\Http\Stream('php://memory', 'w+'));
        })->name('health');

        // API routes group
        $router->group(['prefix' => 'api/v1'], function($router) {
            $router->get('/status', function() {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write(json_encode(['status' => 'ok', 'version' => '1.0.0']));
                return new \Shopologic\Core\Http\Response(200, ['Content-Type' => 'application/json'], $body);
            })->name('api.status');
        });
    }
}