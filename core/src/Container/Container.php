<?php

declare(strict_types=1);

namespace Shopologic\Core\Container;

use Shopologic\PSR\Container\ContainerInterface;
use Shopologic\PSR\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionParameter;
use ReflectionException;

class Container implements ContainerInterface
{
    private array $bindings = [];
    private array $instances = [];
    private array $singletons = [];
    private array $tags = [];
    private array $aliases = [];
    private array $building = [];
    private array $decorators = [];
    private array $methodInjections = [];
    private array $contextual = [];
    private array $factories = [];
    private array $resolved = [];
    private array $afterResolving = [];
    private array $globalParameters = [];

    public function bind(string $abstract, mixed $concrete = null, bool $shared = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared
        ];
    }

    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
        $this->singletons[$abstract] = true;
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    public function tag(array $abstracts, string $tag): void
    {
        foreach ($abstracts as $abstract) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }
            $this->tags[$tag][] = $abstract;
        }
    }

    public function tagged(string $tag): array
    {
        $services = [];
        if (isset($this->tags[$tag])) {
            foreach ($this->tags[$tag] as $abstract) {
                $services[] = $this->get($abstract);
            }
        }
        return $services;
    }

    public function decorate(string $abstract, callable $decorator): void
    {
        if (!isset($this->decorators[$abstract])) {
            $this->decorators[$abstract] = [];
        }
        $this->decorators[$abstract][] = $decorator;
    }

    public function factory(string $abstract, callable $factory): void
    {
        $this->factories[$abstract] = $factory;
    }

    public function methodInjection(string $abstract, string $method, array $parameters = []): void
    {
        if (!isset($this->methodInjections[$abstract])) {
            $this->methodInjections[$abstract] = [];
        }
        $this->methodInjections[$abstract][$method] = $parameters;
    }

    public function when(string $concrete): ContextualBuilder
    {
        return new ContextualBuilder($this, $concrete);
    }

    public function addContextualBinding(string $concrete, string $abstract, mixed $implementation): void
    {
        $this->contextual[$concrete][$abstract] = $implementation;
    }

    public function afterResolving(string $abstract, callable $callback): void
    {
        if (!isset($this->afterResolving[$abstract])) {
            $this->afterResolving[$abstract] = [];
        }
        $this->afterResolving[$abstract][] = $callback;
    }

    public function addGlobalParameter(string $key, mixed $value): void
    {
        $this->globalParameters[$key] = $value;
    }

    public function get(string $id): mixed
    {
        $id = $this->getAlias($id);

        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->factories[$id])) {
            $object = $this->factories[$id]($this);
        } else {
            $concrete = $this->getConcrete($id);
            $object = $this->build($concrete);
        }

        // Apply decorators
        if (isset($this->decorators[$id])) {
            foreach ($this->decorators[$id] as $decorator) {
                $object = $decorator($object, $this);
            }
        }

        // Apply method injections
        if (isset($this->methodInjections[$id])) {
            $this->applyMethodInjections($object, $this->methodInjections[$id]);
        }

        // Fire after resolving callbacks
        if (isset($this->afterResolving[$id])) {
            foreach ($this->afterResolving[$id] as $callback) {
                $callback($object, $this);
            }
        }

        if ($this->isShared($id)) {
            $this->instances[$id] = $object;
        }

        $this->resolved[$id] = true;

        return $object;
    }

    public function has(string $id): bool
    {
        $id = $this->getAlias($id);
        
        return isset($this->bindings[$id]) || 
               isset($this->instances[$id]) || 
               class_exists($id) || 
               interface_exists($id);
    }

    private function getAlias(string $abstract): string
    {
        return $this->aliases[$abstract] ?? $abstract;
    }

    private function getConcrete(string $abstract): mixed
    {
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }

    private function isShared(string $abstract): bool
    {
        return isset($this->singletons[$abstract]) ||
               (isset($this->bindings[$abstract]) && $this->bindings[$abstract]['shared']);
    }

    private function build(mixed $concrete): mixed
    {
        if ($concrete instanceof \Closure) {
            return $concrete($this);
        }

        if (is_string($concrete)) {
            return $this->buildClass($concrete);
        }

        return $concrete;
    }

    private function buildClass(string $concrete): object
    {
        if (isset($this->building[$concrete])) {
            throw new CircularDependencyException("Circular dependency detected for {$concrete}");
        }

        $this->building[$concrete] = true;

        try {
            $reflectionClass = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            // Clear the building flag before throwing exception
            unset($this->building[$concrete]);
            throw new NotFoundException("Class {$concrete} not found");
        }

        if (!$reflectionClass->isInstantiable()) {
            throw new ContainerException("Class {$concrete} is not instantiable");
        }

        $constructor = $reflectionClass->getConstructor();

        if ($constructor === null) {
            unset($this->building[$concrete]);
            return new $concrete;
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters());
        
        unset($this->building[$concrete]);
        
        return $reflectionClass->newInstanceArgs($dependencies);
    }

    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependency = $this->resolveDependency($parameter);
            $dependencies[] = $dependency;
        }

        return $dependencies;
    }

    private function resolveDependency(ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();
        $parameterName = $parameter->getName();

        // Check for global parameter
        if (isset($this->globalParameters[$parameterName])) {
            return $this->globalParameters[$parameterName];
        }

        if ($type === null) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }
            
            throw new ContainerException("Cannot resolve parameter \${$parameterName}");
        }

        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            $className = $type->getName();
            
            // Check for contextual binding
            $contextualConcrete = $this->getContextualConcrete($className);
            if ($contextualConcrete !== null) {
                return $this->build($contextualConcrete);
            }
            
            try {
                return $this->get($className);
            } catch (NotFoundException $e) {
                if ($parameter->isDefaultValueAvailable()) {
                    return $parameter->getDefaultValue();
                }
                
                if ($type->allowsNull()) {
                    return null;
                }
                
                throw $e;
            }
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new ContainerException("Cannot resolve parameter \${$parameterName}");
    }

    private function applyMethodInjections(object $object, array $methods): void
    {
        $reflection = new ReflectionClass($object);
        
        foreach ($methods as $method => $parameters) {
            if (!$reflection->hasMethod($method)) {
                throw new ContainerException("Method {$method} does not exist on " . get_class($object));
            }
            
            $methodReflection = $reflection->getMethod($method);
            $methodParameters = $methodReflection->getParameters();
            $resolvedParameters = [];
            
            foreach ($methodParameters as $param) {
                if (isset($parameters[$param->getName()])) {
                    $resolvedParameters[] = $parameters[$param->getName()];
                } else {
                    $resolvedParameters[] = $this->resolveDependency($param);
                }
            }
            
            $methodReflection->invoke($object, ...$resolvedParameters);
        }
    }

    private function getContextualConcrete(string $abstract): ?string
    {
        if (empty($this->building)) {
            return null;
        }

        // Get the last key (class name) from the building array
        $concrete = array_key_last($this->building);

        if ($concrete && isset($this->contextual[$concrete][$abstract])) {
            return $this->contextual[$concrete][$abstract];
        }

        return null;
    }

    public function isResolved(string $abstract): bool
    {
        $abstract = $this->getAlias($abstract);
        return isset($this->resolved[$abstract]) || isset($this->instances[$abstract]);
    }

    public function flush(): void
    {
        $this->instances = [];
        $this->resolved = [];
    }
}