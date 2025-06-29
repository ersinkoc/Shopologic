<?php

declare(strict_types=1);

namespace Shopologic\Core\Middleware;

use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;

interface MiddlewareInterface
{
    /**
     * Handle an incoming request
     */
    public function handle(Request $request, callable $next): Response;
}