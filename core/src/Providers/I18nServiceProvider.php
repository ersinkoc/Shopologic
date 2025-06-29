<?php

declare(strict_types=1);

namespace Shopologic\Core\Providers;

use Shopologic\Core\Container\ServiceProvider;
use Shopologic\Core\I18n\Translator;
use Shopologic\Core\I18n\Currency\CurrencyManager;
use Shopologic\Core\I18n\Locale\LocaleManager;
use Shopologic\Core\I18n\Middleware\LocaleMiddleware;

class I18nServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register translator
        $this->container->singleton(Translator::class, function ($container) {
            $config = $container->get('config')['i18n'] ?? [];
            
            $translator = new Translator(
                $container->get('cache'),
                $container->get('events'),
                array_merge([
                    'locale' => 'en',
                    'fallback_locale' => 'en',
                    'translation_paths' => [
                        'default' => [dirname(__DIR__, 3) . '/resources/lang']
                    ]
                ], $config)
            );
            
            return $translator;
        });
        
        // Register currency manager
        $this->container->singleton(CurrencyManager::class, function ($container) {
            $config = $container->get('config')['currency'] ?? [];
            
            return new CurrencyManager(
                $container->get('cache'),
                $container->get('events'),
                $container->get('session'),
                $config
            );
        });
        
        // Register locale manager
        $this->container->singleton(LocaleManager::class, function ($container) {
            $config = $container->get('config')['locale'] ?? [];
            
            return new LocaleManager(
                $container->get('session'),
                $container->get('events'),
                $container->get(Translator::class),
                array_merge([
                    'default_locale' => 'en',
                    'available_locales' => ['en', 'es', 'fr', 'de', 'it', 'pt', 'ru', 'ja', 'zh', 'ko']
                ], $config)
            );
        });
        
        // Register middleware
        $this->container->bind(LocaleMiddleware::class, function ($container) {
            return new LocaleMiddleware(
                $container->get(LocaleManager::class)
            );
        });
        
        // Register helpers
        $this->container->bind('translator', function ($container) {
            return $container->get(Translator::class);
        });
        
        $this->container->bind('currency', function ($container) {
            return $container->get(CurrencyManager::class);
        });
        
        $this->container->bind('locale', function ($container) {
            return $container->get(LocaleManager::class);
        });
    }
    
    public function boot(): void
    {
        // Add locale middleware
        $this->container->get('middleware')->addGlobal(
            LocaleMiddleware::class
        );
        
        // Add translation paths
        $translator = $this->container->get(Translator::class);
        
        // Add plugin translation paths
        $pluginPath = dirname(__DIR__, 3) . '/plugins';
        if (is_dir($pluginPath)) {
            foreach (scandir($pluginPath) as $plugin) {
                if ($plugin !== '.' && $plugin !== '..') {
                    $langPath = $pluginPath . '/' . $plugin . '/resources/lang';
                    if (is_dir($langPath)) {
                        $translator->addPath($langPath, $plugin);
                    }
                }
            }
        }
        
        // Add theme translation paths
        $themePath = dirname(__DIR__, 3) . '/themes';
        if (is_dir($themePath)) {
            foreach (scandir($themePath) as $theme) {
                if ($theme !== '.' && $theme !== '..') {
                    $langPath = $themePath . '/' . $theme . '/lang';
                    if (is_dir($langPath)) {
                        $translator->addPath($langPath, 'theme_' . $theme);
                    }
                }
            }
        }
        
        // Add template globals
        $template = $this->container->get('template');
        
        $template->addGlobal('locale', function () {
            return $this->container->get(LocaleManager::class);
        });
        
        $template->addGlobal('currency', function () {
            return $this->container->get(CurrencyManager::class);
        });
        
        $template->addGlobal('current_locale', function () {
            return $this->container->get(LocaleManager::class)->getCurrentLocale();
        });
        
        $template->addGlobal('current_currency', function () {
            return $this->container->get(CurrencyManager::class)->getCurrentCurrency();
        });
        
        // Register template functions
        $template->addFunction('trans', function (string $key, array $parameters = [], ?string $locale = null) {
            return $this->container->get(Translator::class)->translate($key, $parameters, $locale);
        });
        
        $template->addFunction('trans_choice', function (string $key, int $count, array $parameters = [], ?string $locale = null) {
            return $this->container->get(Translator::class)->choice($key, $count, $parameters, $locale);
        });
        
        $template->addFunction('format_currency', function (float $amount, ?string $currency = null) {
            return $this->container->get(CurrencyManager::class)->format($amount, $currency);
        });
        
        $template->addFunction('convert_currency', function (float $amount, string $from, string $to) {
            return $this->container->get(CurrencyManager::class)->convert($amount, $from, $to);
        });
        
        // Auto-update currency rates if configured
        $currencyManager = $this->container->get(CurrencyManager::class);
        if ($currencyManager->shouldUpdateRates()) {
            $currencyManager->updateExchangeRates();
        }
        
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../../config/i18n.php' => $this->container->get('config_path') . '/i18n.php'
        ], 'config');
    }
}