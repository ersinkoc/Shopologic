<?php

declare(strict_types=1);

namespace Shopologic\Plugins\MultiCurrencyLocalization\Controllers;

use Shopologic\Core\Http\Controller;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use MultiCurrencyLocalization\Services\{
    CurrencyManager,
    ExchangeRateProvider,
    PriceConverter,;
    LocalizationManager;
};
use MultiCurrencyLocalization\Repositories\{
    CurrencyRepository,;
    ExchangeRateRepository;
};

class CurrencyController extends Controller
{
    private CurrencyManager $currencyManager;
    private ExchangeRateProvider $exchangeRateProvider;
    private PriceConverter $priceConverter;
    private CurrencyRepository $currencyRepository;
    private ExchangeRateRepository $exchangeRateRepository;

    public function __construct()
    {
        $this->currencyManager = app(CurrencyManager::class);
        $this->exchangeRateProvider = app(ExchangeRateProvider::class);
        $this->priceConverter = app(PriceConverter::class);
        $this->currencyRepository = app(CurrencyRepository::class);
        $this->exchangeRateRepository = app(ExchangeRateRepository::class);
    }

    /**
     * Get all currencies
     */
    public function index(Request $request): Response
    {
        $active = $request->query('active');
        
        if ($active !== null) {
            $currencies = $active ? $this->currencyRepository->getActiveCurrencies() : 
                         $this->currencyRepository->getAll();
        } else {
            $currencies = $this->currencyRepository->getAll();
        }

        return $this->json([
            'status' => 'success',
            'data' => $currencies
        ]);
    }

    /**
     * Get currency details
     */
    public function show(Request $request, string $code): Response
    {
        $currency = $this->currencyManager->getCurrency($code);
        
        if (!$currency) {
            return $this->json([
                'status' => 'error',
                'message' => 'Currency not found'
            ], 404);
        }

        return $this->json([
            'status' => 'success',
            'data' => $currency
        ]);
    }

    /**
     * Create new currency
     */
    public function create(Request $request): Response
    {
        $this->validate($request, [
            'code' => 'required|string|size:3',
            'name' => 'required|string',
            'symbol' => 'required|string',
            'symbol_position' => 'in:before,after',
            'decimal_places' => 'integer|min:0|max:4',
            'decimal_separator' => 'string|size:1',
            'thousands_separator' => 'string|size:1',
            'rate_to_base' => 'numeric|min:0',
            'is_active' => 'boolean'
        ]);

        try {
            $currency = $this->currencyManager->createCurrency($request->all());

            return $this->json([
                'status' => 'success',
                'message' => 'Currency created successfully',
                'data' => $currency
            ], 201);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update currency
     */
    public function update(Request $request, string $code): Response
    {
        $this->validate($request, [
            'name' => 'string',
            'symbol' => 'string',
            'symbol_position' => 'in:before,after',
            'decimal_places' => 'integer|min:0|max:4',
            'decimal_separator' => 'string|size:1',
            'thousands_separator' => 'string|size:1',
            'rate_to_base' => 'numeric|min:0',
            'is_active' => 'boolean',
            'is_default' => 'boolean'
        ]);

        try {
            $result = $this->currencyManager->updateCurrency($code, $request->all());

            if (!$result) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Currency not found'
                ], 404);
            }

            return $this->json([
                'status' => 'success',
                'message' => 'Currency updated successfully'
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Delete currency
     */
    public function delete(Request $request, string $code): Response
    {
        try {
            $currency = $this->currencyManager->getCurrency($code);
            
            if (!$currency) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Currency not found'
                ], 404);
            }

            if ($currency->is_base_currency) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Cannot delete base currency'
                ], 400);
            }

            $this->currencyRepository->delete($currency->id);

            return $this->json([
                'status' => 'success',
                'message' => 'Currency deleted successfully'
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Set base currency
     */
    public function setBaseCurrency(Request $request): Response
    {
        $this->validate($request, [
            'currency_code' => 'required|string|size:3'
        ]);

        try {
            $result = $this->currencyManager->setBaseCurrency($request->input('currency_code'));

            if (!$result) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Failed to set base currency'
                ], 400);
            }

            return $this->json([
                'status' => 'success',
                'message' => 'Base currency updated successfully'
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get current currency
     */
    public function getCurrentCurrency(Request $request): Response
    {
        $currentCode = $this->currencyManager->getCurrentCurrency();
        $currency = $this->currencyManager->getCurrency($currentCode);

        return $this->json([
            'status' => 'success',
            'data' => $currency
        ]);
    }

    /**
     * Set current currency
     */
    public function setCurrentCurrency(Request $request): Response
    {
        $this->validate($request, [
            'currency_code' => 'required|string|size:3'
        ]);

        try {
            $result = $this->currencyManager->setCurrentCurrency($request->input('currency_code'));

            if (!$result) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Invalid or inactive currency'
                ], 400);
            }

            return $this->json([
                'status' => 'success',
                'message' => 'Current currency updated successfully'
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get exchange rates
     */
    public function getExchangeRates(Request $request): Response
    {
        $fromCurrency = $request->query('from', $this->currencyManager->getBaseCurrency()->getCode());
        $toCurrencies = $request->query('to', []);
        
        if (!is_array($toCurrencies)) {
            $toCurrencies = [$toCurrencies];
        }

        $rates = [];
        foreach ($toCurrencies as $toCurrency) {
            $rates[$toCurrency] = $this->exchangeRateProvider->getRate($fromCurrency, $toCurrency);
        }

        return $this->json([
            'status' => 'success',
            'data' => [
                'from' => $fromCurrency,
                'rates' => $rates,
                'timestamp' => now()->toIso8601String()
            ]
        ]);
    }

    /**
     * Update exchange rates
     */
    public function updateExchangeRates(Request $request): Response
    {
        try {
            $this->exchangeRateProvider->updateAllRates();

            return $this->json([
                'status' => 'success',
                'message' => 'Exchange rates updated successfully'
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Convert amount between currencies
     */
    public function convert(Request $request): Response
    {
        $this->validate($request, [
            'amount' => 'required|numeric',
            'from' => 'required|string|size:3',
            'to' => 'required|string|size:3'
        ]);

        try {
            $amount = (float)$request->input('amount');
            $from = $request->input('from');
            $to = $request->input('to');

            $converted = $this->priceConverter->convert($amount, $from, $to);
            $rate = $this->exchangeRateProvider->getRate($from, $to);

            return $this->json([
                'status' => 'success',
                'data' => [
                    'original' => [
                        'amount' => $amount,
                        'currency' => $from,
                        'formatted' => $this->currencyManager->formatPrice($amount, $from)
                    ],
                    'converted' => [
                        'amount' => $converted,
                        'currency' => $to,
                        'formatted' => $this->currencyManager->formatPrice($converted, $to)
                    ],
                    'rate' => $rate,
                    'timestamp' => now()->toIso8601String()
                ]
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get historical rates
     */
    public function getHistoricalRates(Request $request): Response
    {
        $this->validate($request, [
            'from' => 'required|string|size:3',
            'to' => 'required|string|size:3',
            'days' => 'integer|min:1|max:365'
        ]);

        $from = $request->input('from');
        $to = $request->input('to');
        $days = (int)$request->input('days', 30);

        $rates = $this->exchangeRateProvider->getHistoricalRates($from, $to, $days);

        return $this->json([
            'status' => 'success',
            'data' => [
                'from' => $from,
                'to' => $to,
                'days' => $days,
                'rates' => $rates
            ]
        ]);
    }

    /**
     * Get currency statistics
     */
    public function statistics(Request $request): Response
    {
        $stats = $this->currencyManager->getCurrencyStatistics();

        return $this->json([
            'status' => 'success',
            'data' => $stats
        ]);
    }

    /**
     * Get supported currencies list
     */
    public function getSupportedCurrencies(Request $request): Response
    {
        $currencies = $this->currencyManager->getSupportedCurrencies();

        return $this->json([
            'status' => 'success',
            'data' => $currencies
        ]);
    }

    /**
     * Activate currency
     */
    public function activate(Request $request, string $code): Response
    {
        try {
            $result = $this->currencyManager->activateCurrency($code);

            if (!$result) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Currency not found'
                ], 404);
            }

            return $this->json([
                'status' => 'success',
                'message' => 'Currency activated successfully'
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Deactivate currency
     */
    public function deactivate(Request $request, string $code): Response
    {
        try {
            $result = $this->currencyManager->deactivateCurrency($code);

            if (!$result) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Currency not found'
                ], 404);
            }

            return $this->json([
                'status' => 'success',
                'message' => 'Currency deactivated successfully'
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Import currencies
     */
    public function import(Request $request): Response
    {
        $this->validate($request, [
            'currencies' => 'required|array'
        ]);

        try {
            $result = $this->currencyManager->importCurrencies($request->input('currencies'));

            return $this->json([
                'status' => 'success',
                'message' => 'Currencies imported successfully',
                'data' => $result
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Export currencies
     */
    public function export(Request $request): Response
    {
        $format = $request->query('format', 'json');
        $codes = $request->query('codes', []);

        try {
            $currencies = $this->currencyManager->exportCurrencies($codes);

            if ($format === 'csv') {
                return $this->csv($currencies, 'currencies_export.csv');
            }

            return $this->json([
                'status' => 'success',
                'data' => $currencies
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}