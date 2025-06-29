<?php

declare(strict_types=1);

namespace HelloWorld;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\PluginAPI;
use Shopologic\Core\Plugin\Hook;

class HelloWorldPlugin extends AbstractPlugin
{
    protected string $name = 'HelloWorld';
    protected string $version = '1.0.0';
    protected string $description = 'A simple hello world plugin for Shopologic';
    protected string $author = 'Shopologic Team';
    protected array $dependencies = [];
    
    private ?PluginAPI $api = null;

    public function activate(): void
    {
        // Called when plugin is activated
        $this->log('info', 'HelloWorld plugin activated!');
    }

    public function deactivate(): void
    {
        // Called when plugin is deactivated
        $this->log('info', 'HelloWorld plugin deactivated!');
    }

    public function install(): void
    {
        // Called when plugin is installed
        // Could create database tables, copy files, etc.
        $this->log('info', 'HelloWorld plugin installed!');
        
        // Set default configuration
        if ($this->api) {
            $this->api->setPluginConfig('greeting', 'Hello from Shopologic!');
            $this->api->setPluginConfig('enabled', true);
        }
    }

    public function uninstall(): void
    {
        // Called when plugin is uninstalled
        // Could remove database tables, cleanup files, etc.
        $this->log('info', 'HelloWorld plugin uninstalled!');
    }

    public function update(string $previousVersion): void
    {
        // Called when plugin is updated
        $this->log('info', "HelloWorld plugin updated from {$previousVersion} to {$this->version}!");
    }

    public function boot(): void
    {
        // Called when plugin is booted
        // This is where you register hooks, events, routes, etc.
        
        // Add action hooks
        Hook::addAction('app.init', [$this, 'onAppInit']);
        Hook::addAction('page.header', [$this, 'addHeaderContent']);
        
        // Add filter hooks
        Hook::addFilter('page.title', [$this, 'filterPageTitle']);
        Hook::addFilter('product.price', [$this, 'filterProductPrice'], 10, 2);
        
        // Register a route
        if ($this->api) {
            $this->api->registerRoute('GET', '/hello', [$this, 'handleHelloRoute']);
            $this->api->registerRoute('GET', '/greet/{name}', [$this, 'handleGreetRoute']);
        }
        
        $this->log('info', 'HelloWorld plugin booted!');
    }

    /**
     * Set the plugin API instance
     */
    public function setAPI(PluginAPI $api): void
    {
        $this->api = $api;
    }

    /**
     * Called when app initializes
     */
    public function onAppInit(): void
    {
        if ($this->api && $this->api->getPluginConfig('enabled', true)) {
            $greeting = $this->api->getPluginConfig('greeting', 'Hello!');
            $this->log('debug', "App initialized with greeting: {$greeting}");
        }
    }

    /**
     * Add content to page header
     */
    public function addHeaderContent(): void
    {
        if ($this->api && $this->api->getPluginConfig('enabled', true)) {
            echo '<!-- HelloWorld Plugin Active -->';
        }
    }

    /**
     * Filter page title
     */
    public function filterPageTitle(string $title): string
    {
        if ($this->api && $this->api->getPluginConfig('enabled', true)) {
            return $title . ' | HelloWorld';
        }
        return $title;
    }

    /**
     * Filter product price
     */
    public function filterProductPrice(float $price, array $product): float
    {
        // Example: Apply 10% discount for products in 'sale' category
        if (isset($product['category']) && $product['category'] === 'sale') {
            return $price * 0.9;
        }
        return $price;
    }

    /**
     * Handle /hello route
     */
    public function handleHelloRoute(): array
    {
        $greeting = $this->api ? $this->api->getPluginConfig('greeting', 'Hello!') : 'Hello!';
        
        return [
            'status' => 'success',
            'message' => $greeting,
            'plugin' => $this->getName(),
            'version' => $this->getVersion(),
        ];
    }

    /**
     * Handle /greet/{name} route
     */
    public function handleGreetRoute(array $params): array
    {
        $name = $params['name'] ?? 'Guest';
        $greeting = $this->api ? $this->api->getPluginConfig('greeting', 'Hello') : 'Hello';
        
        return [
            'status' => 'success',
            'message' => "{$greeting}, {$name}!",
            'plugin' => $this->getName(),
        ];
    }

    /**
     * Log helper
     */
    private function log(string $level, string $message): void
    {
        if ($this->api) {
            $this->api->log($level, $message);
        } else {
            error_log("[HelloWorld] [{$level}] {$message}");
        }
    }
}