<?php

declare(strict_types=1);

namespace Shopologic\Core\Events;

use Shopologic\PSR\EventDispatcher\EventDispatcherInterface;

class EventManager
{
    private EventDispatcherInterface $dispatcher;
    private ListenerProvider $listenerProvider;

    public function __construct()
    {
        $this->listenerProvider = new ListenerProvider();
        $this->dispatcher = new EventDispatcher($this->listenerProvider);
    }

    public function dispatch(object $event): object
    {
        return $this->dispatcher->dispatch($event);
    }

    public function listen(string $eventType, callable $listener, int $priority = 0): void
    {
        $this->listenerProvider->addListener($eventType, $listener, $priority);
    }

    public function subscribe(EventSubscriberInterface $subscriber): void
    {
        foreach ($subscriber->getSubscribedEvents() as $eventType => $params) {
            if (is_string($params)) {
                $this->listen($eventType, [$subscriber, $params]);
            } elseif (is_array($params) && isset($params[0])) {
                $method = $params[0];
                $priority = $params[1] ?? 0;
                $this->listen($eventType, [$subscriber, $method], $priority);
            }
        }
    }

    public function removeListener(string $eventType, callable $listener): void
    {
        $this->listenerProvider->removeListener($eventType, $listener);
    }

    public function hasListeners(string $eventType): bool
    {
        return $this->listenerProvider->hasListeners($eventType);
    }
}