<?php

declare(strict_types=1);

namespace Shopologic\Core\Plugin;

abstract class AbstractPlugin implements PluginInterface
{
    protected string $name;
    protected string $version = '1.0.0';
    protected string $description = '';
    protected string $author = '';
    protected array $dependencies = [];

    public function getName(): string
    {
        if (!isset($this->name)) {
            $this->name = $this->generateNameFromClass();
        }
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function activate(): void
    {
        // Override in child class if needed
    }

    public function deactivate(): void
    {
        // Override in child class if needed
    }

    public function install(): void
    {
        // Override in child class if needed
    }

    public function uninstall(): void
    {
        // Override in child class if needed
    }

    public function update(string $previousVersion): void
    {
        // Override in child class if needed
    }

    public function boot(): void
    {
        // Override in child class if needed
    }

    protected function generateNameFromClass(): string
    {
        $className = get_class($this);
        $parts = explode('\\', $className);
        $name = end($parts);
        
        // Remove 'Plugin' suffix if present
        if (str_ends_with($name, 'Plugin')) {
            $name = substr($name, 0, -6);
        }
        
        return $name;
    }
}