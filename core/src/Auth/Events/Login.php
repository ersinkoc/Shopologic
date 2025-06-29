<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth\Events;

use Shopologic\Core\Auth\Contracts\Authenticatable;

class Login
{
    public Authenticatable $user;
    public bool $remember;

    public function __construct(Authenticatable $user, bool $remember = false)
    {
        $this->user = $user;
        $this->remember = $remember;
    }
}