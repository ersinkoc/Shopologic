<?php

declare(strict_types=1);

namespace Shopologic\Core\Router;

use Shopologic\PSR\Http\Message\RequestInterface;

class Router implements RouterInterface
{
    private array $routes = [];
    private array $groupStack = [];
    private RouteCompiler $compiler;

    public function __construct()
    {
        $this->compiler = new RouteCompiler();
    }

    public function get(string $path, $handler): Route
    {
        return $this->addRoute(new Route(['GET'], $path, $handler));
    }

    public function post(string $path, $handler): Route
    {
        return $this->addRoute(new Route(['POST'], $path, $handler));
    }

    public function put(string $path, $handler): Route
    {
        return $this->addRoute(new Route(['PUT'], $path, $handler));
    }

    public function patch(string $path, $handler): Route
    {
        return $this->addRoute(new Route(['PATCH'], $path, $handler));
    }

    public function delete(string $path, $handler): Route
    {
        return $this->addRoute(new Route(['DELETE'], $path, $handler));
    }

    public function options(string $path, $handler): Route
    {
        return $this->addRoute(new Route(['OPTIONS'], $path, $handler));
    }

    public function head(string $path, $handler): Route
    {
        return $this->addRoute(new Route(['HEAD'], $path, $handler));
    }

    public function any(string $path, $handler): Route
    {
        return $this->addRoute(new Route(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'], $path, $handler));
    }

    public function match(array $methods, string $path, $handler): Route
    {
        return $this->addRoute(new Route($methods, $path, $handler));
    }

    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        
        $callback($this);
        
        array_pop($this->groupStack);
    }

    public function addRoute(Route $route): Route
    {
        $this->applyGroupAttributes($route);
        $this->routes[] = $route;
        return $route;
    }

    public function findRoute(RequestInterface $request): ?Route
    {
        foreach ($this->routes as $route) {
            if ($route->matches($request)) {
                return $route;
            }
        }

        return null;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function url(string $name, array $parameters = []): string
    {
        foreach ($this->routes as $route) {
            if ($route->getName() === $name) {
                return $this->generateUrl($route, $parameters);
            }
        }

        throw new RouteNotFoundException("Route '{$name}' not found");
    }

    private function applyGroupAttributes(Route $route): void
    {
        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $currentPath = $route->getPath();
                $prefix = trim($group['prefix'], '/');
                $newPath = '/' . $prefix . '/' . ltrim($currentPath, '/');
                $newPath = rtrim($newPath, '/') ?: '/';
                
                // Update the route path using reflection since there's no setter
                $reflection = new \ReflectionClass($route);
                $pathProperty = $reflection->getProperty('path');
                $pathProperty->setAccessible(true);
                $pathProperty->setValue($route, $newPath);
            }

            if (isset($group['middleware'])) {
                $route->middleware($group['middleware']);
            }

            if (isset($group['namespace'])) {
                $route->namespace($group['namespace']);
            }

            if (isset($group['domain'])) {
                $route->domain($group['domain']);
            }

            if (isset($group['where'])) {
                $route->where($group['where']);
            }
        }
    }

    private function generateUrl(Route $route, array $parameters = []): string
    {
        $path = $route->getPath();
        
        foreach ($parameters as $key => $value) {
            $path = str_replace('{' . $key . '}', $value, $path);
            $path = str_replace('{' . $key . '?}', $value, $path);
        }
        
        // Remove unused optional parameters
        $path = preg_replace('/\{[^}]*\?\}/', '', $path);
        
        return $path;
    }
}