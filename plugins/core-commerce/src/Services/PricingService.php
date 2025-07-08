<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce\Services;

class PricingService
{
    public function calculatePrice(array $product, ?array $customer = null): float
    {
        $price = $product['price'];
        
        // Apply sale price if available
        if ($product['sale_price'] && $product['sale_price'] < $price) {
            $price = $product['sale_price'];
        }
        
        // Apply customer-specific pricing if available
        if ($customer && isset($customer['pricing_tier'])) {
            $discount = $this->getTierDiscount($customer['pricing_tier']);
            $price = $price * (1 - $discount);
        }
        
        return round($price, 2);
    }
    
    private function getTierDiscount(string $tier): float
    {
        return match($tier) {
            'bronze' => 0.05,
            'silver' => 0.10,
            'gold' => 0.15,
            'platinum' => 0.20,
            default => 0
        };
    }
}