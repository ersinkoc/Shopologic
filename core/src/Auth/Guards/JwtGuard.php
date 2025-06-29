<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth\Guards;

use Shopologic\Core\Auth\Contracts\Authenticatable;
use Shopologic\Core\Auth\Models\User;
use Shopologic\Core\Auth\Jwt\JwtToken;
use Shopologic\Core\Events\EventDispatcher;
use Shopologic\Core\Http\Request;

class JwtGuard implements Guard
{
    protected ?Authenticatable $user = null;
    protected JwtToken $jwt;
    protected EventDispatcher $events;
    protected ?Request $request = null;
    protected ?array $payload = null;

    public function __construct(JwtToken $jwt, EventDispatcher $events)
    {
        $this->jwt = $jwt;
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
            $payload = $this->jwt->parse($token);
            
            if ($payload && isset($payload['sub'])) {
                $this->payload = $payload;
                $this->user = User::find($payload['sub']);
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

        $payload = $this->jwt->parse($credentials['token']);
        
        return $payload !== null;
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
        // JWT tokens are stateless, so we just clear the user
        $this->user = null;
        $this->payload = null;
    }

    /**
     * Generate a JWT token for the user
     */
    public function generateToken(Authenticatable $user, array $claims = []): string
    {
        $token = $this->jwt
            ->subject($user->getAuthIdentifier())
            ->issuedAt(time())
            ->expiresAt(time() + 3600) // 1 hour
            ->claim('email', $user->email ?? '')
            ->claim('name', $user->name ?? '');

        foreach ($claims as $key => $value) {
            $token->claim($key, $value);
        }

        return $token->generate();
    }

    /**
     * Get the JWT payload
     */
    public function payload(): ?array
    {
        if ($this->payload) {
            return $this->payload;
        }

        $token = $this->getTokenFromRequest();
        
        if ($token) {
            $this->payload = $this->jwt->parse($token);
        }

        return $this->payload;
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
        if (isset($params['token'])) {
            return $params['token'];
        }

        // Check request body
        $body = $this->request->getParsedBody();
        if (is_array($body) && isset($body['token'])) {
            return $body['token'];
        }

        return null;
    }
}