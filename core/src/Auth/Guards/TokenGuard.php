<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth\Guards;

use Shopologic\Core\Auth\Contracts\Authenticatable;
use Shopologic\Core\Auth\Models\User;
use Shopologic\Core\Auth\Models\PersonalAccessToken;
use Shopologic\Core\Events\EventDispatcher;
use Shopologic\Core\Http\Request;

class TokenGuard implements Guard
{
    protected ?Authenticatable $user = null;
    protected EventDispatcher $events;
    protected ?Request $request = null;
    protected ?PersonalAccessToken $currentToken = null;

    public function __construct(EventDispatcher $events)
    {
        $this->events = $events;
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
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
        if (!is_null($this->user)) {
            return $this->user;
        }

        $token = $this->getTokenFromRequest();

        if ($token) {
            $accessToken = PersonalAccessToken::findToken($token);
            
            if ($accessToken && !$accessToken->isExpired()) {
                $this->currentToken = $accessToken;
                $accessToken->touch();
                
                $this->user = $accessToken->user;
            }
        }

        return $this->user;
    }

    public function id(): mixed
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function validate(array $credentials): bool
    {
        if (empty($credentials['token'])) {
            return false;
        }

        $accessToken = PersonalAccessToken::findToken($credentials['token']);
        
        return $accessToken && !$accessToken->isExpired();
    }

    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
    }

    public function attempt(array $credentials, bool $remember = false): bool
    {
        if (empty($credentials['email']) || empty($credentials['password'])) {
            return false;
        }

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !password_verify($credentials['password'], $user->getAuthPassword())) {
            return false;
        }

        $this->login($user, $remember);
        return true;
    }

    public function login(Authenticatable $user, bool $remember = false): void
    {
        $this->setUser($user);
    }

    public function logout(): void
    {
        if ($this->currentToken) {
            $this->currentToken->delete();
        }

        $this->user = null;
        $this->currentToken = null;
    }

    /**
     * Get the current access token
     */
    public function currentAccessToken(): ?PersonalAccessToken
    {
        return $this->currentToken;
    }

    /**
     * Get token from request
     */
    protected function getTokenFromRequest(): ?string
    {
        if (!$this->request) {
            return null;
        }

        // Check Authorization header
        $header = $this->request->getHeaderLine('Authorization');
        if (preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
            return $matches[1];
        }

        // Check query parameter
        $params = $this->request->getQueryParams();
        if (isset($params['api_token'])) {
            return $params['api_token'];
        }

        // Check request body
        $body = $this->request->getParsedBody();
        if (is_array($body) && isset($body['api_token'])) {
            return $body['api_token'];
        }

        return null;
    }
}