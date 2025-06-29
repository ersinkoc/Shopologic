<?php
declare(strict_types=1);

namespace LiveChat;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\PluginInterface;
use Shopologic\Core\Hook\HookSystem;
use LiveChat\Services\ChatService;
use LiveChat\Services\AgentService;
use LiveChat\Services\VisitorService;
use LiveChat\Services\AnalyticsService;

/**
 * Live Chat Support Plugin
 * 
 * Real-time customer support chat with agent dashboard, canned responses,
 * file sharing, visitor tracking, and chatbot integration
 */
class LiveChatPlugin extends AbstractPlugin implements PluginInterface
{
    protected string $name = 'live-chat';
    protected string $version = '1.0.0';
    
    /**
     * Plugin installation
     */
    public function install(): bool
    {
        $this->runMigrations();
        $this->setDefaultConfig();
        $this->createDefaultResponses();
        return true;
    }
    
    /**
     * Plugin activation
     */
    public function activate(): bool
    {
        $this->initializeChatSystem();
        return true;
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate(): bool
    {
        $this->pauseChatSystem();
        return true;
    }
    
    /**
     * Plugin uninstall
     */
    public function uninstall(): bool
    {
        if ($this->confirmDataRemoval()) {
            $this->dropTables();
            $this->removeConfig();
        }
        return true;
    }
    
    /**
     * Plugin update
     */
    public function update(string $previousVersion): bool
    {
        $this->runUpdateMigrations($previousVersion);
        return true;
    }
    
    /**
     * Plugin boot
     */
    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->registerWebSocketEvents();
        $this->registerWidgets();
        $this->registerPermissions();
    }
    
    /**
     * Register plugin services
     */
    protected function registerServices(): void
    {
        // Chat service
        $this->container->singleton(ChatService::class, function ($container) {
            return new ChatService(
                $container->get('db'),
                $container->get('websocket'),
                $this->getConfig()
            );
        });
        
        // Agent service
        $this->container->singleton(AgentService::class, function ($container) {
            return new AgentService(
                $container->get('db'),
                $container->get('events'),
                $this->getConfig('agent_assignment_strategy', 'round_robin')
            );
        });
        
        // Visitor service
        $this->container->singleton(VisitorService::class, function ($container) {
            return new VisitorService(
                $container->get('db'),
                $container->get('cache'),
                $this->getConfig('visitor_tracking', true)
            );
        });
        
        // Analytics service
        $this->container->singleton(AnalyticsService::class, function ($container) {
            return new AnalyticsService(
                $container->get('db'),
                $this->getConfig()
            );
        });
    }
    
    /**
     * Register plugin hooks
     */
    protected function registerHooks(): void
    {
        // Chat widget injection
        HookSystem::addAction('page.footer', [$this, 'injectChatWidget'], 100);
        
        // Admin menu
        HookSystem::addAction('admin.menu', [$this, 'addChatMenu'], 40);
        
        // Visitor tracking
        HookSystem::addAction('user.online', [$this, 'trackVisitor'], 10);
        
        // Order notifications
        HookSystem::addAction('order.placed', [$this, 'notifyAgents'], 50);
        
        // Customer events
        HookSystem::addAction('customer.login', [$this, 'updateCustomerStatus'], 10);
        HookSystem::addAction('customer.logout', [$this, 'updateCustomerStatus'], 10);
        
        // Agent availability
        HookSystem::addAction('user.login', [$this, 'updateAgentAvailability'], 10);
        HookSystem::addAction('user.logout', [$this, 'updateAgentAvailability'], 10);
    }
    
    /**
     * Register API routes
     */
    protected function registerRoutes(): void
    {
        // Widget configuration
        $this->registerRoute('GET', '/api/v1/chat/widget/config', 
            'LiveChat\\Controllers\\WidgetController@getConfig');
        
        // Conversation management
        $this->registerRoute('POST', '/api/v1/chat/conversations', 
            'LiveChat\\Controllers\\ChatController@startConversation');
        $this->registerRoute('GET', '/api/v1/chat/conversations/{id}', 
            'LiveChat\\Controllers\\ChatController@getConversation');
        $this->registerRoute('POST', '/api/v1/chat/conversations/{id}/messages', 
            'LiveChat\\Controllers\\ChatController@sendMessage');
        $this->registerRoute('POST', '/api/v1/chat/conversations/{id}/typing', 
            'LiveChat\\Controllers\\ChatController@updateTyping');
        $this->registerRoute('POST', '/api/v1/chat/conversations/{id}/end', 
            'LiveChat\\Controllers\\ChatController@endConversation');
        
        // Agent dashboard
        $this->registerRoute('GET', '/api/v1/chat/agent/conversations', 
            'LiveChat\\Controllers\\AgentController@getConversations');
        $this->registerRoute('POST', '/api/v1/chat/agent/status', 
            'LiveChat\\Controllers\\AgentController@updateStatus');
        $this->registerRoute('GET', '/api/v1/chat/agent/visitors', 
            'LiveChat\\Controllers\\AgentController@getVisitors');
        
        // File uploads
        $this->registerRoute('POST', '/api/v1/chat/upload', 
            'LiveChat\\Controllers\\FileController@upload');
        
        // Canned responses
        $this->registerRoute('GET', '/api/v1/chat/responses', 
            'LiveChat\\Controllers\\ResponseController@index');
        $this->registerRoute('POST', '/api/v1/chat/responses', 
            'LiveChat\\Controllers\\ResponseController@create');
        
        // Analytics
        $this->registerRoute('GET', '/api/v1/chat/analytics', 
            'LiveChat\\Controllers\\AnalyticsController@getStats');
    }
    
    /**
     * Register WebSocket events
     */
    protected function registerWebSocketEvents(): void
    {
        if (!$this->container->has('websocket')) {
            return;
        }
        
        $events = [
            'chat.message.send' => [$this, 'handleMessageSent'],
            'chat.typing.start' => [$this, 'handleTypingStart'],
            'chat.typing.stop' => [$this, 'handleTypingStop'],
            'chat.agent.join' => [$this, 'handleAgentJoin'],
            'chat.agent.leave' => [$this, 'handleAgentLeave'],
            'chat.visitor.online' => [$this, 'handleVisitorOnline'],
            'chat.visitor.offline' => [$this, 'handleVisitorOffline']
        ];
        
        foreach ($events as $event => $handler) {
            $this->container->get('websocket')->on($event, $handler);
        }
    }
    
    /**
     * Register dashboard widgets
     */
    protected function registerWidgets(): void
    {
        $this->registerWidget('chat_summary', Widgets\ChatSummaryWidget::class);
        $this->registerWidget('agent_status', Widgets\AgentStatusWidget::class);
        $this->registerWidget('active_chats', Widgets\ActiveChatsWidget::class);
    }
    
    /**
     * Register permissions
     */
    protected function registerPermissions(): void
    {
        $this->addPermission('chat.agent', 'Chat Agent');
        $this->addPermission('chat.supervisor', 'Chat Supervisor');
        $this->addPermission('chat.admin', 'Chat Administrator');
        $this->addPermission('chat.view_analytics', 'View Chat Analytics');
    }
    
    /**
     * Inject chat widget into page footer
     */
    public function injectChatWidget(): void
    {
        if (!$this->shouldShowChatWidget()) {
            return;
        }
        
        $chatService = $this->container->get(ChatService::class);
        $config = $chatService->getWidgetConfig();
        
        echo $this->render('widgets/chat-widget', [
            'config' => $config,
            'visitor_id' => $this->getVisitorId(),
            'is_customer' => $this->isCustomerLoggedIn()
        ]);
    }
    
    /**
     * Add chat menu to admin dashboard
     */
    public function addChatMenu(): void
    {
        if (!$this->hasPermission('chat.agent')) {
            return;
        }
        
        $this->addMenuItem([
            'title' => 'Live Chat',
            'icon' => 'chat',
            'url' => $this->adminUrl('chat'),
            'order' => 60,
            'badge' => $this->getActiveChatCount()
        ]);
    }
    
    /**
     * Track visitor activity
     */
    public function trackVisitor(array $data): void
    {
        if (!$this->getConfig('visitor_tracking', true)) {
            return;
        }
        
        $visitorService = $this->container->get(VisitorService::class);
        $visitorService->trackActivity($data);
    }
    
    /**
     * Notify agents of new order
     */
    public function notifyAgents(array $data): void
    {
        if (!$this->getConfig('notify_agents_on_order', true)) {
            return;
        }
        
        $order = $data['order'];
        $agentService = $this->container->get(AgentService::class);
        
        $message = "New order #{$order->id} placed by {$order->customer_name}";
        $agentService->broadcastNotification($message, 'order_placed');
    }
    
    /**
     * Update customer status
     */
    public function updateCustomerStatus(array $data): void
    {
        $customer = $data['customer'];
        $visitorService = $this->container->get(VisitorService::class);
        
        $visitorService->updateCustomerStatus($customer->id, $data['action'] === 'login');
    }
    
    /**
     * Update agent availability
     */
    public function updateAgentAvailability(array $data): void
    {
        $user = $data['user'];
        
        if (!$this->hasRole($user, 'chat_agent')) {
            return;
        }
        
        $agentService = $this->container->get(AgentService::class);
        $agentService->updateAvailability($user->id, $data['action'] === 'login');
    }
    
    /**
     * Handle WebSocket message sent event
     */
    public function handleMessageSent(array $data): void
    {
        $chatService = $this->container->get(ChatService::class);
        $chatService->broadcastMessage($data);
    }
    
    /**
     * Handle typing start event
     */
    public function handleTypingStart(array $data): void
    {
        $chatService = $this->container->get(ChatService::class);
        $chatService->broadcastTyping($data['conversation_id'], $data['user_id'], true);
    }
    
    /**
     * Handle typing stop event
     */
    public function handleTypingStop(array $data): void
    {
        $chatService = $this->container->get(ChatService::class);
        $chatService->broadcastTyping($data['conversation_id'], $data['user_id'], false);
    }
    
    /**
     * Handle agent joining conversation
     */
    public function handleAgentJoin(array $data): void
    {
        $agentService = $this->container->get(AgentService::class);
        $agentService->joinConversation($data['agent_id'], $data['conversation_id']);
    }
    
    /**
     * Handle agent leaving conversation
     */
    public function handleAgentLeave(array $data): void
    {
        $agentService = $this->container->get(AgentService::class);
        $agentService->leaveConversation($data['agent_id'], $data['conversation_id']);
    }
    
    /**
     * Check if chat widget should be shown
     */
    protected function shouldShowChatWidget(): bool
    {
        // Don't show if disabled
        if (!$this->getConfig('enable_chat_widget', true)) {
            return false;
        }
        
        // Don't show on admin pages
        if ($this->isAdminPage()) {
            return false;
        }
        
        // Check if agents are available
        if ($this->getConfig('hide_when_offline', false)) {
            $agentService = $this->container->get(AgentService::class);
            return $agentService->hasAvailableAgents();
        }
        
        return true;
    }
    
    /**
     * Get current visitor ID
     */
    protected function getVisitorId(): string
    {
        $visitorService = $this->container->get(VisitorService::class);
        return $visitorService->getVisitorId();
    }
    
    /**
     * Check if customer is logged in
     */
    protected function isCustomerLoggedIn(): bool
    {
        return $this->api->getCurrentUser() !== null;
    }
    
    /**
     * Get active chat count for menu badge
     */
    protected function getActiveChatCount(): int
    {
        $chatService = $this->container->get(ChatService::class);
        return $chatService->getActiveConversationCount();
    }
    
    /**
     * Initialize chat system
     */
    protected function initializeChatSystem(): void
    {
        $chatService = $this->container->get(ChatService::class);
        $chatService->initialize();
    }
    
    /**
     * Pause chat system
     */
    protected function pauseChatSystem(): void
    {
        $agentService = $this->container->get(AgentService::class);
        $agentService->setAllAgentsOffline();
    }
    
    /**
     * Create default canned responses
     */
    protected function createDefaultResponses(): void
    {
        $responses = [
            [
                'title' => 'Welcome Message',
                'content' => 'Hello! Welcome to our store. How can I help you today?',
                'category' => 'greetings',
                'shortcut' => 'welcome'
            ],
            [
                'title' => 'Order Status',
                'content' => 'Let me check your order status for you. Could you please provide your order number?',
                'category' => 'orders',
                'shortcut' => 'order-status'
            ],
            [
                'title' => 'Shipping Info',
                'content' => 'We offer free shipping on orders over $50. Standard shipping takes 3-5 business days.',
                'category' => 'shipping',
                'shortcut' => 'shipping'
            ],
            [
                'title' => 'Return Policy',
                'content' => 'We accept returns within 30 days of purchase. Items must be unused and in original packaging.',
                'category' => 'returns',
                'shortcut' => 'returns'
            ],
            [
                'title' => 'Technical Support',
                'content' => 'I\'ll connect you with our technical support team who can better assist you with this issue.',
                'category' => 'support',
                'shortcut' => 'tech-support'
            ]
        ];
        
        foreach ($responses as $response) {
            $this->api->database()->table('chat_responses')->insert($response);
        }
    }
    
    /**
     * Run database migrations
     */
    protected function runMigrations(): void
    {
        $migrations = [
            'create_chat_conversations_table.php',
            'create_chat_messages_table.php',
            'create_chat_participants_table.php',
            'create_chat_responses_table.php',
            'create_chat_visitors_table.php',
            'create_chat_agents_table.php',
            'create_chat_files_table.php',
            'create_chat_analytics_table.php'
        ];
        
        foreach ($migrations as $migration) {
            $this->api->runMigration($this->getPath('migrations/' . $migration));
        }
    }
    
    /**
     * Set default configuration
     */
    protected function setDefaultConfig(): void
    {
        $defaults = [
            'enable_chat_widget' => true,
            'widget_position' => 'bottom-right',
            'widget_theme' => 'default',
            'visitor_tracking' => true,
            'agent_assignment_strategy' => 'round_robin',
            'max_conversations_per_agent' => 5,
            'auto_close_inactive_after' => 30,
            'notify_agents_on_order' => true,
            'enable_file_uploads' => true,
            'max_file_size' => 10,
            'allowed_file_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
            'enable_chatbot' => false,
            'hide_when_offline' => false,
            'enable_visitor_info' => true,
            'enable_typing_indicators' => true,
            'enable_sound_notifications' => true,
            'conversation_rating' => true
        ];
        
        foreach ($defaults as $key => $value) {
            if ($this->getConfig($key) === null) {
                $this->setConfig($key, $value);
            }
        }
    }
}