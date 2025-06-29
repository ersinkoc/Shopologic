<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Shipping;

class AddressValidationResponse
{
    private bool $valid;
    private array $suggestions;
    private array $errors;
    private float $confidence;

    public function __construct(
        bool $valid,
        array $suggestions = [],
        array $errors = [],
        float $confidence = 0.0
    ) {
        $this->valid = $valid;
        $this->suggestions = $suggestions;
        $this->errors = $errors;
        $this->confidence = $confidence;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getSuggestions(): array
    {
        return $this->suggestions;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getConfidence(): float
    {
        return $this->confidence;
    }

    public function hasSuggestions(): bool
    {
        return !empty($this->suggestions);
    }
}