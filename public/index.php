<?php

declare(strict_types=1);

// Define constants
define('SHOPOLOGIC_START', microtime(true));
define('SHOPOLOGIC_ROOT', dirname(__DIR__));

// Autoloader
require_once SHOPOLOGIC_ROOT . '/core/src/Autoloader.php';

use Shopologic\Core\Autoloader;
use Shopologic\Core\Kernel\Application;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;

// Register autoloader
$autoloader = new Autoloader();
$autoloader->addNamespace('Shopologic\\Core', SHOPOLOGIC_ROOT . '/core/src');
$autoloader->addNamespace('Shopologic\\PSR', SHOPOLOGIC_ROOT . '/core/src/PSR');
$autoloader->addNamespace('Shopologic\\Plugins', SHOPOLOGIC_ROOT . '/plugins');
$autoloader->register();

// Load helper functions
if (file_exists(SHOPOLOGIC_ROOT . '/core/src/helpers.php')) {
    require_once SHOPOLOGIC_ROOT . '/core/src/helpers.php';
}

try {
    // Create application
    $app = new Application(SHOPOLOGIC_ROOT);
    
    // Store app instance globally for helper functions
    $GLOBALS['SHOPOLOGIC_APP'] = $app;
    
    // Configuration is loaded automatically by ConfigurationManager
    
    // Register plugin service provider
    $app->register(\Shopologic\Core\Plugin\PluginServiceProvider::class);
    
    // Boot application
    $app->boot();
    
    // Load and activate plugins (if plugin manager exists)
    if ($app->getContainer()->has(\Shopologic\Core\Plugin\PluginManager::class)) {
        $pluginManager = $app->getContainer()->get(\Shopologic\Core\Plugin\PluginManager::class);
        // Methods will be implemented in PluginManager
        // $pluginManager->loadAll();
        // $pluginManager->bootAll();
    }
    
    // Create request from globals
    $request = \Shopologic\Core\Http\ServerRequestFactory::fromGlobals();
    
    // Handle request
    $response = $app->handle($request);
    
    // Send response
    $response->send();
    
    // Terminate
    $app->terminate($request, $response);
    
} catch (\Exception $e) {
    // Error handling
    $isDevelopment = isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'development';

    // Log the error regardless of environment
    error_log(sprintf(
        "Application Error: %s in %s:%d\nStack trace:\n%s",
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));

    if ($isDevelopment) {
        $content = sprintf(
            '<h1>Error: %s</h1><pre>%s</pre>',
            htmlspecialchars($e->getMessage()),
            htmlspecialchars($e->getTraceAsString())
        );
    } else {
        $content = '<h1>Internal Server Error</h1><p>Something went wrong. Please try again later.</p>';
    }
    
    // Create a stream for the response body
    $stream = new \Shopologic\Core\Http\Stream('php://temp', 'w+');
    $stream->write($content);
    
    $response = new Response(500, ['Content-Type' => 'text/html'], $stream);
    $response->send();
}