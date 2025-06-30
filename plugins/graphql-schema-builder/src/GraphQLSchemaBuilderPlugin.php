<?php

declare(strict_types=1);

namespace Shopologic\Plugins\GraphqlSchemaBuilder;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\HookSystem;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use GraphQLSchemaBuilder\Services\{
    SchemaBuilder,
    TypeRegistry,
    ResolverManager,
    QueryExecutor,
    SubscriptionManager,
    SchemaValidator,
    PerformanceMonitor,
    CodeGenerator,;
    RestToGraphQLConverter;
};
use GraphQLSchemaBuilder\Types\{
    ScalarTypes,
    DirectiveTypes,
    InterfaceTypes,;
    DefaultTypes;
};
use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use GraphQL\Error\FormattedError;

class GraphQLSchemaBuilderPlugin extends AbstractPlugin
{
    private SchemaBuilder $schemaBuilder;
    private TypeRegistry $typeRegistry;
    private ResolverManager $resolverManager;
    private QueryExecutor $queryExecutor;
    private SubscriptionManager $subscriptionManager;
    private SchemaValidator $schemaValidator;
    private PerformanceMonitor $performanceMonitor;
    private CodeGenerator $codeGenerator;
    private RestToGraphQLConverter $restConverter;
    
    private ?Schema $schema = null;
    
    /**
     * Plugin activation
     */
    public function activate(): void
    {
        // Run migrations
        $this->runMigrations();
        
        // Install default types
        $this->installDefaultTypes();
        
        // Generate initial schema
        $this->generateInitialSchema();
        
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
        // Close any active subscriptions
        $this->closeActiveSubscriptions();
        
        // Clear schema cache
        $this->clearSchemaCache();
    }
    
    /**
     * Register hooks
     */
    protected function registerHooks(): void
    {
        // Initialize services
        HookSystem::addAction('init', [$this, 'initializeServices']);
        
        // GraphQL endpoint
        HookSystem::addAction('rest_api_init', [$this, 'registerGraphQLEndpoint']);
        HookSystem::addAction('parse_request', [$this, 'handleGraphQLRequest']);
        
        // Admin interface
        HookSystem::addAction('admin_menu', [$this, 'registerAdminMenu']);
        HookSystem::addAction('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        
        // Schema building hooks
        HookSystem::addAction('graphql_register_types', [$this, 'registerCoreTypes']);
        HookSystem::addFilter('graphql_schema', [$this, 'filterSchema']);
        
        // Model integration
        HookSystem::addAction('model_registered', [$this, 'generateTypeFromModel']);
        
        // WebSocket support for subscriptions
        HookSystem::addAction('websocket_init', [$this, 'initializeWebSocket']);
        
        // API endpoints
        $this->registerApiEndpoints();
        
        // Query execution hooks
        HookSystem::addFilter('graphql_query_context', [$this, 'buildQueryContext']);
        HookSystem::addFilter('graphql_query_result', [$this, 'filterQueryResult']);
        
        // Performance monitoring
        HookSystem::addAction('graphql_query_start', [$this, 'startQueryMonitoring']);
        HookSystem::addAction('graphql_query_end', [$this, 'endQueryMonitoring']);
        
        // Scheduled tasks
        HookSystem::addAction('graphql_cleanup_cache', [$this, 'cleanupQueryCache']);
        HookSystem::addAction('graphql_generate_report', [$this, 'generatePerformanceReport']);
    }
    
    /**
     * Initialize services
     */
    public function initializeServices(): void
    {
        // Initialize core services
        $this->typeRegistry = new TypeRegistry($this->container);
        $this->resolverManager = new ResolverManager($this->container);
        $this->schemaBuilder = new SchemaBuilder($this->typeRegistry, $this->resolverManager);
        $this->queryExecutor = new QueryExecutor($this->container);
        $this->subscriptionManager = new SubscriptionManager($this->container);
        $this->schemaValidator = new SchemaValidator($this->container);
        $this->performanceMonitor = new PerformanceMonitor($this->container);
        $this->codeGenerator = new CodeGenerator($this->container);
        $this->restConverter = new RestToGraphQLConverter($this->container);
        
        // Register scalar types
        $this->registerScalarTypes();
        
        // Register directives
        $this->registerDirectives();
        
        // Build schema
        $this->buildSchema();
    }
    
    /**
     * Handle GraphQL request
     */
    public function handleGraphQLRequest(): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        
        if (!$this->isGraphQLRequest($requestUri)) {
            return;
        }
        
        // Handle playground
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $this->getOption('enable_playground', true)) {
            $this->renderPlayground();
            exit;
        }
        
        // Handle GraphQL query
        try {
            $request = Request::createFromGlobals();
            $result = $this->executeGraphQLQuery($request);
            
            $response = new Response();
            $response->json($result);
            $response->send();
            exit;
            
        } catch (\RuntimeException $e) {
            $this->handleGraphQLError($e);
        }
    }
    
    /**
     * Execute GraphQL query
     */
    private function executeGraphQLQuery(Request $request): array
    {
        $input = $request->getParsedBody();
        
        if (empty($input)) {
            $input = json_decode($request->getBody(), true);
        }
        
        $query = $input['query'] ?? '';
        $variables = $input['variables'] ?? null;
        $operationName = $input['operationName'] ?? null;
        
        // Check for batched queries
        if (isset($input[0]) && is_array($input[0])) {
            return $this->executeBatchedQueries($input);
        }
        
        // Start monitoring
        $queryId = $this->performanceMonitor->startQuery($query, $variables);
        
        try {
            // Build context
            $context = $this->buildContext($request);
            
            // Validate query
            $validationErrors = $this->schemaValidator->validate($this->schema, $query);
            if (!empty($validationErrors)) {
                return [
                    'errors' => array_map([FormattedError::class, 'createFromException'], $validationErrors)
                ];
            }
            
            // Check complexity
            if (!$this->checkQueryComplexity($query)) {
                throw new \Exception('Query too complex');
            }
            
            // Execute query
            $result = GraphQL::executeQuery(
                $this->schema,
                $query,
                null,
                $context,
                $variables,
                $operationName
            );
            
            $output = $result->toArray();
            
            // Add extensions if tracing enabled
            if ($this->getOption('enable_tracing', true)) {
                $output['extensions'] = [
                    'tracing' => $this->performanceMonitor->getTracing($queryId)
                ];
            }
            
            return $output;
            
        } finally {
            $this->performanceMonitor->endQuery($queryId);
        }
    }
    
    /**
     * Build GraphQL schema
     */
    private function buildSchema(): void
    {
        // Register default types
        $this->registerDefaultTypes();
        
        // Register model types if auto-generation enabled
        if ($this->getOption('enable_auto_generation', true)) {
            $this->generateTypesFromModels();
        }
        
        // Register REST wrapper types if enabled
        if ($this->getOption('enable_rest_wrapper', true)) {
            $this->registerRestWrapperTypes();
        }
        
        // Allow plugins to register types
        HookSystem::doAction('graphql_register_types', $this->typeRegistry);
        
        // Build schema
        try {
            $this->schema = $this->schemaBuilder->build();
            
            // Validate schema
            $this->schemaValidator->validateSchema($this->schema);
            
            // Cache schema
            $this->cacheSchema();
            
        } catch (\RuntimeException $e) {
            $this->log('Failed to build GraphQL schema: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Register scalar types
     */
    private function registerScalarTypes(): void
    {
        $enabledScalars = $this->getOption('scalar_types', ['DateTime', 'JSON']);
        
        foreach ($enabledScalars as $scalar) {
            switch ($scalar) {
                case 'DateTime':
                    $this->typeRegistry->register('DateTime', ScalarTypes::dateTime());
                    break;
                case 'Date':
                    $this->typeRegistry->register('Date', ScalarTypes::date());
                    break;
                case 'Time':
                    $this->typeRegistry->register('Time', ScalarTypes::time());
                    break;
                case 'JSON':
                    $this->typeRegistry->register('JSON', ScalarTypes::json());
                    break;
                case 'Upload':
                    $this->typeRegistry->register('Upload', ScalarTypes::upload());
                    break;
                case 'Email':
                    $this->typeRegistry->register('Email', ScalarTypes::email());
                    break;
                case 'URL':
                    $this->typeRegistry->register('URL', ScalarTypes::url());
                    break;
            }
        }
    }
    
    /**
     * Register directives
     */
    private function registerDirectives(): void
    {
        $enabledDirectives = $this->getOption('directive_support', ['include', 'skip', 'deprecated']);
        
        foreach ($enabledDirectives as $directive) {
            switch ($directive) {
                case 'auth':
                    $this->typeRegistry->registerDirective('auth', DirectiveTypes::auth());
                    break;
                case 'hasRole':
                    $this->typeRegistry->registerDirective('hasRole', DirectiveTypes::hasRole());
                    break;
                case 'rateLimit':
                    $this->typeRegistry->registerDirective('rateLimit', DirectiveTypes::rateLimit());
                    break;
                case 'cache':
                    $this->typeRegistry->registerDirective('cache', DirectiveTypes::cache());
                    break;
            }
        }
    }
    
    /**
     * Register default types
     */
    private function registerDefaultTypes(): void
    {
        $defaultTypes = $this->config['default_types'] ?? [];
        
        foreach ($defaultTypes as $typeName => $typeConfig) {
            $this->typeRegistry->registerFromConfig($typeName, $typeConfig);
        }
        
        // Register Node interface
        $this->typeRegistry->register('Node', InterfaceTypes::node());
        
        // Register PageInfo type
        $this->typeRegistry->register('PageInfo', DefaultTypes::pageInfo());
    }
    
    /**
     * Generate types from models
     */
    private function generateTypesFromModels(): void
    {
        $models = $this->getRegisteredModels();
        
        foreach ($models as $model) {
            $this->generateTypeFromModel($model);
        }
    }
    
    /**
     * Generate type from model
     */
    public function generateTypeFromModel($model): void
    {
        $typeName = $this->getTypeNameFromModel($model);
        
        // Generate object type
        $type = $this->schemaBuilder->generateTypeFromModel($model);
        $this->typeRegistry->register($typeName, $type);
        
        // Generate input type
        $inputType = $this->schemaBuilder->generateInputTypeFromModel($model);
        $this->typeRegistry->register($typeName . 'Input', $inputType);
        
        // Generate connection type
        $connectionType = $this->schemaBuilder->generateConnectionType($typeName);
        $this->typeRegistry->register($typeName . 'Connection', $connectionType);
        
        // Register CRUD resolvers
        $this->registerModelResolvers($model, $typeName);
    }
    
    /**
     * Register model resolvers
     */
    private function registerModelResolvers($model, string $typeName): void
    {
        $modelName = strtolower($typeName);
        $pluralName = $this->pluralize($modelName);
        
        // Query resolvers
        $this->resolverManager->register("Query.{$modelName}", function($root, $args) use ($model) {
            return $model::find($args['id']);
        });
        
        $this->resolverManager->register("Query.{$pluralName}", function($root, $args) use ($model) {
            return $this->paginateQuery($model::query(), $args);
        });
        
        // Mutation resolvers
        $this->resolverManager->register("Mutation.create{$typeName}", function($root, $args) use ($model) {
            return $model::create($args['input']);
        });
        
        $this->resolverManager->register("Mutation.update{$typeName}", function($root, $args) use ($model) {
            $instance = $model::find($args['id']);
            $instance->update($args['input']);
            return $instance;
        });
        
        $this->resolverManager->register("Mutation.delete{$typeName}", function($root, $args) use ($model) {
            $instance = $model::find($args['id']);
            $instance->delete();
            return true;
        });
    }
    
    /**
     * Render GraphQL playground
     */
    private function renderPlayground(): void
    {
        $endpoint = $this->getOption('playground_settings.endpoint', '/graphql');
        $subscriptionEndpoint = $this->getOption('playground_settings.subscription_endpoint', '/graphql-ws');
        
        include $this->getPluginPath() . '/templates/playground.php';
    }
    
    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        add_menu_page(
            'GraphQL Schema',
            'GraphQL',
            'graphql.access',
            'graphql-schema-builder',
            [$this, 'renderSchemaBuilder'],
            'dashicons-networking',
            60
        );
        
        add_submenu_page(
            'graphql-schema-builder',
            'Schema Builder',
            'Schema Builder',
            'graphql.build_schema',
            'graphql-schema-builder',
            [$this, 'renderSchemaBuilder']
        );
        
        add_submenu_page(
            'graphql-schema-builder',
            'Types',
            'Types',
            'graphql.manage_types',
            'graphql-types',
            [$this, 'renderTypes']
        );
        
        add_submenu_page(
            'graphql-schema-builder',
            'Resolvers',
            'Resolvers',
            'graphql.manage_resolvers',
            'graphql-resolvers',
            [$this, 'renderResolvers']
        );
        
        add_submenu_page(
            'graphql-schema-builder',
            'Playground',
            'Playground',
            'graphql.execute_queries',
            'graphql-playground',
            [$this, 'renderPlaygroundAdmin']
        );
        
        add_submenu_page(
            'graphql-schema-builder',
            'Performance',
            'Performance',
            'graphql.access',
            'graphql-performance',
            [$this, 'renderPerformance']
        );
        
        add_submenu_page(
            'graphql-schema-builder',
            'Settings',
            'Settings',
            'graphql.manage_permissions',
            'graphql-settings',
            [$this, 'renderSettings']
        );
    }
    
    /**
     * Build query context
     */
    private function buildContext(Request $request): array
    {
        $context = [
            'request' => $request,
            'user' => wp_get_current_user(),
            'loader' => new \GraphQL\DataLoader(),
            'cache' => $this->getOption('enable_caching', true) ? new \GraphQL\Cache() : null
        ];
        
        // Allow filtering context
        return HookSystem::applyFilters('graphql_query_context', $context, $request);
    }
    
    /**
     * Check query complexity
     */
    private function checkQueryComplexity(string $query): bool
    {
        $complexity = $this->schemaValidator->calculateComplexity($this->schema, $query);
        $limit = $this->getOption('query_complexity_limit', 1000);
        
        return $complexity <= $limit;
    }
    
    /**
     * Create required directories
     */
    private function createDirectories(): void
    {
        $dirs = [
            $this->getPluginPath() . '/schemas',
            $this->getPluginPath() . '/generated',
            $this->getPluginPath() . '/cache',
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