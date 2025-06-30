<?php

declare(strict_types=1);

namespace Shopologic\Plugins\RealtimeCollaboration;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\HookSystem;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use RealtimeCollaboration\Services\{
    WebSocketServer,
    PresenceManager,
    CollaborationEngine,
    WebRTCSignaling,
    DocumentSyncService,
    ActivityTracker,
    ConflictResolver,;
    RecordingService;
};

class RealtimeCollaborationPlugin extends AbstractPlugin
{
    private WebSocketServer $wsServer;
    private PresenceManager $presenceManager;
    private CollaborationEngine $collaborationEngine;
    private WebRTCSignaling $webrtcSignaling;
    private DocumentSyncService $documentSync;
    private ActivityTracker $activityTracker;
    private ConflictResolver $conflictResolver;
    private RecordingService $recordingService;
    
    /**
     * Plugin activation
     */
    public function activate(): void
    {
        // Run migrations
        $this->runMigrations();
        
        // Initialize WebSocket server configuration
        $this->initializeWebSocketConfig();
        
        // Set default options
        $this->setDefaultOptions();
        
        // Create required directories
        $this->createDirectories();
        
        // Install default room templates
        $this->installRoomTemplates();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        // Close all active connections
        $this->closeAllConnections();
        
        // Stop WebSocket server if running
        $this->stopWebSocketServer();
        
        // Clean up temporary files
        $this->cleanupTempFiles();
    }
    
    /**
     * Register hooks
     */
    protected function registerHooks(): void
    {
        // Initialize collaboration services
        HookSystem::addAction('init', [$this, 'initializeServices']);
        
        // WebSocket server management
        HookSystem::addAction('admin_init', [$this, 'checkWebSocketServer']);
        
        // Frontend assets
        HookSystem::addAction('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
        HookSystem::addAction('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        
        // User presence hooks
        HookSystem::addAction('wp_login', [$this, 'userLogin'], 10, 2);
        HookSystem::addAction('wp_logout', [$this, 'userLogout']);
        HookSystem::addAction('heartbeat_tick', [$this, 'updatePresence']);
        
        // Content collaboration hooks
        HookSystem::addFilter('the_content', [$this, 'enableCollaboration'], 999);
        HookSystem::addAction('save_post', [$this, 'syncCollaborativeChanges'], 10, 3);
        
        // Admin interface
        HookSystem::addAction('admin_menu', [$this, 'registerAdminMenu']);
        
        // API endpoints
        $this->registerApiEndpoints();
        
        // WebRTC signaling
        HookSystem::addAction('wp_ajax_webrtc_signal', [$this, 'handleWebRTCSignal']);
        HookSystem::addAction('wp_ajax_nopriv_webrtc_signal', [$this, 'handleWebRTCSignal']);
        
        // Activity tracking
        HookSystem::addAction('collaboration_activity', [$this, 'trackActivity'], 10, 3);
        
        // Scheduled tasks
        HookSystem::addAction('collaboration_cleanup_sessions', [$this, 'cleanupInactiveSessions']);
        HookSystem::addAction('collaboration_process_recordings', [$this, 'processRecordings']);
    }
    
    /**
     * Initialize collaboration services
     */
    public function initializeServices(): void
    {
        // Initialize core services
        $this->wsServer = new WebSocketServer($this->getOption('websocket_server'));
        $this->presenceManager = new PresenceManager($this->container);
        $this->collaborationEngine = new CollaborationEngine($this->container);
        $this->webrtcSignaling = new WebRTCSignaling($this->getOption('webrtc_config'));
        $this->documentSync = new DocumentSyncService($this->getOption('conflict_resolution', 'ot'));
        $this->activityTracker = new ActivityTracker($this->container);
        $this->conflictResolver = new ConflictResolver($this->getOption('conflict_resolution', 'ot'));
        $this->recordingService = new RecordingService($this->container);
        
        // Start WebSocket server if not running
        if ($this->shouldStartWebSocketServer()) {
            $this->startWebSocketServer();
        }
        
        // Initialize presence tracking
        if ($this->getOption('enable_presence', true)) {
            $this->presenceManager->initialize();
        }
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueueFrontendAssets(): void
    {
        if (!$this->shouldLoadCollaborationAssets()) {
            return;
        }
        
        // Core collaboration script
        wp_enqueue_script(
            'collaboration-core',
            $this->getAssetUrl('js/collaboration-core.js'),
            ['jquery'],
            $this->getVersion(),
            true
        );
        
        // WebSocket client
        wp_enqueue_script(
            'websocket-client',
            $this->getAssetUrl('js/websocket-client.js'),
            ['collaboration-core'],
            $this->getVersion(),
            true
        );
        
        // Presence manager
        if ($this->getOption('enable_presence', true)) {
            wp_enqueue_script(
                'presence-manager',
                $this->getAssetUrl('js/presence-manager.js'),
                ['collaboration-core'],
                $this->getVersion(),
                true
            );
        }
        
        // WebRTC handler
        if ($this->getOption('enable_voice_chat', true) || $this->getOption('enable_video_chat', true)) {
            wp_enqueue_script(
                'webrtc-handler',
                $this->getAssetUrl('js/webrtc-handler.js'),
                ['collaboration-core'],
                $this->getVersion(),
                true
            );
        }
        
        // Localize scripts
        wp_localize_script('collaboration-core', 'collaborationConfig', [
            'wsUrl' => $this->getOption('websocket_server'),
            'apiUrl' => rest_url('collaboration/v1'),
            'nonce' => wp_create_nonce('collaboration-nonce'),
            'userId' => get_current_user_id(),
            'userName' => wp_get_current_user()->display_name ?? 'Anonymous',
            'userAvatar' => get_avatar_url(get_current_user_id()),
            'features' => [
                'presence' => $this->getOption('enable_presence', true),
                'cursors' => $this->getOption('enable_cursors', true),
                'voice' => $this->getOption('enable_voice_chat', true),
                'video' => $this->getOption('enable_video_chat', true),
                'screen' => $this->getOption('enable_screen_sharing', true),
                'chat' => $this->getOption('enable_chat', true),
                'files' => $this->getOption('enable_file_sharing', true)
            ],
            'webrtc' => $this->getOption('webrtc_config'),
            'i18n' => $this->getTranslations()
        ]);
        
        // Styles
        wp_enqueue_style(
            'collaboration-styles',
            $this->getAssetUrl('css/collaboration.css'),
            [],
            $this->getVersion()
        );
    }
    
    /**
     * Enable collaboration on content
     */
    public function enableCollaboration(string $content): string
    {
        if (!$this->isCollaborativeContent()) {
            return $content;
        }
        
        // Get or create collaboration room
        $roomId = $this->getOrCreateRoom();
        
        // Add collaboration wrapper
        $collaborationHtml = '<div class="collaboration-wrapper" data-room-id="' . esc_attr($roomId) . '">';
        
        // Add presence indicators
        if ($this->getOption('enable_presence', true)) {
            $collaborationHtml .= '<div class="collaboration-presence"></div>';
        }
        
        // Add collaborative content area
        $collaborationHtml .= '<div class="collaboration-content" data-sync="true">';
        $collaborationHtml .= $content;
        $collaborationHtml .= '</div>';
        
        // Add collaboration toolbar
        $collaborationHtml .= $this->renderCollaborationToolbar($roomId);
        
        $collaborationHtml .= '</div>';
        
        return $collaborationHtml;
    }
    
    /**
     * Handle WebRTC signaling
     */
    public function handleWebRTCSignal(): void
    {
        // Verify nonce
        if (!check_ajax_referer('collaboration-nonce', 'nonce', false)) {
            wp_die('Security check failed');
        }
        
        // Get signal data
        $type = sanitize_text_field($request->input('type'] ?? '');
        $roomId = sanitize_text_field($request->input('room_id'] ?? '');
        $targetUserId = intval($request->input('target_user_id'] ?? 0);
        $data = json_decode(stripslashes($request->input('data'] ?? '{}'), true);
        
        // Process signal
        $result = $this->webrtcSignaling->processSignal($type, $roomId, get_current_user_id(), $targetUserId, $data);
        
        if ($result) {
            wp_send_json_success(['message' => 'Signal processed']);
        } else {
            wp_send_json_error(['message' => 'Failed to process signal']);
        }
    }
    
    /**
     * Start WebSocket server
     */
    private function startWebSocketServer(): void
    {
        // Configure server
        $this->wsServer->configure([
            'host' => '0.0.0.0',
            'port' => 8080,
            'ssl' => false,
            'max_connections' => 1000,
            'heartbeat_interval' => 30
        ]);
        
        // Register message handlers
        $this->registerWebSocketHandlers();
        
        // Start server in background
        $this->wsServer->start();
        
        // Log server start
        $this->log('WebSocket server started', 'info');
    }
    
    /**
     * Register WebSocket message handlers
     */
    private function registerWebSocketHandlers(): void
    {
        // Connection handlers
        $this->wsServer->on('connection', [$this, 'handleConnection']);
        $this->wsServer->on('disconnect', [$this, 'handleDisconnect']);
        
        // Room handlers
        $this->wsServer->on('room.join', [$this, 'handleRoomJoin']);
        $this->wsServer->on('room.leave', [$this, 'handleRoomLeave']);
        
        // Presence handlers
        $this->wsServer->on('presence.update', [$this, 'handlePresenceUpdate']);
        $this->wsServer->on('cursor.move', [$this, 'handleCursorMove']);
        
        // Document sync handlers
        $this->wsServer->on('doc.operation', [$this, 'handleDocumentOperation']);
        $this->wsServer->on('doc.sync', [$this, 'handleDocumentSync']);
        
        // WebRTC handlers
        $this->wsServer->on('webrtc.offer', [$this, 'handleWebRTCOffer']);
        $this->wsServer->on('webrtc.answer', [$this, 'handleWebRTCAnswer']);
        $this->wsServer->on('webrtc.ice', [$this, 'handleWebRTCIce']);
        
        // Chat handlers
        $this->wsServer->on('chat.message', [$this, 'handleChatMessage']);
        $this->wsServer->on('chat.typing', [$this, 'handleTypingIndicator']);
        
        // File sharing handlers
        $this->wsServer->on('file.share', [$this, 'handleFileShare']);
    }
    
    /**
     * Render collaboration toolbar
     */
    private function renderCollaborationToolbar(string $roomId): string
    {
        $toolbar = '<div class="collaboration-toolbar">';
        
        // Voice chat button
        if ($this->getOption('enable_voice_chat', true)) {
            $toolbar .= '<button class="collab-btn voice-chat" data-room="' . esc_attr($roomId) . '">';
            $toolbar .= '<i class="dashicons dashicons-microphone"></i>';
            $toolbar .= '</button>';
        }
        
        // Video chat button
        if ($this->getOption('enable_video_chat', true)) {
            $toolbar .= '<button class="collab-btn video-chat" data-room="' . esc_attr($roomId) . '">';
            $toolbar .= '<i class="dashicons dashicons-video-alt2"></i>';
            $toolbar .= '</button>';
        }
        
        // Screen share button
        if ($this->getOption('enable_screen_sharing', true)) {
            $toolbar .= '<button class="collab-btn screen-share" data-room="' . esc_attr($roomId) . '">';
            $toolbar .= '<i class="dashicons dashicons-desktop"></i>';
            $toolbar .= '</button>';
        }
        
        // Chat button
        if ($this->getOption('enable_chat', true)) {
            $toolbar .= '<button class="collab-btn text-chat" data-room="' . esc_attr($roomId) . '">';
            $toolbar .= '<i class="dashicons dashicons-format-chat"></i>';
            $toolbar .= '<span class="chat-badge" style="display:none;">0</span>';
            $toolbar .= '</button>';
        }
        
        // File share button
        if ($this->getOption('enable_file_sharing', true)) {
            $toolbar .= '<button class="collab-btn file-share" data-room="' . esc_attr($roomId) . '">';
            $toolbar .= '<i class="dashicons dashicons-paperclip"></i>';
            $toolbar .= '</button>';
        }
        
        // Users indicator
        $toolbar .= '<div class="collab-users">';
        $toolbar .= '<i class="dashicons dashicons-groups"></i>';
        $toolbar .= '<span class="user-count">1</span>';
        $toolbar .= '</div>';
        
        $toolbar .= '</div>';
        
        return $toolbar;
    }
    
    /**
     * Create required directories
     */
    private function createDirectories(): void
    {
        $dirs = [
            $this->getPluginPath() . '/recordings',
            $this->getPluginPath() . '/recordings/audio',
            $this->getPluginPath() . '/recordings/video',
            $this->getPluginPath() . '/shared-files',
            $this->getPluginPath() . '/temp',
            $this->getPluginPath() . '/logs'
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