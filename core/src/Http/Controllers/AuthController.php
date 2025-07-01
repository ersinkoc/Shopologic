<?php

declare(strict_types=1);

namespace Shopologic\Core\Http\Controllers;

use Shopologic\Core\Auth\AuthService;
use Shopologic\Core\Http\Stream;
use Shopologic\Core\Http\Response;
use Shopologic\Core\Plugin\HookSystem;
use Shopologic\Core\Template\TemplateEngine;
use Psr\Http\Message\RequestInterface;

class AuthController
{
    private TemplateEngine $template;
    private AuthService $authService;
    
    public function __construct(TemplateEngine $template)
    {
        $this->template = $template;
        $this->authService = new AuthService();
    }
    
    public function loginForm(RequestInterface $request): Response
    {
        // Redirect if already logged in
        if ($this->authService->isLoggedIn()) {
            return $this->redirect('/account');
        }
        
        try {
            $content = $this->template->render('auth/login', [
                'title' => 'Login',
                'login_action_url' => '/auth/login',
                'register_url' => '/auth/register',
                'forgot_password_url' => '/auth/forgot-password',
                'redirect_url' => $request->getUri()->getQuery() ? parse_query($request->getUri()->getQuery())['redirect'] ?? '/' : '/'
            ]);
            
            $body = new Stream('php://memory', 'w+');
            $body->write($content);
            return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
            
        } catch (\Exception $e) {
            return $this->errorResponse('Error loading login page: ' . $e->getMessage());
        }
    }
    
    public function login(RequestInterface $request): Response
    {
        try {
            $data = $this->getRequestData($request);
            
            if (empty($data['email']) || empty($data['password'])) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Email and password are required'
                ]);
            }
            
            $result = $this->authService->login($data['email'], $data['password']);
            
            if ($result['success']) {
                $redirectUrl = $data['redirect_url'] ?? '/account';
                
                if ($this->isJsonRequest($request)) {
                    return $this->jsonResponse([
                        'success' => true,
                        'message' => $result['message'],
                        'redirect_url' => $redirectUrl,
                        'user' => $result['user']
                    ]);
                } else {
                    return $this->redirect($redirectUrl);
                }
            } else {
                if ($this->isJsonRequest($request)) {
                    return $this->jsonResponse($result, 400);
                } else {
                    // Render login form with error
                    $content = $this->template->render('auth/login', [
                        'title' => 'Login',
                        'error' => $result['message'],
                        'email' => $data['email'],
                        'login_action_url' => '/auth/login',
                        'register_url' => '/auth/register',
                        'forgot_password_url' => '/auth/forgot-password',
                        'redirect_url' => $data['redirect_url'] ?? '/'
                    ]);
                    
                    $body = new Stream('php://memory', 'w+');
                    $body->write($content);
                    return new Response(400, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
                }
            }
            
        } catch (\Exception $e) {
            return $this->errorResponse('Login error: ' . $e->getMessage());
        }
    }
    
    public function registerForm(RequestInterface $request): Response
    {
        // Redirect if already logged in
        if ($this->authService->isLoggedIn()) {
            return $this->redirect('/account');
        }
        
        try {
            $content = $this->template->render('auth/register', [
                'title' => 'Create Account',
                'register_action_url' => '/auth/register',
                'login_url' => '/auth/login',
                'countries' => $this->getCountries()
            ]);
            
            $body = new Stream('php://memory', 'w+');
            $body->write($content);
            return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
            
        } catch (\Exception $e) {
            return $this->errorResponse('Error loading register page: ' . $e->getMessage());
        }
    }
    
    public function register(RequestInterface $request): Response
    {
        try {
            $data = $this->getRequestData($request);
            
            $result = $this->authService->register($data);
            
            if ($result['success']) {
                // Auto-login after successful registration
                $loginResult = $this->authService->login($data['email'], $data['password']);
                
                if ($this->isJsonRequest($request)) {
                    return $this->jsonResponse([
                        'success' => true,
                        'message' => 'Account created successfully! Welcome to Shopologic.',
                        'redirect_url' => '/account',
                        'user' => $result['user']
                    ]);
                } else {
                    return $this->redirect('/account');
                }
            } else {
                if ($this->isJsonRequest($request)) {
                    return $this->jsonResponse($result, 400);
                } else {
                    // Render register form with errors
                    $content = $this->template->render('auth/register', [
                        'title' => 'Create Account',
                        'error' => $result['message'],
                        'errors' => $result['errors'] ?? [],
                        'form_data' => $data,
                        'register_action_url' => '/auth/register',
                        'login_url' => '/auth/login',
                        'countries' => $this->getCountries()
                    ]);
                    
                    $body = new Stream('php://memory', 'w+');
                    $body->write($content);
                    return new Response(400, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
                }
            }
            
        } catch (\Exception $e) {
            return $this->errorResponse('Registration error: ' . $e->getMessage());
        }
    }
    
    public function logout(RequestInterface $request): Response
    {
        $this->authService->logout();
        
        if ($this->isJsonRequest($request)) {
            return $this->jsonResponse([
                'success' => true,
                'message' => 'Logged out successfully',
                'redirect_url' => '/'
            ]);
        } else {
            return $this->redirect('/');
        }
    }
    
    public function account(RequestInterface $request): Response
    {
        if (!$this->authService->isLoggedIn()) {
            return $this->redirect('/auth/login?redirect=' . urlencode('/account'));
        }
        
        try {
            $user = $this->authService->getCurrentUser();
            
            $content = $this->template->render('account/dashboard', [
                'title' => 'My Account',
                'user' => $user,
                'recent_orders' => $this->getRecentOrders($user['id']),
                'profile_update_url' => '/account/profile',
                'password_change_url' => '/account/password',
                'addresses_url' => '/account/addresses',
                'orders_url' => '/account/orders'
            ]);
            
            $body = new Stream('php://memory', 'w+');
            $body->write($content);
            return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
            
        } catch (\Exception $e) {
            return $this->errorResponse('Error loading account page: ' . $e->getMessage());
        }
    }
    
    public function profile(RequestInterface $request): Response
    {
        if (!$this->authService->isLoggedIn()) {
            return $this->redirect('/auth/login?redirect=' . urlencode('/account/profile'));
        }
        
        try {
            $user = $this->authService->getCurrentUser();
            
            $content = $this->template->render('account/profile', [
                'title' => 'Profile Settings',
                'user' => $user,
                'profile_update_url' => '/account/profile',
                'account_url' => '/account'
            ]);
            
            $body = new Stream('php://memory', 'w+');
            $body->write($content);
            return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
            
        } catch (\Exception $e) {
            return $this->errorResponse('Error loading profile page: ' . $e->getMessage());
        }
    }
    
    public function updateProfile(RequestInterface $request): Response
    {
        if (!$this->authService->isLoggedIn()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
        }
        
        try {
            $data = $this->getRequestData($request);
            $result = $this->authService->updateProfile($data);
            
            if ($this->isJsonRequest($request)) {
                return $this->jsonResponse($result, $result['success'] ? 200 : 400);
            } else {
                if ($result['success']) {
                    return $this->redirect('/account/profile?updated=1');
                } else {
                    $user = $this->authService->getCurrentUser();
                    $content = $this->template->render('account/profile', [
                        'title' => 'Profile Settings',
                        'user' => $user,
                        'error' => $result['message'],
                        'errors' => $result['errors'] ?? [],
                        'form_data' => $data,
                        'profile_update_url' => '/account/profile',
                        'account_url' => '/account'
                    ]);
                    
                    $body = new Stream('php://memory', 'w+');
                    $body->write($content);
                    return new Response(400, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
                }
            }
            
        } catch (\Exception $e) {
            return $this->errorResponse('Profile update error: ' . $e->getMessage());
        }
    }
    
    public function addresses(RequestInterface $request): Response
    {
        if (!$this->authService->isLoggedIn()) {
            return $this->redirect('/auth/login?redirect=' . urlencode('/account/addresses'));
        }
        
        try {
            $user = $this->authService->getCurrentUser();
            
            $content = $this->template->render('account/addresses', [
                'title' => 'My Addresses',
                'user' => $user,
                'addresses' => $user['addresses'] ?? [],
                'add_address_url' => '/account/addresses/add',
                'countries' => $this->getCountries(),
                'account_url' => '/account'
            ]);
            
            $body = new Stream('php://memory', 'w+');
            $body->write($content);
            return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
            
        } catch (\Exception $e) {
            return $this->errorResponse('Error loading addresses page: ' . $e->getMessage());
        }
    }
    
    public function addAddress(RequestInterface $request): Response
    {
        if (!$this->authService->isLoggedIn()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
        }
        
        try {
            $data = $this->getRequestData($request);
            $result = $this->authService->addAddress($data);
            
            if ($this->isJsonRequest($request)) {
                return $this->jsonResponse($result, $result['success'] ? 200 : 400);
            } else {
                if ($result['success']) {
                    return $this->redirect('/account/addresses?added=1');
                } else {
                    return $this->redirect('/account/addresses?error=' . urlencode($result['message']));
                }
            }
            
        } catch (\Exception $e) {
            return $this->errorResponse('Add address error: ' . $e->getMessage());
        }
    }
    
    private function getRequestData(RequestInterface $request): array
    {
        $body = $request->getBody()->getContents();
        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        
        if (!empty($contentType) && strpos($contentType, 'json') !== false) {
            $data = json_decode($body, true);
            return is_array($data) ? $data : [];
        } else {
            parse_str($body, $data);
            return $data;
        }
    }
    
    private function isJsonRequest(RequestInterface $request): bool
    {
        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        $acceptHeader = strtolower($request->getHeaderLine('Accept'));
        
        return (strpos($contentType, 'json') !== false) || 
               (strpos($acceptHeader, 'json') !== false) ||
               (!empty($request->getHeaderLine('X-Requested-With')));
    }
    
    private function jsonResponse(array $data, int $status = 200): Response
    {
        $body = new Stream('php://memory', 'w+');
        $body->write(json_encode($data));
        return new Response($status, ['Content-Type' => 'application/json'], $body);
    }
    
    private function redirect(string $url): Response
    {
        $body = new Stream('php://memory', 'w+');
        return new Response(302, ['Location' => $url], $body);
    }
    
    private function errorResponse(string $message): Response
    {
        $body = new Stream('php://memory', 'w+');
        $body->write('<h1>Error</h1><p>' . htmlspecialchars($message) . '</p>');
        return new Response(500, ['Content-Type' => 'text/html'], $body);
    }
    
    private function getCountries(): array
    {
        return [
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
            'CN' => 'China',
            'IN' => 'India',
            'BR' => 'Brazil',
            'MX' => 'Mexico',
            'AR' => 'Argentina',
            'CL' => 'Chile',
            'NZ' => 'New Zealand'
        ];
    }
    
    private function getRecentOrders(int $userId): array
    {
        // Sample recent orders
        return [
            [
                'id' => 'ORD-2024-001',
                'order_number' => 'ORD-2024-001',
                'date' => '2024-06-25',
                'status' => 'delivered',
                'total' => 149.99,
                'items_count' => 3
            ],
            [
                'id' => 'ORD-2024-002',
                'order_number' => 'ORD-2024-002',
                'date' => '2024-06-20',
                'status' => 'processing',
                'total' => 89.50,
                'items_count' => 2
            ],
            [
                'id' => 'ORD-2024-003',
                'order_number' => 'ORD-2024-003',
                'date' => '2024-06-15',
                'status' => 'completed',
                'total' => 234.75,
                'items_count' => 5
            ]
        ];
    }
}