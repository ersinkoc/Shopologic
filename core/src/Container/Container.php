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

    public function get(string $id): mixed
    {
        $id = $this->getAlias($id);

        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $concrete = $this->getConcrete($id);
        $object = $this->build($concrete);

        if ($this->isShared($id)) {
            $this->instances[$id] = $object;
        }

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

        if ($type === null) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }
            
            throw new ContainerException("Cannot resolve parameter \${$parameter->getName()}");
        }

        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            $className = $type->getName();
            
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

        throw new ContainerException("Cannot resolve parameter \${$parameter->getName()}");
    }
}