<?php

declare(strict_types=1);

namespace Shopologic\Core\Middleware;

use Shopologic\PSR\Http\Message\RequestInterface;
use Shopologic\PSR\Http\Message\ResponseInterface;
use Shopologic\Core\Security\CsrfProtection;
use Shopologic\Core\Http\Response;
use Shopologic\Core\Http\Stream;

class CsrfMiddleware implements MiddlewareInterface
{
    private CsrfProtection $csrf;
    private array $except = [];

    public function __construct(CsrfProtection $csrf, array $except = [])
    {
        $this->csrf = $csrf;
        $this->except = $except;
    }

    public function process(RequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        // Only check state-changing methods
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $handler->handle($request);
        }

        // Check if path is in exception list
        if ($this->shouldSkip($path)) {
            return $handler->handle($request);
        }

        // Get request data
        $parsedBody = $request->getParsedBody();
        $data = is_array($parsedBody) ? $parsedBody : [];

        // Get headers
        $headers = [];
        if ($request->hasHeader('X-CSRF-Token')) {
            $headers['X-CSRF-Token'] = $request->getHeaderLine('X-CSRF-Token');
        }

        // Validate CSRF token
        if (!$this->csrf->validateRequest($data, $headers)) {
            return $this->forbiddenResponse();
        }

        return $handler->handle($request);
    }

    /**
     * Check if path should skip CSRF validation
     */
    private function shouldSkip(string $path): bool
    {
        foreach ($this->except as $pattern) {
            if ($this->matchesPattern($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if path matches pattern
     */
    private function matchesPattern(string $pattern, string $path): bool
    {
        // Exact match
        if ($pattern === $path) {
            return true;
        }

        // Wildcard match
        $pattern = str_replace(['*', '/'], ['.*', '\/'], $pattern);
        return (bool) preg_match('#^' . $pattern . '$#', $path);
    }

    /**
     * Return forbidden response
     */
    private function forbiddenResponse(): ResponseInterface
    {
        $body = new Stream('php://temp', 'w+');
        $body->write(json_encode([
            'error' => 'CSRF token validation failed',
            'message' => 'Invalid or missing CSRF token',
        ]));

        return new Response(403, ['Content-Type' => 'application/json'], $body);
    }

    /**
     * Add path to exception list
     */
    public function except(string $path): self
    {
        $this->except[] = $path;
        return $this;
    }
}
