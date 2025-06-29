<?php

declare(strict_types=1);

/**
 * Shopologic - Enterprise E-commerce Platform
 * 
 * API front controller for REST and GraphQL endpoints
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

// Set CORS headers for API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Create application
    $app = new Application(SHOPOLOGIC_ROOT);
    
    // Set API context
    $app->setContext('api');
    
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
    // Handle API errors
    http_response_code(500);
    
    $error = [
        'error' => [
            'message' => getenv('APP_DEBUG') === 'true' ? $e->getMessage() : 'Internal server error',
            'code' => 'INTERNAL_ERROR'
        ]
    ];
    
    if (getenv('APP_DEBUG') === 'true') {
        $error['error']['trace'] = $e->getTraceAsString();
        $error['error']['file'] = $e->getFile();
        $error['error']['line'] = $e->getLine();
    }
    
    echo json_encode($error, JSON_PRETTY_PRINT);
    
    // Log error
    error_log('Shopologic API Error: ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());
}