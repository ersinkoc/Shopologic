<?php

declare(strict_types=1);

namespace Shopologic\Core\Events;

use Shopologic\PSR\EventDispatcher\StoppableEventInterface;

abstract class Event implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}