<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth\Guards;

use Shopologic\Core\Auth\Contracts\Authenticatable;
use Shopologic\Core\Auth\Models\User;
use Shopologic\Core\Session\SessionManager;
use Shopologic\Core\Events\EventDispatcher;
use Shopologic\Core\Auth\Events\Login;
use Shopologic\Core\Auth\Events\Logout;
use Shopologic\Core\Auth\Events\Failed;

class SessionGuard implements Guard
{
    protected ?Authenticatable $user = null;
    protected SessionManager $session;
    protected EventDispatcher $events;
    protected bool $loggedOut = false;

    public function __construct(SessionManager $session, EventDispatcher $events)
    {
        $this->session = $session;
        $this->events = $events;
    }

    public function check(): bool
    {
        return !is_null($this->user());
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function user(): ?Authenticatable
    {
        if ($this->loggedOut) {
            return null;
        }

        if (!is_null($this->user)) {
            return $this->user;
        }

        $id = $this->session->get('auth.id');

        if (!is_null($id)) {
            $this->user = $this->retrieveById($id);
        }

        return $this->user;
    }

    public function id(): mixed
    {
        if ($this->loggedOut) {
            return null;
        }

        return $this->user()
            ? $this->user()->getAuthIdentifier()
            : $this->session->get('auth.id');
    }

    public function validate(array $credentials): bool
    {
        $user = $this->retrieveByCredentials($credentials);

        if (!$user) {
            return false;
        }

        return $this->validateCredentials($user, $credentials);
    }

    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
        $this->loggedOut = false;
    }

    public function attempt(array $credentials, bool $remember = false): bool
    {
        $this->events->dispatch(new Events\Attempting($credentials, $remember));

        $user = $this->retrieveByCredentials($credentials);

        if ($this->hasValidCredentials($user, $credentials)) {
            $this->login($user, $remember);
            return true;
        }

        $this->events->dispatch(new Failed($credentials));

        return false;
    }

    public function login(Authenticatable $user, bool $remember = false): void
    {
        $this->updateSession($user->getAuthIdentifier());

        if ($remember) {
            $this->createRememberToken($user);
            $this->queueRememberCookie($user);
        }

        $this->events->dispatch(new Login($user, $remember));

        $this->setUser($user);
    }

    public function logout(): void
    {
        $user = $this->user();

        $this->clearUserDataFromSession();

        if (!is_null($user) && !is_null($user->getRememberToken())) {
            $this->updateRememberToken($user);
        }

        if (!is_null($user)) {
            $this->events->dispatch(new Logout($user));
        }

        $this->user = null;
        $this->loggedOut = true;
    }

    /**
     * Update the session with the given ID
     */
    protected function updateSession(mixed $id): void
    {
        $this->session->put('auth.id', $id);
        $this->session->migrate(true);
    }

    /**
     * Remove user data from the session
     */
    protected function clearUserDataFromSession(): void
    {
        $this->session->remove('auth.id');
    }

    /**
     * Retrieve a user by their unique identifier
     */
    protected function retrieveById(mixed $identifier): ?Authenticatable
    {
        return User::find($identifier);
    }

    /**
     * Retrieve a user by the given credentials
     */
    protected function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (empty($credentials) || 
            (count($credentials) === 1 && array_key_exists('password', $credentials))) {
            return null;
        }

        $query = User::query();

        foreach ($credentials as $key => $value) {
            if ($key !== 'password') {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }

    /**
     * Validate a user against the given credentials
     */
    protected function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $password = $credentials['password'] ?? '';
        return password_verify($password, $user->getAuthPassword());
    }

    /**
     * Determine if the user matches the credentials
     */
    protected function hasValidCredentials(?Authenticatable $user, array $credentials): bool
    {
        return !is_null($user) && $this->validateCredentials($user, $credentials);
    }

    /**
     * Create a new remember token for the user
     */
    protected function createRememberToken(Authenticatable $user): void
    {
        $token = bin2hex(random_bytes(32));
        $user->setRememberToken($token);
        
        if (method_exists($user, 'save')) {
            $user->save();
        }
    }

    /**
     * Queue the remember cookie
     */
    protected function queueRememberCookie(Authenticatable $user): void
    {
        $value = $user->getAuthIdentifier() . '|' . $user->getRememberToken();
        $this->session->put('auth.remember', $value);
    }

    /**
     * Update the remember token
     */
    protected function updateRememberToken(Authenticatable $user): void
    {
        $user->setRememberToken('');
        
        if (method_exists($user, 'save')) {
            $user->save();
        }
    }
}