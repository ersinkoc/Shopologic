<?php

declare(strict_types=1);

namespace Shopologic\PSR\EventDispatcher;

interface EventDispatcherInterface
{
    public function dispatch(object $event): object;
}