<?php

declare(strict_types=1);

namespace Shopologic\Plugins\Shared\Events;

/**
 * Plugin event object
 * Carries event data and manages propagation
 */
class PluginEvent
{
    private string $name;
    private array $data;
    private array $results = [];
    private bool $propagationStopped = false;
    private float $timestamp;
    private array $metadata = [];
    
    public function __construct(string $name, array $data = [])
    {
        $this->name = $name;
        $this->data = $data;
        $this->timestamp = microtime(true);
    }
    
    /**
     * Get event name
     */
    public function getName(): string
    {
        return $this->name;
    }
    
    /**
     * Get all event data
     */
    public function getData(string $key = null): mixed
    {
        if ($key === null) {
            return $this->data;
        }
        
        return $this->data[$key] ?? null;
    }
    
    /**
     * Set event data
     */
    public function setData(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }
    
    /**
     * Check if event data exists
     */
    public function hasData(string $key): bool
    {
        return isset($this->data[$key]);
    }
    
    /**
     * Get event timestamp
     */
    public function getTimestamp(): float
    {
        return $this->timestamp;
    }
    
    /**
     * Stop event propagation
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
    
    /**
     * Check if propagation is stopped
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
    
    /**
     * Add listener result
     */
    public function addResult(string $listenerId, array $result): void
    {
        $this->results[$listenerId] = $result;
    }
    
    /**
     * Get all results
     */
    public function getResults(): array
    {
        return $this->results;
    }
    
    /**
     * Get successful results only
     */
    public function getSuccessfulResults(): array
    {
        return array_filter($this->results, fn($result) => $result['success'] ?? false);
    }
    
    /**
     * Get failed results only
     */
    public function getFailedResults(): array
    {
        return array_filter($this->results, fn($result) => !($result['success'] ?? true));
    }
    
    /**
     * Set metadata
     */
    public function setMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }
    
    /**
     * Get metadata
     */
    public function getMetadata(string $key = null): mixed
    {
        if ($key === null) {
            return $this->metadata;
        }
        
        return $this->metadata[$key] ?? null;
    }
    
    /**
     * Check if event is broadcast
     */
    public function isBroadcast(): bool
    {
        return $this->getData('_broadcast') === true;
    }
    
    /**
     * Get event source
     */
    public function getSource(): ?string
    {
        return $this->getData('_source');
    }
    
    /**
     * Get event age in seconds
     */
    public function getAge(): float
    {
        return microtime(true) - $this->timestamp;
    }
    
    /**
     * Format event for logging
     */
    public function formatForLogging(): array
    {
        return [
            'name' => $this->name,
            'timestamp' => date('Y-m-d H:i:s', (int)$this->timestamp),
            'data_keys' => array_keys($this->data),
            'results_count' => count($this->results),
            'successful_results' => count($this->getSuccessfulResults()),
            'failed_results' => count($this->getFailedResults()),
            'propagation_stopped' => $this->propagationStopped,
            'is_broadcast' => $this->isBroadcast(),
            'source' => $this->getSource(),
            'age_ms' => round($this->getAge() * 1000, 2)
        ];
    }
    
    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'data' => $this->data,
            'results' => $this->results,
            'metadata' => $this->metadata,
            'timestamp' => $this->timestamp,
            'propagation_stopped' => $this->propagationStopped
        ];
    }
    
    /**
     * Create from array
     */
    public static function fromArray(array $data): self
    {
        $event = new self($data['name'], $data['data'] ?? []);
        $event->results = $data['results'] ?? [];
        $event->metadata = $data['metadata'] ?? [];
        $event->timestamp = $data['timestamp'] ?? microtime(true);
        $event->propagationStopped = $data['propagation_stopped'] ?? false;
        
        return $event;
    }
}