<?php

declare(strict_types=1);

namespace Shopologic\Core\Api\Validation;

class ValidationException extends \Exception
{
    protected array $errors;

    public function __construct(array $errors, string $message = 'Validation failed')
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    /**
     * Get validation errors
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get first error message
     */
    public function first(): ?string
    {
        foreach ($this->errors as $field => $messages) {
            if (!empty($messages)) {
                return $messages[0];
            }
        }
        
        return null;
    }
}