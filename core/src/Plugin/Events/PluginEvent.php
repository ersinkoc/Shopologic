<?php

declare(strict_types=1);

namespace Shopologic\Core\Plugin\Events;

use Shopologic\Core\Events\Event;
use Shopologic\Core\Plugin\PluginInterface;

abstract class PluginEvent extends Event
{
    protected string $pluginName;
    protected PluginInterface $plugin;

    public function __construct(string $pluginName, PluginInterface $plugin)
    {
        $this->pluginName = $pluginName;
        $this->plugin = $plugin;
    }

    public function getPluginName(): string
    {
        return $this->pluginName;
    }

    public function getPlugin(): PluginInterface
    {
        return $this->plugin;
    }
}