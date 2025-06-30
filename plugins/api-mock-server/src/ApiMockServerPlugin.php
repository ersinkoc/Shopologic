<?php

declare(strict_types=1);

namespace Shopologic\Plugins\ApiMockServer;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\HookSystem;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use ApiMockServer\Services\{
    MockEngine,
    RequestRecorder,
    ResponseGenerator,
    ScenarioRunner,
    TemplateProcessor,
    SwaggerImporter,;
    WebhookSimulator,;
    ChaosEngine;
};

class ApiMockServerPlugin extends AbstractPlugin
{
    private MockEngine $mockEngine;
    private RequestRecorder $recorder;
    private ResponseGenerator $responseGenerator;
    private ScenarioRunner $scenarioRunner;
    private TemplateProcessor $templateProcessor;
    private ChaosEngine $chaosEngine;
    
    /**
     * Plugin activation
     */
    public function activate(): void
    {
        // Run migrations
        $this->runMigrations();
        
        // Install default mocks
        $this->installDefaultMocks();
        
        // Set default options
        $this->setDefaultOptions();
        
        // Create required directories
        $this->createDirectories();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        // Stop any active recordings
        $this->stopActiveRecordings();
        
        // Clear mock cache
        $this->clearMockCache();
    }
    
    /**
     * Register hooks
     */
    protected function registerHooks(): void
    {
        // Initialize mock server
        HookSystem::addAction('init', [$this, 'initializeMockServer']);
        
        // Intercept API requests for mocking
        HookSystem::addFilter('api.request.before', [$this, 'interceptApiRequest'], 1);
        
        // Record requests if enabled
        HookSystem::addAction('api.request.complete', [$this, 'recordRequest'], 999);
        
        // Admin interface
        HookSystem::addAction('admin_menu', [$this, 'registerAdminMenu']);
        HookSystem::addAction('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        
        // API endpoints registration
        $this->registerApiEndpoints();
        
        // Mock server route handler
        HookSystem::addAction('parse_request', [$this, 'handleMockRoutes'], 1);
        
        // Webhook processing
        HookSystem::addAction('mockserver_process_webhooks', [$this, 'processWebhookQueue']);
        
        // Chaos testing
        if ($this->getOption('enable_chaos_testing', false)) {
            HookSystem::addFilter('mockserver.response', [$this, 'applyChaosEngineering']);
        }
    }
    
    /**
     * Initialize mock server services
     */
    public function initializeMockServer(): void
    {
        // Initialize services
        $this->mockEngine = new MockEngine($this->container);
        $this->recorder = new RequestRecorder($this->container);
        $this->responseGenerator = new ResponseGenerator($this->container);
        $this->scenarioRunner = new ScenarioRunner($this->container);
        $this->templateProcessor = new TemplateProcessor($this->getOption('template_engine', 'twig'));
        $this->chaosEngine = new ChaosEngine($this->getOption('chaos_probability', 10));
        
        // Load active mocks into memory
        $this->mockEngine->loadMocks();
        
        // Start recording if enabled
        if ($this->getOption('enable_recording', true) && $this->recorder->isRecording()) {
            $this->recorder->startSession();
        }
    }
    
    /**
     * Intercept API requests for mocking
     */
    public function interceptApiRequest(Request $request): ?Response
    {
        // Check if this is a mock request
        if (!$this->shouldMockRequest($request)) {
            return null;
        }
        
        // Find matching mock
        $mock = $this->mockEngine->findMock(
            $request->getMethod(),
            $request->getUri()->getPath()
        );
        
        if (!$mock) {
            return null;
        }
        
        // Generate response
        $response = $this->generateMockResponse($mock, $request);
        
        // Apply latency if configured
        $this->applyLatency($mock);
        
        // Log the mock request
        $this->logMockRequest($request, $response, $mock);
        
        // Trigger webhook if configured
        $this->triggerWebhook($mock, $request, $response);
        
        return $response;
    }
    
    /**
     * Generate mock response
     */
    private function generateMockResponse(array $mock, Request $request): Response
    {
        // Extract variables from request
        $variables = $this->extractVariables($request, $mock);
        
        // Check conditions if any
        $responseData = $this->evaluateConditions($mock, $request, $variables);
        
        // Process template if enabled
        if ($this->getOption('response_templates', true)) {
            $responseData = $this->templateProcessor->process($responseData, $variables);
        }
        
        // Create response
        $response = new Response();
        $response->setStatusCode($responseData['status'] ?? 200);
        
        // Set headers
        foreach ($responseData['headers'] ?? [] as $name => $value) {
            $response->setHeader($name, $value);
        }
        
        // Set body
        $body = $responseData['body'] ?? '';
        if (is_array($body) || is_object($body)) {
            $response->json($body);
        } else {
            $response->setBody((string)$body);
        }
        
        // Add mock server headers
        $response->setHeader('X-Mock-Server', 'true');
        $response->setHeader('X-Mock-Id', (string)$mock['id']);
        
        return $response;
    }
    
    /**
     * Extract variables from request
     */
    private function extractVariables(Request $request, array $mock): array
    {
        $variables = [
            'timestamp' => time(),
            'now' => date('Y-m-d H:i:s'),
            'uuid' => $this->generateUuid(),
            'random' => rand(1000, 9999),
            'method' => $request->getMethod(),
            'path' => [],
            'query' => $request->getQueryParams(),
            'headers' => $request->getHeaders(),
            'body' => $this->parseRequestBody($request),
            'env' => $_ENV
        ];
        
        // Extract path parameters
        if (isset($mock['path_pattern'])) {
            preg_match($mock['path_pattern'], $request->getUri()->getPath(), $matches);
            foreach ($mock['path_params'] ?? [] as $index => $param) {
                $variables['path'][$param] = $matches[$index + 1] ?? '';
            }
        }
        
        // Add custom variables
        $customVars = $this->getCustomVariables($mock['collection_id'] ?? null);
        $variables = array_merge($variables, $customVars);
        
        return $variables;
    }
    
    /**
     * Record API request
     */
    public function recordRequest(Request $request, Response $response): void
    {
        if (!$this->getOption('enable_recording', true)) {
            return;
        }
        
        if (!$this->recorder->isRecording()) {
            return;
        }
        
        // Check recording filters
        if (!$this->matchesRecordingFilters($request)) {
            return;
        }
        
        // Record the request/response pair
        $this->recorder->record([
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'headers' => $request->getHeaders(),
            'query' => $request->getQueryParams(),
            'body' => $this->parseRequestBody($request),
            'response' => [
                'status' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
                'body' => $response->getBody()
            ],
            'timestamp' => microtime(true),
            'duration' => $this->getRequestDuration()
        ]);
    }
    
    /**
     * Handle mock routes
     */
    public function handleMockRoutes(): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Check if this is a mock endpoint
        if (!str_starts_with($requestUri, '/mock/')) {
            return;
        }
        
        // Create request object
        $request = Request::createFromGlobals();
        
        // Remove /mock prefix
        $mockPath = substr($request->getUri()->getPath(), 5);
        $request->getUri()->setPath($mockPath);
        
        // Find and execute mock
        $response = $this->interceptApiRequest($request);
        
        if (!$response) {
            $response = new Response();
            $response->setStatusCode(404);
            $response->json([
                'error' => 'Mock not found',
                'path' => $mockPath,
                'method' => $request->getMethod()
            ]);
        }
        
        // Send response
        $response->send();
        exit;
    }
    
    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        add_menu_page(
            'API Mock Server',
            'Mock Server',
            'mockserver.access',
            'api-mock-server',
            [$this, 'renderDashboard'],
            'dashicons-database-view',
            65
        );
        
        add_submenu_page(
            'api-mock-server',
            'Mocks',
            'Mocks',
            'mockserver.access',
            'api-mock-server',
            [$this, 'renderMocksPage']
        );
        
        add_submenu_page(
            'api-mock-server',
            'Recorder',
            'Recorder',
            'mockserver.record_requests',
            'mockserver-recorder',
            [$this, 'renderRecorderPage']
        );
        
        add_submenu_page(
            'api-mock-server',
            'Scenarios',
            'Scenarios',
            'mockserver.manage_scenarios',
            'mockserver-scenarios',
            [$this, 'renderScenariosPage']
        );
        
        add_submenu_page(
            'api-mock-server',
            'Logs',
            'Logs',
            'mockserver.view_logs',
            'mockserver-logs',
            [$this, 'renderLogsPage']
        );
        
        add_submenu_page(
            'api-mock-server',
            'Import/Export',
            'Import/Export',
            'mockserver.export_data',
            'mockserver-import-export',
            [$this, 'renderImportExportPage']
        );
        
        add_submenu_page(
            'api-mock-server',
            'Settings',
            'Settings',
            'mockserver.configure',
            'mockserver-settings',
            [$this, 'renderSettingsPage']
        );
    }
    
    /**
     * Install default mocks
     */
    private function installDefaultMocks(): void
    {
        $defaultMocks = [
            [
                'name' => 'Health Check',
                'method' => 'GET',
                'path' => '/health',
                'response' => [
                    'status' => 200,
                    'body' => [
                        'status' => 'healthy',
                        'timestamp' => '{{now}}',
                        'version' => '1.0.0'
                    ]
                ]
            ],
            [
                'name' => 'Echo Service',
                'method' => 'POST',
                'path' => '/echo',
                'response' => [
                    'status' => 200,
                    'headers' => [
                        'X-Echo-Time' => '{{timestamp}}'
                    ],
                    'body' => [
                        'echo' => '{{body}}',
                        'headers' => '{{headers}}',
                        'method' => '{{method}}'
                    ]
                ]
            ],
            [
                'name' => 'User List',
                'method' => 'GET',
                'path' => '/api/users',
                'response' => [
                    'status' => 200,
                    'body' => [
                        'users' => [
                            ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
                            ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com']
                        ],
                        'total' => 2,
                        'page' => '{{query.page|default:1}}'
                    ]
                ]
            ],
            [
                'name' => 'User Details',
                'method' => 'GET',
                'path' => '/api/users/{id}',
                'response' => [
                    'status' => 200,
                    'body' => [
                        'id' => '{{path.id}}',
                        'name' => 'User {{path.id}}',
                        'email' => 'user{{path.id}}@example.com',
                        'created_at' => '{{now}}'
                    ]
                ]
            ]
        ];
        
        foreach ($defaultMocks as $mock) {
            $this->mockEngine->createMock($mock);
        }
    }
    
    /**
     * Create required directories
     */
    private function createDirectories(): void
    {
        $dirs = [
            $this->getPluginPath() . '/recordings',
            $this->getPluginPath() . '/exports',
            $this->getPluginPath() . '/imports',
            $this->getPluginPath() . '/templates',
            $this->getPluginPath() . '/scenarios'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
        }
    }
    
    /**
     * Apply chaos engineering
     */
    public function applyChaosEngineering(Response $response): Response
    {
        if (!$this->chaosEngine->shouldTriggerChaos()) {
            return $response;
        }
        
        $chaosType = $this->chaosEngine->selectChaosType();
        
        switch ($chaosType) {
            case 'latency':
                $this->chaosEngine->addLatency();
                break;
                
            case 'error':
                $response = $this->chaosEngine->injectError($response);
                break;
                
            case 'timeout':
                $this->chaosEngine->simulateTimeout();
                break;
                
            case 'partial_response':
                $response = $this->chaosEngine->corruptResponse($response);
                break;
        }
        
        // Add chaos header for debugging
        $response->setHeader('X-Chaos-Applied', $chaosType);
        
        return $response;
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