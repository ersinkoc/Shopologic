<?php
namespace SupportHub;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\Hook;

/**
 * Customer Support Hub Plugin
 * 
 * Integrated customer support with live chat, tickets, FAQ automation, and AI assistance
 */
class SupportHubPlugin extends AbstractPlugin
{
    private $ticketManager;
    private $chatEngine;
    private $knowledgeBase;
    private $autoResponder;
    private $supportAnalytics;

    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->initializeSupportChannels();
    }

    private function registerServices(): void
    {
        $this->ticketManager = new Services\TicketManager($this->api);
        $this->chatEngine = new Services\ChatEngine($this->api);
        $this->knowledgeBase = new Services\KnowledgeBase($this->api);
        $this->autoResponder = new Services\AutoResponder($this->api);
        $this->supportAnalytics = new Services\SupportAnalytics($this->api);
    }

    private function registerHooks(): void
    {
        // Frontend integration
        Hook::addAction('frontend.footer', [$this, 'renderChatWidget'], 10);
        Hook::addFilter('frontend.help_center', [$this, 'renderHelpCenter'], 10, 1);
        Hook::addAction('frontend.chat_widget', [$this, 'initializeChatWidget'], 10);
        
        // Support ticket lifecycle
        Hook::addAction('support.ticket_created', [$this, 'processNewTicket'], 10, 1);
        Hook::addAction('support.ticket_replied', [$this, 'handleTicketReply'], 10, 2);
        Hook::addAction('support.ticket_closed', [$this, 'onTicketClosed'], 10, 1);
        Hook::addAction('support.ticket_escalated', [$this, 'escalateTicket'], 10, 1);
        
        // Chat events
        Hook::addAction('chat.message_received', [$this, 'processChatMessage'], 10, 2);
        Hook::addAction('chat.conversation_started', [$this, 'onChatStarted'], 10, 1);
        Hook::addAction('chat.agent_assigned', [$this, 'notifyAgentAssignment'], 10, 2);
        
        // Order issue integration
        Hook::addAction('order.issue_reported', [$this, 'createOrderTicket'], 10, 2);
        Hook::addFilter('order.actions', [$this, 'addSupportActions'], 10, 2);
        
        // Knowledge base
        Hook::addFilter('search.results', [$this, 'includeKnowledgeBase'], 10, 2);
        Hook::addAction('faq.article_viewed', [$this, 'trackArticleView'], 10, 2);
        
        // Admin interface
        Hook::addAction('admin.support.dashboard', [$this, 'supportDashboard'], 10);
        Hook::addFilter('admin.header.notifications', [$this, 'addSupportNotifications'], 10, 1);
    }

    public function renderChatWidget(): void
    {
        if (!$this->getConfig('enable_live_chat', true)) {
            return;
        }
        
        $customer = $this->api->auth()->user();
        $businessHours = $this->getBusinessHours();
        $isOnline = $this->isWithinBusinessHours($businessHours);
        
        echo $this->api->view('support/chat-widget', [
            'customer' => $customer,
            'is_online' => $isOnline,
            'business_hours' => $businessHours,
            'position' => 'bottom-right',
            'initial_message' => $this->getGreetingMessage($customer, $isOnline),
            'suggested_topics' => $this->getSuggestedTopics(),
            'chat_config' => [
                'enable_file_upload' => true,
                'enable_emojis' => true,
                'enable_typing_indicator' => true,
                'enable_read_receipts' => true,
                'sound_notifications' => true
            ]
        ]);
    }

    public function processNewTicket($ticket): void
    {
        // Categorize ticket
        $category = $this->categorizeTicket($ticket);
        $ticket->category = $category['category'];
        $ticket->subcategory = $category['subcategory'];
        $ticket->priority = $this->calculatePriority($ticket);
        
        // Check for auto-response
        if ($this->getConfig('auto_response_enabled', true)) {
            $autoResponse = $this->autoResponder->generateResponse($ticket);
            
            if ($autoResponse && $autoResponse['confidence'] > 0.8) {
                $this->sendAutoResponse($ticket, $autoResponse);
                
                // Mark as potentially resolved
                if ($autoResponse['resolves_issue']) {
                    $ticket->status = 'pending_customer_confirmation';
                }
            }
        }
        
        // Search knowledge base for solutions
        $suggestions = $this->knowledgeBase->findSolutions($ticket->subject . ' ' . $ticket->message);
        if (!empty($suggestions)) {
            $this->attachSuggestedArticles($ticket, $suggestions);
        }
        
        // Assign to appropriate agent
        $agent = $this->findBestAgent($ticket);
        if ($agent) {
            $this->assignTicketToAgent($ticket, $agent);
        }
        
        // Set SLA timers
        $this->setSLATimers($ticket);
        
        // Track in analytics
        $this->supportAnalytics->trackNewTicket($ticket);
    }

    public function processChatMessage($message, $conversation): void
    {
        // Check if agent is assigned
        if (!$conversation->agent_id) {
            // Try to handle with AI first
            if ($this->shouldUseAIResponse($message)) {
                $aiResponse = $this->generateAIResponse($message, $conversation);
                
                if ($aiResponse) {
                    $this->sendChatResponse($conversation, $aiResponse, 'ai_assistant');
                    
                    // Check if AI resolved the issue
                    if ($aiResponse['resolved']) {
                        $this->offerHumanAssistance($conversation);
                        return;
                    }
                }
            }
            
            // Assign to human agent
            $this->assignChatToAgent($conversation);
        }
        
        // Process message content
        $this->detectSentiment($message);
        $this->extractEntities($message);
        
        // Check for escalation triggers
        if ($this->shouldEscalate($message, $conversation)) {
            $this->escalateConversation($conversation);
        }
        
        // Update conversation context
        $this->updateConversationContext($conversation, $message);
    }

    public function createOrderTicket($order, $issue): void
    {
        $ticket = $this->ticketManager->create([
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'subject' => $this->generateTicketSubject($order, $issue),
            'message' => $issue['description'],
            'category' => 'order_issue',
            'subcategory' => $issue['type'],
            'priority' => $this->calculateOrderIssuePriority($order, $issue),
            'attachments' => $issue['attachments'] ?? [],
            'metadata' => [
                'order_total' => $order->total,
                'order_date' => $order->created_at,
                'issue_type' => $issue['type'],
                'affected_items' => $issue['items'] ?? []
            ]
        ]);
        
        // Auto-assign based on issue type
        $this->autoAssignOrderTicket($ticket, $issue['type']);
        
        // Send confirmation
        $this->sendTicketConfirmation($ticket);
        
        Hook::doAction('support.ticket_created', $ticket);
    }

    public function supportDashboard(): void
    {
        $metrics = [
            'overview' => $this->getSupportOverview(),
            'ticket_stats' => $this->ticketManager->getStatistics(),
            'chat_metrics' => $this->chatEngine->getMetrics(),
            'response_times' => $this->supportAnalytics->getResponseTimes(),
            'satisfaction_scores' => $this->supportAnalytics->getSatisfactionScores(),
            'agent_performance' => $this->getAgentPerformance(),
            'trending_issues' => $this->identifyTrendingIssues(),
            'knowledge_base_stats' => $this->knowledgeBase->getStatistics(),
            'sla_compliance' => $this->calculateSLACompliance(),
            'channel_distribution' => $this->getChannelDistribution()
        ];
        
        echo $this->api->view('support/admin-dashboard', $metrics);
    }

    public function renderHelpCenter($content): string
    {
        $categories = $this->knowledgeBase->getCategories();
        $popularArticles = $this->knowledgeBase->getPopularArticles(10);
        $recentArticles = $this->knowledgeBase->getRecentArticles(5);
        
        $helpCenter = $this->api->view('support/help-center', [
            'categories' => $categories,
            'popular_articles' => $popularArticles,
            'recent_articles' => $recentArticles,
            'search_enabled' => true,
            'contact_options' => $this->getContactOptions(),
            'business_hours' => $this->getBusinessHours(),
            'estimated_response_time' => $this->getEstimatedResponseTime()
        ]);
        
        return $content . $helpCenter;
    }

    private function categorizeTicket($ticket): array
    {
        $keywords = $this->extractKeywords($ticket->subject . ' ' . $ticket->message);
        
        $categories = [
            'order_issue' => ['order', 'shipping', 'delivery', 'tracking', 'refund', 'return'],
            'product_inquiry' => ['product', 'item', 'specification', 'availability', 'size'],
            'technical_support' => ['login', 'password', 'account', 'error', 'website', 'bug'],
            'billing' => ['payment', 'charge', 'invoice', 'billing', 'subscription'],
            'general' => []
        ];
        
        $scores = [];
        foreach ($categories as $category => $terms) {
            $score = 0;
            foreach ($terms as $term) {
                if (in_array($term, $keywords)) {
                    $score++;
                }
            }
            $scores[$category] = $score;
        }
        
        arsort($scores);
        $topCategory = key($scores);
        
        return [
            'category' => $topCategory,
            'subcategory' => $this->determineSubcategory($topCategory, $keywords),
            'confidence' => $scores[$topCategory] / max(1, count($keywords))
        ];
    }

    private function calculatePriority($ticket): string
    {
        $priority = 'normal';
        
        // Check customer value
        $customer = $this->api->service('CustomerRepository')->find($ticket->customer_id);
        if ($customer && $this->isVIPCustomer($customer)) {
            $priority = 'high';
        }
        
        // Check for urgent keywords
        $urgentKeywords = ['urgent', 'asap', 'immediately', 'emergency', 'critical'];
        $message = strtolower($ticket->subject . ' ' . $ticket->message);
        
        foreach ($urgentKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                $priority = 'urgent';
                break;
            }
        }
        
        // Check order value if order-related
        if ($ticket->order_id) {
            $order = $this->api->service('OrderRepository')->find($ticket->order_id);
            if ($order && $order->total > 500) {
                $priority = max($priority, 'high');
            }
        }
        
        return $priority;
    }

    private function generateAIResponse($message, $conversation): ?array
    {
        // Analyze message intent
        $intent = $this->analyzeIntent($message);
        
        // Get conversation context
        $context = $this->getConversationContext($conversation);
        
        // Search knowledge base
        $relevantArticles = $this->knowledgeBase->searchArticles($message->content, 3);
        
        // Generate response based on intent
        $response = null;
        
        switch ($intent['type']) {
            case 'order_status':
                $response = $this->generateOrderStatusResponse($intent, $context);
                break;
                
            case 'product_question':
                $response = $this->generateProductResponse($intent, $context);
                break;
                
            case 'return_request':
                $response = $this->generateReturnResponse($intent, $context);
                break;
                
            case 'faq':
                if (!empty($relevantArticles)) {
                    $response = $this->generateFAQResponse($relevantArticles);
                }
                break;
        }
        
        if ($response) {
            $response['intent'] = $intent;
            $response['confidence'] = $this->calculateResponseConfidence($response, $intent);
        }
        
        return $response;
    }

    private function findBestAgent($ticket): ?object
    {
        $availableAgents = $this->getAvailableAgents();
        
        if (empty($availableAgents)) {
            return null;
        }
        
        $scores = [];
        
        foreach ($availableAgents as $agent) {
            $score = 0;
            
            // Check expertise match
            if (in_array($ticket->category, $agent->expertise)) {
                $score += 50;
            }
            
            // Check current workload
            $activeTickets = $this->ticketManager->getAgentActiveTickets($agent->id);
            $score -= count($activeTickets) * 5;
            
            // Check performance rating
            $score += $agent->performance_rating * 10;
            
            // Check language match
            if ($this->matchesLanguage($agent, $ticket)) {
                $score += 20;
            }
            
            $scores[$agent->id] = $score;
        }
        
        arsort($scores);
        $bestAgentId = key($scores);
        
        return $availableAgents[$bestAgentId] ?? null;
    }

    private function setSLATimers($ticket): void
    {
        $slaRules = $this->getSLARules($ticket->priority);
        
        $timers = [
            'first_response_due' => date('Y-m-d H:i:s', strtotime('+' . $slaRules['first_response'] . ' hours')),
            'resolution_due' => date('Y-m-d H:i:s', strtotime('+' . $slaRules['resolution'] . ' hours')),
            'escalation_due' => date('Y-m-d H:i:s', strtotime('+' . $slaRules['escalation'] . ' hours'))
        ];
        
        $this->ticketManager->setSLATimers($ticket->id, $timers);
        
        // Schedule SLA reminders
        $this->scheduleSLAReminders($ticket, $timers);
    }

    private function getBusinessHours(): array
    {
        return [
            'monday' => ['start' => $this->getConfig('business_hours_start', '09:00'), 'end' => $this->getConfig('business_hours_end', '18:00')],
            'tuesday' => ['start' => $this->getConfig('business_hours_start', '09:00'), 'end' => $this->getConfig('business_hours_end', '18:00')],
            'wednesday' => ['start' => $this->getConfig('business_hours_start', '09:00'), 'end' => $this->getConfig('business_hours_end', '18:00')],
            'thursday' => ['start' => $this->getConfig('business_hours_start', '09:00'), 'end' => $this->getConfig('business_hours_end', '18:00')],
            'friday' => ['start' => $this->getConfig('business_hours_start', '09:00'), 'end' => $this->getConfig('business_hours_end', '18:00')],
            'saturday' => ['closed' => true],
            'sunday' => ['closed' => true]
        ];
    }

    private function initializeSupportChannels(): void
    {
        // Process ticket queue
        $this->api->scheduler()->addJob('process_ticket_queue', '* * * * *', function() {
            $this->ticketManager->processQueue();
        });
        
        // Check SLA compliance
        $this->api->scheduler()->addJob('check_sla_compliance', '*/15 * * * *', function() {
            $this->checkSLACompliance();
        });
        
        // Update agent availability
        $this->api->scheduler()->addJob('update_agent_availability', '*/5 * * * *', function() {
            $this->updateAgentAvailability();
        });
        
        // Generate support reports
        $this->api->scheduler()->addJob('generate_support_reports', '0 6 * * *', function() {
            $this->supportAnalytics->generateDailyReport();
        });
    }

    private function registerRoutes(): void
    {
        // Ticket endpoints
        $this->api->router()->post('/support/tickets', 'Controllers\TicketController@create');
        $this->api->router()->get('/support/tickets/{id}', 'Controllers\TicketController@show');
        $this->api->router()->post('/support/tickets/{id}/reply', 'Controllers\TicketController@reply');
        $this->api->router()->put('/support/tickets/{id}/close', 'Controllers\TicketController@close');
        
        // Chat endpoints
        $this->api->router()->post('/support/chat/start', 'Controllers\ChatController@startConversation');
        $this->api->router()->post('/support/chat/{id}/message', 'Controllers\ChatController@sendMessage');
        $this->api->router()->get('/support/chat/{id}/messages', 'Controllers\ChatController@getMessages');
        
        // FAQ endpoints
        $this->api->router()->post('/support/faq/search', 'Controllers\FAQController@search');
        $this->api->router()->get('/support/knowledge-base', 'Controllers\FAQController@getKnowledgeBase');
        $this->api->router()->post('/support/faq/{id}/helpful', 'Controllers\FAQController@markHelpful');
    }

    public function install(): void
    {
        $this->runMigrations();
        $this->createDefaultFAQCategories();
        $this->setupAutoResponses();
        $this->createSupportRoles();
    }

    private function createDefaultFAQCategories(): void
    {
        $categories = [
            ['name' => 'Orders & Shipping', 'slug' => 'orders-shipping', 'icon' => 'truck'],
            ['name' => 'Returns & Refunds', 'slug' => 'returns-refunds', 'icon' => 'return'],
            ['name' => 'Account & Login', 'slug' => 'account-login', 'icon' => 'user'],
            ['name' => 'Products', 'slug' => 'products', 'icon' => 'box'],
            ['name' => 'Payment', 'slug' => 'payment', 'icon' => 'credit-card']
        ];

        foreach ($categories as $category) {
            $this->api->database()->table('faq_categories')->insert($category);
        }
    }

    private function setupAutoResponses(): void
    {
        $responses = [
            [
                'trigger' => 'order_status',
                'response' => 'I can help you track your order. Please provide your order number.',
                'follow_up_action' => 'request_order_number'
            ],
            [
                'trigger' => 'return_request',
                'response' => 'I understand you want to return an item. Our return policy allows returns within 30 days.',
                'follow_up_action' => 'provide_return_instructions'
            ]
        ];

        foreach ($responses as $response) {
            $this->api->database()->table('auto_responses')->insert($response);
        }
    }

    private function createSupportRoles(): void
    {
        $roles = [
            ['name' => 'support_agent', 'display_name' => 'Support Agent'],
            ['name' => 'support_lead', 'display_name' => 'Support Team Lead'],
            ['name' => 'support_manager', 'display_name' => 'Support Manager']
        ];

        foreach ($roles as $role) {
            $this->api->service('RoleService')->create($role);
        }
    }
}