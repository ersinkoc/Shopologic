<?php

declare(strict_types=1);

/**
 * Router Unit Tests
 */

use Shopologic\Core\Router\Router;
use Shopologic\Core\Router\Route;
use Shopologic\Core\Router\RouteNotFoundException;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Uri;

TestFramework::describe('Router', function() {
    TestFramework::it('should create router instance', function() {
        $router = new Router();
        TestFramework::expect($router)->toBeInstanceOf(Router::class);
    });
    
    TestFramework::it('should register GET routes', function() {
        $router = new Router();
        $route = $router->get('/users', function() {
            return 'Users list';
        });
        
        TestFramework::expect($route)->toBeInstanceOf(Route::class);
        TestFramework::expect($route->getMethod())->toBe('GET');
        TestFramework::expect($route->getPath())->toBe('/users');
    });
    
    TestFramework::it('should register POST routes', function() {
        $router = new Router();
        $route = $router->post('/users', function() {
            return 'Create user';
        });
        
        TestFramework::expect($route->getMethod())->toBe('POST');
    });
    
    TestFramework::it('should register multiple HTTP methods', function() {
        $router = new Router();
        
        $router->put('/users/{id}', function($id) {
            return "Update user {$id}";
        });
        
        $router->delete('/users/{id}', function($id) {
            return "Delete user {$id}";
        });
        
        $routes = $router->getRoutes();
        TestFramework::expect(count($routes))->toBe(2);
    });
    
    TestFramework::it('should match simple routes', function() {
        $router = new Router();
        $router->get('/about', function() {
            return 'About page';
        });
        
        $request = new Request('GET', new Uri('http://example.com/about'));
        $route = $router->match($request);
        
        TestFramework::expect($route)->toBeInstanceOf(Route::class);
        TestFramework::expect($route->getPath())->toBe('/about');
    });
    
    TestFramework::it('should match routes with parameters', function() {
        $router = new Router();
        $router->get('/users/{id}', function($id) {
            return "User {$id}";
        });
        
        $request = new Request('GET', new Uri('http://example.com/users/123'));
        $route = $router->match($request);
        
        TestFramework::expect($route)->toBeInstanceOf(Route::class);
        TestFramework::expect($route->getParameters()['id'])->toBe('123');
    });
    
    TestFramework::it('should match routes with multiple parameters', function() {
        $router = new Router();
        $router->get('/users/{userId}/posts/{postId}', function($userId, $postId) {
            return "User {$userId}, Post {$postId}";
        });
        
        $request = new Request('GET', new Uri('http://example.com/users/123/posts/456'));
        $route = $router->match($request);
        
        $params = $route->getParameters();
        TestFramework::expect($params['userId'])->toBe('123');
        TestFramework::expect($params['postId'])->toBe('456');
    });
    
    TestFramework::it('should handle optional parameters', function() {
        $router = new Router();
        $router->get('/posts/{id?}', function($id = null) {
            return $id ? "Post {$id}" : 'All posts';
        });
        
        // Test with parameter
        $request1 = new Request('GET', new Uri('http://example.com/posts/123'));
        $route1 = $router->match($request1);
        TestFramework::expect($route1->getParameters()['id'])->toBe('123');
        
        // Test without parameter
        $request2 = new Request('GET', new Uri('http://example.com/posts'));
        $route2 = $router->match($request2);
        TestFramework::expect(isset($route2->getParameters()['id']))->toBeFalse();
    });
    
    TestFramework::it('should handle parameter constraints', function() {
        $router = new Router();
        $router->get('/users/{id}', function($id) {
            return "User {$id}";
        })->where('id', '[0-9]+');
        
        // Should match numeric ID
        $request1 = new Request('GET', new Uri('http://example.com/users/123'));
        $route1 = $router->match($request1);
        TestFramework::expect($route1)->toBeInstanceOf(Route::class);
        
        // Should not match non-numeric ID
        TestFramework::expect(function() use ($router) {
            $request = new Request('GET', new Uri('http://example.com/users/abc'));
            $router->match($request);
        })->toThrow(RouteNotFoundException::class);
    });
    
    TestFramework::it('should throw exception for unmatched routes', function() {
        $router = new Router();
        $router->get('/users', function() {
            return 'Users';
        });
        
        TestFramework::expect(function() use ($router) {
            $request = new Request('GET', new Uri('http://example.com/posts'));
            $router->match($request);
        })->toThrow(RouteNotFoundException::class);
    });
    
    TestFramework::it('should handle route groups', function() {
        $router = new Router();
        
        $router->group('/api/v1', function($router) {
            $router->get('/users', function() {
                return 'API Users';
            });
            $router->get('/posts', function() {
                return 'API Posts';
            });
        });
        
        $request = new Request('GET', new Uri('http://example.com/api/v1/users'));
        $route = $router->match($request);
        
        TestFramework::expect($route->getPath())->toBe('/api/v1/users');
    });
    
    TestFramework::it('should handle route names', function() {
        $router = new Router();
        $router->get('/users', function() {
            return 'Users';
        })->name('users.index');
        
        $route = $router->getRouteByName('users.index');
        TestFramework::expect($route)->toBeInstanceOf(Route::class);
        TestFramework::expect($route->getName())->toBe('users.index');
    });
});

TestFramework::describe('Route', function() {
    TestFramework::it('should create route instance', function() {
        $route = new Route('GET', '/test', function() {
            return 'test';
        });
        
        TestFramework::expect($route)->toBeInstanceOf(Route::class);
        TestFramework::expect($route->getMethod())->toBe('GET');
        TestFramework::expect($route->getPath())->toBe('/test');
    });
    
    TestFramework::it('should handle route middleware', function() {
        $route = new Route('GET', '/test', function() {
            return 'test';
        });
        
        $route->middleware(['auth', 'throttle']);
        $middleware = $route->getMiddleware();
        
        TestFramework::expect(count($middleware))->toBe(2);
        TestFramework::expect($middleware[0])->toBe('auth');
        TestFramework::expect($middleware[1])->toBe('throttle');
    });
    
    TestFramework::it('should handle route parameters', function() {
        $route = new Route('GET', '/users/{id}', function($id) {
            return "User {$id}";
        });
        
        $route->setParameters(['id' => '123']);
        $params = $route->getParameters();
        
        TestFramework::expect($params['id'])->toBe('123');
    });
    
    TestFramework::it('should execute route action', function() {
        $route = new Route('GET', '/test', function() {
            return 'route executed';
        });
        
        $result = $route->run();
        TestFramework::expect($result)->toBe('route executed');
    });
    
    TestFramework::it('should execute route action with parameters', function() {
        $route = new Route('GET', '/users/{id}', function($id) {
            return "User ID: {$id}";
        });
        
        $route->setParameters(['id' => '456']);
        $result = $route->run();
        
        TestFramework::expect($result)->toBe('User ID: 456');
    });
});