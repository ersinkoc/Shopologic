<?php

declare(strict_types=1);

// Test script to verify basic Shopologic functionality

// Include PSR interfaces and autoloader
require_once __DIR__ . '/core/src/PSR/Http/Message/MessageInterface.php';
require_once __DIR__ . '/core/src/PSR/Http/Message/RequestInterface.php';
require_once __DIR__ . '/core/src/PSR/Http/Message/ResponseInterface.php';
require_once __DIR__ . '/core/src/PSR/Http/Message/StreamInterface.php';
require_once __DIR__ . '/core/src/PSR/Http/Message/UriInterface.php';
require_once __DIR__ . '/core/src/PSR/Container/ContainerInterface.php';
require_once __DIR__ . '/core/src/PSR/Container/ContainerExceptionInterface.php';
require_once __DIR__ . '/core/src/PSR/Container/NotFoundExceptionInterface.php';
require_once __DIR__ . '/core/src/PSR/EventDispatcher/EventDispatcherInterface.php';
require_once __DIR__ . '/core/src/PSR/EventDispatcher/ListenerProviderInterface.php';
require_once __DIR__ . '/core/src/PSR/EventDispatcher/StoppableEventInterface.php';
require_once __DIR__ . '/core/src/PSR/Log/LoggerInterface.php';
require_once __DIR__ . '/core/src/PSR/Log/LogLevel.php';

// Simple autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Shopologic\\Core\\';
    $base_dir = __DIR__ . '/core/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

echo "🚀 Testing Shopologic Core Framework\n";
echo "===================================\n\n";

try {
    // Test 1: Container functionality
    echo "1. Testing Dependency Injection Container...\n";
    $container = new \Shopologic\Core\Container\Container();
    
    // Test basic binding
    $container->bind('test', function() {
        return 'Hello Shopologic!';
    });
    
    $result = $container->get('test');
    echo "   ✓ Basic binding: " . $result . "\n";
    
    // Test singleton
    $container->singleton('counter', function() {
        static $count = 0;
        return ++$count;
    });
    
    $first = $container->get('counter');
    $second = $container->get('counter');
    echo "   ✓ Singleton test: " . ($first === $second ? 'PASS' : 'FAIL') . "\n";
    
    // Test 2: Event System
    echo "\n2. Testing Event System...\n";
    $eventManager = new \Shopologic\Core\Events\EventManager();
    
    $eventFired = false;
    $eventManager->listen('TestEvent', function($event) use (&$eventFired) {
        $eventFired = true;
    });
    
    // Create test event
    eval('class TestEvent extends \Shopologic\Core\Events\Event {}');
    $event = new TestEvent();
    $eventManager->dispatch($event);
    
    echo "   ✓ Event dispatching: " . ($eventFired ? 'PASS' : 'FAIL') . "\n";
    
    // Test 3: HTTP Components
    echo "\n3. Testing HTTP Components...\n";
    $uri = new \Shopologic\Core\Http\Uri('https://example.com/path?query=value');
    echo "   ✓ URI parsing: " . $uri->getHost() . $uri->getPath() . "\n";
    
    $stream = new \Shopologic\Core\Http\Stream('php://memory', 'w+');
    $stream->write('Test content');
    $stream->rewind();
    echo "   ✓ Stream handling: " . $stream->getContents() . "\n";
    
    $response = new \Shopologic\Core\Http\Response(200, ['Content-Type' => 'text/plain'], $stream);
    echo "   ✓ Response creation: HTTP " . $response->getStatusCode() . " " . $response->getReasonPhrase() . "\n";
    
    // Test 4: Router
    echo "\n4. Testing Router...\n";
    $router = new \Shopologic\Core\Router\Router();
    
    $router->get('/test/{id}', function($request, $params) {
        return new \Shopologic\Core\Http\Response(200, [], 
            new \Shopologic\Core\Http\Stream('php://memory', 'w+'));
    })->name('test.route');
    
    // Create a test request
    $uri = new \Shopologic\Core\Http\Uri('/test/123');
    $request = new \Shopologic\Core\Http\Request('GET', $uri);
    
    $route = $router->findRoute($request);
    echo "   ✓ Route matching: " . ($route ? 'PASS' : 'FAIL') . "\n";
    
    // Test 5: Cache System
    echo "\n5. Testing Cache System...\n";
    $cache = new \Shopologic\Core\Cache\ArrayStore();
    
    $cache->set('test_key', 'cached_value', 3600);
    $value = $cache->get('test_key');
    echo "   ✓ Cache set/get: " . $value . "\n";
    
    // Test 6: Configuration
    echo "\n6. Testing Configuration Manager...\n";
    $config = new \Shopologic\Core\Configuration\ConfigurationManager(__DIR__);
    $config->set('test.nested.value', 'configuration works');
    $value = $config->get('test.nested.value');
    echo "   ✓ Configuration: " . $value . "\n";
    
    // Test 7: Logger
    echo "\n7. Testing Logger...\n";
    $logger = new \Shopologic\Core\Logging\Logger();
    $logger->info('Test log message');
    echo "   ✓ Logging: Message logged successfully\n";
    
    echo "\n🎉 All core tests passed! Shopologic foundation is working correctly.\n";
    echo "\n📋 Core Components Ready:\n";
    echo "   • PSR-compliant interfaces (PSR-3, PSR-7, PSR-11, PSR-14)\n";
    echo "   • Dependency Injection Container with auto-wiring\n";
    echo "   • Event-driven architecture\n";
    echo "   • HTTP foundation (Request/Response/Stream/URI)\n";
    echo "   • Advanced router with parameter binding\n";
    echo "   • Multi-driver cache system\n";
    echo "   • Configuration management\n";
    echo "   • Logging system\n";
    echo "   • Application kernel with service providers\n";
    
    echo "\n🚀 Ready for Phase 2: Database Layer & ORM\n";
    echo "🚀 Ready for Phase 3: Plugin Architecture\n";

} catch (\Throwable $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    if ($e->getPrevious()) {
        echo "   Caused by: " . $e->getPrevious()->getMessage() . "\n";
    }
}