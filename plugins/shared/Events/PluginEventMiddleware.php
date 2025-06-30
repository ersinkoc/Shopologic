<?php

declare(strict_types=1);

namespace Shopologic\Plugins\Shared\Events;

/**
 * Abstract middleware for event processing
 * Allows modification of events before they reach listeners
 */
abstract class PluginEventMiddleware
{
    /**
     * Handle the event
     */
    abstract public function handle(PluginEvent $event): PluginEvent;
    
    /**
     * Determine if middleware should process this event
     */
    public function shouldHandle(PluginEvent $event): bool
    {
        return true;
    }
}

/**
 * Logging middleware - logs all events
 */
class LoggingMiddleware extends PluginEventMiddleware
{
    private string $logLevel;
    private array $excludeEvents;
    
    public function __construct(string $logLevel = 'info', array $excludeEvents = [])
    {
        $this->logLevel = $logLevel;
        $this->excludeEvents = $excludeEvents;
    }
    
    public function handle(PluginEvent $event): PluginEvent
    {
        if (!$this->shouldHandle($event)) {
            return $event;
        }
        
        $logData = $event->formatForLogging();
        $message = "Plugin Event: {$event->getName()}";
        
        error_log(json_encode(['level' => $this->logLevel, 'message' => $message, 'data' => $logData]));
        
        return $event;
    }
    
    public function shouldHandle(PluginEvent $event): bool
    {
        return !in_array($event->getName(), $this->excludeEvents);
    }
}

/**
 * Rate limiting middleware - prevents event spam
 */
class RateLimitingMiddleware extends PluginEventMiddleware
{
    private array $eventCounts = [];
    private int $maxEventsPerMinute;
    private int $windowSize;
    
    public function __construct(int $maxEventsPerMinute = 60, int $windowSize = 60)
    {
        $this->maxEventsPerMinute = $maxEventsPerMinute;
        $this->windowSize = $windowSize;
    }
    
    public function handle(PluginEvent $event): PluginEvent
    {
        $eventName = $event->getName();
        $now = time();
        $windowStart = $now - $this->windowSize;
        
        // Clean old entries
        if (isset($this->eventCounts[$eventName])) {
            $this->eventCounts[$eventName] = array_filter(
                $this->eventCounts[$eventName],
                fn($timestamp) => $timestamp > $windowStart
            );
        } else {
            $this->eventCounts[$eventName] = [];
        }
        
        // Check rate limit
        if (count($this->eventCounts[$eventName]) >= $this->maxEventsPerMinute) {
            $event->stopPropagation();
            $event->setMetadata('rate_limited', true);
            error_log("Rate limit exceeded for event: {$eventName}");
            return $event;
        }
        
        // Record this event
        $this->eventCounts[$eventName][] = $now;
        
        return $event;
    }
}

/**
 * Authentication middleware - validates event sources
 */
class AuthenticationMiddleware extends PluginEventMiddleware
{
    private array $trustedSources;
    private array $secureEvents;
    
    public function __construct(array $trustedSources = [], array $secureEvents = [])
    {
        $this->trustedSources = $trustedSources;
        $this->secureEvents = $secureEvents;
    }
    
    public function handle(PluginEvent $event): PluginEvent
    {
        if (!$this->shouldHandle($event)) {
            return $event;
        }
        
        $source = $event->getSource();
        
        if (!$source || !in_array($source, $this->trustedSources)) {
            $event->stopPropagation();
            $event->setMetadata('authentication_failed', true);
            error_log("Authentication failed for secure event: {$event->getName()}");
        }
        
        return $event;
    }
    
    public function shouldHandle(PluginEvent $event): bool
    {
        return in_array($event->getName(), $this->secureEvents);
    }
}

/**
 * Transformation middleware - modifies event data
 */
class TransformationMiddleware extends PluginEventMiddleware
{
    private array $transformations;
    
    public function __construct(array $transformations = [])
    {
        $this->transformations = $transformations;
    }
    
    public function handle(PluginEvent $event): PluginEvent
    {
        $eventName = $event->getName();
        
        if (!isset($this->transformations[$eventName])) {
            return $event;
        }
        
        $transformation = $this->transformations[$eventName];
        
        if (is_callable($transformation)) {
            $transformedData = $transformation($event->getData());
            foreach ($transformedData as $key => $value) {
                $event->setData($key, $value);
            }
        }
        
        return $event;
    }
}

/**
 * Caching middleware - caches event results
 */
class CachingMiddleware extends PluginEventMiddleware
{
    private array $cache = [];
    private array $cacheableEvents;
    private int $cacheTtl;
    
    public function __construct(array $cacheableEvents = [], int $cacheTtl = 300)
    {
        $this->cacheableEvents = $cacheableEvents;
        $this->cacheTtl = $cacheTtl;
    }
    
    public function handle(PluginEvent $event): PluginEvent
    {
        if (!$this->shouldHandle($event)) {
            return $event;
        }
        
        $cacheKey = $this->getCacheKey($event);
        
        // Check cache
        if (isset($this->cache[$cacheKey])) {
            $cached = $this->cache[$cacheKey];
            if ($cached['expires'] > time()) {
                // Return cached results
                foreach ($cached['results'] as $listenerId => $result) {
                    $event->addResult($listenerId, $result);
                }
                $event->stopPropagation();
                $event->setMetadata('cached_result', true);
                return $event;
            }
            unset($this->cache[$cacheKey]);
        }
        
        // Store event for post-processing cache storage
        $event->setMetadata('cache_key', $cacheKey);
        
        return $event;
    }
    
    public function shouldHandle(PluginEvent $event): bool
    {
        return in_array($event->getName(), $this->cacheableEvents);
    }
    
    private function getCacheKey(PluginEvent $event): string
    {
        return md5($event->getName() . serialize($event->getData()));
    }
    
    public function cacheResults(PluginEvent $event): void
    {
        $cacheKey = $event->getMetadata('cache_key');
        if (!$cacheKey) {
            return;
        }
        
        $this->cache[$cacheKey] = [
            'results' => $event->getResults(),
            'expires' => time() + $this->cacheTtl
        ];
    }
}