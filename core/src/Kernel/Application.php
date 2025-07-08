<?php

declare(strict_types=1);

namespace Shopologic\Core\Kernel;

use Shopologic\Core\Container\Container;
use Shopologic\Core\Container\ServiceProvider;
use Shopologic\Core\Events\EventManager;
use Shopologic\Core\Events\PerformanceAwareEventManager;
use Shopologic\Core\Configuration\ConfigurationManager;
use Shopologic\Core\Kernel\EnvironmentDetector;
use Shopologic\PSR\Container\ContainerInterface;
use Shopologic\PSR\EventDispatcher\EventDispatcherInterface;
use Shopologic\PSR\Log\LoggerInterface;

class Application
{
    private Container $container;
    private EventManager $eventManager;
    private ConfigurationManager $config;
    private EnvironmentDetector $env;
    private array $serviceProviders = [];
    private array $loadedProviders = [];
    private bool $booted = false;
    private string $basePath;
    private string $environment;

    public function __construct(string $basePath = null)
    {
        $this->basePath = $basePath ?: dirname(__DIR__, 3);
        
        // Initialize environment detector first
        $this->env = new EnvironmentDetector($this->basePath);
        $this->environment = $this->env->getEnvironment();
        
        // Initialize container
        $this->container = new Container();
        
        // Initialize event manager (use performance aware version if debug mode)
        if ($this->env->isDebug()) {
            $this->eventManager = new PerformanceAwareEventManager();
        } else {
            $this->eventManager = new EventManager();
        }
        
        // Initialize configuration with environment
        $this->config = new ConfigurationManager($this->basePath, $this->environment);
        
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

        // Only boot the provider if the application has already been booted
        // This prevents early booting before all providers are registered
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
        return $this->env->isProduction();
    }

    public function isDevelopment(): bool
    {
        return $this->env->isDevelopment();
    }

    public function isTesting(): bool
    {
        return $this->env->isTesting();
    }
    
    public function isDebug(): bool
    {
        return $this->env->isDebug();
    }
    
    public function getEnv(): EnvironmentDetector
    {
        return $this->env;
    }

    private function registerBaseBindings(): void
    {
        $this->container->instance(Application::class, $this);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(ContainerInterface::class, $this->container);
        $this->container->instance(EventManager::class, $this->eventManager);
        $this->container->instance(EventDispatcherInterface::class, $this->eventManager);
        $this->container->instance(ConfigurationManager::class, $this->config);
        $this->container->instance(EnvironmentDetector::class, $this->env);
        
        // Add some aliases
        $this->container->instance('app', $this);
        $this->container->instance('container', $this->container);
        $this->container->instance('events', $this->eventManager);
        $this->container->instance('config', $this->config);
        $this->container->instance('env', $this->env);
        $this->container->instance('app.base_path', $this->basePath);
        $this->container->instance('app.env', $this->environment);
        
        // Logger will be injected later when LoggingServiceProvider is registered
    }

    private function registerBaseServiceProviders(): void
    {
        $providers = [
            // Core services first
            \Shopologic\Core\Logging\LoggingServiceProvider::class,
            \Shopologic\Core\Database\DatabaseServiceProvider::class,
            \Shopologic\Core\Cache\CacheServiceProvider::class,
            // Router before HTTP (HTTP kernel needs router)
            \Shopologic\Core\Router\RouterServiceProvider::class,
            \Shopologic\Core\Http\HttpServiceProvider::class,
            // Template can be loaded last
            \Shopologic\Core\Template\TemplateServiceProvider::class,
            // Admin panel
            \Shopologic\Core\Admin\AdminServiceProvider::class,
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