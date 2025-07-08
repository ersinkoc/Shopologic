<?php

declare(strict_types=1);

namespace Shopologic\PSR\Http\Server;

use Shopologic\PSR\Http\Message\ResponseInterface;
use Shopologic\PSR\Http\Message\ServerRequestInterface;

/**
 * PSR-15: HTTP Server Request Handlers
 * 
 * Handles a server request and produces a response.
 * 
 * An HTTP request handler process an HTTP request in order to produce an
 * HTTP response.
 */
interface RequestHandlerInterface
{
    /**
     * Handles a request and produces a response.
     *
     * May call other collaborating code to generate the response.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface;
}