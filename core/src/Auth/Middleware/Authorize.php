<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth\Middleware;

use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use Shopologic\Core\Http\JsonResponse;
use Shopologic\Core\Auth\AuthManager;
use Shopologic\Core\Middleware\MiddlewareInterface;

class Authorize implements MiddlewareInterface
{
    protected AuthManager $auth;
    protected string|array $abilities;

    public function __construct(AuthManager $auth, string|array $abilities)
    {
        $this->auth = $auth;
        $this->abilities = $abilities;
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = $this->auth->user();

        if (!$user) {
            return $this->unauthorized($request);
        }

        $abilities = is_array($this->abilities) ? $this->abilities : [$this->abilities];

        foreach ($abilities as $ability) {
            if (str_contains($ability, ':')) {
                // Check permission
                if (!$user->hasPermission($ability)) {
                    return $this->forbidden($request);
                }
            } else {
                // Check role
                if (!$user->hasRole($ability)) {
                    return $this->forbidden($request);
                }
            }
        }

        return $next($request);
    }

    /**
     * Handle unauthorized response
     */
    protected function unauthorized(Request $request): Response
    {
        if ($this->expectsJson($request)) {
            return new JsonResponse([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $stream = new \Shopologic\Core\Http\Stream('php://memory', 'rw');
        return new Response(302, ['Location' => '/login'], $stream);
    }

    /**
     * Handle forbidden response
     */
    protected function forbidden(Request $request): Response
    {
        if ($this->expectsJson($request)) {
            return new JsonResponse([
                'message' => 'This action is unauthorized.'
            ], 403);
        }

        $stream = new \Shopologic\Core\Http\Stream('php://memory', 'rw');
        $stream->write('Forbidden');
        $stream->rewind();
        return new Response(403, [], $stream);
    }

    /**
     * Determine if the request expects a JSON response
     */
    protected function expectsJson(Request $request): bool
    {
        $accept = $request->getHeaderLine('Accept');
        return str_contains($accept, '/json') || str_contains($accept, '+json');
    }
}