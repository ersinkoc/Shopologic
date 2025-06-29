<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth\OAuth\Events;

use Shopologic\Core\Auth\Models\User;

class UserCreatedViaOAuth
{
    public User $user;
    public string $provider;

    public function __construct(User $user, string $provider)
    {
        $this->user = $user;
        $this->provider = $provider;
    }
}