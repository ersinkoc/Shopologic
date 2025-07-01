<?php

declare(strict_types=1);

namespace Shopologic\Core\Router;

use Shopologic\Core\Container\ServiceProvider;

class RouterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(RouteCompiler::class);
        
        $this->singleton(Router::class, function($container) {
            return new Router();
        });
        
        $this->singleton(RouterInterface::class, Router::class);
    }

    public function boot(): void
    {
        $router = $this->container->get(RouterInterface::class);
        
        // Register basic routes
        $this->registerRoutes($router);
    }

    private function registerRoutes(RouterInterface $router): void
    {
        // Test product card route
        $router->get('/test-products', function() {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\HomeController($template);
                $request = $container->get('request');
                
                // Get sample data
                $reflection = new \ReflectionClass($controller);
                $method = $reflection->getMethod('getSampleProducts');
                $method->setAccessible(true);
                $products = $method->invoke($controller);
                
                $content = $template->render('test-product-card', ['featured_products' => $products]);
                
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write($content);
                return new \Shopologic\Core\Http\Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write('<h1>Test Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>');
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'text/html'], $body);
            }
        })->name('test.products');

        // Test route
        $router->get('/test', function() {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                
                $content = $template->render('test', ['title' => 'Test Page']);
                
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write($content);
                return new \Shopologic\Core\Http\Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write('<h1>Test Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>');
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'text/html'], $body);
            }
        })->name('test');

        // Homepage with real template
        $router->get('/', function() {
            try {
                $container = app()->getContainer();
                
                // Create template engine
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                // Create controller and render
                $controller = new \Shopologic\Core\Http\Controllers\HomeController($template);
                $request = $container->get('request');
                
                // Controller'dan gelen response'u döndür
                return $controller->index($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write('<h1>Hata Oluştu</h1>');
                $body->write('<div style="background: #f8d7da; color: #721c24; padding: 1rem; margin: 1rem; border-radius: 5px;">');
                $body->write('<strong>Hata:</strong> ' . htmlspecialchars($e->getMessage()) . '<br>');
                $body->write('<strong>Dosya:</strong> ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '<br><br>');
                $body->write('<details><summary>Detaylı Hata</summary><pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre></details>');
                $body->write('</div>');
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
            }
        })->name('home');
        
        // Products catalog
        $router->get('/products', function() {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\ProductController($template);
                $request = $container->get('request');
                return $controller->index($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write('<h1>Products Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>');
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'text/html'], $body);
            }
        })->name('products.index');
        
        // Single product by slug
        $router->get('/product/{slug}', function($request, $parameters) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\ProductController($template);
                $slug = $parameters['slug'] ?? '';
                return $controller->show($request, $slug);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write('<h1>Product Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>');
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'text/html'], $body);
            }
        })->name('products.show');

        // Health check endpoint
        $router->get('/health', function() {
            $container = app()->getContainer();
            $app = app();
            
            // Get registered service providers
            $providers = [];
            $reflection = new \ReflectionClass($app);
            $property = $reflection->getProperty('loadedProviders');
            $property->setAccessible(true);
            $loadedProviders = $property->getValue($app);
            
            $data = [
                'status' => 'ok',
                'services' => [
                    'template_engine' => $container->has(\Shopologic\Core\Template\TemplateEngine::class),
                    'plugin_manager' => $container->has(\Shopologic\Core\Plugin\PluginManager::class),
                    'hook_system' => $container->has(\Shopologic\Core\Plugin\HookSystem::class),
                    'router' => $container->has(\Shopologic\Core\Router\RouterInterface::class),
                ],
                'loaded_providers' => $loadedProviders
            ];
            $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
            $body->write(json_encode($data, JSON_PRETTY_PRINT));
            return new \Shopologic\Core\Http\Response(200, ['Content-Type' => 'application/json'], $body);
        })->name('health');

        // Test template engine from container
        $router->get('/test-template', function() {
            try {
                $container = app()->getContainer();
                
                // Try to get it from container
                $hasEngine = $container->has(\Shopologic\Core\Template\TemplateEngine::class);
                
                if ($hasEngine) {
                    $engine = $container->get(\Shopologic\Core\Template\TemplateEngine::class);
                    $content = '<h1>Template Engine Test</h1><p>Template engine loaded from container successfully!</p>';
                } else {
                    // Try to instantiate manually
                    $basePath = SHOPOLOGIC_ROOT;
                    $engine = new \Shopologic\Core\Template\TemplateEngine(true);
                    $engine->addPath($basePath . '/themes/default/templates');
                    $content = '<h1>Template Engine Test</h1><p>Template engine instantiated manually (not in container)!</p>';
                }
                
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write($content);
                return new \Shopologic\Core\Http\Response(200, ['Content-Type' => 'text/html'], $body);
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write('<h1>Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>');
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'text/html'], $body);
            }
        })->name('test.template');
        

        // Cart routes
        $router->get('/cart', function() {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\CartController($template);
                $request = $container->get('request');
                return $controller->index($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write('<h1>Cart Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>');
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'text/html'], $body);
            }
        })->name('cart.index');
        
        $router->post('/cart/add', function($request) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\CartController($template);
                return $controller->add($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write(json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]));
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'application/json'], $body);
            }
        })->name('cart.add');
        
        $router->post('/cart/update', function($request) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\CartController($template);
                return $controller->update($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write(json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]));
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'application/json'], $body);
            }
        })->name('cart.update');
        
        $router->post('/cart/remove', function($request) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\CartController($template);
                return $controller->remove($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write(json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]));
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'application/json'], $body);
            }
        })->name('cart.remove');
        
        $router->post('/cart/clear', function($request) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\CartController($template);
                return $controller->clear($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write(json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]));
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'application/json'], $body);
            }
        })->name('cart.clear');
        
        $router->get('/cart/count', function($request) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\CartController($template);
                return $controller->count($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write(json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]));
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'application/json'], $body);
            }
        })->name('cart.count');
        
        // Debug cart add
        $router->post('/debug/cart/add', function($request) {
            try {
                $body = $request->getBody()->getContents();
                $contentType = strtolower($request->getHeaderLine('Content-Type'));
                $hasContentTypeHeader = $request->hasHeader('Content-Type');
                $hasContentTypeHeaderLower = $request->hasHeader('content-type');
                $hasJsonInContentType = strpos($contentType, 'json') !== false;
                $hasJsonHeader = !empty($contentType) && $hasJsonInContentType;
                
                $debug = [
                    'body' => $body,
                    'body_length' => strlen($body),
                    'content_type' => $request->getHeaderLine('Content-Type'),
                    'content_type_lower' => $contentType,
                    'json_search_pos' => strpos($contentType, 'json'),
                    'has_content_type_header' => $hasContentTypeHeader,
                    'has_content_type_header_lower' => $hasContentTypeHeaderLower,
                    'has_json_in_content_type' => $hasJsonInContentType,
                    'has_json_header' => $hasJsonHeader,
                    'method' => $request->getMethod(),
                    'headers' => $request->getHeaders()
                ];
                
                // Parse data
                if ($hasJsonHeader) {
                    $data = json_decode($body, true);
                    $debug['json_decode_result'] = $data;
                    $debug['json_error'] = json_last_error_msg();
                } else {
                    parse_str($body, $data);
                    $debug['parse_str_result'] = $data;
                }
                
                $debug['final_data'] = $data;
                $debug['product_id'] = (int) ($data['product_id'] ?? 0);
                
                $bodyStream = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $bodyStream->write(json_encode($debug, JSON_PRETTY_PRINT));
                return new \Shopologic\Core\Http\Response(200, ['Content-Type' => 'application/json'], $bodyStream);
                
            } catch (\Exception $e) {
                $bodyStream = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $bodyStream->write(json_encode(['error' => $e->getMessage()]));
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'application/json'], $bodyStream);
            }
        })->name('debug.cart.add');

        // Checkout routes
        $router->get('/checkout', function() {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\CheckoutController($template);
                $request = $container->get('request');
                return $controller->index($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write('<h1>Checkout Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>');
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'text/html'], $body);
            }
        })->name('checkout.index');
        
        $router->post('/checkout/process', function($request) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\CheckoutController($template);
                return $controller->process($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write(json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]));
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'application/json'], $body);
            }
        })->name('checkout.process');
        
        $router->get('/checkout/success', function($request) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\CheckoutController($template);
                return $controller->success($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write('<h1>Order Confirmation Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>');
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'text/html'], $body);
            }
        })->name('checkout.success');

        // Authentication routes
        $router->get('/auth/login', function() {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\AuthController($template);
                $request = $container->get('request');
                return $controller->loginForm($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write('<h1>Login Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>');
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'text/html'], $body);
            }
        })->name('auth.login.form');
        
        $router->post('/auth/login', function($request) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\AuthController($template);
                return $controller->login($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write(json_encode(['success' => false, 'message' => 'Login error: ' . $e->getMessage()]));
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'application/json'], $body);
            }
        })->name('auth.login');
        
        $router->get('/auth/register', function() {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\AuthController($template);
                $request = $container->get('request');
                return $controller->registerForm($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write('<h1>Register Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>');
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'text/html'], $body);
            }
        })->name('auth.register.form');
        
        $router->post('/auth/register', function($request) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\AuthController($template);
                return $controller->register($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write(json_encode(['success' => false, 'message' => 'Registration error: ' . $e->getMessage()]));
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'application/json'], $body);
            }
        })->name('auth.register');
        
        $router->get('/auth/logout', function($request) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\AuthController($template);
                return $controller->logout($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write('<h1>Logout Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>');
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'text/html'], $body);
            }
        })->name('auth.logout');
        
        // Account routes
        $router->get('/account', function($request) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\AuthController($template);
                return $controller->account($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write('<h1>Account Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>');
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'text/html'], $body);
            }
        })->name('account.dashboard');
        
        $router->get('/account/profile', function($request) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\AuthController($template);
                return $controller->profile($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write('<h1>Profile Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>');
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'text/html'], $body);
            }
        })->name('account.profile');
        
        $router->post('/account/profile', function($request) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\AuthController($template);
                return $controller->updateProfile($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write(json_encode(['success' => false, 'message' => 'Profile update error: ' . $e->getMessage()]));
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'application/json'], $body);
            }
        })->name('account.profile.update');
        
        $router->get('/account/addresses', function($request) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\AuthController($template);
                return $controller->addresses($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write('<h1>Addresses Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>');
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'text/html'], $body);
            }
        })->name('account.addresses');
        
        $router->post('/account/addresses/add', function($request) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\AuthController($template);
                return $controller->addAddress($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write(json_encode(['success' => false, 'message' => 'Add address error: ' . $e->getMessage()]));
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'application/json'], $body);
            }
        })->name('account.addresses.add');

        // Search routes
        $router->get('/search', function($request) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\ProductController($template);
                return $controller->search($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write('<h1>Search Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>');
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'text/html'], $body);
            }
        })->name('search');
        
        $router->get('/search/suggestions', function($request) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\ProductController($template);
                return $controller->suggestions($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write(json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]));
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'application/json'], $body);
            }
        })->name('search.suggestions');

        // Admin routes
        $router->get('/admin', function() {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\Admin\DashboardController($template);
                $request = $container->get('request');
                return $controller->index($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write('<h1>Admin Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>');
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'text/html'], $body);
            }
        })->name('admin.dashboard');
        
        $router->get('/admin/login', function() {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\Admin\AuthController($template);
                $request = $container->get('request');
                return $controller->loginForm($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write('<h1>Admin Login Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>');
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'text/html'], $body);
            }
        })->name('admin.login.form');
        
        $router->post('/admin/login', function($request) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\Admin\AuthController($template);
                return $controller->login($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write(json_encode(['success' => false, 'message' => 'Admin login error: ' . $e->getMessage()]));
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'application/json'], $body);
            }
        })->name('admin.login');
        
        $router->get('/admin/logout', function($request) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\Admin\AuthController($template);
                return $controller->logout($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write('<h1>Admin Logout Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>');
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'text/html'], $body);
            }
        })->name('admin.logout');
        
        // Admin Product Management
        $router->get('/admin/products', function() {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\Admin\ProductController($template);
                $request = $container->get('request');
                return $controller->index($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write('<h1>Admin Products Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>');
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'text/html'], $body);
            }
        })->name('admin.products.index');
        
        $router->get('/admin/products/create', function() {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\Admin\ProductController($template);
                $request = $container->get('request');
                return $controller->create($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write('<h1>Create Product Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>');
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'text/html'], $body);
            }
        })->name('admin.products.create');
        
        $router->post('/admin/products', function($request) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\Admin\ProductController($template);
                return $controller->store($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write(json_encode(['success' => false, 'message' => 'Store product error: ' . $e->getMessage()]));
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'application/json'], $body);
            }
        })->name('admin.products.store');
        
        $router->get('/admin/products/{id}/edit', function($request, $parameters) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\Admin\ProductController($template);
                $id = (int)($parameters['id'] ?? 0);
                return $controller->edit($request, $id);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write('<h1>Edit Product Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>');
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'text/html'], $body);
            }
        })->name('admin.products.edit');
        
        $router->post('/admin/products/{id}', function($request, $parameters) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\Admin\ProductController($template);
                $id = (int)($parameters['id'] ?? 0);
                return $controller->update($request, $id);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write(json_encode(['success' => false, 'message' => 'Update product error: ' . $e->getMessage()]));
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'application/json'], $body);
            }
        })->name('admin.products.update');
        
        $router->delete('/admin/products/{id}', function($request, $parameters) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\Admin\ProductController($template);
                $id = (int)($parameters['id'] ?? 0);
                return $controller->delete($request, $id);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write(json_encode(['success' => false, 'message' => 'Delete product error: ' . $e->getMessage()]));
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'application/json'], $body);
            }
        })->name('admin.products.delete');

        // Category routes
        $router->get('/categories', function() {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\CategoryController($template);
                $request = $container->get('request');
                return $controller->index($request);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write('<h1>Categories Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>');
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'text/html'], $body);
            }
        })->name('categories.index');
        
        $router->get('/category/{slug}', function($request, $parameters) {
            try {
                $container = app()->getContainer();
                $basePath = SHOPOLOGIC_ROOT;
                $template = new \Shopologic\Core\Template\TemplateEngine(true);
                $template->addPath($basePath . '/themes/default/templates');
                $template->addGlobal('container', $container);
                
                $controller = new \Shopologic\Core\Http\Controllers\CategoryController($template);
                $slug = $parameters['slug'] ?? '';
                return $controller->show($request, $slug);
                
            } catch (\Exception $e) {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write('<h1>Category Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>');
                return new \Shopologic\Core\Http\Response(500, ['Content-Type' => 'text/html'], $body);
            }
        })->name('categories.show');

        // API routes group
        $router->group(['prefix' => 'api/v1'], function($router) {
            $router->get('/status', function() {
                $body = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
                $body->write(json_encode(['status' => 'ok', 'version' => '1.0.0']));
                return new \Shopologic\Core\Http\Response(200, ['Content-Type' => 'application/json'], $body);
            })->name('api.status');
        });
    }
}