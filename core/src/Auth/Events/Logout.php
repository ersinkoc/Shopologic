<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth\Events;

use Shopologic\Core\Auth\Contracts\Authenticatable;

class Logout
{
    public Authenticatable $user;

    public function __construct(Authenticatable $user)
    {
        $this->user = $user;
    }
}