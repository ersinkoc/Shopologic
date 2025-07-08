<?php

declare(strict_types=1);

// API Documentation Generator
define('SHOPOLOGIC_ROOT', dirname(__DIR__));

// Include autoloader
require_once SHOPOLOGIC_ROOT . '/core/src/Autoloader.php';

use Shopologic\Core\Autoloader;

// Register autoloader
$autoloader = new Autoloader();
$autoloader->addNamespace('Shopologic\\Core', SHOPOLOGIC_ROOT . '/core/src');
$autoloader->addNamespace('Shopologic\\PSR', SHOPOLOGIC_ROOT . '/core/src/PSR');
$autoloader->register();

header('Content-Type: text/html; charset=utf-8');

$apiEndpoints = [
    'rest' => [
        'base_url' => '/api/v1',
        'endpoints' => [
            [
                'method' => 'GET',
                'path' => '/products',
                'description' => 'Get all products with filtering and pagination',
                'parameters' => [
                    'limit' => 'Maximum number of products to return (default: 20)',
                    'offset' => 'Number of products to skip (default: 0)',
                    'category' => 'Filter by category ID',
                    'search' => 'Search products by name or description',
                    'sort' => 'Sort field (name, price, created_at)',
                    'order' => 'Sort order (asc, desc)'
                ],
                'example_response' => [
                    'data' => [
                        [
                            'id' => 1,
                            'name' => 'Premium T-Shirt',
                            'price' => 29.99,
                            'category' => 'Clothing',
                            'stock' => 100
                        ]
                    ],
                    'meta' => [
                        'total' => 234,
                        'limit' => 20,
                        'offset' => 0
                    ]
                ]
            ],
            [
                'method' => 'GET',
                'path' => '/products/{id}',
                'description' => 'Get a specific product by ID',
                'parameters' => [
                    'id' => 'Product ID (required)'
                ],
                'example_response' => [
                    'data' => [
                        'id' => 1,
                        'name' => 'Premium T-Shirt',
                        'description' => 'High quality cotton t-shirt',
                        'price' => 29.99,
                        'sku' => 'TSH-001',
                        'stock' => 100,
                        'category' => [
                            'id' => 1,
                            'name' => 'Clothing'
                        ],
                        'images' => ['/images/tshirt1.jpg'],
                        'attributes' => [
                            ['name' => 'Size', 'value' => 'M'],
                            ['name' => 'Color', 'value' => 'Blue']
                        ]
                    ]
                ]
            ],
            [
                'method' => 'POST',
                'path' => '/products',
                'description' => 'Create a new product',
                'auth_required' => true,
                'request_body' => [
                    'name' => 'Product name (required)',
                    'description' => 'Product description',
                    'price' => 'Product price (required)',
                    'sku' => 'Product SKU (required)',
                    'stock' => 'Stock quantity (required)',
                    'category_id' => 'Category ID',
                    'images' => 'Array of image URLs',
                    'attributes' => 'Array of product attributes'
                ],
                'example_response' => [
                    'data' => [
                        'id' => 235,
                        'name' => 'New Product',
                        'price' => 49.99,
                        'created_at' => '2025-07-01T22:00:00Z'
                    ]
                ]
            ],
            [
                'method' => 'PUT',
                'path' => '/products/{id}',
                'description' => 'Update an existing product',
                'auth_required' => true,
                'parameters' => [
                    'id' => 'Product ID (required)'
                ],
                'request_body' => [
                    'name' => 'Product name',
                    'description' => 'Product description',
                    'price' => 'Product price',
                    'stock' => 'Stock quantity'
                ]
            ],
            [
                'method' => 'DELETE',
                'path' => '/products/{id}',
                'description' => 'Delete a product',
                'auth_required' => true,
                'parameters' => [
                    'id' => 'Product ID (required)'
                ]
            ],
            [
                'method' => 'GET',
                'path' => '/categories',
                'description' => 'Get all product categories',
                'example_response' => [
                    'data' => [
                        [
                            'id' => 1,
                            'name' => 'Electronics',
                            'slug' => 'electronics',
                            'parent_id' => null,
                            'product_count' => 45
                        ]
                    ]
                ]
            ],
            [
                'method' => 'GET',
                'path' => '/orders',
                'description' => 'Get orders (authenticated users only)',
                'auth_required' => true,
                'parameters' => [
                    'status' => 'Filter by order status',
                    'limit' => 'Number of orders to return',
                    'offset' => 'Number of orders to skip'
                ]
            ],
            [
                'method' => 'POST',
                'path' => '/orders',
                'description' => 'Create a new order',
                'auth_required' => true,
                'request_body' => [
                    'items' => 'Array of order items with product_id and quantity',
                    'shipping_address' => 'Shipping address object',
                    'payment_method' => 'Payment method'
                ]
            ]
        ]
    ],
    'graphql' => [
        'endpoint' => '/graphql.php',
        'description' => 'GraphQL endpoint for flexible data querying',
        'queries' => [
            'products' => [
                'description' => 'Get products with flexible field selection',
                'args' => [
                    'limit' => 'Int',
                    'offset' => 'Int',
                    'category' => 'ID',
                    'search' => 'String'
                ],
                'example' => '
{
  products(limit: 10, category: 1) {
    id
    name
    price
    category {
      name
    }
    images
  }
}'
            ],
            'product' => [
                'description' => 'Get a single product by ID',
                'args' => [
                    'id' => 'ID! (required)'
                ],
                'example' => '
{
  product(id: "1") {
    id
    name
    description
    price
    sku
    stock
    attributes {
      name
      value
      type
    }
  }
}'
            ],
            'categories' => [
                'description' => 'Get all categories',
                'example' => '
{
  categories {
    id
    name
    slug
    children {
      id
      name
    }
  }
}'
            ],
            'me' => [
                'description' => 'Get current authenticated user',
                'auth_required' => true,
                'example' => '
{
  me {
    id
    email
    name
    role
  }
}'
            ]
        ],
        'mutations' => [
            'createProduct' => [
                'description' => 'Create a new product',
                'auth_required' => true,
                'args' => [
                    'name' => 'String!',
                    'description' => 'String',
                    'price' => 'Float!',
                    'sku' => 'String!',
                    'stock' => 'Int!'
                ],
                'example' => '
mutation {
  createProduct(
    name: "New Product"
    price: 49.99
    sku: "NEW-001"
    stock: 100
  ) {
    id
    name
    price
    createdAt
  }
}'
            ],
            'updateProduct' => [
                'description' => 'Update an existing product',
                'auth_required' => true,
                'args' => [
                    'id' => 'ID!',
                    'name' => 'String',
                    'price' => 'Float',
                    'stock' => 'Int'
                ],
                'example' => '
mutation {
  updateProduct(
    id: "1"
    name: "Updated Product"
    price: 59.99
  ) {
    id
    name
    price
    updatedAt
  }
}'
            ]
        ]
    ]
];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Shopologic API Documentation</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8f9fa; line-height: 1.6; }
        
        .header { background: #1a1d23; color: white; padding: 2rem 0; text-align: center; }
        .header h1 { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .header p { font-size: 1.1rem; opacity: 0.8; }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        
        .nav-tabs { display: flex; gap: 1rem; margin-bottom: 2rem; border-bottom: 2px solid #dee2e6; }
        .nav-tab { padding: 1rem 2rem; background: white; border: 1px solid #dee2e6; border-bottom: none; cursor: pointer; border-radius: 8px 8px 0 0; transition: all 0.3s; }
        .nav-tab.active { background: #007bff; color: white; border-color: #007bff; }
        .nav-tab:hover:not(.active) { background: #f8f9fa; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .endpoint { background: white; border-radius: 8px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .endpoint-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        .method { padding: 0.25rem 0.75rem; border-radius: 4px; font-weight: bold; font-size: 0.8rem; }
        .method.GET { background: #d4edda; color: #155724; }
        .method.POST { background: #d1ecf1; color: #0c5460; }
        .method.PUT { background: #fff3cd; color: #856404; }
        .method.DELETE { background: #f8d7da; color: #721c24; }
        .path { font-family: 'Courier New', monospace; font-size: 1.1rem; font-weight: bold; }
        .auth-badge { background: #ffc107; color: #856404; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem; }
        
        .description { margin-bottom: 1rem; color: #6c757d; }
        
        .section { margin-bottom: 1.5rem; }
        .section h4 { color: #495057; margin-bottom: 0.5rem; font-size: 1rem; }
        
        .param-list, .field-list { list-style: none; }
        .param-item, .field-item { background: #f8f9fa; padding: 0.75rem; margin-bottom: 0.5rem; border-radius: 4px; border-left: 4px solid #007bff; }
        .param-name, .field-name { font-weight: bold; color: #007bff; }
        .param-desc, .field-desc { color: #6c757d; margin-top: 0.25rem; }
        
        .code-block { background: #2d3748; color: #e2e8f0; padding: 1.5rem; border-radius: 8px; font-family: 'Courier New', monospace; overflow-x: auto; margin: 1rem 0; }
        .code-block pre { margin: 0; }
        
        .graphql-section { background: white; border-radius: 8px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .graphql-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        .graphql-type { padding: 0.25rem 0.75rem; border-radius: 4px; font-weight: bold; font-size: 0.8rem; }
        .graphql-type.query { background: #d4edda; color: #155724; }
        .graphql-type.mutation { background: #d1ecf1; color: #0c5460; }
        
        .try-button { background: #28a745; color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; margin-top: 1rem; }
        .try-button:hover { background: #218838; }
        
        .intro { background: white; border-radius: 8px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-top: 1.5rem; }
        .feature { background: #f8f9fa; padding: 1.5rem; border-radius: 8px; border-left: 4px solid #007bff; }
        .feature h4 { color: #007bff; margin-bottom: 0.5rem; }
        
        .base-url { background: #e9ecef; padding: 1rem; border-radius: 4px; font-family: 'Courier New', monospace; margin-bottom: 1rem; }
        
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .nav-tabs { flex-direction: column; }
            .endpoint-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔗 Shopologic API Documentation</h1>
        <p>Complete API reference for REST and GraphQL endpoints</p>
    </div>
    
    <div class="container">
        <div class="intro">
            <h2>Welcome to Shopologic API</h2>
            <p>Shopologic provides both REST and GraphQL APIs for flexible integration with your applications. Our APIs are built with modern standards and provide comprehensive access to all platform features.</p>
            
            <div class="feature-grid">
                <div class="feature">
                    <h4>🔄 REST API</h4>
                    <p>Traditional RESTful endpoints with standard HTTP methods. Perfect for simple integrations and standard CRUD operations.</p>
                </div>
                <div class="feature">
                    <h4>🔀 GraphQL API</h4>
                    <p>Flexible query language that lets you request exactly the data you need. Great for complex queries and reducing over-fetching.</p>
                </div>
                <div class="feature">
                    <h4>🔐 Authentication</h4>
                    <p>Secure API access with JWT tokens and API keys. Role-based permissions ensure proper access control.</p>
                </div>
                <div class="feature">
                    <h4>📊 Real-time Updates</h4>
                    <p>WebSocket support for real-time notifications and live data updates across your applications.</p>
                </div>
            </div>
        </div>
        
        <div class="nav-tabs">
            <div class="nav-tab active" onclick="showTab('rest')">REST API</div>
            <div class="nav-tab" onclick="showTab('graphql')">GraphQL API</div>
            <div class="nav-tab" onclick="showTab('auth')">Authentication</div>
            <div class="nav-tab" onclick="showTab('examples')">Code Examples</div>
        </div>
        
        <div id="rest" class="tab-content active">
            <h2>REST API Endpoints</h2>
            <div class="base-url">Base URL: <?php echo $apiEndpoints['rest']['base_url']; ?></div>
            
            <?php foreach ($apiEndpoints['rest']['endpoints'] as $endpoint): ?>
            <div class="endpoint">
                <div class="endpoint-header">
                    <span class="method <?php echo $endpoint['method']; ?>"><?php echo $endpoint['method']; ?></span>
                    <span class="path"><?php echo $endpoint['path']; ?></span>
                    <?php if (isset($endpoint['auth_required']) && $endpoint['auth_required']): ?>
                    <span class="auth-badge">🔐 Auth Required</span>
                    <?php endif; ?>
                </div>
                
                <div class="description"><?php echo $endpoint['description']; ?></div>
                
                <?php if (isset($endpoint['parameters'])): ?>
                <div class="section">
                    <h4>Parameters</h4>
                    <ul class="param-list">
                        <?php foreach ($endpoint['parameters'] as $name => $desc): ?>
                        <li class="param-item">
                            <div class="param-name"><?php echo $name; ?></div>
                            <div class="param-desc"><?php echo $desc; ?></div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <?php if (isset($endpoint['request_body'])): ?>
                <div class="section">
                    <h4>Request Body</h4>
                    <ul class="param-list">
                        <?php foreach ($endpoint['request_body'] as $name => $desc): ?>
                        <li class="param-item">
                            <div class="param-name"><?php echo $name; ?></div>
                            <div class="param-desc"><?php echo $desc; ?></div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <?php if (isset($endpoint['example_response'])): ?>
                <div class="section">
                    <h4>Example Response</h4>
                    <div class="code-block">
                        <pre><?php echo json_encode($endpoint['example_response'], JSON_PRETTY_PRINT); ?></pre>
                    </div>
                </div>
                <?php endif; ?>
                
                <button class="try-button" onclick="tryEndpoint('<?php echo $endpoint['method']; ?>', '<?php echo $endpoint['path']; ?>')">
                    🧪 Try this endpoint
                </button>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div id="graphql" class="tab-content">
            <h2>GraphQL API</h2>
            <div class="base-url">Endpoint: <?php echo $apiEndpoints['graphql']['endpoint']; ?></div>
            <p style="margin-bottom: 2rem;"><?php echo $apiEndpoints['graphql']['description']; ?></p>
            
            <h3>Queries</h3>
            <?php foreach ($apiEndpoints['graphql']['queries'] as $name => $query): ?>
            <div class="graphql-section">
                <div class="graphql-header">
                    <span class="graphql-type query">QUERY</span>
                    <span class="path"><?php echo $name; ?></span>
                    <?php if (isset($query['auth_required']) && $query['auth_required']): ?>
                    <span class="auth-badge">🔐 Auth Required</span>
                    <?php endif; ?>
                </div>
                
                <div class="description"><?php echo $query['description']; ?></div>
                
                <?php if (isset($query['args'])): ?>
                <div class="section">
                    <h4>Arguments</h4>
                    <ul class="param-list">
                        <?php foreach ($query['args'] as $name => $type): ?>
                        <li class="param-item">
                            <div class="param-name"><?php echo $name; ?>: <?php echo $type; ?></div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <div class="section">
                    <h4>Example Query</h4>
                    <div class="code-block">
                        <pre><?php echo trim($query['example']); ?></pre>
                    </div>
                </div>
                
                <button class="try-button" onclick="tryGraphQL('<?php echo addslashes($query['example']); ?>')">
                    🔀 Try in GraphQL Playground
                </button>
            </div>
            <?php endforeach; ?>
            
            <h3>Mutations</h3>
            <?php foreach ($apiEndpoints['graphql']['mutations'] as $name => $mutation): ?>
            <div class="graphql-section">
                <div class="graphql-header">
                    <span class="graphql-type mutation">MUTATION</span>
                    <span class="path"><?php echo $name; ?></span>
                    <?php if (isset($mutation['auth_required']) && $mutation['auth_required']): ?>
                    <span class="auth-badge">🔐 Auth Required</span>
                    <?php endif; ?>
                </div>
                
                <div class="description"><?php echo $mutation['description']; ?></div>
                
                <?php if (isset($mutation['args'])): ?>
                <div class="section">
                    <h4>Arguments</h4>
                    <ul class="param-list">
                        <?php foreach ($mutation['args'] as $name => $type): ?>
                        <li class="param-item">
                            <div class="param-name"><?php echo $name; ?>: <?php echo $type; ?></div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <div class="section">
                    <h4>Example Mutation</h4>
                    <div class="code-block">
                        <pre><?php echo trim($mutation['example']); ?></pre>
                    </div>
                </div>
                
                <button class="try-button" onclick="tryGraphQL('<?php echo addslashes($mutation['example']); ?>')">
                    🔀 Try in GraphQL Playground
                </button>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div id="auth" class="tab-content">
            <h2>Authentication</h2>
            
            <div class="endpoint">
                <h3>🔐 Authentication Methods</h3>
                <p>Shopologic API supports multiple authentication methods for different use cases:</p>
                
                <div class="section">
                    <h4>1. JWT Token Authentication</h4>
                    <p>For user-specific operations and web applications.</p>
                    <div class="code-block">
                        <pre>Authorization: Bearer your-jwt-token-here</pre>
                    </div>
                </div>
                
                <div class="section">
                    <h4>2. API Key Authentication</h4>
                    <p>For server-to-server communication and integrations.</p>
                    <div class="code-block">
                        <pre>X-API-Key: your-api-key-here</pre>
                    </div>
                </div>
                
                <div class="section">
                    <h4>3. OAuth 2.0</h4>
                    <p>For third-party applications and services.</p>
                    <div class="code-block">
                        <pre>Authorization: Bearer oauth-access-token</pre>
                    </div>
                </div>
            </div>
            
            <div class="endpoint">
                <h3>🎯 Rate Limiting</h3>
                <p>API requests are rate-limited to ensure fair usage and system stability:</p>
                
                <ul class="param-list">
                    <li class="param-item">
                        <div class="param-name">Standard Rate Limit</div>
                        <div class="param-desc">1000 requests per hour per API key</div>
                    </li>
                    <li class="param-item">
                        <div class="param-name">Burst Limit</div>
                        <div class="param-desc">100 requests per minute</div>
                    </li>
                    <li class="param-item">
                        <div class="param-name">GraphQL Complexity</div>
                        <div class="param-desc">Maximum query complexity: 1000 points</div>
                    </li>
                </ul>
            </div>
            
            <div class="endpoint">
                <h3>🛡️ Security Best Practices</h3>
                <ul class="param-list">
                    <li class="param-item">
                        <div class="param-name">HTTPS Only</div>
                        <div class="param-desc">All API requests must use HTTPS encryption</div>
                    </li>
                    <li class="param-item">
                        <div class="param-name">Token Expiration</div>
                        <div class="param-desc">JWT tokens expire after 24 hours by default</div>
                    </li>
                    <li class="param-item">
                        <div class="param-name">CORS Policy</div>
                        <div class="param-desc">Configure allowed origins in your API settings</div>
                    </li>
                    <li class="param-item">
                        <div class="param-name">IP Whitelisting</div>
                        <div class="param-desc">Restrict API access to specific IP addresses</div>
                    </li>
                </ul>
            </div>
        </div>
        
        <div id="examples" class="tab-content">
            <h2>Code Examples</h2>
            
            <div class="endpoint">
                <h3>🐘 PHP Example</h3>
                <div class="code-block">
                    <pre><?php echo htmlspecialchars('
// REST API Example
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://yourstore.com/api/v1/products");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer your-jwt-token",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$data = json_decode($response, true);
curl_close($ch);

// GraphQL Example
$query = \'{"query": "{ products { id name price } }"}\';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://yourstore.com/graphql.php");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$data = json_decode($response, true);
curl_close($ch);
'); ?></pre>
                </div>
            </div>
            
            <div class="endpoint">
                <h3>🟨 JavaScript Example</h3>
                <div class="code-block">
                    <pre><?php echo htmlspecialchars('
// REST API Example
async function getProducts() {
    const response = await fetch("/api/v1/products", {
        headers: {
            "Authorization": "Bearer your-jwt-token",
            "Content-Type": "application/json"
        }
    });
    
    const data = await response.json();
    return data;
}

// GraphQL Example
async function getProductsGraphQL() {
    const query = `
        {
            products(limit: 10) {
                id
                name
                price
                category { name }
            }
        }
    `;
    
    const response = await fetch("/graphql.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({ query })
    });
    
    const data = await response.json();
    return data;
}
'); ?></pre>
                </div>
            </div>
            
            <div class="endpoint">
                <h3>🐍 Python Example</h3>
                <div class="code-block">
                    <pre><?php echo htmlspecialchars('
import requests
import json

# REST API Example
headers = {
    "Authorization": "Bearer your-jwt-token",
    "Content-Type": "application/json"
}

response = requests.get("https://yourstore.com/api/v1/products", headers=headers)
data = response.json()

# GraphQL Example
query = """
{
    products(limit: 10) {
        id
        name
        price
        category { name }
    }
}
"""

payload = {"query": query}
response = requests.post(
    "https://yourstore.com/graphql.php",
    json=payload,
    headers={"Content-Type": "application/json"}
)

data = response.json()
'); ?></pre>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.nav-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
        
        function tryEndpoint(method, path) {
            const baseUrl = '<?php echo $apiEndpoints['rest']['base_url']; ?>';
            const fullUrl = baseUrl + path;
            
            if (method === 'GET') {
                window.open(fullUrl, '_blank');
            } else {
                alert(`To test ${method} requests, use a tool like Postman or curl with the endpoint: ${fullUrl}`);
            }
        }
        
        function tryGraphQL(query) {
            // Create a simple GraphQL playground
            const graphqlUrl = '/graphql.php';
            const playgroundUrl = `${graphqlUrl}?query=${encodeURIComponent(query)}`;
            
            // For now, just open the GraphQL endpoint
            window.open(graphqlUrl, '_blank');
        }
        
        console.log('Shopologic API Documentation loaded');
        console.log('Available endpoints:');
        console.log('- REST API: <?php echo $apiEndpoints['rest']['base_url']; ?>');
        console.log('- GraphQL: <?php echo $apiEndpoints['graphql']['endpoint']; ?>');
    </script>
</body>
</html>