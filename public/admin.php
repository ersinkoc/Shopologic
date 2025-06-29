<?php

declare(strict_types=1);

/**
 * Shopologic - Enterprise E-commerce Platform
 * 
 * Admin panel front controller
 */

// Define root path
define('SHOPOLOGIC_ROOT', dirname(__DIR__));

// Register autoloader
require_once SHOPOLOGIC_ROOT . '/core/src/Autoloader.php';

use Shopologic\Core\Autoloader;
use Shopologic\Core\Application;
use Shopologic\Core\Http\Request;

// Initialize autoloader
$autoloader = new Autoloader();
$autoloader->register();
$autoloader->addNamespace('Shopologic\\Core', SHOPOLOGIC_ROOT . '/core/src');
$autoloader->addNamespace('Shopologic\\Plugins', SHOPOLOGIC_ROOT . '/plugins');

try {
    // Create application
    $app = new Application(SHOPOLOGIC_ROOT);
    
    // Set admin context
    $app->setContext('admin');
    
    // Boot application
    $app->boot();
    
    // Handle request
    $request = Request::createFromGlobals();
    $response = $app->handle($request);
    
    // Send response
    $response->send();
    
    // Terminate application
    $app->terminate($request, $response);
    
} catch (\Exception $e) {
    // Handle critical errors
    http_response_code(500);
    
    if (getenv('APP_DEBUG') === 'true') {
        echo '<h1>Admin Error</h1>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        echo '<h1>500 Internal Server Error</h1>';
        echo '<p>Something went wrong. Please try again later.</p>';
    }
    
    // Log error
    error_log('Shopologic Admin Error: ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());
}