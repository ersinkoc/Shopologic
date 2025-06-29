<?php

declare(strict_types=1);

/**
 * Shopologic - Enterprise E-commerce Platform
 * 
 * Front controller for the storefront
 */

// Define root path
define('SHOPOLOGIC_ROOT', dirname(__DIR__));

// Register autoloader
require_once SHOPOLOGIC_ROOT . '/core/src/Autoloader.php';

use Shopologic\Core\Autoloader;
use Shopologic\Core\Kernel\Application;
use Shopologic\Core\Http\ServerRequestFactory;

// Initialize autoloader
$autoloader = new Autoloader();
$autoloader->register();
$autoloader->addNamespace('Shopologic\\Core', SHOPOLOGIC_ROOT . '/core/src');
$autoloader->addNamespace('Shopologic\\PSR', SHOPOLOGIC_ROOT . '/core/src/PSR');
$autoloader->addNamespace('Shopologic\\Plugins', SHOPOLOGIC_ROOT . '/plugins');

// Load helper functions
require_once SHOPOLOGIC_ROOT . '/core/src/helpers.php';

try {
    // Create application
    $app = new Application(SHOPOLOGIC_ROOT);
    
    // Boot application
    $app->boot();
    
    // Handle request
    $request = ServerRequestFactory::fromGlobals();
    $response = $app->handle($request);
    
    // Send response
    $response->send();
    
    // Terminate application
    $app->terminate($request, $response);
    
} catch (\Exception $e) {
    // Handle critical errors
    http_response_code(500);
    
    if (getenv('APP_DEBUG') === 'true') {
        echo '<h1>Error</h1>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        echo '<h1>500 Internal Server Error</h1>';
        echo '<p>Something went wrong. Please try again later.</p>';
    }
    
    // Log error
    error_log('Shopologic Error: ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());
}