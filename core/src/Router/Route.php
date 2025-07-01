<?php

declare(strict_types=1);

namespace Shopologic\Core\Router;

use Shopologic\PSR\Http\Message\RequestInterface;
use Shopologic\PSR\Http\Message\ResponseInterface;
use Shopologic\Core\Container\Container;

class Route
{
    private array $methods;
    private string $path;
    private $handler;
    private array $parameters = [];
    private array $middleware = [];
    private ?string $name = null;
    private array $where = [];
    private ?string $domain = null;
    private ?string $prefix = null;
    private ?string $namespace = null;

    public function __construct(array $methods, string $path, $handler)
    {
        $this->methods = array_map('strtoupper', $methods);
        $this->path = $path;
        $this->handler = $handler;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHandler()
    {
        return $this->handler;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;
        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function middleware($middleware): self
    {
        $this->middleware = array_merge($this->middleware, is_array($middleware) ? $middleware : [$middleware]);
        return $this;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function where($name, ?string $pattern = null): self
    {
        if (is_array($name)) {
            $this->where = array_merge($this->where, $name);
        } else {
            $this->where[$name] = $pattern;
        }
        return $this;
    }

    public function getWhere(): array
    {
        return $this->where;
    }

    public function domain(string $domain): self
    {
        $this->domain = $domain;
        return $this;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function prefix(string $prefix): self
    {
        $this->prefix = $prefix;
        return $this;
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    public function namespace(string $namespace): self
    {
        $this->namespace = $namespace;
        return $this;
    }

    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    public function matches(RequestInterface $request): bool
    {
        if (!in_array($request->getMethod(), $this->methods)) {
            return false;
        }

        if ($this->domain && $this->domain !== $request->getUri()->getHost()) {
            return false;
        }

        $pattern = $this->compilePattern();
        $path = $request->getUri()->getPath();

        if (preg_match($pattern, $path, $matches)) {
            $parameters = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $parameters[$key] = $value;
                }
            }
            $this->setParameters($parameters);
            return true;
        }

        return false;
    }

    public function run(RequestInterface $request): ResponseInterface
    {
        $handler = $this->resolveHandler();
        
        if (is_callable($handler)) {
            $result = $handler($request, $this->parameters);
            
            
            return $result;
        }

        throw new \RuntimeException('Route handler is not callable');
    }

    private function compilePattern(): string
    {
        $pattern = $this->path;
        
        // Replace route parameters with regex patterns
        $pattern = preg_replace_callback('/\{([^}]+)\}/', function($matches) {
            $param = $matches[1];
            $optional = str_ends_with($param, '?');
            
            if ($optional) {
                $param = substr($param, 0, -1);
            }
            
            $regex = $this->where[$param] ?? '[^/]+';
            
            if ($optional) {
                return "(?P<{$param}>{$regex})?";
            }
            
            return "(?P<{$param}>{$regex})";
        }, $pattern);
        
        // Escape special regex characters - but do this BEFORE processing parameters
        // Only escape forward slashes, not other regex chars that might be in custom patterns
        $pattern = str_replace('/', '\/', $pattern);
        
        return '/^' . $pattern . '$/';
    }

    private function resolveHandler(): callable
    {
        if (is_callable($this->handler)) {
            return $this->handler;
        }

        if (is_string($this->handler)) {
            if (str_contains($this->handler, '@')) {
                [$class, $method] = explode('@', $this->handler, 2);
                
                if ($this->namespace) {
                    $class = $this->namespace . '\\' . $class;
                }
                
                return function(RequestInterface $request, array $parameters) use ($class, $method) {
                    $controller = new $class();
                    return $controller->$method($request, $parameters);
                };
            }
        }

        throw new \RuntimeException('Unable to resolve route handler');
    }
}