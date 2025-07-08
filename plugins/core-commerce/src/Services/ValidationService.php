<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce\Services;

class ValidationService
{
    public function validateProduct(array $data): array
    {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors[] = 'Product name is required';
        }
        
        if (!isset($data['price']) || $data['price'] < 0) {
            $errors[] = 'Valid price is required';
        }
        
        return $errors;
    }
}