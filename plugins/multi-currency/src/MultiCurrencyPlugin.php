<?php
declare(strict_types=1);

namespace MultiCurrency;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\PluginInterface;
use Shopologic\Core\Hook\HookSystem;
use MultiCurrency\Services\CurrencyService;
use MultiCurrency\Services\ExchangeRateService;
use MultiCurrency\Services\PriceConversionService;

/**
 * Multi-Currency Support Plugin
 * 
 * Complete multi-currency solution with automatic exchange rates,
 * currency detection, and seamless price conversion
 */
class MultiCurrencyPlugin extends AbstractPlugin implements PluginInterface
{
    protected string $name = 'multi-currency';
    protected string $version = '1.0.0';
    
    public function install(): bool
    {
        $this->runMigrations();
        $this->setDefaultConfig();
        $this->createDefaultCurrencies();
        return true;
    }
    
    public function activate(): bool
    {
        $this->initializeCurrencySystem();
        $this->scheduleRateUpdates();
        return true;
    }
    
    public function deactivate(): bool
    {
        $this->pauseRateUpdates();
        return true;
    }
    
    public function uninstall(): bool
    {
        if ($this->confirmDataRemoval()) {
            $this->dropTables();
            $this->removeConfig();
        }
        return true;
    }
    
    public function update(string $previousVersion): bool
    {
        $this->runUpdateMigrations($previousVersion);
        return true;
    }
    
    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->registerCronJobs();
    }
    
    protected function registerServices(): void
    {
        $this->container->singleton(CurrencyService::class, function ($container) {
            return new CurrencyService(
                $container->get('db'),
                $this->getConfig('default_currency', 'USD')
            );
        });
        
        $this->container->singleton(ExchangeRateService::class, function ($container) {
            return new ExchangeRateService(
                $container->get('db'),
                $this->getConfig('exchange_rate_provider', 'fixer'),
                $this->getConfig('exchange_rate_api_key')
            );
        });
        
        $this->container->singleton(PriceConversionService::class, function ($container) {
            return new PriceConversionService(
                $container->get(CurrencyService::class),
                $container->get(ExchangeRateService::class)
            );
        });
    }
    
    protected function registerHooks(): void
    {
        // Price conversion
        HookSystem::addFilter('product.price', [$this, 'convertPrice'], 10);
        HookSystem::addFilter('cart.total', [$this, 'convertPrice'], 10);
        HookSystem::addFilter('order.total', [$this, 'convertPrice'], 10);
        
        // Currency selector
        HookSystem::addAction('page.header', [$this, 'displayCurrencySelector'], 20);
        
        // Order processing
        HookSystem::addAction('order.created', [$this, 'recordOrderCurrency'], 5);
    }
    
    protected function registerRoutes(): void
    {
        $this->registerRoute('GET', '/api/v1/currencies', 
            'MultiCurrency\\Controllers\\CurrencyController@getAvailable');
        $this->registerRoute('POST', '/api/v1/currency/set', 
            'MultiCurrency\\Controllers\\CurrencyController@setCurrency');
        $this->registerRoute('GET', '/api/v1/exchange-rates', 
            'MultiCurrency\\Controllers\\ExchangeController@getRates');
    }
    
    protected function registerCronJobs(): void
    {
        $this->scheduleJob('0 */6 * * *', [$this, 'updateExchangeRates']);
    }
    
    public function convertPrice($price, array $data = []): float
    {
        $conversionService = $this->container->get(PriceConversionService::class);
        $targetCurrency = $this->getCurrentCurrency();
        
        return $conversionService->convert($price, $this->getBaseCurrency(), $targetCurrency);
    }
    
    public function displayCurrencySelector(): void
    {
        $currencyService = $this->container->get(CurrencyService::class);
        $currencies = $currencyService->getAvailableCurrencies();
        
        echo $this->render('currency/selector', [
            'currencies' => $currencies,
            'current' => $this->getCurrentCurrency()
        ]);
    }
    
    public function updateExchangeRates(): void
    {
        $exchangeService = $this->container->get(ExchangeRateService::class);
        $updated = $exchangeService->updateAllRates();
        
        $this->logger->info('Updated exchange rates', ['count' => $updated]);
    }
    
    protected function getCurrentCurrency(): string
    {
        return $_SESSION['currency'] ?? $this->detectCurrency();
    }
    
    protected function getBaseCurrency(): string
    {
        return $this->getConfig('default_currency', 'USD');
    }
    
    protected function detectCurrency(): string
    {
        // Detect based on IP geolocation
        $currencyService = $this->container->get(CurrencyService::class);
        return $currencyService->detectByLocation() ?? $this->getBaseCurrency();
    }
    
    protected function initializeCurrencySystem(): void
    {
        $exchangeService = $this->container->get(ExchangeRateService::class);
        $exchangeService->initializeRates();
    }
    
    protected function scheduleRateUpdates(): void
    {
        $this->enableCronJob('updateExchangeRates');
    }
    
    protected function pauseRateUpdates(): void
    {
        $this->disableCronJob('updateExchangeRates');
    }
    
    protected function createDefaultCurrencies(): void
    {
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'is_active' => true],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'is_active' => true],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'is_active' => true],
            ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => 'C$', 'is_active' => true],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$', 'is_active' => true]
        ];
        
        foreach ($currencies as $currency) {
            $this->api->database()->table('currencies')->insert($currency);
        }
    }
    
    protected function runMigrations(): void
    {
        $migrations = [
            'create_currencies_table.php',
            'create_exchange_rates_table.php',
            'create_currency_history_table.php'
        ];
        
        foreach ($migrations as $migration) {
            $this->api->runMigration($this->getPath('migrations/' . $migration));
        }
    }
    
    protected function setDefaultConfig(): void
    {
        $defaults = [
            'default_currency' => 'USD',
            'auto_detect_currency' => true,
            'exchange_rate_provider' => 'fixer',
            'rate_update_frequency' => 6,
            'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD']
        ];
        
        foreach ($defaults as $key => $value) {
            if ($this->getConfig($key) === null) {
                $this->setConfig($key, $value);
            }
        }
    }
}