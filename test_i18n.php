<?php

require_once __DIR__ . '/bootstrap.php';

use Shopologic\Core\I18n\Translator;
use Shopologic\Core\I18n\Currency\Currency;
use Shopologic\Core\I18n\Currency\CurrencyManager;
use Shopologic\Core\I18n\Locale\LocaleManager;
use Shopologic\Core\I18n\Locale\Locale;

echo "Testing Internationalization (i18n) Functionality\n";
echo "==============================================\n\n";

try {
    // Get services
    $translator = $container->get(Translator::class);
    $currencyManager = $container->get(CurrencyManager::class);
    $localeManager = $container->get(LocaleManager::class);
    
    // 1. Test Locale Management
    echo "1. Testing Locale Management...\n";
    
    $availableLocales = $localeManager->getAvailableLocales();
    echo "   Available locales: " . implode(', ', $availableLocales) . "\n";
    
    // Switch locales and test translations
    $testLocales = ['en', 'es', 'fr'];
    foreach ($testLocales as $locale) {
        if ($localeManager->isAvailable($locale)) {
            $localeManager->setCurrentLocale($locale);
            
            $welcome = $translator->translate('welcome');
            $addToCart = $translator->translate('add_to_cart');
            $items = $translator->choice('item', 5, ['count' => 5]);
            
            echo "   [{$locale}] {$welcome} | {$addToCart} | {$items}\n";
        }
    }
    echo "\n";
    
    // 2. Test Currency Management
    echo "2. Testing Currency Management...\n";
    
    // Create test currencies
    $currencies = [
        ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'exchange_rate' => 1.0],
        ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'exchange_rate' => 0.85],
        ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'exchange_rate' => 0.73],
        ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'exchange_rate' => 110.0]
    ];
    
    foreach ($currencies as $currencyData) {
        try {
            $currency = $currencyManager->createCurrency($currencyData);
            echo "   - Created currency: {$currency->name} ({$currency->code})\n";
        } catch (\Exception $e) {
            // Currency might already exist
            $currency = Currency::findByCode($currencyData['code']);
        }
    }
    echo "\n";
    
    // Test currency formatting
    echo "3. Testing Currency Formatting...\n";
    $amount = 99.99;
    
    foreach (['USD', 'EUR', 'GBP', 'JPY'] as $code) {
        $currencyManager->setCurrentCurrency($code);
        $formatted = $currencyManager->format($amount);
        echo "   {$code}: {$formatted}\n";
    }
    echo "\n";
    
    // Test currency conversion
    echo "4. Testing Currency Conversion...\n";
    $baseAmount = 100.00;
    echo "   Base amount: USD {$baseAmount}\n";
    
    foreach (['EUR', 'GBP', 'JPY'] as $targetCurrency) {
        $converted = $currencyManager->convert($baseAmount, 'USD', $targetCurrency);
        $formatted = $currencyManager->format($converted, $targetCurrency);
        echo "   Converted to {$targetCurrency}: {$formatted}\n";
    }
    echo "\n";
    
    // 5. Test Locale-specific Formatting
    echo "5. Testing Locale-specific Formatting...\n";
    
    $date = new DateTime('2024-01-15 14:30:00');
    $number = 1234567.89;
    
    foreach (['en', 'es', 'de', 'fr'] as $locale) {
        if ($localeManager->isAvailable($locale)) {
            $localeManager->setCurrentLocale($locale);
            
            $formattedDate = $translator->formatDate($date, 'long');
            $formattedNumber = $translator->formatNumber($number, 2);
            $formattedCurrency = $translator->formatCurrency($number, 'USD');
            
            echo "   [{$locale}] Date: {$formattedDate} | Number: {$formattedNumber} | Currency: {$formattedCurrency}\n";
        }
    }
    echo "\n";
    
    // 6. Test Translation with Parameters
    echo "6. Testing Translation with Parameters...\n";
    
    $localeManager->setCurrentLocale('en');
    
    // Add custom translations for testing
    $translator->addTranslations([
        'greeting' => 'Hello :name, welcome to :site!',
        'order_status' => 'Your order #:order_number is :status',
        'discount_message' => 'Save :percent% on orders over :amount'
    ], 'en', 'test');
    
    $greeting = $translator->translate('test.greeting', [
        'name' => 'John',
        'site' => 'Shopologic'
    ]);
    echo "   {$greeting}\n";
    
    $orderStatus = $translator->translate('test.order_status', [
        'order_number' => '12345',
        'status' => 'shipped'
    ]);
    echo "   {$orderStatus}\n";
    
    $discount = $translator->translate('test.discount_message', [
        'percent' => 20,
        'amount' => '$100'
    ]);
    echo "   {$discount}\n\n";
    
    // 7. Test RTL Support
    echo "7. Testing RTL Language Support...\n";
    
    $rtlLocales = ['ar', 'he'];
    foreach ($rtlLocales as $locale) {
        $info = Locale::getInfo($locale);
        if ($info) {
            echo "   {$info['name']} ({$locale}): Direction = {$info['dir']}, RTL = " . (Locale::isRtl($locale) ? 'Yes' : 'No') . "\n";
        }
    }
    echo "\n";
    
    // 8. Test Multi-Store with I18n
    echo "8. Testing Multi-Store with I18n...\n";
    
    // Simulate store with different locale/currency
    $storeManager = $container->get(\Shopologic\Core\MultiStore\StoreManager::class);
    
    $store = \Shopologic\Core\MultiStore\Store::where('code', 'eu')->first();
    if ($store) {
        $storeManager->switchToStore($store->id);
        
        echo "   Switched to store: {$store->name}\n";
        echo "   Store locale: {$store->locale}\n";
        echo "   Store currency: {$store->currency}\n";
        
        // Locale and currency should auto-switch
        echo "   Current locale: " . $localeManager->getCurrentLocale() . "\n";
        echo "   Current currency: " . $currencyManager->getCurrentCurrency()->code . "\n";
    }
    echo "\n";
    
    // Summary
    echo "9. I18n Summary:\n";
    echo "   - Available locales: " . count($localeManager->getAvailableLocales()) . "\n";
    echo "   - Active currencies: " . count($currencyManager->getActiveCurrencies()) . "\n";
    echo "   - Translation namespaces: default, test, plugin translations\n";
    echo "   - Locale detection: Session > Store > Browser > Default\n";
    echo "   - Currency detection: Session > Store > Default\n";
    
    echo "\nInternationalization test completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}