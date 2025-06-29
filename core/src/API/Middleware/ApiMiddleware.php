<?php

declare(strict_types=1);

namespace Shopologic\Core\Api\Middleware;

use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use Shopologic\Core\Http\JsonResponse;

abstract class ApiMiddleware
{
    /**
     * Handle the request
     */
    abstract public function handle(Request $request, callable $next): Response;

    /**
     * Create an error response
     */
    protected function errorResponse(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $data = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $data['errors'] = $errors;
        }

        return new JsonResponse($data, $status);
    }
}