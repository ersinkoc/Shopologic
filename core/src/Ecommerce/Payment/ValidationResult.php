<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Payment;

class ValidationResult
{
    private bool $valid;
    private array $errors;

    public function __construct(bool $valid, array $errors = [])
    {
        $this->valid = $valid;
        $this->errors = $errors;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    public function getError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }
}