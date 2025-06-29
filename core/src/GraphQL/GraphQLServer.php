<?php

declare(strict_types=1);

namespace Shopologic\Core\GraphQL;

use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use Shopologic\Core\Container\ContainerInterface;
use Shopologic\Core\Events\EventDispatcherInterface;

/**
 * GraphQL server implementation
 */
class GraphQLServer
{
    private Schema $schema;
    private ContainerInterface $container;
    private EventDispatcherInterface $events;
    private array $config;
    private array $middlewares = [];
    
    public function __construct(
        Schema $schema,
        ContainerInterface $container,
        EventDispatcherInterface $events,
        array $config = []
    ) {
        $this->schema = $schema;
        $this->container = $container;
        $this->events = $events;
        $this->config = array_merge([
            'debug' => false,
            'introspection' => true,
            'max_depth' => 15,
            'max_complexity' => 1000,
            'batch_enabled' => true,
            'subscriptions_enabled' => true,
            'upload_enabled' => true,
            'cache_enabled' => true,
            'cache_ttl' => 3600
        ], $config);
    }
    
    /**
     * Handle GraphQL request
     */
    public function handle(Request $request): Response
    {
        try {
            // Parse request
            $input = $this->parseRequest($request);
            
            // Validate request
            $this->validateRequest($input);
            
            // Check if batch request
            if ($this->isBatchRequest($input)) {
                return $this->handleBatch($input, $request);
            }
            
            // Execute single query
            $result = $this->execute($input, $request);
            
            return new Response(json_encode($result), 200, [
                'Content-Type' => 'application/json'
            ]);
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }
    
    /**
     * Execute GraphQL query
     */
    public function execute(array $input, Request $request): array
    {
        $query = $input['query'] ?? '';
        $variables = $input['variables'] ?? [];
        $operationName = $input['operationName'] ?? null;
        
        // Create context
        $context = $this->createContext($request);
        
        // Apply middleware
        $context = $this->applyMiddleware($context, $query, $variables);
        
        // Parse query
        $document = $this->parseQuery($query);
        
        // Validate query
        $errors = $this->validateQuery($document);
        if (!empty($errors)) {
            return ['errors' => $errors];
        }
        
        // Check complexity
        $complexity = $this->calculateComplexity($document, $variables);
        if ($complexity > $this->config['max_complexity']) {
            return [
                'errors' => [[
                    'message' => 'Query too complex',
                    'extensions' => ['complexity' => $complexity]
                ]]
            ];
        }
        
        // Check cache
        if ($this->config['cache_enabled'] && $this->isQueryCacheable($document)) {
            $cacheKey = $this->getCacheKey($query, $variables, $operationName);
            $cached = $this->container->get('cache')->get($cacheKey);
            
            if ($cached !== null) {
                $this->events->dispatch('graphql.cache_hit', ['key' => $cacheKey]);
                return $cached;
            }
        }
        
        // Execute query
        $result = $this->executeQuery($document, $variables, $operationName, $context);
        
        // Cache result if applicable
        if ($this->config['cache_enabled'] && $this->isQueryCacheable($document) && empty($result['errors'])) {
            $this->container->get('cache')->set($cacheKey, $result, $this->config['cache_ttl']);
        }
        
        // Add extensions if debug mode
        if ($this->config['debug']) {
            $result['extensions'] = [
                'complexity' => $complexity,
                'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
            ];
        }
        
        return $result;
    }
    
    /**
     * Parse GraphQL query
     */
    private function parseQuery(string $query): Document
    {
        $lexer = new Lexer($query);
        $parser = new Parser($lexer);
        
        return $parser->parse();
    }
    
    /**
     * Validate GraphQL query
     */
    private function validateQuery(Document $document): array
    {
        $validator = new Validator($this->schema);
        
        $rules = [
            new ValidationRules\FieldsOnCorrectType(),
            new ValidationRules\FragmentsOnCompositeTypes(),
            new ValidationRules\KnownArgumentNames(),
            new ValidationRules\KnownDirectives(),
            new ValidationRules\KnownFragmentNames(),
            new ValidationRules\KnownTypeNames(),
            new ValidationRules\LoneAnonymousOperation(),
            new ValidationRules\NoFragmentCycles(),
            new ValidationRules\NoUndefinedVariables(),
            new ValidationRules\NoUnusedFragments(),
            new ValidationRules\NoUnusedVariables(),
            new ValidationRules\OverlappingFieldsCanBeMerged(),
            new ValidationRules\PossibleFragmentSpreads(),
            new ValidationRules\ProvidedRequiredArguments(),
            new ValidationRules\ScalarLeafs(),
            new ValidationRules\SingleFieldSubscriptions(),
            new ValidationRules\UniqueArgumentNames(),
            new ValidationRules\UniqueDirectivesPerLocation(),
            new ValidationRules\UniqueFragmentNames(),
            new ValidationRules\UniqueInputFieldNames(),
            new ValidationRules\UniqueOperationNames(),
            new ValidationRules\UniqueVariableNames(),
            new ValidationRules\ValuesOfCorrectType(),
            new ValidationRules\VariablesAreInputTypes(),
            new ValidationRules\VariablesInAllowedPosition()
        ];
        
        if ($this->config['max_depth'] > 0) {
            $rules[] = new ValidationRules\QueryDepth($this->config['max_depth']);
        }
        
        if (!$this->config['introspection']) {
            $rules[] = new ValidationRules\DisableIntrospection();
        }
        
        return $validator->validate($document, $rules);
    }
    
    /**
     * Execute parsed query
     */
    private function executeQuery(
        Document $document,
        array $variables,
        ?string $operationName,
        Context $context
    ): array {
        $executor = new Executor($this->schema, $this->container);
        
        try {
            $data = $executor->execute($document, $variables, $operationName, $context);
            
            return ['data' => $data];
        } catch (ExecutionException $e) {
            return [
                'data' => $e->getData(),
                'errors' => $e->getErrors()
            ];
        }
    }
    
    /**
     * Calculate query complexity
     */
    private function calculateComplexity(Document $document, array $variables): int
    {
        $calculator = new ComplexityCalculator($this->schema);
        
        return $calculator->calculate($document, $variables);
    }
    
    /**
     * Check if query is cacheable
     */
    private function isQueryCacheable(Document $document): bool
    {
        // Don't cache mutations or subscriptions
        foreach ($document->getOperations() as $operation) {
            if ($operation->getType() !== 'query') {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Generate cache key for query
     */
    private function getCacheKey(string $query, array $variables, ?string $operationName): string
    {
        return 'graphql:' . md5(serialize([
            'query' => $query,
            'variables' => $variables,
            'operation' => $operationName
        ]));
    }
    
    /**
     * Create execution context
     */
    private function createContext(Request $request): Context
    {
        return new Context([
            'request' => $request,
            'user' => $this->container->get('auth')->user(),
            'container' => $this->container
        ]);
    }
    
    /**
     * Apply middleware to context
     */
    private function applyMiddleware(Context $context, string $query, array $variables): Context
    {
        foreach ($this->middlewares as $middleware) {
            $context = $middleware->process($context, $query, $variables);
        }
        
        return $context;
    }
    
    /**
     * Handle batch request
     */
    private function handleBatch(array $batch, Request $request): Response
    {
        if (!$this->config['batch_enabled']) {
            return new Response(json_encode([
                'errors' => [['message' => 'Batch requests are not enabled']]
            ]), 400, ['Content-Type' => 'application/json']);
        }
        
        $results = [];
        
        foreach ($batch as $input) {
            $results[] = $this->execute($input, $request);
        }
        
        return new Response(json_encode($results), 200, [
            'Content-Type' => 'application/json'
        ]);
    }
    
    /**
     * Parse request input
     */
    private function parseRequest(Request $request): array
    {
        $contentType = $request->getHeader('Content-Type');
        
        if (strpos($contentType, 'application/json') !== false) {
            return json_decode($request->getContent(), true) ?? [];
        }
        
        if (strpos($contentType, 'application/graphql') !== false) {
            return ['query' => $request->getContent()];
        }
        
        if ($request->getMethod() === 'GET') {
            return [
                'query' => $request->query('query'),
                'variables' => json_decode($request->query('variables', '{}'), true),
                'operationName' => $request->query('operationName')
            ];
        }
        
        return $request->all();
    }
    
    /**
     * Validate request input
     */
    private function validateRequest(array $input): void
    {
        if (empty($input['query']) && !$this->isBatchRequest($input)) {
            throw new \InvalidArgumentException('Query is required');
        }
    }
    
    /**
     * Check if request is batch
     */
    private function isBatchRequest(array $input): bool
    {
        return isset($input[0]) && is_array($input[0]);
    }
    
    /**
     * Handle execution error
     */
    private function handleError(\Exception $e): Response
    {
        $error = [
            'message' => $this->config['debug'] ? $e->getMessage() : 'Internal server error'
        ];
        
        if ($this->config['debug']) {
            $error['extensions'] = [
                'exception' => [
                    'class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ];
        }
        
        $this->events->dispatch('graphql.error', ['exception' => $e]);
        
        return new Response(json_encode(['errors' => [$error]]), 500, [
            'Content-Type' => 'application/json'
        ]);
    }
    
    /**
     * Add middleware
     */
    public function addMiddleware(MiddlewareInterface $middleware): void
    {
        $this->middlewares[] = $middleware;
    }
    
    /**
     * Handle subscription
     */
    public function subscribe(string $query, array $variables = [], ?string $operationName = null): Subscription
    {
        if (!$this->config['subscriptions_enabled']) {
            throw new \RuntimeException('Subscriptions are not enabled');
        }
        
        $document = $this->parseQuery($query);
        
        // Validate subscription
        $errors = $this->validateQuery($document);
        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
        
        // Create subscription
        $subscription = new Subscription(
            $this->schema,
            $document,
            $variables,
            $operationName,
            $this->createContext(new Request())
        );
        
        return $subscription;
    }
}

/**
 * GraphQL execution context
 */
class Context
{
    private array $data;
    
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }
    
    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }
    
    public function set(string $key, $value): void
    {
        $this->data[$key] = $value;
    }
    
    public function all(): array
    {
        return $this->data;
    }
}

/**
 * GraphQL middleware interface
 */
interface MiddlewareInterface
{
    public function process(Context $context, string $query, array $variables): Context;
}

/**
 * GraphQL subscription
 */
class Subscription
{
    private Schema $schema;
    private Document $document;
    private array $variables;
    private ?string $operationName;
    private Context $context;
    private array $listeners = [];
    private bool $active = true;
    
    public function __construct(
        Schema $schema,
        Document $document,
        array $variables,
        ?string $operationName,
        Context $context
    ) {
        $this->schema = $schema;
        $this->document = $document;
        $this->variables = $variables;
        $this->operationName = $operationName;
        $this->context = $context;
    }
    
    public function subscribe(callable $callback): void
    {
        $this->listeners[] = $callback;
    }
    
    public function publish(array $event): void
    {
        if (!$this->active) {
            return;
        }
        
        $executor = new Executor($this->schema);
        $result = $executor->execute(
            $this->document,
            array_merge($this->variables, ['event' => $event]),
            $this->operationName,
            $this->context
        );
        
        foreach ($this->listeners as $listener) {
            $listener($result);
        }
    }
    
    public function unsubscribe(): void
    {
        $this->active = false;
        $this->listeners = [];
    }
    
    public function isActive(): bool
    {
        return $this->active;
    }
}