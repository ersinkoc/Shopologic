<?php

declare(strict_types=1);

namespace Shopologic\Core\Events;

use Shopologic\PSR\EventDispatcher\EventDispatcherInterface;
use Shopologic\PSR\EventDispatcher\ListenerProviderInterface;
use Shopologic\PSR\EventDispatcher\StoppableEventInterface;

class EventDispatcher implements EventDispatcherInterface
{
    private ListenerProviderInterface $listenerProvider;

    public function __construct(ListenerProviderInterface $listenerProvider)
    {
        $this->listenerProvider = $listenerProvider;
    }

    public function dispatch(object $event): object
    {
        $listeners = $this->listenerProvider->getListenersForEvent($event);

        foreach ($listeners as $listener) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }

            $listener($event);
        }

        return $event;
    }
}