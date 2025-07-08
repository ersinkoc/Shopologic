<?php

declare(strict_types=1);

namespace Shopologic\PSR\Http\Message;

/**
 * PSR-17: HTTP Factories - URI Factory
 * 
 * Factory for creating URIs.
 */
interface UriFactoryInterface
{
    /**
     * Create a new URI.
     *
     * @param string $uri The URI to parse.
     * @return UriInterface
     * @throws \InvalidArgumentException If the given URI cannot be parsed.
     */
    public function createUri(string $uri = ''): UriInterface;
}