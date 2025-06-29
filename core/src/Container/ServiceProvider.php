<?php

declare(strict_types=1);

namespace Shopologic\Core\Container;

abstract class ServiceProvider
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    abstract public function register(): void;

    public function boot(): void
    {
    }

    protected function bind(string $abstract, mixed $concrete = null): void
    {
        $this->container->bind($abstract, $concrete);
    }

    protected function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->container->singleton($abstract, $concrete);
    }

    protected function instance(string $abstract, mixed $instance): void
    {
        $this->container->instance($abstract, $instance);
    }

    protected function alias(string $abstract, string $alias): void
    {
        $this->container->alias($abstract, $alias);
    }

    protected function tag(array $abstracts, string $tag): void
    {
        $this->container->tag($abstracts, $tag);
    }
}