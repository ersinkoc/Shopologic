<?php

declare(strict_types=1);

namespace Shopologic\Core\Providers;

use Shopologic\Core\Container\ServiceProvider;
use Shopologic\Core\MultiStore\StoreManager;
use Shopologic\Core\MultiStore\Middleware\StoreDetectionMiddleware;
use Shopologic\Core\MultiStore\Middleware\StoreAccessMiddleware;

class MultiStoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register store manager
        $this->container->singleton(StoreManager::class, function ($container) {
            $config = $container->get('config')['multistore'] ?? [];
            
            return new StoreManager(
                $container->get('cache'),
                $container->get('events'),
                $container,
                $config
            );
        });
        
        // Register middleware
        $this->container->bind(StoreDetectionMiddleware::class, function ($container) {
            return new StoreDetectionMiddleware(
                $container->get(StoreManager::class)
            );
        });
        
        $this->container->bind(StoreAccessMiddleware::class, function ($container) {
            return new StoreAccessMiddleware(
                $container->get(StoreManager::class),
                $container->get('auth')
            );
        });
        
        // Register helper
        $this->container->bind('stores', function ($container) {
            return $container->get(StoreManager::class);
        });
    }
    
    public function boot(): void
    {
        // Add store detection middleware globally
        $this->container->get('middleware')->addGlobal(
            StoreDetectionMiddleware::class
        );
        
        // Register store-specific routes
        $router = $this->container->get('router');
        
        // Admin store management routes
        $router->group(['prefix' => '/admin/stores', 'middleware' => ['auth', 'admin']], function ($router) {
            $router->get('/', 'Admin\StoreController@index');
            $router->get('/create', 'Admin\StoreController@create');
            $router->post('/', 'Admin\StoreController@store');
            $router->get('/{id}', 'Admin\StoreController@show');
            $router->get('/{id}/edit', 'Admin\StoreController@edit');
            $router->put('/{id}', 'Admin\StoreController@update');
            $router->delete('/{id}', 'Admin\StoreController@destroy');
            
            // Store settings
            $router->get('/{id}/settings', 'Admin\StoreController@settings');
            $router->put('/{id}/settings', 'Admin\StoreController@updateSettings');
            
            // Store users
            $router->get('/{id}/users', 'Admin\StoreController@users');
            $router->post('/{id}/users', 'Admin\StoreController@addUser');
            $router->put('/{id}/users/{userId}', 'Admin\StoreController@updateUser');
            $router->delete('/{id}/users/{userId}', 'Admin\StoreController@removeUser');
            
            // Store products
            $router->get('/{id}/products', 'Admin\StoreController@products');
            $router->post('/{id}/products/sync', 'Admin\StoreController@syncProducts');
        });
        
        // Store switcher route
        $router->post('/api/stores/switch', 'Api\StoreController@switch')
            ->middleware(['auth']);
        
        // Add store context to views
        $this->container->get('template')->addGlobal('current_store', function () {
            return $this->container->get(StoreManager::class)->getCurrentStore();
        });
        
        // Listen for store events
        $events = $this->container->get('events');
        
        $events->listen('store.switched', function ($event) {
            // Clear relevant caches when store is switched
            $this->container->get('cache')->deleteByPrefix('store_' . $event['store']->id . '_');
        });
        
        // Add store configuration
        $this->publishes([
            __DIR__ . '/../../../config/multistore.php' => $this->container->get('config_path') . '/multistore.php'
        ], 'config');
    }
}