<?php

declare(strict_types=1);

namespace Shopologic\Core\Api\Middleware;

use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use Shopologic\Core\Cache\CacheManager;

class RateLimitMiddleware extends ApiMiddleware
{
    protected CacheManager $cache;
    protected int $maxAttempts = 60;
    protected int $decayMinutes = 1;
    protected array $limits = [];

    public function __construct(CacheManager $cache)
    {
        $this->cache = $cache;
    }

    public function handle(Request $request, callable $next): Response
    {
        $key = $this->resolveRequestKey($request);
        $maxAttempts = $this->getMaxAttempts($request);
        
        if ($this->tooManyAttempts($key, $maxAttempts)) {
            return $this->buildResponse($key, $maxAttempts);
        }
        
        $this->hit($key);
        
        $response = $next($request);
        
        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts)
        );
    }

    /**
     * Determine if too many attempts have been made
     */
    protected function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        if ($this->attempts($key) >= $maxAttempts) {
            if ($this->cache->has($key . ':timer')) {
                return true;
            }
            
            $this->resetAttempts($key);
        }
        
        return false;
    }

    /**
     * Increment the attempts
     */
    protected function hit(string $key): void
    {
        $this->cache->set(
            $key . ':timer',
            time() + ($this->decayMinutes * 60),
            $this->decayMinutes * 60
        );
        
        $hits = $this->attempts($key) + 1;
        
        $this->cache->set($key, $hits, $this->decayMinutes * 60);
    }

    /**
     * Get the number of attempts
     */
    protected function attempts(string $key): int
    {
        return (int) $this->cache->get($key, 0);
    }

    /**
     * Reset attempts
     */
    protected function resetAttempts(string $key): void
    {
        $this->cache->delete($key);
        $this->cache->delete($key . ':timer');
    }

    /**
     * Get remaining attempts
     */
    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->attempts($key));
    }

    /**
     * Get the rate limit key
     */
    protected function resolveRequestKey(Request $request): string
    {
        $user = $request->getAttribute('user');
        
        if ($user) {
            return 'rate_limit:user:' . $user->id;
        }
        
        // Fall back to IP address
        $ip = $request->getServerParam('REMOTE_ADDR', 'unknown');
        
        return 'rate_limit:ip:' . $ip;
    }

    /**
     * Get max attempts for request
     */
    protected function getMaxAttempts(Request $request): int
    {
        $path = $request->getUri()->getPath();
        
        foreach ($this->limits as $pattern => $limit) {
            if ($this->matches($pattern, $path)) {
                return $limit;
            }
        }
        
        return $this->maxAttempts;
    }

    /**
     * Build rate limit response
     */
    protected function buildResponse(string $key, int $maxAttempts): Response
    {
        $retryAfter = $this->getRetryAfter($key);
        
        return $this->errorResponse(
            'Too many requests. Please retry after ' . $retryAfter . ' seconds.',
            429
        )->withHeader('Retry-After', (string) $retryAfter);
    }

    /**
     * Get retry after seconds
     */
    protected function getRetryAfter(string $key): int
    {
        $expiry = $this->cache->get($key . ':timer');
        
        return max(0, $expiry - time());
    }

    /**
     * Add rate limit headers
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts): Response
    {
        return $response
            ->withHeader('X-RateLimit-Limit', (string) $maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string) $remainingAttempts);
    }

    /**
     * Set rate limit for specific routes
     */
    public function setLimit(string $pattern, int $maxAttempts): self
    {
        $this->limits[$pattern] = $maxAttempts;
        return $this;
    }

    /**
     * Check if pattern matches path
     */
    protected function matches(string $pattern, string $path): bool
    {
        if ($pattern === $path) {
            return true;
        }
        
        $pattern = str_replace('*', '.*', $pattern);
        
        return (bool) preg_match('#^' . $pattern . '$#', $path);
    }
}