<?php

declare(strict_types=1);

namespace Shopologic\PSR\EventDispatcher;

interface StoppableEventInterface
{
    public function isPropagationStopped(): bool;
}