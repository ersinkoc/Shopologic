<?php

declare(strict_types=1);

namespace Shopologic\Core\Api\Middleware;

use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;

class CorsMiddleware extends ApiMiddleware
{
    protected array $options = [
        'allowed_origins' => ['*'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
        'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining'],
        'max_age' => 86400,
        'supports_credentials' => false,
    ];

    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->options, $options);
    }

    public function handle(Request $request, callable $next): Response
    {
        // Handle preflight requests
        if ($this->isPreflightRequest($request)) {
            return $this->handlePreflightRequest($request);
        }
        
        // Handle actual requests
        $response = $next($request);
        
        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Check if request is preflight
     */
    protected function isPreflightRequest(Request $request): bool
    {
        return $request->getMethod() === 'OPTIONS' &&
               $request->hasHeader('Access-Control-Request-Method');
    }

    /**
     * Handle preflight request
     */
    protected function handlePreflightRequest(Request $request): Response
    {
        $response = new Response(204);
        
        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Add CORS headers to response
     */
    protected function addCorsHeaders(Request $request, Response $response): Response
    {
        $origin = $request->getHeaderLine('Origin');
        
        // Check allowed origins
        if ($this->isAllowedOrigin($origin)) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
        } elseif (in_array('*', $this->options['allowed_origins'])) {
            $response = $response->withHeader('Access-Control-Allow-Origin', '*');
        }
        
        // Add other CORS headers
        if ($this->options['supports_credentials']) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }
        
        if (!empty($this->options['exposed_headers'])) {
            $response = $response->withHeader(
                'Access-Control-Expose-Headers',
                implode(', ', $this->options['exposed_headers'])
            );
        }
        
        // Add preflight headers
        if ($this->isPreflightRequest($request)) {
            $response = $response
                ->withHeader(
                    'Access-Control-Allow-Methods',
                    implode(', ', $this->options['allowed_methods'])
                )
                ->withHeader(
                    'Access-Control-Allow-Headers',
                    implode(', ', $this->options['allowed_headers'])
                )
                ->withHeader(
                    'Access-Control-Max-Age',
                    (string) $this->options['max_age']
                );
        }
        
        return $response;
    }

    /**
     * Check if origin is allowed
     */
    protected function isAllowedOrigin(string $origin): bool
    {
        if (empty($origin)) {
            return false;
        }
        
        if (in_array('*', $this->options['allowed_origins'])) {
            return true;
        }
        
        foreach ($this->options['allowed_origins'] as $allowed) {
            if ($allowed === $origin) {
                return true;
            }
            
            // Check wildcard subdomain
            if (str_contains($allowed, '*.')) {
                $pattern = str_replace('*.', '.*\.', $allowed);
                if (preg_match('#^' . $pattern . '$#', $origin)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Set allowed origins
     */
    public function setAllowedOrigins(array $origins): self
    {
        $this->options['allowed_origins'] = $origins;
        return $this;
    }

    /**
     * Set allowed methods
     */
    public function setAllowedMethods(array $methods): self
    {
        $this->options['allowed_methods'] = $methods;
        return $this;
    }

    /**
     * Set allowed headers
     */
    public function setAllowedHeaders(array $headers): self
    {
        $this->options['allowed_headers'] = $headers;
        return $this;
    }
}