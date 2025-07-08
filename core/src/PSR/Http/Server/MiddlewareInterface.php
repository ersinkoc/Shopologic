<?php

declare(strict_types=1);

namespace Shopologic\PSR\Http\Server;

use Shopologic\PSR\Http\Message\ResponseInterface;
use Shopologic\PSR\Http\Message\ServerRequestInterface;

/**
 * PSR-15: HTTP Server Request Handlers - Middleware
 * 
 * Participant in processing a server request and response.
 *
 * An HTTP middleware component participates in processing an HTTP message:
 * by acting on the request, generating the response, or forwarding the
 * request to a subsequent middleware and possibly acting on its response.
 */
interface MiddlewareInterface
{
    /**
     * Process an incoming server request.
     *
     * Processes an incoming server request in order to produce a response.
     * If unable to produce the response itself, it may delegate to the provided
     * request handler to do so.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface;
}