<?php

declare(strict_types=1);

namespace Shopologic\Plugins\MultiCurrencyLocalization;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\PluginInterface;
use Shopologic\Core\Plugin\Hook;
use Shopologic\Core\Container\ContainerInterface;
use MultiCurrencyLocalization\Services\{
    CurrencyManager,
    ExchangeRateProvider,
    LocalizationManager,
    RegionalPricingManager,
    TaxComplianceManager,
    GeolocationService,
    PriceConverter,;
    TranslationService;
};
use MultiCurrencyLocalization\Repositories\{
    CurrencyRepository,
    ExchangeRateRepository,
    LocalizationRepository,
    RegionalPricingRepository,
    TaxRuleRepository,
    CustomerLocationRepository,;
    TranslationRepository;
};
use MultiCurrencyLocalization\Controllers\{
    CurrencyController,
    ExchangeRateController,
    LocalizationController,
    RegionalPricingController,
    TaxComplianceController,;
    PriceController;
};

class MultiCurrencyLocalizationPlugin extends AbstractPlugin implements PluginInterface
{
    protected string $name = 'multi-currency-localization';
    protected string $version = '1.0.0';
    protected string $description = 'Comprehensive multi-currency and localization support';
    protected string $author = 'Shopologic Team';
    protected array $dependencies = ['shopologic/commerce', 'shopologic/customers', 'shopologic/pricing'];

    private CurrencyManager $currencyManager;
    private ExchangeRateProvider $exchangeRateProvider;
    private LocalizationManager $localizationManager;
    private RegionalPricingManager $regionalPricingManager;
    private TaxComplianceManager $taxComplianceManager;
    private GeolocationService $geolocationService;
    private PriceConverter $priceConverter;
    private TranslationService $translationService;

    /**
     * Plugin installation
     */
    public function install(): void
    {
        // Run database migrations
        $this->runMigrations();
        
        // Create default currencies
        $this->createDefaultCurrencies();
        
        // Setup default localization settings
        $this->setupDefaultLocalization();
        
        // Initialize default tax rules
        $this->initializeDefaultTaxRules();
        
        // Setup default country settings
        $this->setupCountrySettings();
        
        // Set default configuration
        $this->setDefaultConfiguration();
        
        // Create necessary directories
        $this->createDirectories();
        
        // Initialize exchange rate providers
        $this->initializeExchangeRateProviders();
    }

    /**
     * Plugin activation
     */
    public function activate(): void
    {
        // Register services
        $this->registerServices();
        
        // Register hooks and filters
        $this->registerHooks();
        
        // Register API routes
        $this->registerRoutes();
        
        // Schedule background tasks
        $this->scheduleBackgroundTasks();
        
        // Initialize geolocation service
        $this->initializeGeolocation();
        
        // Setup currency detection
        $this->setupCurrencyDetection();
        
        // Initialize translation system
        $this->initializeTranslationSystem();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        // Unschedule background tasks
        $this->unscheduleBackgroundTasks();
        
        // Clear cached data
        $this->clearCaches();
        
        // Save current state
        $this->saveCurrentState();
    }

    /**
     * Plugin uninstallation
     */
    public function uninstall(): void
    {
        // Note: Database cleanup is optional and user-configurable
        if ($this->getConfig('cleanup_on_uninstall', false)) {
            $this->cleanupDatabase();
        }
        
        // Remove configuration
        $this->removeConfiguration();
        
        // Clean up files
        $this->cleanupFiles();
        
        // Remove cached translations
        $this->cleanupTranslations();
    }

    /**
     * Plugin update
     */
    public function update(string $previousVersion): void
    {
        // Run version-specific updates
        if (version_compare($previousVersion, '1.0.0', '<')) {
            $this->updateTo100();
        }
        
        // Update database schema if needed
        $this->runMigrations();
        
        // Update configuration schema
        $this->updateConfiguration();
        
        // Migrate existing data
        $this->migrateExistingData($previousVersion);
        
        // Update exchange rate providers
        $this->updateExchangeRateProviders();
    }

    /**
     * Plugin boot - called when plugin is loaded
     */
    public function boot(): void
    {
        // Initialize core services
        $this->initializeServices();
        
        // Register event listeners
        $this->registerEventListeners();
        
        // Load plugin configuration
        $this->loadConfiguration();
        
        // Initialize currency system
        $this->initializeCurrencySystem();
        
        // Setup localization
        $this->setupLocalization();
    }

    /**
     * Register services with the container
     */
    protected function registerServices(): void
    {
        $container = $this->getContainer();
        
        // Register repositories
        $container->singleton(CurrencyRepository::class);
        $container->singleton(ExchangeRateRepository::class);
        $container->singleton(LocalizationRepository::class);
        $container->singleton(RegionalPricingRepository::class);
        $container->singleton(TaxRuleRepository::class);
        $container->singleton(CustomerLocationRepository::class);
        $container->singleton(TranslationRepository::class);
        
        // Register core services
        $container->singleton(CurrencyManager::class, function ($container) {
            return new CurrencyManager(
                $container->get(CurrencyRepository::class),
                $container->get(ExchangeRateRepository::class),
                $this->getConfig('currency_settings', [])
            );
        });
        
        $container->singleton(ExchangeRateProvider::class, function ($container) {
            return new ExchangeRateProvider(
                $container->get(ExchangeRateRepository::class),
                $this->getConfig('currency_settings.exchange_rate_provider', 'fixer'),
                $this->getConfig('exchange_rate_api_keys', [])
            );
        });
        
        $container->singleton(LocalizationManager::class, function ($container) {
            return new LocalizationManager(
                $container->get(LocalizationRepository::class),
                $container->get(TranslationRepository::class),
                $this->getConfig('localization_settings', [])
            );
        });
        
        $container->singleton(RegionalPricingManager::class, function ($container) {
            return new RegionalPricingManager(
                $container->get(RegionalPricingRepository::class),
                $container->get(CurrencyManager::class),
                $this->getConfig('regional_pricing', [])
            );
        });
        
        $container->singleton(TaxComplianceManager::class, function ($container) {
            return new TaxComplianceManager(
                $container->get(TaxRuleRepository::class),
                $this->getConfig('tax_compliance', [])
            );
        });
        
        $container->singleton(GeolocationService::class, function ($container) {
            return new GeolocationService(
                $container->get(CustomerLocationRepository::class),
                $this->getConfig('geolocation', [])
            );
        });
        
        $container->singleton(PriceConverter::class, function ($container) {
            return new PriceConverter(
                $container->get(CurrencyManager::class),
                $container->get(ExchangeRateProvider::class)
            );
        });
        
        $container->singleton(TranslationService::class, function ($container) {
            return new TranslationService(
                $container->get(TranslationRepository::class),
                $this->getConfig('localization_settings', [])
            );
        });
        
        // Register controllers
        $container->singleton(CurrencyController::class);
        $container->singleton(ExchangeRateController::class);
        $container->singleton(LocalizationController::class);
        $container->singleton(RegionalPricingController::class);
        $container->singleton(TaxComplianceController::class);
        $container->singleton(PriceController::class);
    }

    /**
     * Initialize services
     */
    protected function initializeServices(): void
    {
        $container = $this->getContainer();
        
        $this->currencyManager = $container->get(CurrencyManager::class);
        $this->exchangeRateProvider = $container->get(ExchangeRateProvider::class);
        $this->localizationManager = $container->get(LocalizationManager::class);
        $this->regionalPricingManager = $container->get(RegionalPricingManager::class);
        $this->taxComplianceManager = $container->get(TaxComplianceManager::class);
        $this->geolocationService = $container->get(GeolocationService::class);
        $this->priceConverter = $container->get(PriceConverter::class);
        $this->translationService = $container->get(TranslationService::class);
    }

    /**
     * Register hooks and filters
     */
    protected function registerHooks(): void
    {
        // Price calculation hooks
        Hook::addFilter('price.display', [$this, 'formatLocalizedPrice'], 10);
        Hook::addFilter('price.convert', [$this, 'convertPrice'], 10);
        Hook::addAction('product.price_calculated', [$this, 'applyRegionalPricing'], 10);
        
        // Order processing hooks
        Hook::addAction('order.created', [$this, 'applyTaxCompliance'], 10);
        Hook::addAction('order.tax_calculation', [$this, 'calculateOrderTax'], 10);
        Hook::addAction('payment.processing', [$this, 'convertCurrency'], 10);
        
        // Customer hooks
        Hook::addAction('customer.location_detected', [$this, 'updateCustomerCurrency'], 10);
        Hook::addAction('customer.login', [$this, 'loadCustomerPreferences'], 10);
        Hook::addAction('customer.preferences_updated', [$this, 'saveCustomerPreferences'], 10);
        
        // Localization hooks
        Hook::addFilter('text.translate', [$this, 'translateText'], 10);
        Hook::addFilter('currency.list', [$this, 'getActiveCurrencies'], 10);
        Hook::addFilter('language.list', [$this, 'getActiveLanguages'], 10);
        
        // Admin hooks
        Hook::addAction('admin_menu', [$this, 'registerAdminMenu']);
        Hook::addAction('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        
        // Frontend hooks
        Hook::addAction('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
        Hook::addAction('wp_head', [$this, 'addCurrencyMeta']);
        Hook::addAction('wp_footer', [$this, 'addCurrencyScripts']);
        
        // AJAX hooks
        Hook::addAction('wp_ajax_switch_currency', [$this, 'handleCurrencySwitch']);
        Hook::addAction('wp_ajax_nopriv_switch_currency', [$this, 'handleCurrencySwitch']);
        Hook::addAction('wp_ajax_switch_language', [$this, 'handleLanguageSwitch']);
        Hook::addAction('wp_ajax_nopriv_switch_language', [$this, 'handleLanguageSwitch']);
        Hook::addAction('wp_ajax_detect_location', [$this, 'handleLocationDetection']);
        Hook::addAction('wp_ajax_nopriv_detect_location', [$this, 'handleLocationDetection']);
    }

    /**
     * Register API routes
     */
    protected function registerRoutes(): void
    {
        // Currency routes
        $this->registerRoute('GET', '/api/v1/currencies', 'CurrencyController@index');
        $this->registerRoute('POST', '/api/v1/currencies', 'CurrencyController@create');
        $this->registerRoute('PUT', '/api/v1/currencies/{id}', 'CurrencyController@update');
        $this->registerRoute('DELETE', '/api/v1/currencies/{id}', 'CurrencyController@delete');
        
        // Exchange rate routes
        $this->registerRoute('GET', '/api/v1/exchange-rates', 'ExchangeRateController@index');
        $this->registerRoute('POST', '/api/v1/exchange-rates/update', 'ExchangeRateController@updateRates');
        $this->registerRoute('GET', '/api/v1/exchange-rates/history', 'ExchangeRateController@getHistory');
        
        // Localization routes
        $this->registerRoute('GET', '/api/v1/localization/detect', 'LocalizationController@detectLocation');
        $this->registerRoute('GET', '/api/v1/localization/translations', 'LocalizationController@getTranslations');
        $this->registerRoute('POST', '/api/v1/localization/translations', 'LocalizationController@updateTranslations');
        
        // Regional pricing routes
        $this->registerRoute('GET', '/api/v1/regional-pricing', 'RegionalPricingController@index');
        $this->registerRoute('POST', '/api/v1/regional-pricing', 'RegionalPricingController@create');
        $this->registerRoute('PUT', '/api/v1/regional-pricing/{id}', 'RegionalPricingController@update');
        
        // Tax compliance routes
        $this->registerRoute('GET', '/api/v1/tax-compliance/rates', 'TaxComplianceController@getTaxRates');
        $this->registerRoute('POST', '/api/v1/tax-compliance/calculate', 'TaxComplianceController@calculateTax');
        
        // Price conversion routes
        $this->registerRoute('GET', '/api/v1/price/convert', 'PriceController@convertPrice');
        $this->registerRoute('POST', '/api/v1/price/bulk-convert', 'PriceController@bulkConvertPrices');
    }

    /**
     * Apply regional pricing to product
     */
    public function applyRegionalPricing($product, $customerLocation = null): void
    {
        if (!$this->getConfig('regional_pricing.enabled', true)) {
            return;
        }

        $location = $customerLocation ?? $this->geolocationService->getCurrentLocation();
        if (!$location) {
            return;
        }

        $regionalPrice = $this->regionalPricingManager->getRegionalPrice(
            $product->getId(),
            $location['country_code'],
            $location['currency_code'] ?? $this->currencyManager->getBaseCurrency()
        );

        if ($regionalPrice) {
            $product->setPrice($regionalPrice);
        }
    }

    /**
     * Apply tax compliance to order
     */
    public function applyTaxCompliance($order): void
    {
        if (!$this->getConfig('tax_compliance.enabled', true)) {
            return;
        }

        $taxData = $this->taxComplianceManager->calculateOrderTax($order);
        $order->setTaxData($taxData);
    }

    /**
     * Update customer currency based on location
     */
    public function updateCustomerCurrency($customer, $location): void
    {
        if (!$this->getConfig('currency_settings.auto_detect_currency', true)) {
            return;
        }

        $detectedCurrency = $this->currencyManager->detectCurrencyByLocation($location);
        if ($detectedCurrency && $this->currencyManager->isActiveCurrency($detectedCurrency)) {
            $customer->setPreferredCurrency($detectedCurrency);
        }
    }

    /**
     * Convert currency for payment processing
     */
    public function convertCurrency($payment): void
    {
        $fromCurrency = $payment->getCurrency();
        $toCurrency = $payment->getGatewayCurrency();

        if ($fromCurrency !== $toCurrency) {
            $convertedAmount = $this->priceConverter->convert(
                $payment->getAmount(),
                $fromCurrency,
                $toCurrency
            );
            $payment->setConvertedAmount($convertedAmount);
            $payment->setExchangeRate($this->exchangeRateProvider->getRate($fromCurrency, $toCurrency));
        }
    }

    /**
     * Format price with localization
     */
    public function formatLocalizedPrice($price, $currency = null, $locale = null): string
    {
        $currency = $currency ?? $this->currencyManager->getCurrentCurrency();
        $locale = $locale ?? $this->localizationManager->getCurrentLocale();

        return $this->priceConverter->formatPrice($price, $currency, $locale);
    }

    /**
     * Translate text
     */
    public function translateText($text, $domain = 'general', $language = null): string
    {
        $language = $language ?? $this->localizationManager->getCurrentLanguage();
        return $this->translationService->translate($text, $language, $domain);
    }

    /**
     * Get active currencies
     */
    public function getActiveCurrencies(): array
    {
        return $this->currencyManager->getActiveCurrencies();
    }

    /**
     * Get active languages
     */
    public function getActiveLanguages(): array
    {
        return $this->localizationManager->getActiveLanguages();
    }

    /**
     * Scheduled task: Update exchange rates
     */
    public function updateExchangeRates(): void
    {
        $this->exchangeRateProvider->updateAllRates();
    }

    /**
     * Scheduled task: Process regional pricing adjustments
     */
    public function processRegionalPricingAdjustments(): void
    {
        if (!$this->getConfig('regional_pricing.automatic_adjustments', false)) {
            return;
        }

        $this->regionalPricingManager->processAutomaticAdjustments();
    }

    /**
     * Scheduled task: Update tax rates
     */
    public function updateTaxRates(): void
    {
        $this->taxComplianceManager->updateTaxRatesFromExternalSources();
    }

    /**
     * Scheduled task: Generate tax reports
     */
    public function generateTaxReports(): void
    {
        if (!$this->getConfig('tax_compliance.tax_reporting', true)) {
            return;
        }

        $this->taxComplianceManager->generateWeeklyReports();
    }

    /**
     * Scheduled task: Sync currency conversions
     */
    public function syncCurrencyConversions(): void
    {
        $this->priceConverter->syncConversionCache();
    }

    /**
     * Handle currency switch AJAX request
     */
    public function handleCurrencySwitch(): void
    {
        $currency = $request->input('currency'] ?? '';
        
        if ($this->currencyManager->isActiveCurrency($currency)) {
            $this->currencyManager->setCurrentCurrency($currency);
            wp_send_json_success(['currency' => $currency]);
        } else {
            wp_send_json_error(['message' => 'Invalid currency']);
        }
    }

    /**
     * Handle language switch AJAX request
     */
    public function handleLanguageSwitch(): void
    {
        $language = $request->input('language'] ?? '';
        
        if ($this->localizationManager->isActiveLanguage($language)) {
            $this->localizationManager->setCurrentLanguage($language);
            wp_send_json_success(['language' => $language]);
        } else {
            wp_send_json_error(['message' => 'Invalid language']);
        }
    }

    /**
     * Handle location detection AJAX request
     */
    public function handleLocationDetection(): void
    {
        $location = $this->geolocationService->detectLocation();
        wp_send_json_success($location);
    }

    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        add_menu_page(
            'Multi-Currency',
            'Multi-Currency',
            'currencies.view',
            'multi-currency',
            [$this, 'renderCurrencyPage'],
            'dashicons-money-alt',
            25
        );
        
        add_submenu_page(
            'multi-currency',
            'Currencies',
            'Currencies',
            'currencies.view',
            'currencies',
            [$this, 'renderCurrencyPage']
        );
        
        add_submenu_page(
            'multi-currency',
            'Exchange Rates',
            'Exchange Rates',
            'exchange_rates.view',
            'exchange-rates',
            [$this, 'renderExchangeRatesPage']
        );
        
        add_submenu_page(
            'multi-currency',
            'Regional Pricing',
            'Regional Pricing',
            'regional_pricing.view',
            'regional-pricing',
            [$this, 'renderRegionalPricingPage']
        );
        
        add_submenu_page(
            'multi-currency',
            'Tax Compliance',
            'Tax Compliance',
            'tax_compliance.view',
            'tax-compliance',
            [$this, 'renderTaxCompliancePage']
        );
        
        add_submenu_page(
            'multi-currency',
            'Localization',
            'Localization',
            'localization.view',
            'localization',
            [$this, 'renderLocalizationPage']
        );
    }

    /**
     * Set default configuration
     */
    private function setDefaultConfiguration(): void
    {
        $defaults = [
            'currency_settings' => [
                'base_currency' => 'USD',
                'auto_detect_currency' => true,
                'currency_switching_enabled' => true,
                'exchange_rate_provider' => 'fixer',
                'exchange_rate_update_frequency' => 'hourly',
                'price_precision' => 2
            ],
            'localization_settings' => [
                'default_language' => 'en',
                'auto_detect_language' => true,
                'fallback_language' => 'en',
                'translation_caching' => true,
                'rtl_languages' => ['ar', 'he']
            ],
            'regional_pricing' => [
                'enabled' => true,
                'pricing_strategy' => 'purchasing_power',
                'automatic_adjustments' => false,
                'adjustment_frequency' => 'monthly'
            ],
            'tax_compliance' => [
                'enabled' => true,
                'vat_enabled' => true,
                'sales_tax_enabled' => true,
                'tax_calculation_method' => 'inclusive',
                'digital_services_tax' => false,
                'tax_reporting' => true
            ],
            'geolocation' => [
                'enabled' => true,
                'ip_detection_service' => 'maxmind',
                'cache_location_data' => true,
                'location_cache_duration' => 24
            ]
        ];
        
        foreach ($defaults as $key => $value) {
            if (!$this->hasConfig($key)) {
                $this->setConfig($key, $value);
            }
        }
    }

    /**
     * Create default currencies
     */
    private function createDefaultCurrencies(): void
    {
        $defaultCurrencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'is_base_currency' => true],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£'],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'decimal_places' => 0],
            ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => 'C$'],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$']
        ];

        foreach ($defaultCurrencies as $currency) {
            $this->currencyManager->createCurrency($currency);
        }
    }

    /**
     * Create necessary directories
     */
    private function createDirectories(): void
    {
        $dirs = [
            $this->getPluginPath() . '/translations',
            $this->getPluginPath() . '/cache',
            $this->getPluginPath() . '/logs',
            $this->getPluginPath() . '/exports'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
        }
    }

    /**
     * Register EventListeners
     */
    protected function registerEventListeners(): void
    {
        // TODO: Implement registerEventListeners
    }

    /**
     * Register Permissions
     */
    protected function registerPermissions(): void
    {
        // TODO: Implement registerPermissions
    }

    /**
     * Register ScheduledJobs
     */
    protected function registerScheduledJobs(): void
    {
        // TODO: Implement registerScheduledJobs
    }
}