<?php

declare(strict_types=1);

namespace Shopologic\Core\Events;

interface EventSubscriberInterface
{
    public static function getSubscribedEvents(): array;
}