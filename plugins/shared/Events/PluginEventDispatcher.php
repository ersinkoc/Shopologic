<?php

declare(strict_types=1);

namespace Shopologic\Plugins\Shared\Events;

/**
 * Advanced event dispatcher for plugin communication
 * Supports async processing, event queuing, and real-time notifications
 */
class PluginEventDispatcher
{
    private array $listeners = [];
    private array $eventQueue = [];
    private array $middleware = [];
    private bool $asyncProcessing = false;
    private ?string $queueDriver = null;
    
    /**
     * Add event listener
     */
    public function listen(string $eventName, callable $listener, int $priority = 0, array $conditions = []): void
    {
        if (!isset($this->listeners[$eventName])) {
            $this->listeners[$eventName] = [];
        }
        
        $this->listeners[$eventName][] = [
            'listener' => $listener,
            'priority' => $priority,
            'conditions' => $conditions,
            'id' => uniqid()
        ];
        
        // Sort by priority (higher priority first)
        usort($this->listeners[$eventName], function($a, $b) {
            return $b['priority'] - $a['priority'];
        });
    }
    
    /**
     * Remove event listener
     */
    public function unlisten(string $eventName, string $listenerId = null): void
    {
        if (!isset($this->listeners[$eventName])) {
            return;
        }
        
        if ($listenerId) {
            $this->listeners[$eventName] = array_filter(
                $this->listeners[$eventName],
                fn($listener) => $listener['id'] !== $listenerId
            );
        } else {
            unset($this->listeners[$eventName]);
        }
    }
    
    /**
     * Dispatch event
     */
    public function dispatch(string $eventName, array $data = [], bool $async = null): array
    {
        $event = new PluginEvent($eventName, $data);
        
        // Apply middleware
        foreach ($this->middleware as $middleware) {
            $event = $middleware->handle($event);
            if ($event->isPropagationStopped()) {
                return $event->getResults();
            }
        }
        
        $useAsync = $async ?? $this->asyncProcessing;
        
        if ($useAsync && $this->queueDriver) {
            return $this->dispatchAsync($event);
        } else {
            return $this->dispatchSync($event);
        }
    }
    
    /**
     * Dispatch event synchronously
     */
    private function dispatchSync(PluginEvent $event): array
    {
        $eventName = $event->getName();
        
        if (!isset($this->listeners[$eventName])) {
            return [];
        }
        
        foreach ($this->listeners[$eventName] as $listenerData) {
            if ($event->isPropagationStopped()) {
                break;
            }
            
            // Check conditions
            if (!$this->checkConditions($listenerData['conditions'], $event)) {
                continue;
            }
            
            try {
                $startTime = microtime(true);
                $result = call_user_func($listenerData['listener'], $event);
                $endTime = microtime(true);
                
                $event->addResult($listenerData['id'], [
                    'result' => $result,
                    'execution_time' => $endTime - $startTime,
                    'success' => true
                ]);
                
            } catch (\Exception $e) {
                $event->addResult($listenerData['id'], [
                    'error' => $e->getMessage(),
                    'success' => false
                ]);
                
                // Log error but continue with other listeners
                error_log("Event listener error ({$eventName}): " . $e->getMessage());
            }
        }
        
        return $event->getResults();
    }
    
    /**
     * Dispatch event asynchronously
     */
    private function dispatchAsync(PluginEvent $event): array
    {
        $jobId = uniqid('event_', true);
        
        $this->eventQueue[] = [
            'id' => $jobId,
            'event' => $event,
            'created_at' => time(),
            'status' => 'queued'
        ];
        
        // In a real implementation, this would use a proper queue system
        $this->processQueue();
        
        return ['job_id' => $jobId, 'status' => 'queued'];
    }
    
    /**
     * Process queued events
     */
    public function processQueue(): void
    {
        foreach ($this->eventQueue as &$job) {
            if ($job['status'] !== 'queued') {
                continue;
            }
            
            $job['status'] = 'processing';
            
            try {
                $results = $this->dispatchSync($job['event']);
                $job['results'] = $results;
                $job['status'] = 'completed';
                $job['completed_at'] = time();
            } catch (\Exception $e) {
                $job['error'] = $e->getMessage();
                $job['status'] = 'failed';
                $job['failed_at'] = time();
            }
        }
    }
    
    /**
     * Check listener conditions
     */
    private function checkConditions(array $conditions, PluginEvent $event): bool
    {
        if (empty($conditions)) {
            return true;
        }
        
        foreach ($conditions as $key => $expectedValue) {
            $actualValue = $event->getData($key);
            
            if (is_callable($expectedValue)) {
                if (!$expectedValue($actualValue)) {
                    return false;
                }
            } elseif ($actualValue !== $expectedValue) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Add middleware
     */
    public function addMiddleware(PluginEventMiddleware $middleware): void
    {
        $this->middleware[] = $middleware;
    }
    
    /**
     * Enable async processing
     */
    public function enableAsync(string $queueDriver = 'memory'): void
    {
        $this->asyncProcessing = true;
        $this->queueDriver = $queueDriver;
    }
    
    /**
     * Disable async processing
     */
    public function disableAsync(): void
    {
        $this->asyncProcessing = false;
        $this->queueDriver = null;
    }
    
    /**
     * Get event statistics
     */
    public function getStatistics(): array
    {
        $totalListeners = array_sum(array_map('count', $this->listeners));
        $queuedJobs = count(array_filter($this->eventQueue, fn($job) => $job['status'] === 'queued'));
        $completedJobs = count(array_filter($this->eventQueue, fn($job) => $job['status'] === 'completed'));
        $failedJobs = count(array_filter($this->eventQueue, fn($job) => $job['status'] === 'failed'));
        
        return [
            'registered_events' => count($this->listeners),
            'total_listeners' => $totalListeners,
            'async_enabled' => $this->asyncProcessing,
            'queue_driver' => $this->queueDriver,
            'queue_stats' => [
                'total_jobs' => count($this->eventQueue),
                'queued' => $queuedJobs,
                'completed' => $completedJobs,
                'failed' => $failedJobs
            ]
        ];
    }
    
    /**
     * Clear event queue
     */
    public function clearQueue(): void
    {
        $this->eventQueue = [];
    }
    
    /**
     * Get event queue status
     */
    public function getQueueStatus(): array
    {
        return array_map(function($job) {
            return [
                'id' => $job['id'],
                'event_name' => $job['event']->getName(),
                'status' => $job['status'],
                'created_at' => date('Y-m-d H:i:s', $job['created_at']),
                'completed_at' => isset($job['completed_at']) ? date('Y-m-d H:i:s', $job['completed_at']) : null,
                'error' => $job['error'] ?? null
            ];
        }, $this->eventQueue);
    }
    
    /**
     * Broadcast event to all plugins
     */
    public function broadcast(string $eventName, array $data = []): array
    {
        // Add broadcast metadata
        $data['_broadcast'] = true;
        $data['_timestamp'] = time();
        $data['_source'] = 'system';
        
        return $this->dispatch($eventName, $data);
    }
    
    /**
     * Schedule event for future dispatch
     */
    public function schedule(string $eventName, array $data, int $delaySeconds): string
    {
        $jobId = uniqid('scheduled_', true);
        
        $this->eventQueue[] = [
            'id' => $jobId,
            'event' => new PluginEvent($eventName, $data),
            'created_at' => time(),
            'scheduled_for' => time() + $delaySeconds,
            'status' => 'scheduled'
        ];
        
        return $jobId;
    }
    
    /**
     * Process scheduled events
     */
    public function processScheduledEvents(): void
    {
        $now = time();
        
        foreach ($this->eventQueue as &$job) {
            if ($job['status'] === 'scheduled' && isset($job['scheduled_for']) && $job['scheduled_for'] <= $now) {
                $job['status'] = 'queued';
                unset($job['scheduled_for']);
            }
        }
        
        $this->processQueue();
    }
    
    /**
     * Get singleton instance
     */
    private static ?self $instance = null;
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}