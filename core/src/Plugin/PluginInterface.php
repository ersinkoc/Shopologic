<?php

declare(strict_types=1);

namespace Shopologic\Core\Plugin;

interface PluginInterface
{
    /**
     * Get plugin name
     */
    public function getName(): string;

    /**
     * Get plugin version
     */
    public function getVersion(): string;

    /**
     * Get plugin description
     */
    public function getDescription(): string;

    /**
     * Get plugin author
     */
    public function getAuthor(): string;

    /**
     * Get plugin dependencies
     * @return array<string, string> Plugin name => version constraint
     */
    public function getDependencies(): array;

    /**
     * Called when plugin is activated
     */
    public function activate(): void;

    /**
     * Called when plugin is deactivated
     */
    public function deactivate(): void;

    /**
     * Called when plugin is installed
     */
    public function install(): void;

    /**
     * Called when plugin is uninstalled
     */
    public function uninstall(): void;

    /**
     * Called when plugin is updated
     */
    public function update(string $previousVersion): void;

    /**
     * Boot the plugin
     */
    public function boot(): void;
}