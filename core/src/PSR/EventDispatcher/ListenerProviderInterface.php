<?php

declare(strict_types=1);

namespace Shopologic\PSR\EventDispatcher;

interface ListenerProviderInterface
{
    public function getListenersForEvent(object $event): iterable;
}