<?php

declare(strict_types=1);

namespace Shopologic\Plugins\HeadlessCommerceApi;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\HookSystem;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use HeadlessCommerceAPI\Services\{
    APIVersionManager,
    EndpointRegistry,
    WebhookManager,
    SDKGenerator,
    RateLimiter,
    AuthenticationManager,
    ResponseTransformer,
    CORSHandler,;
    APIMetrics;
};
use HeadlessCommerceAPI\Controllers\{
    ProductController,
    CategoryController,
    CartController,
    CheckoutController,
    OrderController,
    CustomerController,
    AuthController,
    SearchController,
    WebhookController,
    ShippingController,;
    PaymentController;
};

class HeadlessCommerceAPIPlugin extends AbstractPlugin
{
    private APIVersionManager $versionManager;
    private EndpointRegistry $endpointRegistry;
    private WebhookManager $webhookManager;
    private SDKGenerator $sdkGenerator;
    private RateLimiter $rateLimiter;
    private AuthenticationManager $authManager;
    private ResponseTransformer $responseTransformer;
    private CORSHandler $corsHandler;
    private APIMetrics $apiMetrics;
    
    private array $controllers = [];
    private array $activeVersions = [];
    
    /**
     * Plugin activation
     */
    public function activate(): void
    {
        // Run migrations
        $this->runMigrations();
        
        // Initialize API tokens table
        $this->initializeTokens();
        
        // Set default options
        $this->setDefaultOptions();
        
        // Generate initial SDKs
        $this->generateInitialSDKs();
        
        // Create required directories
        $this->createDirectories();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        // Notify webhooks of API deactivation
        $this->notifyAPIDeactivation();
        
        // Clear API cache
        $this->clearAPICache();
    }
    
    /**
     * Register hooks
     */
    protected function registerHooks(): void
    {
        // Initialize services
        HookSystem::addAction('init', [$this, 'initializeServices'], 5);
        
        // API request handling
        HookSystem::addAction('parse_request', [$this, 'handleAPIRequest'], 1);
        HookSystem::addFilter('request_headers', [$this, 'filterRequestHeaders']);
        
        // Admin interface
        HookSystem::addAction('admin_menu', [$this, 'registerAdminMenu']);
        HookSystem::addAction('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        
        // Commerce hooks
        HookSystem::addAction('product_created', [$this, 'triggerProductWebhook']);
        HookSystem::addAction('product_updated', [$this, 'triggerProductWebhook']);
        HookSystem::addAction('product_deleted', [$this, 'triggerProductWebhook']);
        HookSystem::addAction('order_created', [$this, 'triggerOrderWebhook']);
        HookSystem::addAction('order_updated', [$this, 'triggerOrderWebhook']);
        HookSystem::addAction('cart_updated', [$this, 'triggerCartWebhook']);
        HookSystem::addAction('payment_completed', [$this, 'triggerPaymentWebhook']);
        
        // API version management
        HookSystem::addFilter('api_versions', [$this, 'registerAPIVersions']);
        HookSystem::addFilter('api_endpoints', [$this, 'filterEndpoints']);
        
        // GraphQL integration
        if ($this->getOption('enable_graphql', true)) {
            HookSystem::addAction('graphql_register_types', [$this, 'registerGraphQLTypes']);
        }
        
        // Scheduled tasks
        HookSystem::addAction('headless_process_webhooks', [$this, 'processWebhookQueue']);
        HookSystem::addAction('headless_cleanup_tokens', [$this, 'cleanupExpiredTokens']);
        HookSystem::addAction('headless_generate_metrics', [$this, 'generateAPIMetrics']);
        HookSystem::addAction('headless_update_sdks', [$this, 'updateSDKs']);
        
        // Rate limiting
        HookSystem::addFilter('api_rate_limit', [$this, 'checkRateLimit']);
        
        // API documentation
        HookSystem::addAction('rest_api_init', [$this, 'registerDocumentationEndpoints']);
    }
    
    /**
     * Initialize services
     */
    public function initializeServices(): void
    {
        // Get active API versions
        $this->activeVersions = $this->getOption('api_versions', ['v1', 'v2']);
        
        // Initialize services
        $this->versionManager = new APIVersionManager($this->container, $this->activeVersions);
        $this->endpointRegistry = new EndpointRegistry($this->container);
        $this->webhookManager = new WebhookManager($this->container);
        $this->sdkGenerator = new SDKGenerator($this->container);
        $this->rateLimiter = new RateLimiter($this->container);
        $this->authManager = new AuthenticationManager($this->container);
        $this->responseTransformer = new ResponseTransformer($this->container);
        $this->corsHandler = new CORSHandler($this->container);
        $this->apiMetrics = new APIMetrics($this->container);
        
        // Initialize controllers
        $this->initializeControllers();
        
        // Register endpoints
        $this->registerEndpoints();
    }
    
    /**
     * Initialize controllers
     */
    private function initializeControllers(): void
    {
        $this->controllers = [
            'product' => new ProductController($this->container),
            'category' => new CategoryController($this->container),
            'cart' => new CartController($this->container),
            'checkout' => new CheckoutController($this->container),
            'order' => new OrderController($this->container),
            'customer' => new CustomerController($this->container),
            'auth' => new AuthController($this->container),
            'search' => new SearchController($this->container),
            'webhook' => new WebhookController($this->container),
            'shipping' => new ShippingController($this->container),
            'payment' => new PaymentController($this->container)
        ];
    }
    
    /**
     * Handle API request
     */
    public function handleAPIRequest(): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Check if this is an API request
        if (!$this->isAPIRequest($requestUri)) {
            return;
        }
        
        try {
            // Create request object
            $request = Request::createFromGlobals();
            
            // Handle CORS preflight
            if ($request->getMethod() === 'OPTIONS') {
                $this->handlePreflightRequest($request);
                exit;
            }
            
            // Apply CORS headers
            $this->corsHandler->applyCORSHeaders($request);
            
            // Start metrics collection
            $requestId = $this->apiMetrics->startRequest($request);
            
            // Authenticate request
            $auth = $this->authenticateRequest($request);
            
            // Check rate limits
            if (!$this->checkRateLimit($request, $auth)) {
                $this->sendRateLimitResponse();
                exit;
            }
            
            // Route request
            $endpoint = $this->endpointRegistry->match($requestUri, $request->getMethod());
            if (!$endpoint) {
                $this->sendNotFoundResponse();
                exit;
            }
            
            // Check permissions
            if ($endpoint['auth'] && !$auth) {
                $this->sendUnauthorizedResponse();
                exit;
            }
            
            if (isset($endpoint['permission']) && !$this->hasPermission($auth, $endpoint['permission'])) {
                $this->sendForbiddenResponse();
                exit;
            }
            
            // Execute controller action
            $result = $this->executeEndpoint($endpoint, $request, $auth);
            
            // Transform response
            $response = $this->transformResponse($result, $request);
            
            // End metrics collection
            $this->apiMetrics->endRequest($requestId, $response->getStatusCode());
            
            // Send response
            $response->send();
            exit;
            
        } catch (\RuntimeException $e) {
            $this->handleAPIError($e);
        }
    }
    
    /**
     * Check if request is for API
     */
    private function isAPIRequest(string $uri): bool
    {
        $apiPrefixes = ['/api/v1/', '/api/v2/', '/graphql'];
        
        foreach ($apiPrefixes as $prefix) {
            if (strpos($uri, $prefix) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Authenticate request
     */
    private function authenticateRequest(Request $request): ?array
    {
        $authMethods = $this->getOption('authentication_methods', ['jwt', 'api_key']);
        
        foreach ($authMethods as $method) {
            $auth = $this->authManager->authenticate($method, $request);
            if ($auth) {
                return $auth;
            }
        }
        
        return null;
    }
    
    /**
     * Execute endpoint
     */
    private function executeEndpoint(array $endpoint, Request $request, ?array $auth): mixed
    {
        [$controllerName, $action] = explode('@', $endpoint['handler']);
        
        // Get controller
        $controller = $this->getController($controllerName);
        if (!$controller) {
            throw new \Exception("Controller not found: {$controllerName}");
        }
        
        // Set request context
        $controller->setRequest($request);
        $controller->setAuth($auth);
        
        // Extract route parameters
        $params = $this->extractRouteParams($endpoint['path'], $request->getUri());
        
        // Call action
        return $controller->$action(...array_values($params));
    }
    
    /**
     * Get controller instance
     */
    private function getController(string $name): ?object
    {
        $name = str_replace('Controllers\\', '', $name);
        $name = str_replace('Controller', '', $name);
        $name = strtolower($name);
        
        return $this->controllers[$name] ?? null;
    }
    
    /**
     * Transform response
     */
    private function transformResponse(mixed $result, Request $request): Response
    {
        $format = $this->getResponseFormat($request);
        
        $response = new Response();
        
        switch ($format) {
            case 'json':
                $response->json($result);
                break;
                
            case 'xml':
                $response->xml($result);
                break;
                
            case 'msgpack':
                $response->msgpack($result);
                break;
                
            default:
                $response->json($result);
        }
        
        // Add API headers
        $response->header('X-API-Version', $this->getRequestedVersion($request));
        $response->header('X-Request-ID', uniqid('api_', true));
        
        return $response;
    }
    
    /**
     * Register endpoints
     */
    private function registerEndpoints(): void
    {
        $endpoints = $this->config['api']['endpoints'] ?? [];
        
        foreach ($endpoints as $endpoint) {
            foreach ($this->activeVersions as $version) {
                $path = str_replace('{version}', $version, $endpoint['path']);
                
                $this->endpointRegistry->register(
                    $endpoint['method'],
                    $path,
                    $endpoint
                );
            }
        }
    }
    
    /**
     * Trigger product webhook
     */
    public function triggerProductWebhook($product): void
    {
        $event = current_action();
        
        $this->webhookManager->trigger($event, [
            'product' => $this->responseTransformer->transformProduct($product),
            'timestamp' => time(),
            'event' => $event
        ]);
    }
    
    /**
     * Trigger order webhook
     */
    public function triggerOrderWebhook($order): void
    {
        $event = current_action();
        
        $this->webhookManager->trigger($event, [
            'order' => $this->responseTransformer->transformOrder($order),
            'timestamp' => time(),
            'event' => $event
        ]);
    }
    
    /**
     * Process webhook queue
     */
    public function processWebhookQueue(): void
    {
        $this->webhookManager->processQueue();
    }
    
    /**
     * Clean up expired tokens
     */
    public function cleanupExpiredTokens(): void
    {
        $this->authManager->cleanupExpiredTokens();
    }
    
    /**
     * Generate API metrics
     */
    public function generateAPIMetrics(): void
    {
        $metrics = $this->apiMetrics->generateDailyReport();
        
        // Store metrics
        update_option('headless_api_metrics_' . date('Y-m-d'), $metrics);
        
        // Trigger metric generated event
        HookSystem::doAction('headless_metrics_generated', $metrics);
    }
    
    /**
     * Update SDKs
     */
    public function updateSDKs(): void
    {
        if (!$this->getOption('enable_sdk_generation', true)) {
            return;
        }
        
        $languages = $this->getOption('sdk_languages', ['javascript', 'php', 'python']);
        
        foreach ($languages as $language) {
            $this->sdkGenerator->generate($language, $this->endpointRegistry);
        }
    }
    
    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        add_menu_page(
            'Headless Commerce API',
            'Headless API',
            'headless.access',
            'headless-commerce-api',
            [$this, 'renderDashboard'],
            'dashicons-rest-api',
            58
        );
        
        add_submenu_page(
            'headless-commerce-api',
            'API Dashboard',
            'Dashboard',
            'headless.access',
            'headless-commerce-api',
            [$this, 'renderDashboard']
        );
        
        add_submenu_page(
            'headless-commerce-api',
            'API Tokens',
            'Tokens',
            'headless.manage_tokens',
            'headless-api-tokens',
            [$this, 'renderTokens']
        );
        
        add_submenu_page(
            'headless-commerce-api',
            'Webhooks',
            'Webhooks',
            'headless.manage_webhooks',
            'headless-webhooks',
            [$this, 'renderWebhooks']
        );
        
        add_submenu_page(
            'headless-commerce-api',
            'API Explorer',
            'Explorer',
            'headless.access',
            'headless-api-explorer',
            [$this, 'renderExplorer']
        );
        
        add_submenu_page(
            'headless-commerce-api',
            'SDK Downloads',
            'SDKs',
            'headless.generate_sdk',
            'headless-sdks',
            [$this, 'renderSDKs']
        );
        
        add_submenu_page(
            'headless-commerce-api',
            'Settings',
            'Settings',
            'headless.configure_api',
            'headless-settings',
            [$this, 'renderSettings']
        );
    }
    
    /**
     * Handle preflight request
     */
    private function handlePreflightRequest(Request $request): void
    {
        $response = new Response();
        $this->corsHandler->applyCORSHeaders($request, $response);
        $response->setStatusCode(204);
        $response->send();
    }
    
    /**
     * Send rate limit response
     */
    private function sendRateLimitResponse(): void
    {
        $response = new Response();
        $response->json([
            'error' => 'rate_limit_exceeded',
            'message' => 'Too many requests. Please try again later.'
        ]);
        $response->setStatusCode(429);
        $response->header('Retry-After', '60');
        $response->send();
    }
    
    /**
     * Send not found response
     */
    private function sendNotFoundResponse(): void
    {
        $response = new Response();
        $response->json([
            'error' => 'not_found',
            'message' => 'The requested endpoint does not exist.'
        ]);
        $response->setStatusCode(404);
        $response->send();
    }
    
    /**
     * Send unauthorized response
     */
    private function sendUnauthorizedResponse(): void
    {
        $response = new Response();
        $response->json([
            'error' => 'unauthorized',
            'message' => 'Authentication required.'
        ]);
        $response->setStatusCode(401);
        $response->header('WWW-Authenticate', 'Bearer');
        $response->send();
    }
    
    /**
     * Send forbidden response
     */
    private function sendForbiddenResponse(): void
    {
        $response = new Response();
        $response->json([
            'error' => 'forbidden',
            'message' => 'You do not have permission to access this resource.'
        ]);
        $response->setStatusCode(403);
        $response->send();
    }
    
    /**
     * Handle API error
     */
    private function handleAPIError(\Exception $e): void
    {
        $response = new Response();
        
        $isDevelopment = $this->getOption('debug_mode', false);
        
        $error = [
            'error' => 'internal_error',
            'message' => $isDevelopment ? $e->getMessage() : 'An error occurred processing your request.'
        ];
        
        if ($isDevelopment) {
            $error['trace'] = $e->getTraceAsString();
        }
        
        $response->json($error);
        $response->setStatusCode(500);
        $response->send();
        
        // Log error
        $this->log('API Error: ' . $e->getMessage(), 'error');
    }
    
    /**
     * Create required directories
     */
    private function createDirectories(): void
    {
        $dirs = [
            $this->getPluginPath() . '/sdks',
            $this->getPluginPath() . '/cache',
            $this->getPluginPath() . '/logs',
            $this->getPluginPath() . '/webhooks'
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