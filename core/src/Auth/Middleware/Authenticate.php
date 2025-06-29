<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth\Middleware;

use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use Shopologic\Core\Http\JsonResponse;
use Shopologic\Core\Auth\AuthManager;
use Shopologic\Core\Middleware\MiddlewareInterface;

class Authenticate implements MiddlewareInterface
{
    protected AuthManager $auth;
    protected array $guards;

    public function __construct(AuthManager $auth, array $guards = [])
    {
        $this->auth = $auth;
        $this->guards = $guards;
    }

    public function handle(Request $request, callable $next): Response
    {
        $this->authenticate($request, $this->guards);
        
        return $next($request);
    }

    /**
     * Determine if the user is logged in to any of the given guards
     */
    protected function authenticate(Request $request, array $guards): void
    {
        if (empty($guards)) {
            $guards = [null];
        }

        foreach ($guards as $guard) {
            if ($this->auth->guard($guard)->check()) {
                $this->auth->setDefaultGuard($guard);
                return;
            }
        }

        $this->unauthenticated($request, $guards);
    }

    /**
     * Handle an unauthenticated user
     */
    protected function unauthenticated(Request $request, array $guards): void
    {
        if ($this->expectsJson($request)) {
            $response = new JsonResponse([
                'message' => 'Unauthenticated.'
            ], 401);
        } else {
            // Redirect to login page
            $stream = new \Shopologic\Core\Http\Stream('php://memory', 'rw');
            $response = new Response(302, ['Location' => '/login'], $stream);
        }

        throw new \Shopologic\Core\Auth\Exceptions\AuthenticationException(
            'Unauthenticated.',
            $guards,
            $response
        );
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