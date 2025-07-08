<?php

declare(strict_types=1);

namespace Shopologic\Core\Events;

use Shopologic\PSR\EventDispatcher\EventDispatcherInterface;
use Shopologic\PSR\Log\LoggerInterface;

/**
 * Event Manager with Performance Monitoring
 * 
 * Extends the base EventManager with performance tracking,
 * debugging capabilities, and event metrics collection
 */
class PerformanceAwareEventManager extends EventManager
{
    private array $metrics = [];
    private array $eventHistory = [];
    private bool $performanceTracking = true;
    private bool $debugMode = false;
    private ?LoggerInterface $logger = null;
    private float $slowEventThreshold = 0.1; // 100ms
    private int $maxHistorySize = 1000;
    
    public function __construct(?LoggerInterface $logger = null)
    {
        parent::__construct();
        $this->logger = $logger;
        $debug = $_ENV['APP_DEBUG'] ?? 'false';
        $this->debugMode = filter_var($debug, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * Dispatch an event with performance tracking
     */
    public function dispatch(object $event): object
    {
        $eventName = get_class($event);
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        // Log debug info
        if ($this->debugMode && $this->logger) {
            $this->logger->debug("Dispatching event: {$eventName}", [
                'event' => $event,
                'listeners_count' => $this->countListeners($eventName)
            ]);
        }
        
        try {
            // Dispatch the event
            $result = parent::dispatch($event);
            
            // Track performance metrics
            if ($this->performanceTracking) {
                $this->trackEventPerformance($eventName, $startTime, $startMemory);
            }
            
            return $result;
            
        } catch (\Throwable $e) {
            // Log error
            if ($this->logger) {
                $this->logger->error("Event dispatch failed: {$eventName}", [
                    'event' => $event,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            throw $e;
        }
    }
    
    /**
     * Listen to an event with performance tracking for the listener
     */
    public function listen(string $eventType, callable $listener, int $priority = 0): void
    {
        if ($this->performanceTracking) {
            // Wrap the listener with performance tracking
            $wrappedListener = $this->wrapListenerWithTracking($eventType, $listener);
            parent::listen($eventType, $wrappedListener, $priority);
        } else {
            parent::listen($eventType, $listener, $priority);
        }
    }
    
    /**
     * Wrap a listener with performance tracking
     */
    private function wrapListenerWithTracking(string $eventType, callable $listener): callable
    {
        return function ($event) use ($eventType, $listener) {
            $startTime = microtime(true);
            $startMemory = memory_get_usage();
            
            try {
                $result = $listener($event);
                
                $duration = microtime(true) - $startTime;
                $memoryUsed = memory_get_usage() - $startMemory;
                
                // Track listener performance
                $this->trackListenerPerformance($eventType, $listener, $duration, $memoryUsed);
                
                // Log slow listeners
                if ($duration > $this->slowEventThreshold && $this->logger) {
                    $this->logger->warning("Slow event listener detected", [
                        'event' => $eventType,
                        'duration' => $duration,
                        'listener' => $this->getListenerName($listener)
                    ]);
                }
                
                return $result;
                
            } catch (\Throwable $e) {
                if ($this->logger) {
                    $this->logger->error("Event listener failed", [
                        'event' => $eventType,
                        'listener' => $this->getListenerName($listener),
                        'exception' => $e->getMessage()
                    ]);
                }
                throw $e;
            }
        };
    }
    
    /**
     * Track event performance metrics
     */
    private function trackEventPerformance(string $eventName, float $startTime, int $startMemory): void
    {
        $duration = microtime(true) - $startTime;
        $memoryUsed = memory_get_usage() - $startMemory;
        
        if (!isset($this->metrics[$eventName])) {
            $this->metrics[$eventName] = [
                'count' => 0,
                'total_time' => 0,
                'avg_time' => 0,
                'max_time' => 0,
                'min_time' => PHP_FLOAT_MAX,
                'total_memory' => 0,
                'avg_memory' => 0,
                'listeners' => []
            ];
        }
        
        $metrics = &$this->metrics[$eventName];
        $metrics['count'] = ($metrics['count'] ?? 0) + 1;
        $metrics['total_time'] = ($metrics['total_time'] ?? 0) + $duration;
        $metrics['avg_time'] = $metrics['total_time'] / $metrics['count'];
        $metrics['max_time'] = max($metrics['max_time'] ?? 0, $duration);
        $metrics['min_time'] = min($metrics['min_time'] ?? PHP_FLOAT_MAX, $duration);
        $metrics['total_memory'] = ($metrics['total_memory'] ?? 0) + $memoryUsed;
        $metrics['avg_memory'] = $metrics['total_memory'] / $metrics['count'];
        
        // Add to history
        $this->addToHistory($eventName, $duration, $memoryUsed);
    }
    
    /**
     * Track individual listener performance
     */
    private function trackListenerPerformance(string $eventType, callable $listener, float $duration, int $memory): void
    {
        $listenerName = $this->getListenerName($listener);
        
        if (!isset($this->metrics[$eventType]['listeners'][$listenerName])) {
            $this->metrics[$eventType]['listeners'][$listenerName] = [
                'count' => 0,
                'total_time' => 0,
                'avg_time' => 0,
                'max_time' => 0,
                'total_memory' => 0
            ];
        }
        
        $listenerMetrics = &$this->metrics[$eventType]['listeners'][$listenerName];
        $listenerMetrics['count']++;
        $listenerMetrics['total_time'] += $duration;
        $listenerMetrics['avg_time'] = $listenerMetrics['total_time'] / $listenerMetrics['count'];
        $listenerMetrics['max_time'] = max($listenerMetrics['max_time'], $duration);
        $listenerMetrics['total_memory'] += $memory;
    }
    
    /**
     * Add event to history
     */
    private function addToHistory(string $eventName, float $duration, int $memory): void
    {
        $this->eventHistory[] = [
            'event' => $eventName,
            'timestamp' => microtime(true),
            'duration' => $duration,
            'memory' => $memory
        ];
        
        // Limit history size
        if (count($this->eventHistory) > $this->maxHistorySize) {
            array_shift($this->eventHistory);
        }
    }
    
    /**
     * Get a readable name for a listener
     */
    private function getListenerName(callable $listener): string
    {
        if (is_string($listener)) {
            return $listener;
        }
        
        if (is_array($listener)) {
            if (is_object($listener[0])) {
                return get_class($listener[0]) . '::' . $listener[1];
            }
            return implode('::', $listener);
        }
        
        if (is_object($listener)) {
            if ($listener instanceof \Closure) {
                $reflection = new \ReflectionFunction($listener);
                return 'Closure@' . $reflection->getFileName() . ':' . $reflection->getStartLine();
            }
            return get_class($listener);
        }
        
        return 'Unknown';
    }
    
    /**
     * Count listeners for an event
     */
    private function countListeners(string $eventType): int
    {
        $count = 0;
        foreach ($this->listenerProvider->getListenersForEvent(new class($eventType) {
            private string $type;
            public function __construct(string $type) { $this->type = $type; }
        }) as $listener) {
            $count++;
        }
        return $count;
    }
    
    /**
     * Get performance metrics
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }
    
    /**
     * Get metrics for a specific event
     */
    public function getEventMetrics(string $eventName): ?array
    {
        return $this->metrics[$eventName] ?? null;
    }
    
    /**
     * Get event history
     */
    public function getEventHistory(): array
    {
        return $this->eventHistory;
    }
    
    /**
     * Reset metrics
     */
    public function resetMetrics(): void
    {
        $this->metrics = [];
        $this->eventHistory = [];
    }
    
    /**
     * Enable/disable performance tracking
     */
    public function setPerformanceTracking(bool $enabled): void
    {
        $this->performanceTracking = $enabled;
    }
    
    /**
     * Set slow event threshold (in seconds)
     */
    public function setSlowEventThreshold(float $threshold): void
    {
        $this->slowEventThreshold = $threshold;
    }
    
    /**
     * Get a report of slow events
     */
    public function getSlowEventsReport(): array
    {
        $slowEvents = [];
        
        foreach ($this->metrics as $eventName => $metrics) {
            if ($metrics['avg_time'] > $this->slowEventThreshold) {
                $slowEvents[$eventName] = [
                    'avg_time' => $metrics['avg_time'],
                    'max_time' => $metrics['max_time'],
                    'count' => $metrics['count'],
                    'slow_listeners' => []
                ];
                
                // Find slow listeners
                foreach ($metrics['listeners'] as $listenerName => $listenerMetrics) {
                    if ($listenerMetrics['avg_time'] > $this->slowEventThreshold) {
                        $slowEvents[$eventName]['slow_listeners'][$listenerName] = $listenerMetrics;
                    }
                }
            }
        }
        
        return $slowEvents;
    }
    
    /**
     * Export metrics for monitoring systems
     */
    public function exportMetrics(): array
    {
        $export = [
            'timestamp' => time(),
            'total_events' => count($this->metrics),
            'total_dispatches' => array_sum(array_column($this->metrics, 'count')),
            'events' => []
        ];
        
        foreach ($this->metrics as $eventName => $metrics) {
            $export['events'][$eventName] = [
                'count' => $metrics['count'],
                'avg_time_ms' => $metrics['avg_time'] * 1000,
                'max_time_ms' => $metrics['max_time'] * 1000,
                'min_time_ms' => $metrics['min_time'] * 1000,
                'avg_memory_kb' => $metrics['avg_memory'] / 1024,
                'listener_count' => count($metrics['listeners'])
            ];
        }
        
        return $export;
    }
}