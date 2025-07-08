<?php

declare(strict_types=1);

namespace Shopologic\Core\Container;

/**
 * Contextual binding builder for the Container
 * 
 * Allows defining context-specific bindings
 */
class ContextualBuilder
{
    private Container $container;
    private string $concrete;

    public function __construct(Container $container, string $concrete)
    {
        $this->container = $container;
        $this->concrete = $concrete;
    }

    /**
     * Define the implementation for the given abstract when in this context
     *
     * @param string $abstract
     * @return ContextualBindingBuilder
     */
    public function needs(string $abstract): ContextualBindingBuilder
    {
        return new ContextualBindingBuilder($this->container, $this->concrete, $abstract);
    }
}

/**
 * Contextual binding builder for specific abstract-implementation pairs
 */
class ContextualBindingBuilder
{
    private Container $container;
    private string $concrete;
    private string $abstract;

    public function __construct(Container $container, string $concrete, string $abstract)
    {
        $this->container = $container;
        $this->concrete = $concrete;
        $this->abstract = $abstract;
    }

    /**
     * Define the implementation for the contextual binding
     *
     * @param mixed $implementation
     * @return void
     */
    public function give(mixed $implementation): void
    {
        $this->container->addContextualBinding($this->concrete, $this->abstract, $implementation);
    }
}