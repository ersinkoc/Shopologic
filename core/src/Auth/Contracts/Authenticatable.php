<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth\Contracts;

interface Authenticatable
{
    /**
     * Get the unique identifier for the user
     */
    public function getAuthIdentifier(): mixed;

    /**
     * Get the password for the user
     */
    public function getAuthPassword(): string;

    /**
     * Get the token value for the "remember me" session
     */
    public function getRememberToken(): ?string;

    /**
     * Set the token value for the "remember me" session
     */
    public function setRememberToken(string $token): void;

    /**
     * Get the column name for the "remember me" token
     */
    public function getRememberTokenName(): string;
}