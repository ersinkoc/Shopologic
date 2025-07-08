<?php

declare(strict_types=1);

namespace Shopologic\PSR\Http\Message;

/**
 * PSR-17: HTTP Factories - Request Factory
 * 
 * Factory for creating client requests.
 */
interface RequestFactoryInterface
{
    /**
     * Create a new request.
     *
     * @param string $method The HTTP method associated with the request.
     * @param UriInterface|string $uri The URI associated with the request. 
     * @return RequestInterface
     */
    public function createRequest(string $method, $uri): RequestInterface;
}