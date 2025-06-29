<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Tax;

use Shopologic\Core\Ecommerce\Cart\Cart;
use Shopologic\Core\Ecommerce\Shipping\Address;

class TaxManager
{
    protected array $rules = [];
    protected bool $pricesIncludeTax = false;
    protected string $defaultCountry = 'US';
    protected string $defaultState = 'CA';

    public function __construct()
    {
        $this->loadDefaultRules();
    }

    /**
     * Load default tax rules
     */
    protected function loadDefaultRules(): void
    {
        // US State tax rates (simplified)
        $this->addRule('US', 'CA', 0.0725); // California
        $this->addRule('US', 'NY', 0.08);   // New York
        $this->addRule('US', 'TX', 0.0625); // Texas
        $this->addRule('US', 'FL', 0.06);   // Florida
        $this->addRule('US', 'WA', 0.065);  // Washington
        
        // Canadian provinces (GST/PST combined)
        $this->addRule('CA', 'ON', 0.13);   // Ontario
        $this->addRule('CA', 'BC', 0.12);   // British Columbia
        $this->addRule('CA', 'QC', 0.14975); // Quebec
        
        // EU VAT rates
        $this->addRule('GB', '*', 0.20);    // UK
        $this->addRule('DE', '*', 0.19);    // Germany
        $this->addRule('FR', '*', 0.20);    // France
    }

    /**
     * Add a tax rule
     */
    public function addRule(string $country, string $state, float $rate): void
    {
        if (!isset($this->rules[$country])) {
            $this->rules[$country] = [];
        }
        
        $this->rules[$country][$state] = $rate;
    }

    /**
     * Calculate tax for cart
     */
    public function calculateTax(Cart $cart, Address $address): float
    {
        $rate = $this->getTaxRate($address->country, $address->state);
        
        if ($rate === 0) {
            return 0;
        }
        
        $taxableAmount = $this->getTaxableAmount($cart);
        
        if ($this->pricesIncludeTax) {
            // Extract tax from the total
            return $taxableAmount - ($taxableAmount / (1 + $rate));
        }
        
        // Add tax to the total
        return $taxableAmount * $rate;
    }

    /**
     * Get tax rate for location
     */
    public function getTaxRate(string $country, string $state): float
    {
        // Check for specific state/province rate
        if (isset($this->rules[$country][$state])) {
            return $this->rules[$country][$state];
        }
        
        // Check for country-wide rate (using wildcard)
        if (isset($this->rules[$country]['*'])) {
            return $this->rules[$country]['*'];
        }
        
        return 0;
    }

    /**
     * Get taxable amount from cart
     */
    protected function getTaxableAmount(Cart $cart): float
    {
        $taxable = 0;
        
        foreach ($cart->items() as $item) {
            // Check if product is taxable (all products taxable by default)
            if ($this->isProductTaxable($item->product)) {
                $taxable += $item->getTotal();
            }
        }
        
        // Apply discount before tax
        $taxable -= $cart->getDiscount();
        
        return max(0, $taxable);
    }

    /**
     * Check if product is taxable
     */
    protected function isProductTaxable($product): bool
    {
        // Digital products might be tax-exempt in some jurisdictions
        // This is a simplified implementation
        return true;
    }

    /**
     * Set whether prices include tax
     */
    public function setPricesIncludeTax(bool $include): void
    {
        $this->pricesIncludeTax = $include;
    }

    /**
     * Get whether prices include tax
     */
    public function pricesIncludeTax(): bool
    {
        return $this->pricesIncludeTax;
    }

    /**
     * Set default location
     */
    public function setDefaultLocation(string $country, string $state): void
    {
        $this->defaultCountry = $country;
        $this->defaultState = $state;
    }

    /**
     * Get tax info for display
     */
    public function getTaxInfo(string $country, string $state): array
    {
        $rate = $this->getTaxRate($country, $state);
        
        return [
            'rate' => $rate,
            'percentage' => $rate * 100,
            'included_in_price' => $this->pricesIncludeTax,
            'label' => $this->getTaxLabel($country, $state),
        ];
    }

    /**
     * Get tax label
     */
    protected function getTaxLabel(string $country, string $state): string
    {
        switch ($country) {
            case 'US':
                return 'Sales Tax';
            case 'CA':
                return 'GST/PST';
            case 'GB':
            case 'DE':
            case 'FR':
                return 'VAT';
            default:
                return 'Tax';
        }
    }

    /**
     * Validate tax number (VAT, EIN, etc.)
     */
    public function validateTaxNumber(string $number, string $country): bool
    {
        // Simplified validation - in real implementation would check format and possibly verify online
        switch ($country) {
            case 'US':
                // EIN format: XX-XXXXXXX
                return preg_match('/^\d{2}-\d{7}$/', $number) === 1;
            case 'GB':
                // UK VAT format: GB999 9999 99
                return preg_match('/^GB\d{9}$/', str_replace(' ', '', $number)) === 1;
            case 'DE':
                // German VAT format: DE999999999
                return preg_match('/^DE\d{9}$/', $number) === 1;
            default:
                return true;
        }
    }

    /**
     * Check if customer is tax exempt
     */
    public function isCustomerExempt(string $taxNumber, string $country): bool
    {
        if (empty($taxNumber)) {
            return false;
        }
        
        return $this->validateTaxNumber($taxNumber, $country);
    }
}