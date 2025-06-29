<?php

declare(strict_types=1);

namespace Shopologic\Core\Api\Validation;

class Validator
{
    protected array $data;
    protected array $rules;
    protected array $messages = [];
    protected array $errors = [];
    protected array $validated = [];

    public function __construct(array $data, array $rules, array $messages = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = $messages;
    }

    /**
     * Run validation
     */
    public function validate(): bool
    {
        $this->errors = [];
        $this->validated = [];

        foreach ($this->rules as $field => $rules) {
            $value = $this->getValue($field);
            $fieldRules = $this->parseRules($rules);
            
            foreach ($fieldRules as $rule) {
                if (!$this->validateRule($field, $value, $rule)) {
                    break; // Stop validating this field on first error
                }
            }
            
            // Add to validated data if no errors
            if (!isset($this->errors[$field])) {
                $this->setValue($field, $value);
            }
        }

        return empty($this->errors);
    }

    /**
     * Check if validation passes
     */
    public function passes(): bool
    {
        return $this->validate();
    }

    /**
     * Check if validation fails
     */
    public function fails(): bool
    {
        return !$this->validate();
    }

    /**
     * Get validation errors
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get validated data
     */
    public function validated(): array
    {
        return $this->validated;
    }

    /**
     * Get value from data
     */
    protected function getValue(string $field): mixed
    {
        if (str_contains($field, '.')) {
            return $this->getNestedValue($field);
        }
        
        return $this->data[$field] ?? null;
    }

    /**
     * Get nested value
     */
    protected function getNestedValue(string $field): mixed
    {
        $keys = explode('.', $field);
        $value = $this->data;
        
        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }
        
        return $value;
    }

    /**
     * Set validated value
     */
    protected function setValue(string $field, mixed $value): void
    {
        if (str_contains($field, '.')) {
            $this->setNestedValue($field, $value);
            return;
        }
        
        $this->validated[$field] = $value;
    }

    /**
     * Set nested value
     */
    protected function setNestedValue(string $field, mixed $value): void
    {
        $keys = explode('.', $field);
        $current = &$this->validated;
        
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $current[$key] = $value;
            } else {
                if (!isset($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
        }
    }

    /**
     * Parse rules
     */
    protected function parseRules(string|array $rules): array
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }
        
        $parsed = [];
        
        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $parts = explode(':', $rule, 2);
                $parsed[] = [
                    'rule' => $parts[0],
                    'parameters' => isset($parts[1]) ? explode(',', $parts[1]) : [],
                ];
            } else {
                $parsed[] = ['rule' => $rule, 'parameters' => []];
            }
        }
        
        return $parsed;
    }

    /**
     * Validate a rule
     */
    protected function validateRule(string $field, mixed $value, array $rule): bool
    {
        $method = 'validate' . studly_case($rule['rule']);
        
        if (!method_exists($this, $method)) {
            throw new \InvalidArgumentException("Validation rule {$rule['rule']} does not exist");
        }
        
        $result = $this->$method($field, $value, $rule['parameters']);
        
        if (!$result) {
            $this->addError($field, $rule['rule'], $rule['parameters']);
            return false;
        }
        
        return true;
    }

    /**
     * Add validation error
     */
    protected function addError(string $field, string $rule, array $parameters = []): void
    {
        $message = $this->getMessage($field, $rule, $parameters);
        
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        
        $this->errors[$field][] = $message;
    }

    /**
     * Get error message
     */
    protected function getMessage(string $field, string $rule, array $parameters): string
    {
        $key = "{$field}.{$rule}";
        
        if (isset($this->messages[$key])) {
            return $this->replacePlaceholders($this->messages[$key], $field, $parameters);
        }
        
        if (isset($this->messages[$rule])) {
            return $this->replacePlaceholders($this->messages[$rule], $field, $parameters);
        }
        
        return $this->getDefaultMessage($field, $rule, $parameters);
    }

    /**
     * Get default error message
     */
    protected function getDefaultMessage(string $field, string $rule, array $parameters): string
    {
        $messages = [
            'required' => 'The :field field is required.',
            'string' => 'The :field field must be a string.',
            'numeric' => 'The :field field must be numeric.',
            'integer' => 'The :field field must be an integer.',
            'boolean' => 'The :field field must be boolean.',
            'array' => 'The :field field must be an array.',
            'email' => 'The :field field must be a valid email address.',
            'min' => 'The :field field must be at least :min.',
            'max' => 'The :field field must not exceed :max.',
            'between' => 'The :field field must be between :min and :max.',
            'in' => 'The :field field must be one of: :values.',
            'not_in' => 'The :field field must not be one of: :values.',
            'regex' => 'The :field field format is invalid.',
            'confirmed' => 'The :field confirmation does not match.',
            'unique' => 'The :field has already been taken.',
            'exists' => 'The selected :field is invalid.',
            'date' => 'The :field field must be a valid date.',
            'before' => 'The :field field must be before :date.',
            'after' => 'The :field field must be after :date.',
        ];
        
        $message = $messages[$rule] ?? 'The :field field is invalid.';
        
        return $this->replacePlaceholders($message, $field, $parameters);
    }

    /**
     * Replace message placeholders
     */
    protected function replacePlaceholders(string $message, string $field, array $parameters): string
    {
        $replacements = [
            ':field' => str_replace('_', ' ', $field),
            ':min' => $parameters[0] ?? '',
            ':max' => $parameters[1] ?? $parameters[0] ?? '',
            ':values' => implode(', ', $parameters),
            ':date' => $parameters[0] ?? '',
        ];
        
        return strtr($message, $replacements);
    }

    // Validation Rules

    protected function validateRequired(string $field, mixed $value): bool
    {
        if (is_null($value)) {
            return false;
        }
        
        if (is_string($value) && trim($value) === '') {
            return false;
        }
        
        if (is_array($value) && count($value) < 1) {
            return false;
        }
        
        return true;
    }

    protected function validateString(string $field, mixed $value): bool
    {
        return is_string($value);
    }

    protected function validateNumeric(string $field, mixed $value): bool
    {
        return is_numeric($value);
    }

    protected function validateInteger(string $field, mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    protected function validateBoolean(string $field, mixed $value): bool
    {
        return in_array($value, [true, false, 0, 1, '0', '1'], true);
    }

    protected function validateArray(string $field, mixed $value): bool
    {
        return is_array($value);
    }

    protected function validateEmail(string $field, mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    protected function validateMin(string $field, mixed $value, array $parameters): bool
    {
        $min = $parameters[0] ?? 0;
        
        if (is_string($value)) {
            return strlen($value) >= $min;
        }
        
        if (is_numeric($value)) {
            return $value >= $min;
        }
        
        if (is_array($value)) {
            return count($value) >= $min;
        }
        
        return false;
    }

    protected function validateMax(string $field, mixed $value, array $parameters): bool
    {
        $max = $parameters[0] ?? PHP_INT_MAX;
        
        if (is_string($value)) {
            return strlen($value) <= $max;
        }
        
        if (is_numeric($value)) {
            return $value <= $max;
        }
        
        if (is_array($value)) {
            return count($value) <= $max;
        }
        
        return false;
    }

    protected function validateBetween(string $field, mixed $value, array $parameters): bool
    {
        $min = $parameters[0] ?? 0;
        $max = $parameters[1] ?? PHP_INT_MAX;
        
        return $this->validateMin($field, $value, [$min]) && 
               $this->validateMax($field, $value, [$max]);
    }

    protected function validateIn(string $field, mixed $value, array $parameters): bool
    {
        return in_array($value, $parameters, true);
    }

    protected function validateNotIn(string $field, mixed $value, array $parameters): bool
    {
        return !in_array($value, $parameters, true);
    }

    protected function validateRegex(string $field, mixed $value, array $parameters): bool
    {
        if (!is_string($value)) {
            return false;
        }
        
        $pattern = $parameters[0] ?? '';
        
        return (bool) preg_match($pattern, $value);
    }

    protected function validateConfirmed(string $field, mixed $value): bool
    {
        $confirmField = $field . '_confirmation';
        $confirmValue = $this->getValue($confirmField);
        
        return $value === $confirmValue;
    }

    protected function validateDate(string $field, mixed $value): bool
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }
        
        return strtotime($value) !== false;
    }
}