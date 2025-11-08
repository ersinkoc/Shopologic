<?php

declare(strict_types=1);

namespace Shopologic\Core\Api\Middleware;

use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use Shopologic\Core\Auth\Jwt\JwtToken;

class AuthenticationMiddleware extends ApiMiddleware
{
    protected array $except = [];
    protected ?JwtToken $jwtToken = null;

    public function __construct(?JwtToken $jwtToken = null)
    {
        $this->jwtToken = $jwtToken;
    }

    public function handle(Request $request, callable $next): Response
    {
        // Check if route is excluded
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        // Get authorization header
        $authorization = $request->getHeaderLine('Authorization');
        
        if (empty($authorization)) {
            return $this->errorResponse('Missing authorization header', 401);
        }

        // Check bearer token
        if (!str_starts_with($authorization, 'Bearer ')) {
            return $this->errorResponse('Invalid authorization format', 401);
        }

        $token = substr($authorization, 7);
        
        // Validate token (this would integrate with JWT or session system)
        $user = $this->validateToken($token);
        
        if (!$user) {
            return $this->errorResponse('Invalid or expired token', 401);
        }

        // Add user to request
        $request = $request->withAttribute('user', $user);
        $request = $request->withAttribute('token', $token);

        return $next($request);
    }

    /**
     * Check if request should skip authentication
     */
    protected function shouldSkip(Request $request): bool
    {
        $path = $request->getUri()->getPath();
        
        foreach ($this->except as $pattern) {
            if ($this->matches($pattern, $path)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if pattern matches path
     */
    protected function matches(string $pattern, string $path): bool
    {
        if ($pattern === $path) {
            return true;
        }
        
        // Convert wildcard to regex
        $pattern = str_replace('*', '.*', $pattern);
        
        return (bool) preg_match('#^' . $pattern . '$#', $path);
    }

    /**
     * Validate token and return user
     */
    protected function validateToken(string $token): ?object
    {
        // If no JWT token validator is available, fail closed (deny access)
        if (!$this->jwtToken) {
            return null;
        }

        try {
            // Decode and verify JWT token
            $payload = $this->jwtToken->decode($token);

            // Check if token has expired
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return null;
            }

            // Load user data from payload
            // In production, you should load the full user from database
            if (isset($payload['user_id']) || isset($payload['sub'])) {
                $userId = $payload['user_id'] ?? $payload['sub'];

                // TODO: Load user from database
                // For now, return the payload data
                return (object) [
                    'id' => $userId,
                    'email' => $payload['email'] ?? null,
                    'name' => $payload['name'] ?? null,
                ];
            }

            return null;
        } catch (\Exception $e) {
            // Invalid token format or signature
            error_log("JWT validation failed: " . $e->getMessage());
            return null;
        }
    }
}