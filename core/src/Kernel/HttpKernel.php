<?php

declare(strict_types=1);

namespace Shopologic\Core\Kernel;

use Shopologic\PSR\Http\Message\RequestInterface;
use Shopologic\PSR\Http\Message\ResponseInterface;
use Shopologic\Core\Router\RouterInterface;
use Shopologic\Core\Http\Response;
use Shopologic\Core\Http\Stream;
use Shopologic\Core\Events\EventManager;
use Shopologic\Core\Kernel\Events;

class HttpKernel implements HttpKernelInterface
{
    private RouterInterface $router;
    private EventManager $eventManager;
    private array $middleware = [];
    private \Shopologic\PSR\Container\ContainerInterface $container;

    public function __construct(RouterInterface $router, EventManager $eventManager)
    {
        $this->router = $router;
        $this->eventManager = $eventManager;
        // Get container from the global app
        $this->container = app()->getContainer();
    }

    public function handle(RequestInterface $request): ResponseInterface
    {
        try {
            // Store request in container for access in controllers
            $this->container->instance('request', $request);
            
            $this->eventManager->dispatch(new Events\RequestReceived($request));

            $response = $this->sendRequestThroughRouter($request);

            $this->eventManager->dispatch(new Events\ResponsePrepared($request, $response));

            return $response;
        } catch (\Throwable $e) {
            $this->eventManager->dispatch(new Events\ExceptionOccurred($e, $request));
            
            return $this->handleException($e, $request);
        }
    }

    public function terminate(RequestInterface $request, ResponseInterface $response): void
    {
        $this->eventManager->dispatch(new Events\RequestTerminated($request, $response));
    }

    public function addMiddleware(string $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    private function sendRequestThroughRouter(RequestInterface $request): ResponseInterface
    {
        $route = $this->router->findRoute($request);

        if ($route === null) {
            return new Response(404, [], new Stream('php://memory', 'w+'));
        }

        return $this->runMiddleware($request, function($request) use ($route) {
            return $route->run($request);
        });
    }

    private function runMiddleware(RequestInterface $request, callable $destination): ResponseInterface
    {
        $pipeline = array_reverse($this->middleware);

        return array_reduce($pipeline, function($next, $middleware) {
            return function($request) use ($next, $middleware) {
                return (new $middleware)->handle($request, $next);
            };
        }, $destination)($request);
    }

    private function handleException(\Throwable $e, RequestInterface $request): ResponseInterface
    {
        $statusCode = $e instanceof HttpException ? $e->getStatusCode() : 500;
        $message = $e->getMessage() ?: 'Internal Server Error';

        $body = new Stream('php://memory', 'w+');
        
        // Add more debugging info in development
        $data = [
            'error' => $message,
            'code' => $statusCode
        ];
        
        if (getenv('APP_ENV') === 'development') {
            $data['file'] = $e->getFile();
            $data['line'] = $e->getLine();
            $data['trace'] = array_slice($e->getTrace(), 0, 5);
        }
        
        $body->write(json_encode($data));

        return new Response($statusCode, ['Content-Type' => 'application/json'], $body);
    }
}