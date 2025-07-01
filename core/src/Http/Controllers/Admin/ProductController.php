<?php

declare(strict_types=1);

namespace Shopologic\Core\Http\Controllers\Admin;

use Shopologic\Core\Template\TemplateEngine;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use Shopologic\Core\Http\Stream;
use Shopologic\Core\Auth\AuthService;
use Shopologic\Core\Search\SearchService;
use Psr\Http\Message\RequestInterface;

/**
 * Admin Product Management Controller
 */
class ProductController
{
    private TemplateEngine $template;
    private AuthService $auth;
    private SearchService $searchService;
    
    public function __construct(TemplateEngine $template)
    {
        $this->template = $template;
        $this->auth = new AuthService();
        $this->searchService = new SearchService();
    }
    
    /**
     * Display product listing
     */
    public function index(RequestInterface $request): Response
    {
        try {
            // Check admin authentication
            if (!$this->auth->isAuthenticated() || !$this->isAdmin()) {
                return $this->redirectToLogin();
            }
            
            // Get query parameters
            $queryParams = $request->getUri()->getQuery();
            parse_str($queryParams, $params);
            
            // Get products with filtering
            $searchResults = $this->searchService->searchProducts($params);
            $categories = $this->searchService->getCategories();
            
            $data = [
                'title' => 'Product Management - Shopologic Admin',
                'user' => $this->auth->getUser(),
                'products' => $searchResults['products'],
                'pagination' => $searchResults['pagination'],
                'filters' => $searchResults['filters'],
                'categories' => $categories,
                'stats' => $this->getProductStats()
            ];
            
            $content = $this->template->render('admin/products/index', $data);
            
            $body = new Stream('php://memory', 'w+');
            $body->write($content);
            
            return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
            
        } catch (\Exception $e) {
            return $this->errorResponse('Product listing error: ' . $e->getMessage());
        }
    }
    
    /**
     * Display product creation form
     */
    public function create(RequestInterface $request): Response
    {
        try {
            // Check admin authentication
            if (!$this->auth->isAuthenticated() || !$this->isAdmin()) {
                return $this->redirectToLogin();
            }
            
            $categories = $this->searchService->getCategories();
            
            $data = [
                'title' => 'Create Product - Shopologic Admin',
                'user' => $this->auth->getUser(),
                'categories' => $categories,
                'brands' => $this->getBrands(),
                'attributes' => $this->getProductAttributes()
            ];
            
            $content = $this->template->render('admin/products/create', $data);
            
            $body = new Stream('php://memory', 'w+');
            $body->write($content);
            
            return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
            
        } catch (\Exception $e) {
            return $this->errorResponse('Product create error: ' . $e->getMessage());
        }
    }
    
    /**
     * Display product edit form
     */
    public function edit(RequestInterface $request, int $id): Response
    {
        try {
            // Check admin authentication
            if (!$this->auth->isAuthenticated() || !$this->isAdmin()) {
                return $this->redirectToLogin();
            }
            
            // Get product
            $product = $this->getProductById($id);
            if (!$product) {
                return $this->notFoundResponse('Product not found');
            }
            
            $categories = $this->searchService->getCategories();
            
            $data = [
                'title' => 'Edit Product - Shopologic Admin',
                'user' => $this->auth->getUser(),
                'product' => $product,
                'categories' => $categories,
                'brands' => $this->getBrands(),
                'attributes' => $this->getProductAttributes()
            ];
            
            $content = $this->template->render('admin/products/edit', $data);
            
            $body = new Stream('php://memory', 'w+');
            $body->write($content);
            
            return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
            
        } catch (\Exception $e) {
            return $this->errorResponse('Product edit error: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle product creation
     */
    public function store(RequestInterface $request): Response
    {
        try {
            // Check admin authentication
            if (!$this->auth->isAuthenticated() || !$this->isAdmin()) {
                return $this->redirectToLogin();
            }
            
            $body = $request->getBody()->getContents();
            parse_str($body, $data);
            
            // Validate product data
            $errors = $this->validateProductData($data);
            if (!empty($errors)) {
                $_SESSION['product_errors'] = $errors;
                $_SESSION['product_data'] = $data;
                return $this->redirectToCreate();
            }
            
            // Create product (simulated)
            $productId = $this->createProduct($data);
            
            $_SESSION['success_message'] = 'Product created successfully!';
            
            return $this->redirectToEdit($productId);
            
        } catch (\Exception $e) {
            $_SESSION['product_errors'] = ['error' => $e->getMessage()];
            return $this->redirectToCreate();
        }
    }
    
    /**
     * Handle product update
     */
    public function update(RequestInterface $request, int $id): Response
    {
        try {
            // Check admin authentication
            if (!$this->auth->isAuthenticated() || !$this->isAdmin()) {
                return $this->redirectToLogin();
            }
            
            $body = $request->getBody()->getContents();
            parse_str($body, $data);
            
            // Validate product data
            $errors = $this->validateProductData($data);
            if (!empty($errors)) {
                $_SESSION['product_errors'] = $errors;
                $_SESSION['product_data'] = $data;
                return $this->redirectToEdit($id);
            }
            
            // Update product (simulated)
            $this->updateProduct($id, $data);
            
            $_SESSION['success_message'] = 'Product updated successfully!';
            
            return $this->redirectToEdit($id);
            
        } catch (\Exception $e) {
            $_SESSION['product_errors'] = ['error' => $e->getMessage()];
            return $this->redirectToEdit($id);
        }
    }
    
    /**
     * Handle product deletion
     */
    public function delete(RequestInterface $request, int $id): Response
    {
        try {
            // Check admin authentication
            if (!$this->auth->isAuthenticated() || !$this->isAdmin()) {
                return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
            }
            
            // Delete product (simulated)
            $this->deleteProduct($id);
            
            return $this->jsonResponse(['success' => true, 'message' => 'Product deleted successfully']);
            
        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get product statistics
     */
    private function getProductStats(): array
    {
        return [
            'total_products' => 567,
            'active_products' => 534,
            'out_of_stock' => 23,
            'low_stock' => 45
        ];
    }
    
    /**
     * Get product by ID
     */
    private function getProductById(int $id): ?array
    {
        // Simulated product data
        return [
            'id' => $id,
            'name' => 'Sample Product',
            'slug' => 'sample-product',
            'description' => 'This is a sample product description',
            'short_description' => 'Sample product',
            'price' => 99.99,
            'sale_price' => 79.99,
            'category' => 'electronics',
            'brand' => 'SampleBrand',
            'sku' => 'SKU-001',
            'stock' => 50,
            'featured' => true,
            'status' => 'active',
            'images' => [],
            'attributes' => []
        ];
    }
    
    /**
     * Get available brands
     */
    private function getBrands(): array
    {
        return [
            'Apple', 'Samsung', 'Nike', 'Adidas', 'Sony', 
            'Microsoft', 'Dell', 'HP', 'Lenovo', 'Asus'
        ];
    }
    
    /**
     * Get product attributes
     */
    private function getProductAttributes(): array
    {
        return [
            'Color' => ['Red', 'Blue', 'Green', 'Black', 'White'],
            'Size' => ['S', 'M', 'L', 'XL', 'XXL'],
            'Material' => ['Cotton', 'Polyester', 'Leather', 'Metal', 'Plastic']
        ];
    }
    
    /**
     * Validate product data
     */
    private function validateProductData(array $data): array
    {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors['name'] = 'Product name is required';
        }
        
        if (empty($data['price']) || !is_numeric($data['price'])) {
            $errors['price'] = 'Valid price is required';
        }
        
        if (empty($data['category'])) {
            $errors['category'] = 'Category is required';
        }
        
        if (empty($data['sku'])) {
            $errors['sku'] = 'SKU is required';
        }
        
        return $errors;
    }
    
    /**
     * Create product (simulated)
     */
    private function createProduct(array $data): int
    {
        // In real implementation, this would save to database
        return rand(1000, 9999);
    }
    
    /**
     * Update product (simulated)
     */
    private function updateProduct(int $id, array $data): void
    {
        // In real implementation, this would update database
    }
    
    /**
     * Delete product (simulated)
     */
    private function deleteProduct(int $id): void
    {
        // In real implementation, this would delete from database
    }
    
    /**
     * Check if current user is admin
     */
    private function isAdmin(): bool
    {
        $user = $this->auth->getUser();
        return $user && ($user['role'] ?? '') === 'admin';
    }
    
    /**
     * Redirect helpers
     */
    private function redirectToLogin(): Response
    {
        $body = new Stream('php://memory', 'w+');
        return new Response(302, ['Location' => '/admin/login'], $body);
    }
    
    private function redirectToCreate(): Response
    {
        $body = new Stream('php://memory', 'w+');
        return new Response(302, ['Location' => '/admin/products/create'], $body);
    }
    
    private function redirectToEdit(int $id): Response
    {
        $body = new Stream('php://memory', 'w+');
        return new Response(302, ['Location' => "/admin/products/{$id}/edit"], $body);
    }
    
    /**
     * Response helpers
     */
    private function errorResponse(string $message): Response
    {
        $body = new Stream('php://memory', 'w+');
        $body->write('<h1>Error</h1><p>' . htmlspecialchars($message) . '</p>');
        return new Response(500, ['Content-Type' => 'text/html'], $body);
    }
    
    private function notFoundResponse(string $message): Response
    {
        $body = new Stream('php://memory', 'w+');
        $body->write('<h1>Not Found</h1><p>' . htmlspecialchars($message) . '</p>');
        return new Response(404, ['Content-Type' => 'text/html'], $body);
    }
    
    private function jsonResponse(array $data, int $status = 200): Response
    {
        $body = new Stream('php://memory', 'w+');
        $body->write(json_encode($data));
        return new Response($status, ['Content-Type' => 'application/json'], $body);
    }
}