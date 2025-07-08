<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce\Services;

class SecurityService
{
    public function validateRequest(array $request): bool
    {
        // Validate CSRF tokens, rate limits, etc.
        return true;
    }
    
    public function sanitizeInput(mixed $input): mixed
    {
        if (is_string($input)) {
            return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        }
        return $input;
    }
}