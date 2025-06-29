<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth\Events;

class Failed
{
    public array $credentials;

    public function __construct(array $credentials)
    {
        $this->credentials = $credentials;
    }
}