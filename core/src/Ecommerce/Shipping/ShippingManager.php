<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Shipping;

use Shopologic\Core\Ecommerce\Cart\Cart;
use Shopologic\Core\Ecommerce\Shipping\Methods\ShippingMethodInterface;

class ShippingManager
{
    protected array $methods = [];
    protected string $defaultMethod = 'flat_rate';

    public function __construct()
    {
        $this->registerDefaultMethods();
    }

    /**
     * Register default shipping methods
     */
    protected function registerDefaultMethods(): void
    {
        $this->registerMethod('flat_rate', new Methods\FlatRateMethod());
        $this->registerMethod('free_shipping', new Methods\FreeShippingMethod());
        $this->registerMethod('weight_based', new Methods\WeightBasedMethod());
        $this->registerMethod('pickup', new Methods\PickupMethod());
    }

    /**
     * Register a shipping method
     */
    public function registerMethod(string $name, ShippingMethodInterface $method): void
    {
        $this->methods[$name] = $method;
    }

    /**
     * Get a shipping method
     */
    public function method(string $name): ShippingMethodInterface
    {
        if (!isset($this->methods[$name])) {
            throw new \InvalidArgumentException("Shipping method [{$name}] is not registered.");
        }
        
        return $this->methods[$name];
    }

    /**
     * Calculate shipping rates for cart
     */
    public function calculateRates(Cart $cart, Address $shippingAddress): array
    {
        $rates = [];
        
        foreach ($this->methods as $name => $method) {
            if ($method->isAvailable($cart, $shippingAddress)) {
                $rate = $method->calculateRate($cart, $shippingAddress);
                
                if ($rate !== null) {
                    $rates[$name] = [
                        'name' => $method->getName(),
                        'display_name' => $method->getDisplayName(),
                        'description' => $method->getDescription(),
                        'cost' => $rate,
                        'estimated_days' => $method->getEstimatedDays(),
                    ];
                }
            }
        }
        
        return $rates;
    }

    /**
     * Get cheapest rate
     */
    public function getCheapestRate(Cart $cart, Address $shippingAddress): ?array
    {
        $rates = $this->calculateRates($cart, $shippingAddress);
        
        if (empty($rates)) {
            return null;
        }
        
        $cheapest = null;
        $lowestCost = PHP_FLOAT_MAX;
        
        foreach ($rates as $name => $rate) {
            if ($rate['cost'] < $lowestCost) {
                $lowestCost = $rate['cost'];
                $cheapest = array_merge($rate, ['method' => $name]);
            }
        }
        
        return $cheapest;
    }

    /**
     * Get fastest rate
     */
    public function getFastestRate(Cart $cart, Address $shippingAddress): ?array
    {
        $rates = $this->calculateRates($cart, $shippingAddress);
        
        if (empty($rates)) {
            return null;
        }
        
        $fastest = null;
        $leastDays = PHP_INT_MAX;
        
        foreach ($rates as $name => $rate) {
            if ($rate['estimated_days'] < $leastDays) {
                $leastDays = $rate['estimated_days'];
                $fastest = array_merge($rate, ['method' => $name]);
            }
        }
        
        return $fastest;
    }

    /**
     * Validate shipping method
     */
    public function validateMethod(string $method, Cart $cart, Address $shippingAddress): bool
    {
        if (!isset($this->methods[$method])) {
            return false;
        }
        
        return $this->methods[$method]->isAvailable($cart, $shippingAddress);
    }

    /**
     * Get all available methods
     */
    public function getAvailableMethods(): array
    {
        $available = [];
        
        foreach ($this->methods as $name => $method) {
            $available[$name] = [
                'name' => $method->getName(),
                'display_name' => $method->getDisplayName(),
                'description' => $method->getDescription(),
            ];
        }
        
        return $available;
    }
}