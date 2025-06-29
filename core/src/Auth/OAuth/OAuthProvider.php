<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth\OAuth;

interface OAuthProvider
{
    /**
     * Get the authorization URL
     */
    public function getAuthUrl(array $scopes = []): string;

    /**
     * Redirect to the OAuth provider
     */
    public function redirect(array $scopes = []): string;

    /**
     * Get the access token from the callback code
     */
    public function getAccessToken(string $code): string;

    /**
     * Get user by access token
     */
    public function getUserByToken(string $token): OAuthUser;

    /**
     * Set redirect URL
     */
    public function setRedirectUrl(string $url): void;

    /**
     * Set client ID
     */
    public function setClientId(string $clientId): void;

    /**
     * Set client secret
     */
    public function setClientSecret(string $clientSecret): void;
}