<?php

declare(strict_types=1);

namespace Shopologic\Core\Http\Controllers;

use Shopologic\PSR\Http\Message\RequestInterface;
use Shopologic\PSR\Http\Message\ResponseInterface;
use Shopologic\Core\Http\Response;
use Shopologic\Core\Http\Stream;
use Shopologic\Core\Template\TemplateEngine;
use Shopologic\Core\Cart\CartService;
use Shopologic\Core\Order\OrderService;
use Shopologic\Core\Auth\AuthService;
use Shopologic\Core\Plugin\HookSystem;

class CheckoutController
{
    private TemplateEngine $template;
    private CartService $cart;
    private OrderService $orderService;
    private AuthService $authService;
    
    public function __construct(TemplateEngine $template)
    {
        $this->template = $template;
        $this->cart = new CartService();
        $this->orderService = new OrderService();
        $this->authService = new AuthService();
    }
    
    /**
     * Display checkout page
     */
    public function index(RequestInterface $request): ResponseInterface
    {
        try {
            // Check if cart is empty
            if ($this->cart->isEmpty()) {
                return $this->redirectResponse('/cart?empty=1');
            }
            
            $items = $this->cart->getItems();
            $totals = $this->cart->getTotals();
            
            // Get user info if logged in
            $user = $this->authService->getCurrentUser();
            $isLoggedIn = $this->authService->isLoggedIn();
            
            // Pre-fill form with user data if logged in
            $defaultData = [];
            if ($isLoggedIn && $user) {
                $defaultData = [
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'email' => $user['email'],
                    'phone' => $user['phone'] ?? ''
                ];
                
                // Get default addresses
                $billingAddress = $this->authService->getDefaultAddress('billing');
                $shippingAddress = $this->authService->getDefaultAddress('shipping');
                
                if ($billingAddress) {
                    $defaultData = array_merge($defaultData, [
                        'billing_first_name' => $billingAddress['first_name'],
                        'billing_last_name' => $billingAddress['last_name'],
                        'billing_company' => $billingAddress['company'],
                        'billing_address_1' => $billingAddress['address_1'],
                        'billing_address_2' => $billingAddress['address_2'],
                        'billing_city' => $billingAddress['city'],
                        'billing_state' => $billingAddress['state'],
                        'billing_postcode' => $billingAddress['postcode'],
                        'billing_country' => $billingAddress['country']
                    ]);
                }
                
                if ($shippingAddress) {
                    $defaultData = array_merge($defaultData, [
                        'shipping_first_name' => $shippingAddress['first_name'],
                        'shipping_last_name' => $shippingAddress['last_name'],
                        'shipping_company' => $shippingAddress['company'],
                        'shipping_address_1' => $shippingAddress['address_1'],
                        'shipping_address_2' => $shippingAddress['address_2'],
                        'shipping_city' => $shippingAddress['city'],
                        'shipping_state' => $shippingAddress['state'],
                        'shipping_postcode' => $shippingAddress['postcode'],
                        'shipping_country' => $shippingAddress['country']
                    ]);
                }
            }
            
            // Get available payment methods
            $paymentMethods = $this->getAvailablePaymentMethods();
            
            // Get available shipping methods
            $shippingMethods = $this->getAvailableShippingMethods();
            
            // Get available countries
            $countries = $this->getAvailableCountries();
            
            $data = [
                'title' => 'Checkout',
                'cart_items' => $items,
                'cart_totals' => $totals,
                'cart_count' => $this->cart->getItemCount(),
                'payment_methods' => $paymentMethods,
                'shipping_methods' => $shippingMethods,
                'countries' => $countries,
                'cart_url' => '/cart',
                'checkout_process_url' => '/checkout/process',
                // Authentication data
                'is_logged_in' => $isLoggedIn,
                'user' => $user,
                'default_data' => $defaultData,
                'login_url' => '/auth/login',
                'register_url' => '/auth/register'
            ];
            
            // Apply filters to allow plugins to modify checkout data
            $data = HookSystem::applyFilters('checkout.page_data', $data);
            
            HookSystem::doAction('checkout.page.before_render', $data);
            
            $content = $this->template->render('checkout/index', $data);
            
            $body = new Stream('php://memory', 'w+');
            $body->write($content);
            
            return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
            
        } catch (\Exception $e) {
            return $this->errorResponse('Error loading checkout: ' . $e->getMessage());
        }
    }
    
    /**
     * Process checkout form submission
     */
    public function process(RequestInterface $request): ResponseInterface
    {
        try {
            // Check if cart is empty
            if ($this->cart->isEmpty()) {
                return $this->jsonResponse(['success' => false, 'message' => 'Cart is empty'], 400);
            }
            
            $data = $this->getRequestData($request);
            
            // Validate checkout data
            $validation = $this->validateCheckoutData($data);
            if (!$validation['valid']) {
                return $this->jsonResponse([
                    'success' => false, 
                    'message' => 'Validation failed',
                    'errors' => $validation['errors']
                ], 400);
            }
            
            // Prepare order data
            $customerData = [
                'email' => $data['email'] ?? '',
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'phone' => $data['phone'] ?? '',
                'billing_first_name' => $data['billing_first_name'] ?? $data['first_name'] ?? '',
                'billing_last_name' => $data['billing_last_name'] ?? $data['last_name'] ?? '',
                'billing_company' => $data['billing_company'] ?? '',
                'billing_address_1' => $data['billing_address_1'] ?? '',
                'billing_address_2' => $data['billing_address_2'] ?? '',
                'billing_city' => $data['billing_city'] ?? '',
                'billing_state' => $data['billing_state'] ?? '',
                'billing_postcode' => $data['billing_postcode'] ?? '',
                'billing_country' => $data['billing_country'] ?? 'US',
                'order_notes' => $data['order_notes'] ?? '',
            ];
            
            $shippingData = [
                'shipping_method' => $data['shipping_method'] ?? 'standard',
                'shipping_first_name' => $data['shipping_first_name'] ?? $data['first_name'] ?? '',
                'shipping_last_name' => $data['shipping_last_name'] ?? $data['last_name'] ?? '',
                'shipping_company' => $data['shipping_company'] ?? '',
                'shipping_address_1' => $data['shipping_address_1'] ?? '',
                'shipping_address_2' => $data['shipping_address_2'] ?? '',
                'shipping_city' => $data['shipping_city'] ?? '',
                'shipping_state' => $data['shipping_state'] ?? '',
                'shipping_postcode' => $data['shipping_postcode'] ?? '',
                'shipping_country' => $data['shipping_country'] ?? 'US',
            ];
            
            $paymentData = [
                'payment_method' => $data['payment_method'] ?? 'card',
                'card_number' => $data['card_number'] ?? '',
                'expiry_month' => $data['expiry_month'] ?? '',
                'expiry_year' => $data['expiry_year'] ?? '',
                'cvv' => $data['cvv'] ?? '',
                'cardholder_name' => $data['cardholder_name'] ?? '',
            ];
            
            // Create order
            $order = $this->orderService->createOrder($this->cart, $customerData, $shippingData, $paymentData);
            if (!$order) {
                return $this->jsonResponse(['success' => false, 'message' => 'Failed to create order'], 500);
            }
            
            // Process payment
            $paymentResult = $this->orderService->processPayment($order['id'], $paymentData);
            
            if ($paymentResult['success']) {
                // Clear cart after successful payment
                $this->cart->clear();
                
                $response = [
                    'success' => true,
                    'message' => 'Order placed successfully',
                    'order_id' => $order['id'],
                    'order_number' => $order['order_number'],
                    'redirect_url' => $this->getUrl('checkout/success?order=' . $order['id'])
                ];
                
                // If this is a regular form submission, redirect
                if ($this->isFormSubmission($request)) {
                    return $this->redirectResponse('/checkout/success?order=' . $order['id']);
                }
                
                return $this->jsonResponse($response);
            } else {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => $paymentResult['message'],
                    'order_id' => $order['id']
                ], 400);
            }
            
        } catch (\Exception $e) {
            error_log('Checkout processing error: ' . $e->getMessage());
            return $this->jsonResponse(['success' => false, 'message' => 'Checkout processing failed'], 500);
        }
    }
    
    /**
     * Display order success page
     */
    public function success(RequestInterface $request): ResponseInterface
    {
        try {
            $query = $request->getUri()->getQuery();
            parse_str($query, $params);
            $orderId = $params['order'] ?? '';
            
            if (empty($orderId)) {
                return $this->redirectResponse('/cart');
            }
            
            $order = $this->orderService->getOrder($orderId);
            if (!$order) {
                return $this->redirectResponse('/cart');
            }
            
            $data = [
                'title' => 'Order Confirmation',
                'order' => $order,
                'continue_shopping_url' => $this->getUrl('products'),
            ];
            
            // Apply filters to allow plugins to modify success page data
            $data = HookSystem::applyFilters('checkout.success_data', $data);
            
            HookSystem::doAction('checkout.success.before_render', $data, $order);
            
            $content = $this->template->render('checkout/success', $data);
            
            $body = new Stream('php://memory', 'w+');
            $body->write($content);
            
            return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
            
        } catch (\Exception $e) {
            return $this->errorResponse('Error loading order confirmation: ' . $e->getMessage());
        }
    }
    
    /**
     * Get available payment methods
     */
    private function getAvailablePaymentMethods(): array
    {
        $methods = [
            'card' => [
                'id' => 'card',
                'title' => 'Credit/Debit Card',
                'description' => 'Pay securely with your credit or debit card',
                'icon' => 'credit-card',
                'enabled' => true
            ],
            'paypal' => [
                'id' => 'paypal',
                'title' => 'PayPal',
                'description' => 'Pay with your PayPal account',
                'icon' => 'paypal',
                'enabled' => true
            ],
            'bank_transfer' => [
                'id' => 'bank_transfer',
                'title' => 'Bank Transfer',
                'description' => 'Pay by direct bank transfer',
                'icon' => 'bank',
                'enabled' => true
            ],
            'cash_on_delivery' => [
                'id' => 'cash_on_delivery',
                'title' => 'Cash on Delivery',
                'description' => 'Pay with cash when your order is delivered',
                'icon' => 'truck',
                'enabled' => true
            ]
        ];
        
        return HookSystem::applyFilters('checkout.payment_methods', $methods);
    }
    
    /**
     * Get available shipping methods
     */
    private function getAvailableShippingMethods(): array
    {
        $methods = [
            'standard' => [
                'id' => 'standard',
                'title' => 'Standard Shipping',
                'description' => '5-7 business days',
                'cost' => 9.99,
                'enabled' => true
            ],
            'express' => [
                'id' => 'express',
                'title' => 'Express Shipping',
                'description' => '2-3 business days',
                'cost' => 19.99,
                'enabled' => true
            ],
            'overnight' => [
                'id' => 'overnight',
                'title' => 'Overnight Shipping',
                'description' => 'Next business day',
                'cost' => 39.99,
                'enabled' => true
            ],
            'free' => [
                'id' => 'free',
                'title' => 'Free Shipping',
                'description' => '7-10 business days (orders over $100)',
                'cost' => 0,
                'enabled' => true,
                'minimum_order' => 100
            ]
        ];
        
        return HookSystem::applyFilters('checkout.shipping_methods', $methods);
    }
    
    /**
     * Get available countries
     */
    private function getAvailableCountries(): array
    {
        $countries = [
            'US' => 'United States',
            'CA' => 'Canada',
            'GB' => 'United Kingdom',
            'AU' => 'Australia',
            'DE' => 'Germany',
            'FR' => 'France',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'CH' => 'Switzerland',
            'AT' => 'Austria',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'JP' => 'Japan',
            'KR' => 'South Korea',
            'SG' => 'Singapore',
            'NZ' => 'New Zealand',
        ];
        
        return HookSystem::applyFilters('checkout.available_countries', $countries);
    }
    
    /**
     * Validate checkout data
     */
    private function validateCheckoutData(array $data): array
    {
        $errors = [];
        
        // Email validation
        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address';
        }
        
        // Name validation
        if (empty($data['first_name'])) {
            $errors['first_name'] = 'First name is required';
        }
        if (empty($data['last_name'])) {
            $errors['last_name'] = 'Last name is required';
        }
        
        // Billing address validation
        if (empty($data['billing_address_1'])) {
            $errors['billing_address_1'] = 'Billing address is required';
        }
        if (empty($data['billing_city'])) {
            $errors['billing_city'] = 'Billing city is required';
        }
        if (empty($data['billing_postcode'])) {
            $errors['billing_postcode'] = 'Billing postal code is required';
        }
        if (empty($data['billing_country'])) {
            $errors['billing_country'] = 'Billing country is required';
        }
        
        // Payment method validation
        $paymentMethod = $data['payment_method'] ?? '';
        if (empty($paymentMethod)) {
            $errors['payment_method'] = 'Payment method is required';
        }
        
        // Card payment validation
        if ($paymentMethod === 'card') {
            if (empty($data['card_number'])) {
                $errors['card_number'] = 'Card number is required';
            }
            if (empty($data['expiry_month'])) {
                $errors['expiry_month'] = 'Expiry month is required';
            }
            if (empty($data['expiry_year'])) {
                $errors['expiry_year'] = 'Expiry year is required';
            }
            if (empty($data['cvv'])) {
                $errors['cvv'] = 'CVV is required';
            }
            if (empty($data['cardholder_name'])) {
                $errors['cardholder_name'] = 'Cardholder name is required';
            }
        }
        
        // Allow plugins to add custom validation
        $errors = HookSystem::applyFilters('checkout.validation_errors', $errors, $data);
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
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
     */
    private function getUrl(string $path = ''): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
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