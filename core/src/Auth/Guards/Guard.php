<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth\Guards;

use Shopologic\Core\Auth\Contracts\Authenticatable;

interface Guard
{
    /**
     * Determine if the current user is authenticated
     */
    public function check(): bool;

    /**
     * Determine if the current user is a guest
     */
    public function guest(): bool;

    /**
     * Get the currently authenticated user
     */
    public function user(): ?Authenticatable;

    /**
     * Get the ID for the currently authenticated user
     */
    public function id(): mixed;

    /**
     * Validate a user's credentials
     */
    public function validate(array $credentials): bool;

    /**
     * Set the current user
     */
    public function setUser(Authenticatable $user): void;

    /**
     * Attempt to authenticate a user using the given credentials
     */
    public function attempt(array $credentials, bool $remember = false): bool;

    /**
     * Log a user into the application
     */
    public function login(Authenticatable $user, bool $remember = false): void;

    /**
     * Log the user out of the application
     */
    public function logout(): void;
}