<?php

declare(strict_types=1);

namespace Shopologic\Plugins\WebsocketRealtimeEngine;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\HookSystem;
use WebSocketRealtimeEngine\Core\{
    WebSocketServer,
    ChannelManager,
    ConnectionManager,
    MessageRouter,
    PresenceTracker,
    BroadcastManager,
    AuthenticationHandler,;
    RateLimiter;
};
use WebSocketRealtimeEngine\Services\{
    BroadcastService,
    PresenceService,
    StatisticsService,
    ClusteringService,
    WebhookService,
    HistoryService,
    WhisperService,;
    SubscriptionService;
};
use WebSocketRealtimeEngine\Channels\{
    PublicChannel,
    PrivateChannel,
    PresenceChannel,
    DirectChannel,;
    BroadcastChannel;
};

class WebSocketRealtimeEnginePlugin extends AbstractPlugin
{
    private ?WebSocketServer $server = null;
    private ChannelManager $channelManager;
    private ConnectionManager $connectionManager;
    private MessageRouter $messageRouter;
    private PresenceTracker $presenceTracker;
    private BroadcastManager $broadcastManager;
    private AuthenticationHandler $authHandler;
    private RateLimiter $rateLimiter;
    
    private BroadcastService $broadcastService;
    private PresenceService $presenceService;
    private StatisticsService $statisticsService;
    private ?ClusteringService $clusteringService = null;
    private WebhookService $webhookService;
    private HistoryService $historyService;
    private WhisperService $whisperService;
    private SubscriptionService $subscriptionService;
    
    private array $activeConnections = [];
    private array $channelTypes = [];
    
    /**
     * Plugin activation
     */
    public function activate(): void
    {
        // Run migrations
        $this->runMigrations();
        
        // Initialize WebSocket server
        $this->initializeServer();
        
        // Set default options
        $this->setDefaultOptions();
        
        // Create required directories
        $this->createDirectories();
        
        // Generate client libraries
        $this->generateClientLibraries();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        // Stop WebSocket server
        $this->stopServer();
        
        // Close all connections
        $this->closeAllConnections();
        
        // Save current state
        $this->saveServerState();
    }
    
    /**
     * Register hooks
     */
    protected function registerHooks(): void
    {
        // Initialize services
        HookSystem::addAction('init', [$this, 'initializeServices'], 5);
        
        // Start server
        HookSystem::addAction('init', [$this, 'startServer'], 10);
        
        // Admin interface
        HookSystem::addAction('admin_menu', [$this, 'registerAdminMenu']);
        HookSystem::addAction('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        
        // API endpoints
        $this->registerApiEndpoints();
        
        // WebSocket events
        HookSystem::addAction('websocket_client_connected', [$this, 'onClientConnected']);
        HookSystem::addAction('websocket_client_disconnected', [$this, 'onClientDisconnected']);
        HookSystem::addAction('websocket_message_received', [$this, 'onMessageReceived']);
        
        // Channel events
        HookSystem::addAction('channel_joined', [$this, 'onChannelJoined']);
        HookSystem::addAction('channel_left', [$this, 'onChannelLeft']);
        
        // Broadcasting
        HookSystem::addAction('broadcast_message', [$this, 'broadcastMessage']);
        HookSystem::addAction('whisper_client', [$this, 'whisperToClient']);
        
        // Scheduled tasks
        HookSystem::addAction('websocket_cleanup_connections', [$this, 'cleanupInactiveConnections']);
        HookSystem::addAction('websocket_collect_stats', [$this, 'collectStatistics']);
        HookSystem::addAction('websocket_prune_history', [$this, 'pruneMessageHistory']);
        HookSystem::addAction('websocket_generate_report', [$this, 'generateUsageReport']);
        
        // Frontend integration
        HookSystem::addAction('wp_enqueue_scripts', [$this, 'enqueueClientScripts']);
        HookSystem::addAction('wp_footer', [$this, 'injectWebSocketConfig']);
        
        // Authentication
        HookSystem::addFilter('websocket_authenticate', [$this, 'authenticateConnection'], 10, 2);
        HookSystem::addFilter('websocket_authorize_channel', [$this, 'authorizeChannel'], 10, 3);
        
        // CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            $this->registerCLICommands();
        }
    }
    
    /**
     * Initialize services
     */
    public function initializeServices(): void
    {
        // Initialize managers
        $this->connectionManager = new ConnectionManager($this->container);
        $this->channelManager = new ChannelManager($this->container);
        $this->presenceTracker = new PresenceTracker($this->container);
        $this->broadcastManager = new BroadcastManager($this->container);
        $this->authHandler = new AuthenticationHandler($this->container);
        $this->rateLimiter = new RateLimiter($this->container);
        $this->messageRouter = new MessageRouter($this->container);
        
        // Initialize services
        $this->broadcastService = new BroadcastService($this->broadcastManager);
        $this->presenceService = new PresenceService($this->presenceTracker);
        $this->statisticsService = new StatisticsService($this->container);
        $this->webhookService = new WebhookService($this->container);
        $this->historyService = new HistoryService($this->container);
        $this->whisperService = new WhisperService($this->connectionManager);
        $this->subscriptionService = new SubscriptionService($this->channelManager);
        
        // Initialize clustering if enabled
        if ($this->getOption('clustering.enabled', false)) {
            $this->initializeClustering();
        }
        
        // Register channel types
        $this->registerChannelTypes();
        
        // Configure services
        $this->configureServices();
    }
    
    /**
     * Initialize WebSocket server
     */
    private function initializeServer(): void
    {
        $config = $this->getOption('server_config', []);
        
        $this->server = new WebSocketServer([
            'host' => $config['host'] ?? '0.0.0.0',
            'port' => $config['port'] ?? 6001,
            'ssl' => $config['ssl'] ?? false,
            'ssl_cert' => $config['ssl_cert'] ?? null,
            'ssl_key' => $config['ssl_key'] ?? null
        ]);
        
        // Set event handlers
        $this->server->on('connection', [$this, 'handleConnection']);
        $this->server->on('message', [$this, 'handleMessage']);
        $this->server->on('close', [$this, 'handleDisconnection']);
        $this->server->on('error', [$this, 'handleError']);
    }
    
    /**
     * Start WebSocket server
     */
    public function startServer(): void
    {
        if (!$this->server || $this->server->isRunning()) {
            return;
        }
        
        try {
            $this->server->start();
            $this->log('WebSocket server started', 'info');
            
            // Update server status
            update_option('websocket_server_status', 'running');
            
        } catch (\RuntimeException $e) {
            $this->log('Failed to start WebSocket server: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Register channel types
     */
    private function registerChannelTypes(): void
    {
        $enabledTypes = $this->getOption('channel_types', ['public', 'private', 'presence']);
        
        if (in_array('public', $enabledTypes)) {
            $this->channelManager->registerType('public', PublicChannel::class);
        }
        
        if (in_array('private', $enabledTypes)) {
            $this->channelManager->registerType('private', PrivateChannel::class);
        }
        
        if (in_array('presence', $enabledTypes)) {
            $this->channelManager->registerType('presence', PresenceChannel::class);
        }
        
        if (in_array('direct', $enabledTypes)) {
            $this->channelManager->registerType('direct', DirectChannel::class);
        }
        
        if (in_array('broadcast', $enabledTypes)) {
            $this->channelManager->registerType('broadcast', BroadcastChannel::class);
        }
    }
    
    /**
     * Handle new connection
     */
    public function handleConnection($connection): void
    {
        try {
            // Check rate limits
            if (!$this->rateLimiter->allowConnection($connection->getRemoteAddress())) {
                $connection->close(1008, 'Rate limit exceeded');
                return;
            }
            
            // Create connection object
            $conn = $this->connectionManager->createConnection($connection);
            
            // Authenticate if required
            if ($this->getOption('authentication.required', true)) {
                $this->authHandler->requestAuthentication($conn);
            } else {
                $this->onConnectionAuthenticated($conn);
            }
            
        } catch (\RuntimeException $e) {
            $this->log('Connection error: ' . $e->getMessage(), 'error');
            $connection->close(1011, 'Server error');
        }
    }
    
    /**
     * Handle incoming message
     */
    public function handleMessage($connection, $message): void
    {
        try {
            $conn = $this->connectionManager->getConnection($connection);
            if (!$conn) {
                return;
            }
            
            // Check rate limits
            if (!$this->rateLimiter->allowMessage($conn)) {
                $conn->send([
                    'event' => 'error',
                    'data' => ['message' => 'Rate limit exceeded']
                ]);
                return;
            }
            
            // Parse message
            $data = json_decode($message, true);
            if (!$data) {
                $conn->send([
                    'event' => 'error',
                    'data' => ['message' => 'Invalid message format']
                ]);
                return;
            }
            
            // Route message
            $this->messageRouter->route($conn, $data);
            
            // Store message history if enabled
            if ($this->getOption('message_settings.history', true)) {
                $this->historyService->store($conn, $data);
            }
            
            // Trigger webhook if configured
            if ($this->getOption('webhooks.enabled', false)) {
                $this->webhookService->trigger('message', [
                    'connection' => $conn->getId(),
                    'message' => $data
                ]);
            }
            
        } catch (\RuntimeException $e) {
            $this->log('Message handling error: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Handle channel subscription
     */
    public function handleSubscribe($connection, array $data): void
    {
        $channel = $data['channel'] ?? null;
        if (!$channel) {
            $connection->send([
                'event' => 'subscription_error',
                'channel' => $channel,
                'data' => ['message' => 'Channel name required']
            ]);
            return;
        }
        
        // Check authorization
        if (!$this->authorizeChannel($channel, $connection, $data)) {
            $connection->send([
                'event' => 'subscription_error',
                'channel' => $channel,
                'data' => ['message' => 'Unauthorized']
            ]);
            return;
        }
        
        // Subscribe to channel
        $this->subscriptionService->subscribe($connection, $channel, $data);
        
        // Send success response
        $connection->send([
            'event' => 'subscription_succeeded',
            'channel' => $channel
        ]);
        
        // Handle presence channel
        if ($this->channelManager->isPresenceChannel($channel)) {
            $this->handlePresenceJoin($connection, $channel);
        }
    }
    
    /**
     * Handle presence join
     */
    private function handlePresenceJoin($connection, string $channel): void
    {
        $userInfo = $this->presenceService->getUserInfo($connection);
        
        // Add to presence
        $this->presenceService->join($channel, $connection->getId(), $userInfo);
        
        // Get current members
        $members = $this->presenceService->getMembers($channel);
        
        // Send presence info to joining user
        $connection->send([
            'event' => 'presence:members',
            'channel' => $channel,
            'data' => ['members' => $members]
        ]);
        
        // Broadcast join to other members
        $this->broadcastService->toChannel($channel, [
            'event' => 'presence:joining',
            'channel' => $channel,
            'data' => ['user' => $userInfo]
        ], [$connection->getId()]);
    }
    
    /**
     * Broadcast message
     */
    public function broadcastMessage(string $channel, array $data, array $except = []): void
    {
        $this->broadcastService->broadcast($channel, $data, $except);
        
        // Update statistics
        $this->statisticsService->recordBroadcast($channel);
    }
    
    /**
     * Send direct message
     */
    public function whisperToClient(string $connectionId, array $data): void
    {
        $this->whisperService->send($connectionId, $data);
    }
    
    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        add_menu_page(
            'WebSocket Real-time',
            'WebSocket',
            'websocket.access',
            'websocket-realtime-engine',
            [$this, 'renderDashboard'],
            'dashicons-rss',
            54
        );
        
        add_submenu_page(
            'websocket-realtime-engine',
            'Connections',
            'Connections',
            'websocket.view_connections',
            'websocket-connections',
            [$this, 'renderConnections']
        );
        
        add_submenu_page(
            'websocket-realtime-engine',
            'Channels',
            'Channels',
            'websocket.access',
            'websocket-channels',
            [$this, 'renderChannels']
        );
        
        add_submenu_page(
            'websocket-realtime-engine',
            'Broadcast',
            'Broadcast',
            'websocket.broadcast',
            'websocket-broadcast',
            [$this, 'renderBroadcast']
        );
        
        add_submenu_page(
            'websocket-realtime-engine',
            'Statistics',
            'Statistics',
            'websocket.access',
            'websocket-statistics',
            [$this, 'renderStatistics']
        );
        
        add_submenu_page(
            'websocket-realtime-engine',
            'Settings',
            'Settings',
            'websocket.configure',
            'websocket-settings',
            [$this, 'renderSettings']
        );
    }
    
    /**
     * Inject WebSocket configuration
     */
    public function injectWebSocketConfig(): void
    {
        $config = [
            'url' => $this->getWebSocketUrl(),
            'auth_endpoint' => rest_url('api/v1/websocket/auth'),
            'auth_headers' => [
                'X-WP-Nonce' => wp_create_nonce('wp_rest')
            ],
            'options' => $this->getClientOptions()
        ];
        
        ?>
        <script>
            window.WebSocketConfig = <?php echo json_encode($config); ?>;
        </script>
        <?php
    }
    
    /**
     * Get WebSocket URL
     */
    private function getWebSocketUrl(): string
    {
        $config = $this->getOption('server_config', []);
        $protocol = ($config['ssl'] ?? false) ? 'wss' : 'ws';
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 6001;
        
        return "{$protocol}://{$host}:{$port}";
    }
    
    /**
     * Create required directories
     */
    private function createDirectories(): void
    {
        $dirs = [
            $this->getPluginPath() . '/logs',
            $this->getPluginPath() . '/stats',
            $this->getPluginPath() . '/clients',
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