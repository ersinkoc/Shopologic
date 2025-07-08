<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce\Services;

class IntegrationService
{
    public function syncWithExternalSystem(string $system, array $data): bool
    {
        // Handle external system integrations
        return true;
    }
}