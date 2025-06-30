<?php

declare(strict_types=1);

namespace Shopologic\Plugins\EventSourcing;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\HookSystem;
use EventSourcing\Core\{
    EventStore,
    EventBus,
    AggregateRepository,
    ProjectionManager,
    SnapshotStore,
    EventSerializer,
    EventDispatcher,;
    StreamManager;
};
use EventSourcing\Services\{
    ReplayService,
    TimeTravelService,
    AuditService,
    SubscriptionManager,
    ProcessManagerRegistry,
    SagaManager,
    EventUpgrader,;
    MetadataEnricher;
};
use EventSourcing\Projections\{
    ProjectionRunner,
    ProjectionRegistry,
    ProjectionState,;
    ProjectionBuilder;
};

class EventSourcingPlugin extends AbstractPlugin
{
    private EventStore $eventStore;
    private EventBus $eventBus;
    private AggregateRepository $aggregateRepository;
    private ProjectionManager $projectionManager;
    private SnapshotStore $snapshotStore;
    private EventSerializer $eventSerializer;
    private EventDispatcher $eventDispatcher;
    private StreamManager $streamManager;
    
    private ReplayService $replayService;
    private TimeTravelService $timeTravelService;
    private AuditService $auditService;
    private SubscriptionManager $subscriptionManager;
    private ProcessManagerRegistry $processManagerRegistry;
    private SagaManager $sagaManager;
    private EventUpgrader $eventUpgrader;
    private MetadataEnricher $metadataEnricher;
    
    private ProjectionRunner $projectionRunner;
    private array $registeredProjections = [];
    private array $registeredAggregates = [];
    private array $registeredSagas = [];
    
    /**
     * Plugin activation
     */
    public function activate(): void
    {
        // Run migrations
        $this->runMigrations();
        
        // Initialize event store schema
        $this->initializeEventStore();
        
        // Set default options
        $this->setDefaultOptions();
        
        // Create required directories
        $this->createDirectories();
        
        // Register default projections
        $this->registerDefaultProjections();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        // Stop all projections
        $this->stopAllProjections();
        
        // Flush pending events
        $this->flushPendingEvents();
        
        // Clear projection cache
        $this->clearProjectionCache();
    }
    
    /**
     * Register hooks
     */
    protected function registerHooks(): void
    {
        // Initialize services
        HookSystem::addAction('init', [$this, 'initializeServices'], 5);
        
        // Model events
        HookSystem::addAction('model_created', [$this, 'recordModelCreated'], 10, 2);
        HookSystem::addAction('model_updated', [$this, 'recordModelUpdated'], 10, 3);
        HookSystem::addAction('model_deleted', [$this, 'recordModelDeleted'], 10, 2);
        
        // Transaction hooks
        HookSystem::addAction('transaction_begin', [$this, 'beginEventTransaction']);
        HookSystem::addAction('transaction_commit', [$this, 'commitEventTransaction']);
        HookSystem::addAction('transaction_rollback', [$this, 'rollbackEventTransaction']);
        
        // Admin interface
        HookSystem::addAction('admin_menu', [$this, 'registerAdminMenu']);
        HookSystem::addAction('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        
        // API endpoints
        $this->registerApiEndpoints();
        
        // Scheduled tasks
        HookSystem::addAction('event_process_projections', [$this, 'processProjections']);
        HookSystem::addAction('event_create_snapshots', [$this, 'createSnapshots']);
        HookSystem::addAction('event_cleanup_old', [$this, 'cleanupOldEvents']);
        HookSystem::addAction('event_process_subscriptions', [$this, 'processEventSubscriptions']);
        
        // Event streaming
        if ($this->getOption('enable_event_streaming', true)) {
            HookSystem::addAction('event_stored', [$this, 'streamEvent']);
        }
        
        // Audit trail
        if ($this->getOption('enable_audit_trail', true)) {
            $this->registerAuditHooks();
        }
        
        // Process managers and sagas
        if ($this->getOption('enable_saga_support', true)) {
            HookSystem::addAction('event_published', [$this, 'handleSagaEvent']);
        }
        
        if ($this->getOption('enable_process_managers', true)) {
            HookSystem::addAction('event_published', [$this, 'handleProcessManagerEvent']);
        }
    }
    
    /**
     * Initialize services
     */
    public function initializeServices(): void
    {
        // Initialize event serializer
        $format = $this->getOption('serialization_format', 'json');
        $this->eventSerializer = new EventSerializer($format);
        
        // Initialize event store
        $engine = $this->getOption('event_store_engine', 'database');
        $this->eventStore = $this->createEventStore($engine);
        
        // Initialize stream manager
        $this->streamManager = new StreamManager($this->eventStore);
        
        // Initialize snapshot store
        if ($this->getOption('enable_snapshots', true)) {
            $this->snapshotStore = new SnapshotStore($this->container);
        }
        
        // Initialize aggregate repository
        $this->aggregateRepository = new AggregateRepository(
            $this->eventStore,
            $this->snapshotStore,
            $this->eventSerializer
        );
        
        // Initialize event bus
        $this->eventBus = new EventBus($this->container);
        
        // Initialize event dispatcher
        $mode = $this->getOption('event_dispatcher_mode', 'async');
        $this->eventDispatcher = new EventDispatcher($this->eventBus, $mode);
        
        // Initialize projection manager
        $this->projectionManager = new ProjectionManager($this->container);
        $this->projectionRunner = new ProjectionRunner($this->projectionManager);
        
        // Initialize services
        $this->replayService = new ReplayService($this->eventStore, $this->eventDispatcher);
        $this->timeTravelService = new TimeTravelService($this->eventStore, $this->aggregateRepository);
        $this->auditService = new AuditService($this->eventStore);
        $this->subscriptionManager = new SubscriptionManager($this->container);
        $this->processManagerRegistry = new ProcessManagerRegistry($this->container);
        $this->sagaManager = new SagaManager($this->container);
        $this->eventUpgrader = new EventUpgrader($this->container);
        $this->metadataEnricher = new MetadataEnricher($this->container);
        
        // Register projections
        $this->registerProjections();
        
        // Register aggregates
        $this->registerAggregates();
        
        // Register sagas and process managers
        $this->registerSagasAndProcessManagers();
    }
    
    /**
     * Create event store based on engine
     */
    private function createEventStore(string $engine): EventStore
    {
        switch ($engine) {
            case 'database':
                return new EventStore\DatabaseEventStore($this->container);
                
            case 'file':
                return new EventStore\FileEventStore($this->container);
                
            case 'memory':
                return new EventStore\InMemoryEventStore($this->container);
                
            case 'distributed':
                return new EventStore\DistributedEventStore($this->container);
                
            default:
                throw new \InvalidArgumentException("Unknown event store engine: {$engine}");
        }
    }
    
    /**
     * Record model created event
     */
    public function recordModelCreated($model, array $data): void
    {
        $event = $this->createDomainEvent('ModelCreated', [
            'model_type' => get_class($model),
            'model_id' => $model->getId(),
            'data' => $data,
            'timestamp' => microtime(true)
        ]);
        
        $this->storeAndPublishEvent($event, $model);
    }
    
    /**
     * Record model updated event
     */
    public function recordModelUpdated($model, array $oldData, array $newData): void
    {
        $event = $this->createDomainEvent('ModelUpdated', [
            'model_type' => get_class($model),
            'model_id' => $model->getId(),
            'old_data' => $oldData,
            'new_data' => $newData,
            'changes' => array_diff_assoc($newData, $oldData),
            'timestamp' => microtime(true)
        ]);
        
        $this->storeAndPublishEvent($event, $model);
    }
    
    /**
     * Record model deleted event
     */
    public function recordModelDeleted($model, array $data): void
    {
        $event = $this->createDomainEvent('ModelDeleted', [
            'model_type' => get_class($model),
            'model_id' => $model->getId(),
            'data' => $data,
            'timestamp' => microtime(true)
        ]);
        
        $this->storeAndPublishEvent($event, $model);
    }
    
    /**
     * Create domain event
     */
    private function createDomainEvent(string $type, array $payload): DomainEvent
    {
        $metadata = $this->metadataEnricher->enrich([
            'user_id' => get_current_user_id(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'request_id' => $this->getCurrentRequestId()
        ]);
        
        return new DomainEvent(
            $this->generateEventId(),
            $type,
            $payload,
            $metadata,
            microtime(true)
        );
    }
    
    /**
     * Store and publish event
     */
    private function storeAndPublishEvent(DomainEvent $event, $aggregate): void
    {
        // Determine stream
        $streamId = $this->getStreamId($aggregate);
        
        // Store event
        $this->eventStore->append($streamId, [$event]);
        
        // Publish event
        $this->eventDispatcher->dispatch($event);
        
        // Update projections if synchronous
        if ($this->getOption('projection_mode', 'async') === 'sync') {
            $this->updateProjections($event);
        }
        
        // Check for snapshot creation
        if ($this->shouldCreateSnapshot($streamId)) {
            $this->createAggregateSnapshot($aggregate, $streamId);
        }
    }
    
    /**
     * Process projections
     */
    public function processProjections(): void
    {
        if (!$this->getOption('enable_projections', true)) {
            return;
        }
        
        $concurrency = $this->getOption('concurrent_projections', 4);
        $this->projectionRunner->run($concurrency);
    }
    
    /**
     * Create snapshots
     */
    public function createSnapshots(): void
    {
        if (!$this->getOption('enable_snapshots', true)) {
            return;
        }
        
        $threshold = $this->getOption('snapshot_threshold', 100);
        
        // Get streams that need snapshots
        $streams = $this->streamManager->getStreamsNeedingSnapshots($threshold);
        
        foreach ($streams as $streamId) {
            try {
                $this->createStreamSnapshot($streamId);
            } catch (\RuntimeException $e) {
                $this->log("Failed to create snapshot for stream {$streamId}: " . $e->getMessage(), 'error');
            }
        }
    }
    
    /**
     * Register projections
     */
    private function registerProjections(): void
    {
        $projections = $this->config['projections'] ?? [];
        
        foreach ($projections as $name => $config) {
            $projection = new ProjectionBuilder()
                ->withName($config['name'])
                ->fromEvents($config['events'])
                ->toTarget($config['target'])
                ->inMode($config['mode'] ?? 'async')
                ->build();
                
            $this->projectionManager->register($name, $projection);
            $this->registeredProjections[$name] = $projection;
        }
    }
    
    /**
     * Handle saga event
     */
    public function handleSagaEvent(DomainEvent $event): void
    {
        $this->sagaManager->handle($event);
    }
    
    /**
     * Handle process manager event
     */
    public function handleProcessManagerEvent(DomainEvent $event): void
    {
        $this->processManagerRegistry->handle($event);
    }
    
    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        add_menu_page(
            'Event Sourcing',
            'Event Store',
            'events.access',
            'event-sourcing',
            [$this, 'renderEventStore'],
            'dashicons-database',
            55
        );
        
        add_submenu_page(
            'event-sourcing',
            'Event Streams',
            'Streams',
            'events.access',
            'event-streams',
            [$this, 'renderStreams']
        );
        
        add_submenu_page(
            'event-sourcing',
            'Projections',
            'Projections',
            'events.access',
            'event-projections',
            [$this, 'renderProjections']
        );
        
        add_submenu_page(
            'event-sourcing',
            'Time Travel',
            'Time Travel',
            'events.replay',
            'event-time-travel',
            [$this, 'renderTimeTravel']
        );
        
        add_submenu_page(
            'event-sourcing',
            'Audit Trail',
            'Audit Trail',
            'events.access',
            'event-audit',
            [$this, 'renderAuditTrail']
        );
        
        add_submenu_page(
            'event-sourcing',
            'Settings',
            'Settings',
            'events.configure',
            'event-settings',
            [$this, 'renderSettings']
        );
    }
    
    /**
     * Get aggregate from event store
     */
    public function getAggregate(string $aggregateId, string $aggregateType): ?AggregateRoot
    {
        return $this->aggregateRepository->load($aggregateId, $aggregateType);
    }
    
    /**
     * Save aggregate
     */
    public function saveAggregate(AggregateRoot $aggregate): void
    {
        $this->aggregateRepository->save($aggregate);
    }
    
    /**
     * Replay events
     */
    public function replayEvents(array $criteria = [], ?callable $callback = null): string
    {
        return $this->replayService->replay($criteria, $callback);
    }
    
    /**
     * Query state at point in time
     */
    public function queryAtPointInTime(string $aggregateId, \DateTimeInterface $pointInTime): ?AggregateRoot
    {
        return $this->timeTravelService->queryAggregate($aggregateId, $pointInTime);
    }
    
    /**
     * Get audit trail
     */
    public function getAuditTrail(string $entityType, string $entityId): array
    {
        return $this->auditService->getTrail($entityType, $entityId);
    }
    
    /**
     * Create required directories
     */
    private function createDirectories(): void
    {
        $dirs = [
            $this->getPluginPath() . '/snapshots',
            $this->getPluginPath() . '/projections',
            $this->getPluginPath() . '/logs',
            $this->getPluginPath() . '/cache'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
        }
    }

    /**
     * Register Services
     */
    protected function registerServices(): void
    {
        // TODO: Implement registerServices
    }

    /**
     * Register EventListeners
     */
    protected function registerEventListeners(): void
    {
        // TODO: Implement registerEventListeners
    }

    /**
     * Register Routes
     */
    protected function registerRoutes(): void
    {
        // TODO: Implement registerRoutes
    }

    /**
     * Register Permissions
     */
    protected function registerPermissions(): void
    {
        // TODO: Implement registerPermissions
    }

    /**
     * Register ScheduledJobs
     */
    protected function registerScheduledJobs(): void
    {
        // TODO: Implement registerScheduledJobs
    }
}