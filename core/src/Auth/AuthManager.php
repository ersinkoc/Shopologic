<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth;

use Shopologic\Core\Auth\Contracts\Authenticatable;
use Shopologic\Core\Auth\Models\User;
use Shopologic\Core\Auth\Jwt\JwtToken;
use Shopologic\Core\Session\SessionManager;
use Shopologic\Core\Events\EventDispatcher;
use Shopologic\Core\Auth\Events\Login;
use Shopologic\Core\Auth\Events\Logout;
use Shopologic\Core\Auth\Events\Failed;
use Shopologic\Core\Auth\Events\Validated;

class AuthManager
{
    protected ?Authenticatable $user = null;
    protected SessionManager $session;
    protected EventDispatcher $events;
    protected array $config;
    protected string $defaultGuard = 'web';
    protected array $guards = [];

    public function __construct(
        SessionManager $session,
        EventDispatcher $events,
        array $config = []
    ) {
        $this->session = $session;
        $this->events = $events;
        $this->config = $config;
        
        $this->registerDefaultGuards();
    }

    /**
     * Register default authentication guards
     */
    protected function registerDefaultGuards(): void
    {
        // Session-based authentication
        $this->guards['web'] = new Guards\SessionGuard($this->session, $this->events);
        
        // Token-based authentication
        $this->guards['api'] = new Guards\TokenGuard($this->events);
        
        // JWT authentication
        $jwtSecret = $this->config['jwt_secret'] ?? 'default-secret-change-me';
        $this->guards['jwt'] = new Guards\JwtGuard(new JwtToken($jwtSecret), $this->events);
    }

    /**
     * Get a guard instance
     */
    public function guard(?string $name = null): Guards\Guard
    {
        $name = $name ?: $this->defaultGuard;
        
        if (!isset($this->guards[$name])) {
            throw new \InvalidArgumentException("Auth guard [{$name}] is not defined.");
        }
        
        return $this->guards[$name];
    }

    /**
     * Attempt to authenticate a user
     */
    public function attempt(array $credentials, bool $remember = false): bool
    {
        return $this->guard()->attempt($credentials, $remember);
    }

    /**
     * Log a user into the application
     */
    public function login(Authenticatable $user, bool $remember = false): void
    {
        $this->guard()->login($user, $remember);
    }

    /**
     * Log the user out of the application
     */
    public function logout(): void
    {
        $this->guard()->logout();
    }

    /**
     * Get the currently authenticated user
     */
    public function user(): ?Authenticatable
    {
        return $this->guard()->user();
    }

    /**
     * Get the ID of the currently authenticated user
     */
    public function id(): mixed
    {
        return $this->user()?->getAuthIdentifier();
    }

    /**
     * Determine if the current user is authenticated
     */
    public function check(): bool
    {
        return $this->guard()->check();
    }

    /**
     * Determine if the current user is a guest
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Validate a user's credentials
     */
    public function validate(array $credentials): bool
    {
        return $this->guard()->validate($credentials);
    }

    /**
     * Set the current user
     */
    public function setUser(Authenticatable $user): void
    {
        $this->guard()->setUser($user);
    }

    /**
     * Register a custom guard
     */
    public function extend(string $name, Guards\Guard $guard): void
    {
        $this->guards[$name] = $guard;
    }

    /**
     * Set the default guard
     */
    public function setDefaultGuard(string $name): void
    {
        $this->defaultGuard = $name;
    }

    /**
     * Create a token for the user
     */
    public function createToken(Authenticatable $user, string $name, array $abilities = ['*']): array
    {
        if (method_exists($user, 'createToken')) {
            return $user->createToken($name, $abilities);
        }
        
        throw new \RuntimeException('User model does not support token creation');
    }

    /**
     * Hash a password
     */
    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verify a password against a hash
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Generate a random remember token
     */
    public function generateRememberToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Magic method to proxy calls to the default guard
     */
    public function __call(string $method, array $arguments)
    {
        return $this->guard()->$method(...$arguments);
    }
}