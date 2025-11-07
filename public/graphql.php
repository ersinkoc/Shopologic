<?php

declare(strict_types=1);

// GraphQL API endpoint
define('SHOPOLOGIC_START', microtime(true));
define('SHOPOLOGIC_ROOT', dirname(__DIR__));

// Autoloader
require_once SHOPOLOGIC_ROOT . '/core/src/Autoloader.php';

use Shopologic\Core\Autoloader;
use Shopologic\Core\Kernel\Application;
use Shopologic\Core\Http\ServerRequestFactory;
use Shopologic\Core\Http\Response;
use Shopologic\Core\Http\Stream;
use Shopologic\Core\API\GraphQL\Schema;
use Shopologic\Core\API\GraphQL\Executor;

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

header('Content-Type: application/json');

// SECURITY: Implement restrictive CORS policy
// Only allow specific trusted origins from configuration
$allowedOrigins = [
    'http://localhost:17000',
    'http://localhost:3000',
    'https://shopologic.com',
    'https://www.shopologic.com',
    'https://admin.shopologic.com',
];

// Get configuration if available
if (function_exists('config')) {
    $configuredOrigins = config('cors.allowed_origins', []);
    if (!empty($configuredOrigins)) {
        $allowedOrigins = $configuredOrigins;
    }
}

// Validate and set CORS headers only for allowed origins
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
} else {
    // For development: check if we're in development mode
    $isDevelopment = isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'development';
    if ($isDevelopment && !empty($origin)) {
        // In development, allow localhost origins only
        if (preg_match('/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/', $origin)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
        }
    }
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// SECURITY: Require authentication for GraphQL endpoint
$authenticated = false;
$currentUser = null;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

// Check for Bearer token (JWT)
if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];

    // Validate JWT token
    if (!empty($token) && strlen($token) > 32) {
        // TODO: Implement proper JWT validation with JwtToken class
        // For now, we require a valid-looking token to proceed
        // This should be replaced with actual JWT validation:
        // require_once SHOPOLOGIC_ROOT . '/core/src/Auth/Jwt/JwtToken.php';
        // $jwtToken = new \Shopologic\Core\Auth\Jwt\JwtToken($secret);
        // $payload = $jwtToken->decode($token);
        $authenticated = true; // Temporary - replace with real validation
    }
}

// Reject unauthenticated requests
if (!$authenticated) {
    http_response_code(401);
    echo json_encode([
        'errors' => [[
            'message' => 'Authentication required',
            'extensions' => [
                'category' => 'authentication',
                'code' => 'UNAUTHENTICATED',
                'hint' => 'Please provide a valid Bearer token in the Authorization header'
            ]
        ]]
    ]);
    exit;
}

try {
    // Create application
    $app = new Application(SHOPOLOGIC_ROOT);
    $GLOBALS['SHOPOLOGIC_APP'] = $app;
    
    // Boot application
    $app->boot();
    
    // Create request
    $request = ServerRequestFactory::fromGlobals();
    
    // Only allow POST requests for GraphQL
    if ($request->getMethod() !== 'POST') {
        throw new \Exception('GraphQL only accepts POST requests');
    }
    
    // Parse request body
    $body = (string) $request->getBody();
    $input = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception('Invalid JSON in request body');
    }
    
    $query = $input['query'] ?? '';
    $variables = $input['variables'] ?? [];
    $operationName = $input['operationName'] ?? null;
    
    if (empty($query)) {
        throw new \Exception('No query provided');
    }
    
    // Create GraphQL schema
    $schema = new Schema();
    
    // Define Product type
    $schema->type('Product', [
        'fields' => [
            'id' => ['type' => 'ID!'],
            'name' => ['type' => 'String!'],
            'description' => ['type' => 'String'],
            'price' => ['type' => 'Float!'],
            'sku' => ['type' => 'String!'],
            'stock' => ['type' => 'Int!'],
            'category' => ['type' => 'Category'],
            'images' => ['type' => '[String!]!'],
            'attributes' => ['type' => '[ProductAttribute!]!'],
            'createdAt' => ['type' => 'String!'],
            'updatedAt' => ['type' => 'String!'],
        ],
    ]);
    
    // Define Category type
    $schema->type('Category', [
        'fields' => [
            'id' => ['type' => 'ID!'],
            'name' => ['type' => 'String!'],
            'slug' => ['type' => 'String!'],
            'description' => ['type' => 'String'],
            'parent' => ['type' => 'Category'],
            'children' => ['type' => '[Category!]!'],
            'products' => ['type' => '[Product!]!'],
        ],
    ]);
    
    // Define ProductAttribute type
    $schema->type('ProductAttribute', [
        'fields' => [
            'name' => ['type' => 'String!'],
            'value' => ['type' => 'String!'],
            'type' => ['type' => 'String!'],
        ],
    ]);
    
    // Define User type
    $schema->type('User', [
        'fields' => [
            'id' => ['type' => 'ID!'],
            'email' => ['type' => 'String!'],
            'name' => ['type' => 'String!'],
            'role' => ['type' => 'String!'],
            'isActive' => ['type' => 'Boolean!'],
            'createdAt' => ['type' => 'String!'],
        ],
    ]);
    
    // Define Order type
    $schema->type('Order', [
        'fields' => [
            'id' => ['type' => 'ID!'],
            'orderNumber' => ['type' => 'String!'],
            'user' => ['type' => 'User!'],
            'items' => ['type' => '[OrderItem!]!'],
            'total' => ['type' => 'Float!'],
            'status' => ['type' => 'String!'],
            'createdAt' => ['type' => 'String!'],
        ],
    ]);
    
    // Define OrderItem type
    $schema->type('OrderItem', [
        'fields' => [
            'product' => ['type' => 'Product!'],
            'quantity' => ['type' => 'Int!'],
            'price' => ['type' => 'Float!'],
            'total' => ['type' => 'Float!'],
        ],
    ]);
    
    // Add custom scalars
    $schema->scalar('DateTime', [
        'description' => 'Date and time in ISO 8601 format',
    ]);
    
    // Define Query fields
    $schema->query('products', [
        'type' => '[Product!]!',
        'args' => [
            'limit' => 'Int',
            'offset' => 'Int',
            'category' => 'ID',
            'search' => 'String',
        ],
        'resolve' => function($root, $args, $context) {
            // Mock data for demonstration
            return [
                [
                    'id' => '1',
                    'name' => 'Premium T-Shirt',
                    'description' => 'High quality cotton t-shirt',
                    'price' => 29.99,
                    'sku' => 'TSH-001',
                    'stock' => 100,
                    'images' => ['/images/tshirt1.jpg'],
                    'attributes' => [
                        ['name' => 'Size', 'value' => 'M', 'type' => 'select'],
                        ['name' => 'Color', 'value' => 'Blue', 'type' => 'color'],
                    ],
                    'createdAt' => '2024-01-01T00:00:00Z',
                    'updatedAt' => '2024-01-01T00:00:00Z',
                ],
                [
                    'id' => '2',
                    'name' => 'Wireless Headphones',
                    'description' => 'Bluetooth wireless headphones with noise cancellation',
                    'price' => 199.99,
                    'sku' => 'HDP-001',
                    'stock' => 50,
                    'images' => ['/images/headphones1.jpg'],
                    'attributes' => [
                        ['name' => 'Color', 'value' => 'Black', 'type' => 'color'],
                        ['name' => 'Battery Life', 'value' => '30 hours', 'type' => 'text'],
                    ],
                    'createdAt' => '2024-01-02T00:00:00Z',
                    'updatedAt' => '2024-01-02T00:00:00Z',
                ],
            ];
        },
    ]);
    
    $schema->query('product', [
        'type' => 'Product',
        'args' => [
            'id' => 'ID!',
        ],
        'resolve' => function($root, $args, $context) {
            // Mock data for demonstration
            if ($args['id'] === '1') {
                return [
                    'id' => '1',
                    'name' => 'Premium T-Shirt',
                    'description' => 'High quality cotton t-shirt',
                    'price' => 29.99,
                    'sku' => 'TSH-001',
                    'stock' => 100,
                    'images' => ['/images/tshirt1.jpg'],
                    'attributes' => [
                        ['name' => 'Size', 'value' => 'M', 'type' => 'select'],
                        ['name' => 'Color', 'value' => 'Blue', 'type' => 'color'],
                    ],
                    'createdAt' => '2024-01-01T00:00:00Z',
                    'updatedAt' => '2024-01-01T00:00:00Z',
                ];
            }
            return null;
        },
    ]);
    
    $schema->query('categories', [
        'type' => '[Category!]!',
        'resolve' => function($root, $args, $context) {
            return [
                [
                    'id' => '1',
                    'name' => 'Electronics',
                    'slug' => 'electronics',
                    'description' => 'Electronic devices and accessories',
                ],
                [
                    'id' => '2',
                    'name' => 'Clothing',
                    'slug' => 'clothing',
                    'description' => 'Fashion and apparel',
                ],
            ];
        },
    ]);
    
    $schema->query('me', [
        'type' => 'User',
        'resolve' => function($root, $args, $context) {
            // Return current user or null if not authenticated
            return [
                'id' => '1',
                'email' => 'user@example.com',
                'name' => 'John Doe',
                'role' => 'customer',
                'isActive' => true,
                'createdAt' => '2024-01-01T00:00:00Z',
            ];
        },
    ]);
    
    // Define Mutation fields
    $schema->mutation('createProduct', [
        'type' => 'Product!',
        'args' => [
            'name' => 'String!',
            'description' => 'String',
            'price' => 'Float!',
            'sku' => 'String!',
            'stock' => 'Int!',
        ],
        'resolve' => function($root, $args, $context) {
            // Mock creation
            return [
                'id' => (string) rand(1000, 9999),
                'name' => $args['name'],
                'description' => $args['description'] ?? '',
                'price' => $args['price'],
                'sku' => $args['sku'],
                'stock' => $args['stock'],
                'images' => [],
                'attributes' => [],
                'createdAt' => date('c'),
                'updatedAt' => date('c'),
            ];
        },
    ]);
    
    $schema->mutation('updateProduct', [
        'type' => 'Product',
        'args' => [
            'id' => 'ID!',
            'name' => 'String',
            'description' => 'String',
            'price' => 'Float',
            'stock' => 'Int',
        ],
        'resolve' => function($root, $args, $context) {
            // Mock update
            return [
                'id' => $args['id'],
                'name' => $args['name'] ?? 'Updated Product',
                'description' => $args['description'] ?? 'Updated description',
                'price' => $args['price'] ?? 99.99,
                'sku' => 'UPD-001',
                'stock' => $args['stock'] ?? 10,
                'images' => [],
                'attributes' => [],
                'createdAt' => '2024-01-01T00:00:00Z',
                'updatedAt' => date('c'),
            ];
        },
    ]);
    
    // Create executor and execute query
    $executor = new Executor($schema);
    $result = $executor->execute($query, $variables, $operationName, [
        'user' => null, // Would be populated from authentication
        'request' => $request,
    ]);
    
    // Return JSON response
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (\Throwable $e) {
    // SECURITY: Only expose debugging information in development mode
    $isDevelopment = isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'development';

    // Log the error for debugging (always)
    error_log(sprintf(
        "GraphQL Error: %s in %s:%d\nStack trace:\n%s",
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));

    // Error response
    $extensions = ['category' => 'internal'];

    // Only include file/line in development mode
    if ($isDevelopment) {
        $extensions['file'] = $e->getFile();
        $extensions['line'] = $e->getLine();
        $extensions['trace'] = explode("\n", $e->getTraceAsString());
    }

    $error = [
        'errors' => [[
            'message' => $isDevelopment ? $e->getMessage() : 'An internal error occurred',
            'extensions' => $extensions,
        ]],
    ];

    http_response_code(500);
    echo json_encode($error, JSON_PRETTY_PRINT);
}