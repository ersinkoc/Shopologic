<?php

declare(strict_types=1);

/**
 * Events Unit Tests
 */

use Shopologic\Core\Events\EventDispatcher;
use Shopologic\Core\Events\Event;

TestFramework::describe('Event Dispatcher', function() {
    TestFramework::it('should create event dispatcher instance', function() {
        $dispatcher = new EventDispatcher();
        TestFramework::expect($dispatcher)->toBeInstanceOf(EventDispatcher::class);
    });
    
    TestFramework::it('should register and trigger listeners', function() {
        $dispatcher = new EventDispatcher();
        $called = false;
        
        $dispatcher->listen('test.event', function($event) use (&$called) {
            $called = true;
        });
        
        $event = new class extends Event {
            public function getName(): string {
                return 'test.event';
            }
        };
        
        $dispatcher->dispatch($event);
        TestFramework::expect($called)->toBeTrue();
    });
    
    TestFramework::it('should handle multiple listeners', function() {
        $dispatcher = new EventDispatcher();
        $callCount = 0;
        
        $dispatcher->listen('multi.event', function($event) use (&$callCount) {
            $callCount++;
        });
        
        $dispatcher->listen('multi.event', function($event) use (&$callCount) {
            $callCount++;
        });
        
        $event = new class extends Event {
            public function getName(): string {
                return 'multi.event';
            }
        };
        
        $dispatcher->dispatch($event);
        TestFramework::expect($callCount)->toBe(2);
    });
    
    TestFramework::it('should handle listener priorities', function() {
        $dispatcher = new EventDispatcher();
        $order = [];
        
        $dispatcher->listen('priority.event', function($event) use (&$order) {
            $order[] = 'low';
        }, 1);
        
        $dispatcher->listen('priority.event', function($event) use (&$order) {
            $order[] = 'high';
        }, 10);
        
        $dispatcher->listen('priority.event', function($event) use (&$order) {
            $order[] = 'medium';
        }, 5);
        
        $event = new class extends Event {
            public function getName(): string {
                return 'priority.event';
            }
        };
        
        $dispatcher->dispatch($event);
        TestFramework::expect($order)->toEqual(['high', 'medium', 'low']);
    });
    
    TestFramework::it('should handle event propagation stopping', function() {
        $dispatcher = new EventDispatcher();
        $callCount = 0;
        
        $dispatcher->listen('stoppable.event', function($event) use (&$callCount) {
            $callCount++;
            $event->stopPropagation();
        }, 10);
        
        $dispatcher->listen('stoppable.event', function($event) use (&$callCount) {
            $callCount++;
        }, 5);
        
        $event = new class extends Event {
            private $stopped = false;
            
            public function getName(): string {
                return 'stoppable.event';
            }
            
            public function stopPropagation(): void {
                $this->stopped = true;
            }
            
            public function isPropagationStopped(): bool {
                return $this->stopped;
            }
        };
        
        $dispatcher->dispatch($event);
        TestFramework::expect($callCount)->toBe(1);
    });
    
    TestFramework::it('should handle wildcard listeners', function() {
        $dispatcher = new EventDispatcher();
        $events = [];
        
        $dispatcher->listen('user.*', function($event) use (&$events) {
            $events[] = $event->getName();
        });
        
        $event1 = new class extends Event {
            public function getName(): string {
                return 'user.created';
            }
        };
        
        $event2 = new class extends Event {
            public function getName(): string {
                return 'user.updated';
            }
        };
        
        $dispatcher->dispatch($event1);
        $dispatcher->dispatch($event2);
        
        TestFramework::expect(count($events))->toBe(2);
        TestFramework::expect($events)->toEqual(['user.created', 'user.updated']);
    });
    
    TestFramework::it('should pass event data to listeners', function() {
        $dispatcher = new EventDispatcher();
        $receivedData = null;
        
        $dispatcher->listen('data.event', function($event, $data) use (&$receivedData) {
            $receivedData = $data;
        });
        
        $event = new class extends Event {
            public function getName(): string {
                return 'data.event';
            }
        };
        
        $testData = ['key' => 'value', 'number' => 42];
        $dispatcher->dispatch($event, $testData);
        
        TestFramework::expect($receivedData)->toEqual($testData);
    });
});

TestFramework::describe('Event Base Class', function() {
    TestFramework::it('should create event instance', function() {
        $event = new class extends Event {
            public function getName(): string {
                return 'test.event';
            }
        };
        
        TestFramework::expect($event)->toBeInstanceOf(Event::class);
        TestFramework::expect($event->getName())->toBe('test.event');
    });
    
    TestFramework::it('should handle event timing', function() {
        $event = new class extends Event {
            public function getName(): string {
                return 'timed.event';
            }
        };
        
        $startTime = $event->getTimestamp();
        usleep(1000); // 1ms delay
        $currentTime = microtime(true);
        
        TestFramework::expect($startTime)->toBeLessThan($currentTime);
    });
});