<?php

declare(strict_types=1);

namespace Shopologic\Core\Api\Rest;

use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use Shopologic\Core\Http\JsonResponse;
use Shopologic\Core\Api\Validation\Validator;
use Shopologic\Core\Api\Validation\ValidationException;

abstract class Controller
{
    protected Request $request;
    protected array $middleware = [];

    /**
     * Set the current request
     */
    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * Get middleware for this controller
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Create a JSON response
     */
    protected function json(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }

    /**
     * Create a success response
     */
    protected function success(mixed $data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        return $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Create an error response
     */
    protected function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return $this->json($response, $status);
    }

    /**
     * Create a not found response
     */
    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, 404);
    }

    /**
     * Create an unauthorized response
     */
    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, 401);
    }

    /**
     * Create a forbidden response
     */
    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, 403);
    }

    /**
     * Create a validation error response
     */
    protected function validationError(array $errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->error($message, 422, $errors);
    }

    /**
     * Get request input
     */
    protected function input(string $key = null, mixed $default = null): mixed
    {
        $data = array_merge(
            $this->request->getQueryParams(),
            $this->request->getParsedBody() ?: []
        );

        if ($key === null) {
            return $data;
        }

        return $data[$key] ?? $default;
    }

    /**
     * Validate request input
     */
    protected function validate(array $rules): array
    {
        $validator = new Validator($this->input(), $rules);

        if (!$validator->passes()) {
            throw new ValidationException($validator->errors());
        }

        return $validator->validated();
    }

    /**
     * Get authenticated user
     */
    protected function user(): ?object
    {
        return $this->request->getAttribute('user');
    }

    /**
     * Check if user is authenticated
     */
    protected function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Authorize an action
     */
    protected function authorize(string $ability, mixed $resource = null): bool
    {
        $user = $this->user();
        
        if (!$user) {
            return false;
        }

        // This would integrate with an authorization system
        return true;
    }

    /**
     * Paginate results
     */
    protected function paginate(mixed $query, int $perPage = 15): array
    {
        $page = (int) $this->input('page', 1);
        $perPage = (int) $this->input('per_page', $perPage);

        // This would integrate with the query builder
        return [
            'data' => [],
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => 0,
                'last_page' => 1,
            ],
            'links' => [
                'first' => null,
                'last' => null,
                'prev' => null,
                'next' => null,
            ],
        ];
    }
}