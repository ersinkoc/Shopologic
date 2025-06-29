<?php

declare(strict_types=1);

namespace Shopologic\Core\Api\Rest;

use Shopologic\Core\Router\Router as BaseRouter;
use Shopologic\Core\Router\Route;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;

class Router extends BaseRouter
{
    protected string $prefix = '/api';
    protected string $version = 'v1';
    protected array $middleware = [];
    protected array $resourceDefaults = [
        'index' => ['method' => 'GET', 'path' => ''],
        'store' => ['method' => 'POST', 'path' => ''],
        'show' => ['method' => 'GET', 'path' => '/{id}'],
        'update' => ['method' => 'PUT', 'path' => '/{id}'],
        'destroy' => ['method' => 'DELETE', 'path' => '/{id}'],
    ];

    /**
     * Set API prefix
     */
    public function prefix(string $prefix): self
    {
        $this->prefix = '/' . trim($prefix, '/');
        return $this;
    }

    /**
     * Set API version
     */
    public function version(string $version): self
    {
        $this->version = $version;
        return $this;
    }

    /**
     * Add middleware to all routes
     */
    public function middleware(string|array $middleware): self
    {
        if (is_string($middleware)) {
            $middleware = [$middleware];
        }
        
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    /**
     * Create a route group
     */
    public function group(array $attributes, callable $callback): void
    {
        $prefix = $attributes['prefix'] ?? '';
        $middleware = $attributes['middleware'] ?? [];
        $namespace = $attributes['namespace'] ?? '';
        
        // Save current state
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->middleware;
        
        // Apply group attributes
        if ($prefix) {
            $this->prefix .= '/' . trim($prefix, '/');
        }
        
        if ($middleware) {
            $this->middleware = array_merge($this->middleware, (array) $middleware);
        }
        
        // Execute callback
        $callback($this);
        
        // Restore state
        $this->prefix = $previousPrefix;
        $this->middleware = $previousMiddleware;
    }

    /**
     * Register a resource controller
     */
    public function resource(string $name, string $controller, array $options = []): void
    {
        $only = $options['only'] ?? array_keys($this->resourceDefaults);
        $except = $options['except'] ?? [];
        $parameters = $options['parameters'] ?? [];
        
        $actions = array_diff($only, $except);
        
        foreach ($actions as $action) {
            if (!isset($this->resourceDefaults[$action])) {
                continue;
            }
            
            $route = $this->resourceDefaults[$action];
            $method = $route['method'];
            $path = '/' . $name . $route['path'];
            
            // Replace parameter placeholders
            foreach ($parameters as $key => $value) {
                $path = str_replace('{' . $key . '}', '{' . $value . '}', $path);
            }
            
            $this->addApiRoute($method, $path, [$controller, $action]);
        }
    }

    /**
     * Register an API singleton resource
     */
    public function singleton(string $name, string $controller, array $options = []): void
    {
        $singletonDefaults = [
            'show' => ['method' => 'GET', 'path' => ''],
            'create' => ['method' => 'GET', 'path' => '/create'],
            'store' => ['method' => 'POST', 'path' => ''],
            'edit' => ['method' => 'GET', 'path' => '/edit'],
            'update' => ['method' => 'PUT', 'path' => ''],
            'destroy' => ['method' => 'DELETE', 'path' => ''],
        ];
        
        $only = $options['only'] ?? array_keys($singletonDefaults);
        $except = $options['except'] ?? [];
        
        $actions = array_diff($only, $except);
        
        foreach ($actions as $action) {
            if (!isset($singletonDefaults[$action])) {
                continue;
            }
            
            $route = $singletonDefaults[$action];
            $method = $route['method'];
            $path = '/' . $name . $route['path'];
            
            $this->addApiRoute($method, $path, [$controller, $action]);
        }
    }

    /**
     * Add an API route
     */
    protected function addApiRoute(string $method, string $path, $handler): Route
    {
        $fullPath = $this->prefix . '/' . $this->version . $path;
        $fullPath = '/' . trim($fullPath, '/');
        
        $route = new Route([$method], $fullPath, $handler);
        $this->addRoute($route);
        
        // Add middleware
        if (!empty($this->middleware)) {
            foreach ($this->middleware as $mw) {
                $route->middleware($mw);
            }
        }
        
        return $route;
    }

    /**
     * Register API routes with common methods
     */
    public function apiGet(string $path, $handler): Route
    {
        return $this->addApiRoute('GET', $path, $handler);
    }

    public function apiPost(string $path, $handler): Route
    {
        return $this->addApiRoute('POST', $path, $handler);
    }

    public function apiPut(string $path, $handler): Route
    {
        return $this->addApiRoute('PUT', $path, $handler);
    }

    public function apiPatch(string $path, $handler): Route
    {
        return $this->addApiRoute('PATCH', $path, $handler);
    }

    public function apiDelete(string $path, $handler): Route
    {
        return $this->addApiRoute('DELETE', $path, $handler);
    }

    public function apiOptions(string $path, $handler): Route
    {
        return $this->addApiRoute('OPTIONS', $path, $handler);
    }

    /**
     * Register routes for API documentation
     */
    public function documentation(string $path = '/docs'): void
    {
        $this->apiGet($path, function(Request $request) {
            $stream = new \Shopologic\Core\Http\Stream('php://memory', 'rw');
            $stream->write($this->getDocumentationHtml());
            $stream->rewind();
            return new Response(200, ['Content-Type' => 'text/html'], $stream);
        });
        
        $this->apiGet($path . '/openapi.json', function(Request $request) {
            return new JsonResponse($this->generateOpenApiSpec());
        });
    }

    /**
     * Generate OpenAPI specification
     */
    protected function generateOpenApiSpec(): array
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Shopologic API',
                'version' => $this->version,
                'description' => 'RESTful API for Shopologic e-commerce platform',
            ],
            'servers' => [
                ['url' => $this->prefix . '/' . $this->version],
            ],
            'paths' => [],
            'components' => [
                'schemas' => [],
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                ],
            ],
        ];
        
        // Generate paths from routes
        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $pattern => $route) {
                if (!str_starts_with($pattern, $this->prefix)) {
                    continue;
                }
                
                $path = str_replace($this->prefix . '/' . $this->version, '', $pattern);
                $path = $path ?: '/';
                
                if (!isset($spec['paths'][$path])) {
                    $spec['paths'][$path] = [];
                }
                
                $spec['paths'][$path][strtolower($method)] = [
                    'summary' => 'API endpoint',
                    'responses' => [
                        '200' => ['description' => 'Success'],
                        '400' => ['description' => 'Bad Request'],
                        '401' => ['description' => 'Unauthorized'],
                        '404' => ['description' => 'Not Found'],
                    ],
                ];
            }
        }
        
        return $spec;
    }

    /**
     * Get documentation HTML
     */
    protected function getDocumentationHtml(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Shopologic API Documentation</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@4/swagger-ui.css">
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@4/swagger-ui-bundle.js"></script>
    <script>
        window.ui = SwaggerUIBundle({
            url: './openapi.json',
            dom_id: '#swagger-ui',
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIBundle.SwaggerUIStandalonePreset
            ],
            layout: 'BaseLayout'
        });
    </script>
</body>
</html>
HTML;
    }
}