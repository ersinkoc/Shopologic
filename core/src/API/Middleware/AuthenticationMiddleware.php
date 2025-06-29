<?php

declare(strict_types=1);

namespace Shopologic\Core\Api\Middleware;

use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;

class AuthenticationMiddleware extends ApiMiddleware
{
    protected array $except = [];

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
        // This is a mock implementation
        // In real implementation, this would:
        // 1. Decode JWT token
        // 2. Verify signature
        // 3. Check expiration
        // 4. Load user from database
        
        if ($token === 'valid_token') {
            return (object) [
                'id' => 1,
                'name' => 'Test User',
                'email' => 'test@example.com',
            ];
        }
        
        return null;
    }
}