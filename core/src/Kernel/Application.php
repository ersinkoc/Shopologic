<?php

declare(strict_types=1);

namespace Shopologic\Core\Kernel;

use Shopologic\Core\Container\Container;
use Shopologic\Core\Container\ServiceProvider;
use Shopologic\Core\Events\EventManager;
use Shopologic\Core\Configuration\ConfigurationManager;
use Shopologic\PSR\Container\ContainerInterface;
use Shopologic\PSR\EventDispatcher\EventDispatcherInterface;

class Application
{
    private Container $container;
    private EventManager $eventManager;
    private ConfigurationManager $config;
    private array $serviceProviders = [];
    private array $loadedProviders = [];
    private bool $booted = false;
    private string $basePath;
    private string $environment;

    public function __construct(string $basePath = null)
    {
        $this->basePath = $basePath ?: dirname(__DIR__, 3);
        $this->environment = $_ENV['APP_ENV'] ?? 'production';
        
        $this->container = new Container();
        $this->eventManager = new EventManager();
        $this->config = new ConfigurationManager($this->basePath);
        
        $this->registerBaseBindings();
        $this->registerBaseServiceProviders();
    }

    public function register(string $provider, bool $force = false): ServiceProvider
    {
        if (($registered = $this->getProvider($provider)) && !$force) {
            return $registered;
        }

        if (is_string($provider)) {
            $provider = new $provider($this->container);
        }

        $provider->register();

        $this->serviceProviders[] = $provider;

        if ($this->booted) {
            $this->bootProvider($provider);
        }

        return $provider;
    }

    public function getProvider(string $provider): ?ServiceProvider
    {
        foreach ($this->serviceProviders as $serviceProvider) {
            if ($serviceProvider instanceof $provider) {
                return $serviceProvider;
            }
        }

        return null;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->serviceProviders as $provider) {
            $this->bootProvider($provider);
        }

        $this->booted = true;
    }

    public function handle(\Shopologic\PSR\Http\Message\RequestInterface $request): \Shopologic\PSR\Http\Message\ResponseInterface
    {
        $this->boot();
        
        $kernel = $this->container->get(HttpKernelInterface::class);
        return $kernel->handle($request);
    }

    public function terminate(\Shopologic\PSR\Http\Message\RequestInterface $request, \Shopologic\PSR\Http\Message\ResponseInterface $response): void
    {
        $kernel = $this->container->get(HttpKernelInterface::class);
        
        if (method_exists($kernel, 'terminate')) {
            $kernel->terminate($request, $response);
        }
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getEventManager(): EventManager
    {
        return $this->eventManager;
    }

    public function getConfig(): ConfigurationManager
    {
        return $this->config;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }

    public function isDevelopment(): bool
    {
        return $this->environment === 'development';
    }

    public function isTesting(): bool
    {
        return $this->environment === 'testing';
    }

    private function registerBaseBindings(): void
    {
        $this->container->instance(Application::class, $this);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(ContainerInterface::class, $this->container);
        $this->container->instance(EventManager::class, $this->eventManager);
        $this->container->instance(EventDispatcherInterface::class, $this->eventManager);
        $this->container->instance(ConfigurationManager::class, $this->config);
    }

    private function registerBaseServiceProviders(): void
    {
        $providers = [
            \Shopologic\Core\Http\HttpServiceProvider::class,
            \Shopologic\Core\Router\RouterServiceProvider::class,
            \Shopologic\Core\Database\DatabaseServiceProvider::class,
            \Shopologic\Core\Cache\CacheServiceProvider::class,
            \Shopologic\Core\Logging\LoggingServiceProvider::class,
        ];

        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }

    private function bootProvider(ServiceProvider $provider): void
    {
        if (method_exists($provider, 'boot')) {
            $provider->boot();
        }

        $this->loadedProviders[] = get_class($provider);
    }
}