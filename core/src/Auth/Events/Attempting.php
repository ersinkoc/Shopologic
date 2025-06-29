<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth\Events;

class Attempting
{
    public array $credentials;
    public bool $remember;

    public function __construct(array $credentials, bool $remember = false)
    {
        $this->credentials = $credentials;
        $this->remember = $remember;
    }
}