<?php

declare(strict_types=1);

namespace Shopologic\Core\Http\Controllers;

use Shopologic\PSR\Http\Message\RequestInterface;
use Shopologic\PSR\Http\Message\ResponseInterface;
use Shopologic\Core\Http\Response;
use Shopologic\Core\Http\Stream;
use Shopologic\Core\Template\TemplateEngine;
use Shopologic\Core\Cart\CartService;
use Shopologic\Core\Plugin\HookSystem;

class CartController
{
    private TemplateEngine $template;
    private CartService $cart;
    
    public function __construct(TemplateEngine $template)
    {
        $this->template = $template;
        $this->cart = new CartService();
    }
    
    /**
     * Display cart page
     */
    public function index(RequestInterface $request): ResponseInterface
    {
        try {
            $items = $this->cart->getItems();
            $totals = $this->cart->getTotals();
            
            // Apply filters to allow plugins to modify cart display
            $items = HookSystem::applyFilters('cart.display.items', $items);
            $totals = HookSystem::applyFilters('cart.display.totals', $totals);
            
            $data = [
                'title' => 'Shopping Cart',
                'cart_items' => $items,
                'cart_totals' => $totals,
                'cart_count' => $this->cart->getItemCount(),
                'is_empty' => $this->cart->isEmpty(),
                'continue_shopping_url' => $this->getUrl('products'),
                'checkout_url' => $this->getUrl('checkout')
            ];
            
            HookSystem::doAction('cart.view.before_render', $data);
            
            $content = $this->template->render('cart/index', $data);
            
            $body = new Stream('php://memory', 'w+');
            $body->write($content);
            
            return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
            
        } catch (\Exception $e) {
            return $this->errorResponse('Error loading cart: ' . $e->getMessage());
        }
    }
    
    /**
     * Add item to cart (AJAX/Form)
     */
    public function add(RequestInterface $request): ResponseInterface
    {
        try {
            $data = $this->getRequestData($request);
            
            $productId = (int) ($data['product_id'] ?? 0);
            $quantity = (int) ($data['quantity'] ?? 1);
            $options = $data['options'] ?? [];
            
            if ($productId <= 0) {
                return $this->jsonResponse(['success' => false, 'message' => 'Invalid product ID'], 400);
            }
            
            if ($quantity <= 0) {
                return $this->jsonResponse(['success' => false, 'message' => 'Invalid quantity'], 400);
            }
            
            $success = $this->cart->addItem($productId, $quantity, $options);
            
            if ($success) {
                $response = [
                    'success' => true,
                    'message' => 'Item added to cart',
                    'cart_count' => $this->cart->getItemCount(),
                    'cart_total' => $this->cart->getTotal()
                ];
                
                // If this is a regular form submission, redirect to cart
                if ($this->isFormSubmission($request)) {
                    return $this->redirectResponse('/cart?added=1');
                }
                
                return $this->jsonResponse($response);
            } else {
                return $this->jsonResponse(['success' => false, 'message' => 'Failed to add item to cart'], 500);
            }
            
        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Update cart item quantity
     */
    public function update(RequestInterface $request): ResponseInterface
    {
        try {
            $data = $this->getRequestData($request);
            
            $cartKey = $data['cart_key'] ?? '';
            $quantity = (int) ($data['quantity'] ?? 0);
            
            if (empty($cartKey)) {
                return $this->jsonResponse(['success' => false, 'message' => 'Invalid cart key'], 400);
            }
            
            $success = $this->cart->updateItem($cartKey, $quantity);
            
            if ($success) {
                $response = [
                    'success' => true,
                    'message' => $quantity > 0 ? 'Cart updated' : 'Item removed',
                    'cart_count' => $this->cart->getItemCount(),
                    'cart_total' => $this->cart->getTotal(),
                    'cart_totals' => $this->cart->getTotals()
                ];
                
                return $this->jsonResponse($response);
            } else {
                return $this->jsonResponse(['success' => false, 'message' => 'Failed to update cart'], 500);
            }
            
        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Remove item from cart
     */
    public function remove(RequestInterface $request): ResponseInterface
    {
        try {
            $data = $this->getRequestData($request);
            $cartKey = $data['cart_key'] ?? '';
            
            if (empty($cartKey)) {
                return $this->jsonResponse(['success' => false, 'message' => 'Invalid cart key'], 400);
            }
            
            $success = $this->cart->removeItem($cartKey);
            
            if ($success) {
                $response = [
                    'success' => true,
                    'message' => 'Item removed from cart',
                    'cart_count' => $this->cart->getItemCount(),
                    'cart_total' => $this->cart->getTotal()
                ];
                
                return $this->jsonResponse($response);
            } else {
                return $this->jsonResponse(['success' => false, 'message' => 'Failed to remove item'], 500);
            }
            
        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Clear entire cart
     */
    public function clear(RequestInterface $request): ResponseInterface
    {
        try {
            $this->cart->clear();
            
            $response = [
                'success' => true,
                'message' => 'Cart cleared',
                'cart_count' => 0,
                'cart_total' => 0
            ];
            
            // If this is a regular form submission, redirect to cart
            if ($this->isFormSubmission($request)) {
                return $this->redirectResponse('/cart?cleared=1');
            }
            
            return $this->jsonResponse($response);
            
        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Get cart count for AJAX requests
     */
    public function count(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->jsonResponse([
                'success' => true,
                'count' => $this->cart->getItemCount(),
                'total' => $this->cart->getTotal()
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Get request data from POST or JSON body
     */
    private function getRequestData(RequestInterface $request): array
    {
        $body = $request->getBody()->getContents();
        
        // Try JSON first
        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        if (!empty($contentType) && strpos($contentType, 'json') !== false) {
            $data = json_decode($body, true);
            return is_array($data) ? $data : [];
        }
        
        // Parse form data
        parse_str($body, $data);
        return $data ?: [];
    }
    
    /**
     * Check if this is a form submission (not AJAX)
     */
    private function isFormSubmission(RequestInterface $request): bool
    {
        // Check for AJAX headers
        $requestedWith = $request->getHeaderLine('X-Requested-With');
        if (!empty($requestedWith) && $requestedWith === 'XMLHttpRequest') {
            return false;
        }
        
        // Check for JSON content type
        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        if (!empty($contentType) && strpos($contentType, 'json') !== false) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Generate URL
     * SECURITY FIX: Prevent Host header injection attacks
     */
    private function getUrl(string $path = ''): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        // SECURITY: Validate host against whitelist to prevent Host header injection
        $requestHost = $_SERVER['HTTP_HOST'] ?? '';
        $allowedHosts = [
            'localhost:17000',
            'localhost',
            '127.0.0.1:17000',
            '127.0.0.1',
        ];

        // Load configured allowed hosts from environment
        $configuredHost = $_ENV['APP_URL'] ?? getenv('APP_URL') ?? '';
        if (!empty($configuredHost)) {
            $parsedUrl = parse_url($configuredHost);
            if (isset($parsedUrl['host'])) {
                $allowedHosts[] = $parsedUrl['host'];
                if (isset($parsedUrl['port'])) {
                    $allowedHosts[] = $parsedUrl['host'] . ':' . $parsedUrl['port'];
                }
            }
        }

        // Validate request host
        if (!in_array($requestHost, $allowedHosts, true)) {
            error_log('SECURITY WARNING: Invalid Host header detected: ' . $requestHost);
            $host = $allowedHosts[0];
        } else {
            $host = $requestHost;
        }

        $baseUrl = $protocol . '://' . $host;

        $path = ltrim($path, '/');
        return $baseUrl . '/' . $path;
    }
    
    /**
     * Create JSON response
     */
    private function jsonResponse(array $data, int $status = 200): ResponseInterface
    {
        $body = new Stream('php://memory', 'w+');
        $body->write(json_encode($data));
        
        return new Response($status, ['Content-Type' => 'application/json'], $body);
    }
    
    /**
     * Create redirect response
     */
    private function redirectResponse(string $url, int $status = 302): ResponseInterface
    {
        $body = new Stream('php://memory', 'w+');
        $body->write('');
        
        return new Response($status, ['Location' => $url], $body);
    }
    
    /**
     * Create error response
     */
    private function errorResponse(string $message, int $status = 500): ResponseInterface
    {
        $body = new Stream('php://memory', 'w+');
        $body->write('<h1>Error</h1><p>' . htmlspecialchars($message) . '</p>');
        
        return new Response($status, ['Content-Type' => 'text/html'], $body);
    }
}