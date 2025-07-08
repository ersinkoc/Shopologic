<?php

declare(strict_types=1);

namespace Shopologic\Core\GraphQL;

use Shopologic\Core\Container\ServiceProvider;
use Shopologic\Core\GraphQL\Resolvers\ProductResolver;
use Shopologic\Core\GraphQL\Resolvers\OrderResolver;
use Shopologic\Core\GraphQL\Resolvers\CartResolver;
use Shopologic\Core\GraphQL\Resolvers\CustomerResolver;
use Shopologic\Core\GraphQL\Resolvers\AuthResolver;
use Shopologic\Core\GraphQL\Resolvers\CheckoutResolver;
use Shopologic\Core\GraphQL\Resolvers\ReviewResolver;
use Shopologic\Core\GraphQL\Resolvers\WishlistResolver;
use Shopologic\Core\GraphQL\Middleware\AuthenticationMiddleware;
use Shopologic\Core\GraphQL\Middleware\RateLimitMiddleware;
use Shopologic\Core\GraphQL\Middleware\CostAnalysisMiddleware;
use Shopologic\Core\GraphQL\Subscriptions\SubscriptionManager;

/**
 * GraphQL service provider
 */
class GraphQLServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register schema builder
        $this->container->singleton(SchemaBuilder::class, function ($container) {
            return new SchemaBuilder($container);
        });
        
        // Register schema
        $this->container->singleton(Schema::class, function ($container) {
            $builder = $container->get(SchemaBuilder::class);
            
            // Allow plugins to modify schema
            $this->container->get('events')->dispatch('graphql.schema.building', ['builder' => $builder]);
            
            return $builder->build();
        });
        
        // Register GraphQL server
        $this->container->singleton(GraphQLServer::class, function ($container) {
            $server = new GraphQLServer(
                $container->get(Schema::class),
                $container,
                $container->get('events'),
                $container->get('config')['graphql'] ?? []
            );
            
            // Add middleware
            $server->addMiddleware(new AuthenticationMiddleware($container->get('auth')));
            $server->addMiddleware(new RateLimitMiddleware($container->get('cache')));
            $server->addMiddleware(new CostAnalysisMiddleware());
            
            return $server;
        });
        
        // Register resolvers
        $this->registerResolvers();
        
        // Register subscription manager
        $this->container->singleton(SubscriptionManager::class, function ($container) {
            return new SubscriptionManager(
                $container->get(Schema::class),
                $container->get('events'),
                $container->get('cache')
            );
        });
        
        // Register aliases
        $this->container->alias('graphql', GraphQLServer::class);
        $this->container->alias('graphql.schema', Schema::class);
        $this->container->alias('graphql.subscriptions', SubscriptionManager::class);
    }
    
    public function boot(): void
    {
        // Register routes
        $this->registerRoutes();
        
        // Register complexity functions
        $this->registerComplexityFunctions();
        
        // Register GraphQL types from plugins
        $this->registerPluginTypes();
        
        // Register event listeners
        $this->registerEventListeners();
        
        // Register console commands
        $this->registerCommands();
    }
    
    private function registerResolvers(): void
    {
        // Product resolver
        $this->container->singleton('graphql.resolvers.product', function ($container) {
            return new ProductResolver(
                $container->get('db'),
                $container->get('cache'),
                $container->get('search')
            );
        });
        
        // Order resolver
        $this->container->singleton('graphql.resolvers.order', function ($container) {
            return new OrderResolver(
                $container->get('db'),
                $container->get('auth')
            );
        });
        
        // Cart resolver
        $this->container->singleton('graphql.resolvers.cart', function ($container) {
            return new CartResolver(
                $container->get('cart'),
                $container->get('db')
            );
        });
        
        // Customer resolver
        $this->container->singleton('graphql.resolvers.customer', function ($container) {
            return new CustomerResolver(
                $container->get('db'),
                $container->get('auth'),
                $container->get('hash')
            );
        });
        
        // Auth resolver
        $this->container->singleton('graphql.resolvers.auth', function ($container) {
            return new AuthResolver(
                $container->get('auth'),
                $container->get('db'),
                $container->get('hash'),
                $container->get('jwt')
            );
        });
        
        // Checkout resolver
        $this->container->singleton('graphql.resolvers.checkout', function ($container) {
            return new CheckoutResolver(
                $container->get('cart'),
                $container->get('checkout'),
                $container->get('db')
            );
        });
        
        // Review resolver
        $this->container->singleton('graphql.resolvers.review', function ($container) {
            return new ReviewResolver(
                $container->get('db'),
                $container->get('auth')
            );
        });
        
        // Wishlist resolver
        $this->container->singleton('graphql.resolvers.wishlist', function ($container) {
            return new WishlistResolver(
                $container->get('db'),
                $container->get('auth')
            );
        });
    }
    
    private function registerRoutes(): void
    {
        if (!$this->container->has('router')) {
            return;
        }
        
        $router = $this->container->get('router');
        
        // GraphQL endpoint
        $router->post('/graphql', function ($request) {
            return $this->container->get('graphql')->handle($request);
        });
        
        // GraphQL playground (development only)
        if ($this->container->get('config')['app']['debug'] ?? false) {
            $router->get('/graphql', function () {
                return $this->renderPlayground();
            });
        }
        
        // GraphQL subscriptions WebSocket endpoint
        if ($this->container->get('config')['graphql']['subscriptions_enabled'] ?? true) {
            $router->get('/graphql/subscriptions', function ($request) {
                return $this->container->get('graphql.subscriptions')->handleWebSocket($request);
            });
        }
        
        // Schema endpoint (for tools)
        $router->get('/graphql/schema', function () {
            $schema = $this->container->get('graphql.schema');
            
            return new Response(json_encode([
                'data' => [
                    '__schema' => $this->introspectSchema($schema)
                ]
            ]), 200, ['Content-Type' => 'application/json']);
        });
    }
    
    private function registerComplexityFunctions(): void
    {
        $schema = $this->container->get('graphql.schema');
        $calculator = new ComplexityCalculator($schema);
        
        // Products query complexity
        $calculator->registerComplexityFunction('Query', 'products', function ($args) {
            $limit = $args['first'] ?? 20;
            return 1 + $limit; // Base cost + cost per item
        });
        
        // Orders query complexity
        $calculator->registerComplexityFunction('Query', 'orders', function ($args) {
            $limit = $args['first'] ?? 10;
            return 2 + ($limit * 2); // Higher cost for orders
        });
        
        // Search query complexity
        $calculator->registerComplexityFunction('Query', 'search', function ($args) {
            $limit = $args['limit'] ?? 20;
            return 5 + $limit; // Search is expensive
        });
        
        // Nested field complexities
        $calculator->registerComplexityFunction('Product', 'reviews', function ($args) {
            $limit = $args['limit'] ?? 10;
            return $limit;
        });
        
        $calculator->registerComplexityFunction('Product', 'variants', function () {
            return 5; // Variants are moderately expensive
        });
        
        $calculator->registerComplexityFunction('Order', 'items', function () {
            return 10; // Order items are expensive to load
        });
        
        $this->container->instance('graphql.complexity', $calculator);
    }
    
    private function registerPluginTypes(): void
    {
        $builder = $this->container->get(SchemaBuilder::class);
        $events = $this->container->get('events');
        
        // Allow plugins to register custom types
        $events->dispatch('graphql.register_types', ['builder' => $builder]);
        
        // Allow plugins to register custom queries
        $events->dispatch('graphql.register_queries', ['builder' => $builder]);
        
        // Allow plugins to register custom mutations
        $events->dispatch('graphql.register_mutations', ['builder' => $builder]);
        
        // Allow plugins to register custom subscriptions
        $events->dispatch('graphql.register_subscriptions', ['builder' => $builder]);
    }
    
    private function registerEventListeners(): void
    {
        $events = $this->container->get('events');
        
        // Clear GraphQL cache when schema might have changed
        $events->listen([
            'plugin.activated',
            'plugin.deactivated',
            'plugin.updated'
        ], function () {
            $this->container->get('cache')->tags(['graphql'])->flush();
        });
        
        // Track GraphQL metrics
        $events->listen('graphql.query_executed', function ($data) {
            // Log query metrics
            $this->container->get('analytics')->track('graphql_query', [
                'operation' => $data['operation'] ?? 'query',
                'fields' => $data['fields'] ?? [],
                'complexity' => $data['complexity'] ?? 0,
                'duration' => $data['duration'] ?? 0
            ]);
        });
        
        // Handle subscription events
        $events->listen('order.status_changed', function ($order) {
            $this->container->get('graphql.subscriptions')
                ->publish('orderStatusChanged', ['order' => $order]);
        });
        
        $events->listen('product.updated', function ($product) {
            $this->container->get('graphql.subscriptions')
                ->publish('productUpdated', ['product' => $product]);
        });
        
        $events->listen('product.stock_changed', function ($product) {
            $this->container->get('graphql.subscriptions')
                ->publish('stockLevelChanged', ['product' => $product]);
        });
        
        $events->listen('product.price_changed', function ($product) {
            $this->container->get('graphql.subscriptions')
                ->publish('priceChanged', ['product' => $product]);
        });
    }
    
    private function registerCommands(): void
    {
        if (!$this->container->has('console')) {
            return;
        }
        
        $console = $this->container->get('console');
        
        // GraphQL commands
        $console->add(new Commands\SchemaExportCommand($this->container));
        $console->add(new Commands\SchemaValidateCommand($this->container));
        $console->add(new Commands\GenerateTypesCommand($this->container));
        $console->add(new Commands\PlaygroundCommand($this->container));
    }
    
    private function renderPlayground(): Response
    {
        $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <title>GraphQL Playground</title>
            <link rel="stylesheet" href="//cdn.jsdelivr.net/npm/graphql-playground-react/build/static/css/index.css" />
            <script src="//cdn.jsdelivr.net/npm/graphql-playground-react/build/static/js/middleware.js"></script>
        </head>
        <body>
            <div id="root"></div>
            <script>
                window.addEventListener('load', function (event) {
                    GraphQLPlayground.init(document.getElementById('root'), {
                        endpoint: '/graphql',
                        subscriptionEndpoint: 'ws://localhost:17000/graphql/subscriptions',
                        settings: {
                            'request.credentials': 'include',
                            'editor.theme': 'dark',
                            'editor.fontSize': 14,
                            'editor.fontFamily': '"Fira Code", "Monaco", monospace',
                            'editor.reuseHeaders': true,
                            'prettier.printWidth': 80,
                            'schema.polling.enable': true,
                            'schema.polling.endpointFilter': '*',
                            'schema.polling.interval': 2000
                        }
                    })
                })
            </script>
        </body>
        </html>
        HTML;
        
        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }
    
    private function introspectSchema(Schema $schema): array
    {
        // Simplified schema introspection
        // A full implementation would follow the GraphQL introspection spec
        return [
            'queryType' => ['name' => 'Query'],
            'mutationType' => ['name' => 'Mutation'],
            'subscriptionType' => ['name' => 'Subscription'],
            'types' => array_map(function ($type) {
                return [
                    'name' => $type->getName(),
                    'description' => $type->getDescription(),
                    'kind' => $this->getTypeKind($type)
                ];
            }, $schema->getTypes())
        ];
    }
    
    private function getTypeKind(Type $type): string
    {
        if ($type->isScalar()) return 'SCALAR';
        if ($type->isObject()) return 'OBJECT';
        if ($type->isInterface()) return 'INTERFACE';
        if ($type->isUnion()) return 'UNION';
        if ($type->isEnum()) return 'ENUM';
        if ($type->isInputObject()) return 'INPUT_OBJECT';
        if ($type->isList()) return 'LIST';
        if ($type->isNonNull()) return 'NON_NULL';
        
        return 'UNKNOWN';
    }
}

// Middleware implementations
namespace Shopologic\Core\GraphQL\Middleware;

use Shopologic\Core\GraphQL\Context;
use Shopologic\Core\GraphQL\MiddlewareInterface;
use Shopologic\Core\Auth\AuthManager;
use Shopologic\Core\Cache\CacheInterface;

class AuthenticationMiddleware implements MiddlewareInterface
{
    private AuthManager $auth;
    
    public function __construct(AuthManager $auth)
    {
        $this->auth = $auth;
    }
    
    public function process(Context $context, string $query, array $variables): Context
    {
        // Authenticate user from request
        $request = $context->get('request');
        
        if ($request && $token = $request->bearerToken()) {
            $user = $this->auth->guard('api')->user();
            
            if ($user) {
                $context->set('user', $user);
                $context->set('authenticated', true);
            }
        }
        
        return $context;
    }
}

class RateLimitMiddleware implements MiddlewareInterface
{
    private CacheInterface $cache;
    
    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }
    
    public function process(Context $context, string $query, array $variables): Context
    {
        $user = $context->get('user');
        $key = $user ? "graphql_rate:{$user->id}" : "graphql_rate:{$_SERVER['REMOTE_ADDR']}";
        
        $requests = $this->cache->get($key, 0);
        $limit = $user ? 1000 : 100; // Higher limit for authenticated users
        
        if ($requests >= $limit) {
            throw new \Exception('Rate limit exceeded');
        }
        
        $this->cache->increment($key);
        $this->cache->expire($key, 3600); // Reset every hour
        
        return $context;
    }
}

class CostAnalysisMiddleware implements MiddlewareInterface
{
    public function process(Context $context, string $query, array $variables): Context
    {
        // Cost analysis would be performed during validation
        // This is a placeholder for the actual implementation
        
        return $context;
    }
}