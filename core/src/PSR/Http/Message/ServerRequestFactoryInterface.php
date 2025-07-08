<?php

declare(strict_types=1);

namespace Shopologic\PSR\Http\Message;

/**
 * PSR-17: HTTP Factories - Server Request Factory
 * 
 * Factory for creating server requests.
 */
interface ServerRequestFactoryInterface
{
    /**
     * Create a new server request.
     *
     * @param string $method The HTTP method associated with the request.
     * @param UriInterface|string $uri The URI associated with the request. 
     * @param array $serverParams Array of SAPI parameters with which to seed
     *     the generated request instance.
     * @return ServerRequestInterface
     */
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface;
}