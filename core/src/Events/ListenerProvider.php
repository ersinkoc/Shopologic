<?php

declare(strict_types=1);

namespace Shopologic\Core\Events;

use Shopologic\PSR\EventDispatcher\ListenerProviderInterface;

class ListenerProvider implements ListenerProviderInterface
{
    private array $listeners = [];

    public function addListener(string $eventType, callable $listener, int $priority = 0): void
    {
        if (!isset($this->listeners[$eventType])) {
            $this->listeners[$eventType] = [];
        }

        if (!isset($this->listeners[$eventType][$priority])) {
            $this->listeners[$eventType][$priority] = [];
        }

        $this->listeners[$eventType][$priority][] = $listener;
    }

    public function getListenersForEvent(object $event): iterable
    {
        $eventType = get_class($event);
        $listeners = [];

        if (isset($this->listeners[$eventType])) {
            krsort($this->listeners[$eventType]);
            
            foreach ($this->listeners[$eventType] as $priorityListeners) {
                foreach ($priorityListeners as $listener) {
                    $listeners[] = $listener;
                }
            }
        }

        foreach (class_parents($event) as $parentClass) {
            if (isset($this->listeners[$parentClass])) {
                krsort($this->listeners[$parentClass]);
                
                foreach ($this->listeners[$parentClass] as $priorityListeners) {
                    foreach ($priorityListeners as $listener) {
                        $listeners[] = $listener;
                    }
                }
            }
        }

        foreach (class_implements($event) as $interface) {
            if (isset($this->listeners[$interface])) {
                krsort($this->listeners[$interface]);
                
                foreach ($this->listeners[$interface] as $priorityListeners) {
                    foreach ($priorityListeners as $listener) {
                        $listeners[] = $listener;
                    }
                }
            }
        }

        return $listeners;
    }

    public function removeListener(string $eventType, callable $listener): void
    {
        if (!isset($this->listeners[$eventType])) {
            return;
        }

        foreach ($this->listeners[$eventType] as $priority => $priorityListeners) {
            $key = array_search($listener, $priorityListeners, true);
            if ($key !== false) {
                unset($this->listeners[$eventType][$priority][$key]);
                
                if (empty($this->listeners[$eventType][$priority])) {
                    unset($this->listeners[$eventType][$priority]);
                }
                
                if (empty($this->listeners[$eventType])) {
                    unset($this->listeners[$eventType]);
                }
                break;
            }
        }
    }

    public function hasListeners(string $eventType): bool
    {
        return isset($this->listeners[$eventType]) && !empty($this->listeners[$eventType]);
    }
}