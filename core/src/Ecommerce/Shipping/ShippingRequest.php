<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Shipping;

use Shopologic\Core\Ecommerce\Models\CustomerAddress;

class ShippingRequest
{
    private CustomerAddress $fromAddress;
    private CustomerAddress $toAddress;
    private array $packages;
    private ?string $serviceType;
    private array $options;

    public function __construct(
        CustomerAddress $fromAddress,
        CustomerAddress $toAddress,
        array $packages,
        ?string $serviceType = null,
        array $options = []
    ) {
        $this->fromAddress = $fromAddress;
        $this->toAddress = $toAddress;
        $this->packages = $packages;
        $this->serviceType = $serviceType;
        $this->options = $options;
    }

    public function getFromAddress(): CustomerAddress
    {
        return $this->fromAddress;
    }

    public function getToAddress(): CustomerAddress
    {
        return $this->toAddress;
    }

    public function getPackages(): array
    {
        return $this->packages;
    }

    public function getServiceType(): ?string
    {
        return $this->serviceType;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getOption(string $key, $default = null)
    {
        return $this->options[$key] ?? $default;
    }

    public function getTotalWeight(): float
    {
        return array_reduce($this->packages, function ($total, $package) {
            return $total + ($package['weight'] ?? 0);
        }, 0.0);
    }

    public function getPackageCount(): int
    {
        return count($this->packages);
    }
}