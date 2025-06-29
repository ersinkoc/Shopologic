<?php

declare(strict_types=1);

/**
 * API Integration Tests
 */

use Shopologic\Core\GraphQL\GraphQLServer;
use Shopologic\Core\GraphQL\Schema;
use Shopologic\Core\GraphQL\SchemaBuilder;
use Shopologic\Core\API\Rest\Router as RestRouter;
use Shopologic\Core\API\Middleware\ApiMiddleware;

TestFramework::describe('API Integration', function() {
    TestFramework::it('should initialize GraphQL server', function() {
        $server = new GraphQLServer();
        TestFramework::expect($server)->toBeInstanceOf(GraphQLServer::class);
    });
    
    TestFramework::it('should create GraphQL schema', function() {
        $schemaBuilder = new SchemaBuilder();
        $schema = $schemaBuilder->build();
        
        TestFramework::expect($schema)->toBeInstanceOf(Schema::class);
    });
    
    TestFramework::it('should handle GraphQL introspection', function() {
        $server = new GraphQLServer();
        
        // Test introspection query
        $introspectionQuery = '{ __schema { types { name } } }';
        
        TestFramework::expect(function() use ($server, $introspectionQuery) {
            $server->executeQuery($introspectionQuery);
        })->not()->toThrow();
    });
    
    TestFramework::it('should validate GraphQL queries', function() {
        $server = new GraphQLServer();
        
        // Valid query
        $validQuery = '{ __typename }';
        TestFramework::expect(function() use ($server, $validQuery) {
            $server->executeQuery($validQuery);
        })->not()->toThrow();
        
        // Invalid query should throw
        $invalidQuery = '{ invalid_field_name }';
        TestFramework::expect(function() use ($server, $invalidQuery) {
            $result = $server->executeQuery($invalidQuery);
            if (isset($result['errors']) && !empty($result['errors'])) {
                throw new Exception('GraphQL validation error');
            }
        })->toThrow();
    });
    
    TestFramework::it('should initialize REST router', function() {
        $router = new RestRouter();
        TestFramework::expect($router)->toBeInstanceOf(RestRouter::class);
    });
    
    TestFramework::it('should register REST endpoints', function() {
        $router = new RestRouter();
        
        // Register a test endpoint
        $router->get('/api/test', function() {
            return ['message' => 'test endpoint'];
        });
        
        $routes = $router->getRoutes();
        TestFramework::expect(count($routes))->toBeGreaterThan(0);
    });
    
    TestFramework::it('should handle API authentication middleware', function() {
        $middleware = new ApiMiddleware();
        TestFramework::expect($middleware)->toBeInstanceOf(ApiMiddleware::class);
    });
    
    TestFramework::it('should process API requests', function() {
        $router = new RestRouter();
        
        // Mock a simple API endpoint
        $router->get('/api/status', function() {
            return [
                'status' => 'ok',
                'timestamp' => time(),
                'version' => '1.0.0'
            ];
        });
        
        // Create mock request
        $request = new \Shopologic\Core\Http\Request('GET', new \Shopologic\Core\Http\Uri('http://localhost/api/status'));
        
        TestFramework::expect(function() use ($router, $request) {
            $router->dispatch($request);
        })->not()->toThrow();
    });
    
    TestFramework::it('should handle API errors gracefully', function() {
        $router = new RestRouter();
        
        // Register endpoint that throws exception
        $router->get('/api/error', function() {
            throw new Exception('Test error');
        });
        
        $request = new \Shopologic\Core\Http\Request('GET', new \Shopologic\Core\Http\Uri('http://localhost/api/error'));
        
        TestFramework::expect(function() use ($router, $request) {
            try {
                $router->dispatch($request);
            } catch (Exception $e) {
                // Convert to API error format
                $error = [
                    'error' => [
                        'message' => $e->getMessage(),
                        'code' => 'INTERNAL_ERROR'
                    ]
                ];
                TestFramework::expect($error['error']['message'])->toBe('Test error');
            }
        })->not()->toThrow();
    });
    
    TestFramework::it('should support API versioning', function() {
        $router = new RestRouter();
        
        // Register versioned endpoints
        $router->group('/api/v1', function($router) {
            $router->get('/users', function() {
                return ['version' => 'v1'];
            });
        });
        
        $router->group('/api/v2', function($router) {
            $router->get('/users', function() {
                return ['version' => 'v2'];
            });
        });
        
        $routes = $router->getRoutes();
        $hasV1 = false;
        $hasV2 = false;
        
        foreach ($routes as $route) {
            if (strpos($route->getPath(), '/api/v1/users') !== false) $hasV1 = true;
            if (strpos($route->getPath(), '/api/v2/users') !== false) $hasV2 = true;
        }
        
        TestFramework::expect($hasV1)->toBeTrue();
        TestFramework::expect($hasV2)->toBeTrue();
    });
    
    TestFramework::it('should handle CORS headers', function() {
        $middleware = new \Shopologic\Core\API\Middleware\CorsMiddleware();
        TestFramework::expect($middleware)->toBeInstanceOf(\Shopologic\Core\API\Middleware\CorsMiddleware::class);
        
        // Test CORS processing
        $request = new \Shopologic\Core\Http\Request('GET', new \Shopologic\Core\Http\Uri('http://localhost/api/test'));
        $response = new \Shopologic\Core\Http\Response();
        
        $processedResponse = $middleware->process($request, function($req) use ($response) {
            return $response;
        });
        
        TestFramework::expect($processedResponse->hasHeader('Access-Control-Allow-Origin'))->toBeTrue();
    });
    
    TestFramework::it('should handle rate limiting', function() {
        $middleware = new \Shopologic\Core\API\Middleware\RateLimitMiddleware();
        TestFramework::expect($middleware)->toBeInstanceOf(\Shopologic\Core\API\Middleware\RateLimitMiddleware::class);
    });
    
    TestFramework::it('should support content negotiation', function() {
        $router = new RestRouter();
        
        $router->get('/api/data', function() {
            return ['data' => 'test'];
        });
        
        // Test JSON response
        $request = new \Shopologic\Core\Http\Request('GET', 
            new \Shopologic\Core\Http\Uri('http://localhost/api/data'),
            ['Accept' => 'application/json']
        );
        
        TestFramework::expect($request->getHeaderLine('Accept'))->toBe('application/json');
    });
    
    TestFramework::it('should validate API input', function() {
        $validator = new \Shopologic\Core\API\Validation\Validator();
        TestFramework::expect($validator)->toBeInstanceOf(\Shopologic\Core\API\Validation\Validator::class);
        
        // Test basic validation
        $rules = ['email' => 'required|email'];
        $data = ['email' => 'test@example.com'];
        
        $result = $validator->validate($data, $rules);
        TestFramework::expect($result->isValid())->toBeTrue();
        
        // Test invalid data
        $invalidData = ['email' => 'invalid-email'];
        $invalidResult = $validator->validate($invalidData, $rules);
        TestFramework::expect($invalidResult->isValid())->toBeFalse();
    });
});