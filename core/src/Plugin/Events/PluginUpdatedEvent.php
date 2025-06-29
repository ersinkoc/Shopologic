<?php

declare(strict_types=1);

namespace Shopologic\Core\Plugin\Events;

use Shopologic\Core\Plugin\PluginInterface;

class PluginUpdatedEvent extends PluginEvent
{
    protected string $previousVersion;

    public function __construct(string $pluginName, PluginInterface $plugin, string $previousVersion)
    {
        parent::__construct($pluginName, $plugin);
        $this->previousVersion = $previousVersion;
    }

    public function getPreviousVersion(): string
    {
        return $this->previousVersion;
    }
}