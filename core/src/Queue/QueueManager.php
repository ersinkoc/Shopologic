<?php

declare(strict_types=1);

namespace Shopologic\Core\Queue;

use Shopologic\Core\Events\EventDispatcherInterface;

/**
 * Queue management system for background job processing
 */
class QueueManager
{
    private array $connections = [];
    private array $config;
    private EventDispatcherInterface $events;
    private string $default;

    public function __construct(array $config, EventDispatcherInterface $events)
    {
        $this->config = array_merge([
            'default' => 'database',
            'connections' => [
                'sync' => [
                    'driver' => 'sync'
                ],
                'database' => [
                    'driver' => 'database',
                    'table' => 'jobs',
                    'queue' => 'default',
                    'retry_after' => 90
                ],
                'file' => [
                    'driver' => 'file',
                    'path' => 'storage/queues'
                ],
                'memory' => [
                    'driver' => 'memory'
                ]
            ]
        ], $config);
        
        $this->events = $events;
        $this->default = $this->config['default'];
    }

    /**
     * Get queue connection
     */
    public function connection(?string $name = null): QueueInterface
    {
        $name = $name ?: $this->default;
        
        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->createConnection($name);
        }
        
        return $this->connections[$name];
    }

    /**
     * Push job to queue
     */
    public function push($job, ?string $queue = null): string
    {
        return $this->connection()->push($job, null, $queue);
    }

    /**
     * Push job with delay
     */
    public function later(int $delay, $job, ?string $queue = null): string
    {
        return $this->connection()->later($delay, $job, null, $queue);
    }

    /**
     * Push job to specific queue
     */
    public function pushOn(string $queue, $job): string
    {
        return $this->push($job, $queue);
    }

    /**
     * Push job with delay to specific queue
     */
    public function laterOn(string $queue, int $delay, $job): string
    {
        return $this->later($delay, $job, $queue);
    }

    /**
     * Push array of jobs
     */
    public function bulk(array $jobs, ?string $queue = null): void
    {
        $this->connection()->bulk($jobs, null, $queue);
    }

    /**
     * Get queue size
     */
    public function size(?string $queue = null): int
    {
        return $this->connection()->size($queue);
    }

    /**
     * Create queue connection
     */
    protected function createConnection(string $name): QueueInterface
    {
        if (!isset($this->config['connections'][$name])) {
            throw new \Exception("Queue connection '{$name}' is not defined.");
        }
        
        $config = $this->config['connections'][$name];
        
        switch ($config['driver']) {
            case 'sync':
                return new SyncQueue($this->events);
                
            case 'database':
                return new DatabaseQueue($config, $this->events);
                
            case 'file':
                return new FileQueue($config, $this->events);
                
            case 'memory':
                return new MemoryQueue($this->events);
                
            default:
                throw new \Exception("Queue driver '{$config['driver']}' is not supported.");
        }
    }
}

/**
 * Queue interface
 */
interface QueueInterface
{
    public function push($job, $data = null, ?string $queue = null): string;
    public function later(int $delay, $job, $data = null, ?string $queue = null): string;
    public function pop(?string $queue = null): ?Job;
    public function bulk(array $jobs, $data = null, ?string $queue = null): void;
    public function release(Job $job, int $delay = 0): void;
    public function delete(Job $job): void;
    public function size(?string $queue = null): int;
    public function clear(?string $queue = null): void;
}

/**
 * Base queue implementation
 */
abstract class AbstractQueue implements QueueInterface
{
    protected EventDispatcherInterface $events;
    protected string $defaultQueue = 'default';

    public function __construct(EventDispatcherInterface $events)
    {
        $this->events = $events;
    }

    public function bulk(array $jobs, $data = null, ?string $queue = null): void
    {
        foreach ($jobs as $job) {
            $this->push($job, $data, $queue);
        }
    }

    protected function createPayload($job, $data = null): array
    {
        if (is_object($job)) {
            return $this->createObjectPayload($job);
        }
        
        return $this->createStringPayload($job, $data);
    }

    protected function createObjectPayload($job): array
    {
        return [
            'displayName' => $this->getDisplayName($job),
            'job' => 'Shopologic\\Core\\Queue\\CallQueuedHandler@call',
            'data' => [
                'commandName' => get_class($job),
                'command' => serialize(clone $job)
            ],
            'id' => $this->generateId(),
            'attempts' => 0
        ];
    }

    protected function createStringPayload(string $job, $data = null): array
    {
        return [
            'displayName' => is_string($job) ? explode('@', $job)[0] : null,
            'job' => $job,
            'data' => $data,
            'id' => $this->generateId(),
            'attempts' => 0
        ];
    }

    protected function getDisplayName($job): string
    {
        return method_exists($job, 'displayName')
            ? $job->displayName()
            : get_class($job);
    }

    protected function generateId(): string
    {
        return uniqid('', true);
    }

    protected function getQueue(?string $queue): string
    {
        return $queue ?: $this->defaultQueue;
    }
}

/**
 * Synchronous queue (executes immediately)
 */
class SyncQueue extends AbstractQueue
{
    public function push($job, $data = null, ?string $queue = null): string
    {
        $payload = $this->createPayload($job, $data);
        
        $this->events->dispatch('queue.job.processing', $payload);
        
        try {
            $this->handleJob($payload);
            
            $this->events->dispatch('queue.job.processed', $payload);
        } catch (\Exception $e) {
            $this->events->dispatch('queue.job.failed', [
                'payload' => $payload,
                'exception' => $e
            ]);
            
            throw $e;
        }
        
        return $payload['id'];
    }

    public function later(int $delay, $job, $data = null, ?string $queue = null): string
    {
        return $this->push($job, $data, $queue);
    }

    public function pop(?string $queue = null): ?Job
    {
        return null;
    }

    public function release(Job $job, int $delay = 0): void
    {
        // Not applicable for sync queue
    }

    public function delete(Job $job): void
    {
        // Not applicable for sync queue
    }

    public function size(?string $queue = null): int
    {
        return 0;
    }

    public function clear(?string $queue = null): void
    {
        // Not applicable for sync queue
    }

    protected function handleJob(array $payload): void
    {
        if ($payload['job'] === 'Shopologic\\Core\\Queue\\CallQueuedHandler@call') {
            // SECURITY FIX (BUG-002): Replace unserialize with JSON to prevent RCE
            // Expect command data in JSON format: {"class": "ClassName", "data": {...}}
            $commandData = json_decode($payload['data']['command'], true);

            if ($commandData === null || !isset($commandData['class'])) {
                throw new \RuntimeException('Invalid queue job data format');
            }

            // Whitelist allowed job classes for additional security
            $allowedClasses = $this->config['allowed_job_classes'] ?? [];
            if (!empty($allowedClasses) && !in_array($commandData['class'], $allowedClasses, true)) {
                throw new \RuntimeException('Queue job class not whitelisted: ' . $commandData['class']);
            }

            $instance = new $commandData['class']($commandData['data'] ?? []);
            $instance->handle();
        } else {
            [$class, $method] = explode('@', $payload['job']);
            $instance = new $class();
            $instance->$method($payload['data']);
        }
    }
}

/**
 * Database queue implementation
 */
class DatabaseQueue extends AbstractQueue
{
    private array $config;
    private $db;

    public function __construct(array $config, EventDispatcherInterface $events)
    {
        parent::__construct($events);
        $this->config = $config;
        $this->defaultQueue = $config['queue'] ?? 'default';
        // Database connection would be injected here
    }

    public function push($job, $data = null, ?string $queue = null): string
    {
        return $this->pushToDatabase($queue, $this->createPayload($job, $data));
    }

    public function later(int $delay, $job, $data = null, ?string $queue = null): string
    {
        return $this->pushToDatabase($queue, $this->createPayload($job, $data), $delay);
    }

    public function pop(?string $queue = null): ?Job
    {
        $queue = $this->getQueue($queue);
        
        // Get next available job
        $job = $this->getNextAvailableJob($queue);
        
        if ($job) {
            return new DatabaseJob($this, $job, $queue);
        }
        
        return null;
    }

    public function release(Job $job, int $delay = 0): void
    {
        $job->release($delay);
    }

    public function delete(Job $job): void
    {
        $job->delete();
    }

    public function size(?string $queue = null): int
    {
        // Query database for queue size
        return 0;
    }

    public function clear(?string $queue = null): void
    {
        // Delete all jobs from queue
    }

    protected function pushToDatabase(?string $queue, array $payload, int $delay = 0): string
    {
        $attributes = [
            'queue' => $this->getQueue($queue),
            'payload' => json_encode($payload),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => time() + $delay,
            'created_at' => time()
        ];
        
        // Insert into database
        // $this->db->table($this->config['table'])->insert($attributes);
        
        $this->events->dispatch('queue.job.pushed', [
            'queue' => $attributes['queue'],
            'id' => $payload['id']
        ]);
        
        return $payload['id'];
    }

    protected function getNextAvailableJob(string $queue): ?array
    {
        // Query database for next available job
        // This would include proper locking to prevent race conditions
        return null;
    }
}

/**
 * File-based queue implementation
 */
class FileQueue extends AbstractQueue
{
    private string $path;
    private array $locks = [];

    public function __construct(array $config, EventDispatcherInterface $events)
    {
        parent::__construct($events);
        $this->path = rtrim($config['path'], '/');
        
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    public function push($job, $data = null, ?string $queue = null): string
    {
        $payload = $this->createPayload($job, $data);
        $queue = $this->getQueue($queue);
        
        $this->writeJob($queue, $payload);
        
        return $payload['id'];
    }

    public function later(int $delay, $job, $data = null, ?string $queue = null): string
    {
        $payload = $this->createPayload($job, $data);
        $payload['available_at'] = time() + $delay;
        
        $queue = $this->getQueue($queue);
        $this->writeJob($queue, $payload);
        
        return $payload['id'];
    }

    public function pop(?string $queue = null): ?Job
    {
        $queue = $this->getQueue($queue);
        $files = $this->getJobFiles($queue);
        
        foreach ($files as $file) {
            if ($this->lockFile($file)) {
                $payload = json_decode(file_get_contents($file), true);
                
                if ($this->isAvailable($payload)) {
                    return new FileJob($this, $payload, $file, $queue);
                }
                
                $this->unlockFile($file);
            }
        }
        
        return null;
    }

    public function release(Job $job, int $delay = 0): void
    {
        $job->release($delay);
    }

    public function delete(Job $job): void
    {
        $job->delete();
    }

    public function size(?string $queue = null): int
    {
        $queue = $this->getQueue($queue);
        return count($this->getJobFiles($queue));
    }

    public function clear(?string $queue = null): void
    {
        $queue = $this->getQueue($queue);
        $files = $this->getJobFiles($queue);
        
        foreach ($files as $file) {
            unlink($file);
        }
    }

    protected function writeJob(string $queue, array $payload): void
    {
        $queuePath = $this->getQueuePath($queue);
        
        if (!is_dir($queuePath)) {
            mkdir($queuePath, 0755, true);
        }
        
        $file = $queuePath . '/' . $payload['id'] . '.job';
        file_put_contents($file, json_encode($payload), LOCK_EX);
    }

    protected function getQueuePath(string $queue): string
    {
        return $this->path . '/' . $queue;
    }

    protected function getJobFiles(string $queue): array
    {
        $queuePath = $this->getQueuePath($queue);
        
        if (!is_dir($queuePath)) {
            return [];
        }
        
        $files = glob($queuePath . '/*.job');
        sort($files);
        
        return $files;
    }

    protected function lockFile(string $file): bool
    {
        $lockFile = $file . '.lock';
        
        if (file_exists($lockFile)) {
            $lockTime = (int)file_get_contents($lockFile);
            
            // Check if lock is expired (older than 5 minutes)
            if (time() - $lockTime > 300) {
                unlink($lockFile);
            } else {
                return false;
            }
        }
        
        file_put_contents($lockFile, time());
        $this->locks[$file] = $lockFile;
        
        return true;
    }

    protected function unlockFile(string $file): void
    {
        if (isset($this->locks[$file])) {
            unlink($this->locks[$file]);
            unset($this->locks[$file]);
        }
    }

    protected function isAvailable(array $payload): bool
    {
        return !isset($payload['available_at']) || $payload['available_at'] <= time();
    }
}

/**
 * Memory queue implementation
 */
class MemoryQueue extends AbstractQueue
{
    private array $queues = [];

    public function push($job, $data = null, ?string $queue = null): string
    {
        $payload = $this->createPayload($job, $data);
        $queue = $this->getQueue($queue);
        
        if (!isset($this->queues[$queue])) {
            $this->queues[$queue] = [];
        }
        
        $this->queues[$queue][] = $payload;
        
        return $payload['id'];
    }

    public function later(int $delay, $job, $data = null, ?string $queue = null): string
    {
        $payload = $this->createPayload($job, $data);
        $payload['available_at'] = time() + $delay;
        
        $queue = $this->getQueue($queue);
        
        if (!isset($this->queues[$queue])) {
            $this->queues[$queue] = [];
        }
        
        $this->queues[$queue][] = $payload;
        
        return $payload['id'];
    }

    public function pop(?string $queue = null): ?Job
    {
        $queue = $this->getQueue($queue);
        
        if (!isset($this->queues[$queue]) || empty($this->queues[$queue])) {
            return null;
        }
        
        foreach ($this->queues[$queue] as $index => $payload) {
            if ($this->isAvailable($payload)) {
                unset($this->queues[$queue][$index]);
                $this->queues[$queue] = array_values($this->queues[$queue]);
                
                return new MemoryJob($this, $payload, $queue);
            }
        }
        
        return null;
    }

    public function release(Job $job, int $delay = 0): void
    {
        $payload = $job->getPayload();
        $payload['attempts']++;
        $payload['available_at'] = time() + $delay;
        
        $this->queues[$job->getQueue()][] = $payload;
    }

    public function delete(Job $job): void
    {
        // Already removed in pop()
    }

    public function size(?string $queue = null): int
    {
        $queue = $this->getQueue($queue);
        
        return isset($this->queues[$queue]) ? count($this->queues[$queue]) : 0;
    }

    public function clear(?string $queue = null): void
    {
        $queue = $this->getQueue($queue);
        
        unset($this->queues[$queue]);
    }

    protected function isAvailable(array $payload): bool
    {
        return !isset($payload['available_at']) || $payload['available_at'] <= time();
    }
}

/**
 * Base job class
 */
abstract class Job
{
    protected QueueInterface $queue;
    protected array $payload;
    protected string $queueName;

    public function __construct(QueueInterface $queue, array $payload, string $queueName)
    {
        $this->queue = $queue;
        $this->payload = $payload;
        $this->queueName = $queueName;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getQueue(): string
    {
        return $this->queueName;
    }

    public function attempts(): int
    {
        return $this->payload['attempts'] ?? 0;
    }

    public function handle(): void
    {
        if ($this->payload['job'] === 'Shopologic\\Core\\Queue\\CallQueuedHandler@call') {
            // SECURITY FIX (BUG-002): Replace unserialize with JSON to prevent RCE
            // Expect command data in JSON format: {"class": "ClassName", "data": {...}}
            $commandData = json_decode($this->payload['data']['command'], true);

            if ($commandData === null || !isset($commandData['class'])) {
                throw new \RuntimeException('Invalid queue job data format');
            }

            $instance = new $commandData['class']($commandData['data'] ?? []);
            $instance->handle();
        } else {
            [$class, $method] = explode('@', $this->payload['job']);
            $instance = new $class();
            $instance->$method($this->payload['data']);
        }
    }

    abstract public function release(int $delay = 0): void;
    abstract public function delete(): void;
}

/**
 * Database job implementation
 */
class DatabaseJob extends Job
{
    private array $job;

    public function __construct(DatabaseQueue $queue, array $job, string $queueName)
    {
        parent::__construct($queue, json_decode($job['payload'], true), $queueName);
        $this->job = $job;
    }

    public function release(int $delay = 0): void
    {
        // Update job in database with new available_at time
    }

    public function delete(): void
    {
        // Delete job from database
    }
}

/**
 * File job implementation
 */
class FileJob extends Job
{
    private string $file;

    public function __construct(FileQueue $queue, array $payload, string $file, string $queueName)
    {
        parent::__construct($queue, $payload, $queueName);
        $this->file = $file;
    }

    public function release(int $delay = 0): void
    {
        $this->payload['attempts']++;
        $this->payload['available_at'] = time() + $delay;
        
        file_put_contents($this->file, json_encode($this->payload), LOCK_EX);
    }

    public function delete(): void
    {
        unlink($this->file);
    }
}

/**
 * Memory job implementation
 */
class MemoryJob extends Job
{
    public function release(int $delay = 0): void
    {
        $this->queue->release($this, $delay);
    }

    public function delete(): void
    {
        // Already deleted from memory
    }
}

/**
 * Queue worker
 */
class Worker
{
    private QueueManager $manager;
    private EventDispatcherInterface $events;
    private array $options = [];

    public function __construct(QueueManager $manager, EventDispatcherInterface $events)
    {
        $this->manager = $manager;
        $this->events = $events;
    }

    public function daemon(string $connection = null, string $queue = null, array $options = []): void
    {
        $this->options = array_merge([
            'sleep' => 3,
            'tries' => 3,
            'timeout' => 60,
            'memory' => 128
        ], $options);
        
        $lastRestart = $this->getTimestampOfLastRestart();
        
        while (true) {
            if ($this->shouldQuit($lastRestart)) {
                break;
            }
            
            $this->runNextJob($connection, $queue);
            
            $this->sleep($this->options['sleep']);
            
            if ($this->memoryExceeded($this->options['memory'])) {
                $this->stop();
            }
        }
    }

    public function runNextJob(?string $connection, ?string $queue): void
    {
        $job = $this->getNextJob($connection, $queue);
        
        if (!$job) {
            return;
        }
        
        $this->events->dispatch('queue.job.processing', [
            'job' => $job,
            'connection' => $connection
        ]);
        
        try {
            $job->handle();
            
            $job->delete();
            
            $this->events->dispatch('queue.job.processed', [
                'job' => $job,
                'connection' => $connection
            ]);
        } catch (\Exception $e) {
            $this->handleJobException($job, $e);
        }
    }

    protected function getNextJob(?string $connection, ?string $queue): ?Job
    {
        return $this->manager->connection($connection)->pop($queue);
    }

    protected function handleJobException(Job $job, \Exception $e): void
    {
        $this->events->dispatch('queue.job.failed', [
            'job' => $job,
            'exception' => $e
        ]);
        
        if ($job->attempts() < $this->options['tries']) {
            $job->release($this->calculateBackoff($job->attempts()));
        } else {
            $job->delete();
            $this->failed($job, $e);
        }
    }

    protected function calculateBackoff(int $attempt): int
    {
        return min(pow(2, $attempt) * 10, 300);
    }

    protected function failed(Job $job, \Exception $e): void
    {
        // Store failed job for later retry or inspection
    }

    protected function sleep(int $seconds): void
    {
        sleep($seconds);
    }

    protected function shouldQuit($lastRestart): bool
    {
        return $this->getTimestampOfLastRestart() != $lastRestart;
    }

    protected function getTimestampOfLastRestart(): int
    {
        return file_exists($file = $this->getRestartFile()) ? (int)file_get_contents($file) : 0;
    }

    protected function getRestartFile(): string
    {
        return 'storage/framework/restart';
    }

    protected function memoryExceeded(int $memoryLimit): bool
    {
        return (memory_get_usage(true) / 1024 / 1024) >= $memoryLimit;
    }

    protected function stop(): void
    {
        exit(0);
    }
}

/**
 * Queueable trait for jobs
 */
trait Queueable
{
    public string $queue = 'default';
    public ?int $delay = null;
    public int $tries = 3;
    public int $timeout = 60;

    public function onQueue(string $queue): self
    {
        $this->queue = $queue;
        return $this;
    }

    public function delay(int $seconds): self
    {
        $this->delay = $seconds;
        return $this;
    }

    public function tries(int $tries): self
    {
        $this->tries = $tries;
        return $this;
    }

    public function timeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }
}