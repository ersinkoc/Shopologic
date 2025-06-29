<?php

declare(strict_types=1);

namespace Shopologic\Core\Router;

use Shopologic\PSR\Http\Message\RequestInterface;

interface RouterInterface
{
    public function get(string $path, $handler): Route;
    public function post(string $path, $handler): Route;
    public function put(string $path, $handler): Route;
    public function patch(string $path, $handler): Route;
    public function delete(string $path, $handler): Route;
    public function options(string $path, $handler): Route;
    public function head(string $path, $handler): Route;
    public function any(string $path, $handler): Route;
    public function match(array $methods, string $path, $handler): Route;
    public function group(array $attributes, callable $callback): void;
    public function addRoute(Route $route): Route;
    public function findRoute(RequestInterface $request): ?Route;
}