<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth\OAuth;

use Shopologic\Core\Http\Client\HttpClient;
use Shopologic\Core\Auth\Models\User;
use Shopologic\Core\Events\EventDispatcher;

class OAuthManager
{
    protected array $providers = [];
    protected HttpClient $http;
    protected EventDispatcher $events;

    public function __construct(HttpClient $http, EventDispatcher $events)
    {
        $this->http = $http;
        $this->events = $events;
        
        $this->registerDefaultProviders();
    }

    /**
     * Register default OAuth providers
     */
    protected function registerDefaultProviders(): void
    {
        // Default providers can be registered here
        // $this->registerProvider('github', new Providers\GithubProvider($this->http));
        // $this->registerProvider('google', new Providers\GoogleProvider($this->http));
        // $this->registerProvider('facebook', new Providers\FacebookProvider($this->http));
    }

    /**
     * Register an OAuth provider
     */
    public function registerProvider(string $name, OAuthProvider $provider): void
    {
        $this->providers[$name] = $provider;
    }

    /**
     * Get an OAuth provider
     */
    public function provider(string $name): OAuthProvider
    {
        if (!isset($this->providers[$name])) {
            throw new \InvalidArgumentException("OAuth provider [{$name}] is not registered.");
        }

        return $this->providers[$name];
    }

    /**
     * Redirect to OAuth provider
     */
    public function redirect(string $provider, array $scopes = []): string
    {
        return $this->provider($provider)->redirect($scopes);
    }

    /**
     * Handle OAuth callback
     */
    public function callback(string $provider, string $code): OAuthUser
    {
        $token = $this->provider($provider)->getAccessToken($code);
        return $this->provider($provider)->getUserByToken($token);
    }

    /**
     * Find or create user from OAuth user
     */
    public function findOrCreateUser(OAuthUser $oauthUser, string $provider): User
    {
        // Try to find by OAuth ID
        $user = User::where('oauth_provider', $provider)
                    ->where('oauth_id', $oauthUser->getId())
                    ->first();

        if ($user) {
            return $user;
        }

        // Try to find by email
        $user = User::where('email', $oauthUser->getEmail())->first();

        if ($user) {
            // Link OAuth account
            $user->oauth_provider = $provider;
            $user->oauth_id = $oauthUser->getId();
            $user->save();
            return $user;
        }

        // Create new user
        return $this->createUserFromOAuth($oauthUser, $provider);
    }

    /**
     * Create user from OAuth user
     */
    protected function createUserFromOAuth(OAuthUser $oauthUser, string $provider): User
    {
        $user = new User();
        $user->name = $oauthUser->getName();
        $user->email = $oauthUser->getEmail();
        $user->email_verified_at = new \DateTime();
        $user->oauth_provider = $provider;
        $user->oauth_id = $oauthUser->getId();
        $user->avatar = $oauthUser->getAvatar();
        $user->password = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
        $user->save();

        $this->events->dispatch(new Events\UserCreatedViaOAuth($user, $provider));

        return $user;
    }
}