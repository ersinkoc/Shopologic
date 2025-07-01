<?php

declare(strict_types=1);

namespace Shopologic\Core\Http\Controllers\Admin;

use Shopologic\Core\Template\TemplateEngine;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use Shopologic\Core\Http\Stream;
use Shopologic\Core\Auth\AuthService;
use Psr\Http\Message\RequestInterface;

/**
 * Admin Authentication Controller
 */
class AuthController
{
    private TemplateEngine $template;
    private AuthService $auth;
    
    public function __construct(TemplateEngine $template)
    {
        $this->template = $template;
        $this->auth = new AuthService();
    }
    
    /**
     * Display admin login form
     */
    public function loginForm(RequestInterface $request): Response
    {
        try {
            // If already logged in as admin, redirect to dashboard
            if ($this->auth->isAuthenticated() && $this->isAdmin()) {
                return $this->redirectToDashboard();
            }
            
            $data = [
                'title' => 'Admin Login - Shopologic',
                'error' => $_SESSION['admin_login_error'] ?? null,
                'email' => $_SESSION['admin_login_email'] ?? ''
            ];
            
            // Clear session errors
            unset($_SESSION['admin_login_error']);
            unset($_SESSION['admin_login_email']);
            
            $content = $this->template->render('admin/auth/login', $data);
            
            $body = new Stream('php://memory', 'w+');
            $body->write($content);
            
            return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
            
        } catch (\Exception $e) {
            return $this->errorResponse('Login form error: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle admin login
     */
    public function login(RequestInterface $request): Response
    {
        try {
            $body = $request->getBody()->getContents();
            parse_str($body, $data);
            
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            
            // Validate input
            if (empty($email) || empty($password)) {
                $_SESSION['admin_login_error'] = 'Email and password are required';
                $_SESSION['admin_login_email'] = $email;
                return $this->redirectToLogin();
            }
            
            // Attempt login
            if ($this->auth->login($email, $password)) {
                // Check if user is admin
                if (!$this->isAdmin()) {
                    $this->auth->logout();
                    $_SESSION['admin_login_error'] = 'Access denied. Admin privileges required.';
                    $_SESSION['admin_login_email'] = $email;
                    return $this->redirectToLogin();
                }
                
                // Set admin session flag
                $_SESSION['is_admin'] = true;
                $_SESSION['admin_login_time'] = time();
                
                // Log admin login
                error_log("Admin login successful: {$email}");
                
                return $this->redirectToDashboard();
            } else {
                $_SESSION['admin_login_error'] = 'Invalid email or password';
                $_SESSION['admin_login_email'] = $email;
                return $this->redirectToLogin();
            }
            
        } catch (\Exception $e) {
            $_SESSION['admin_login_error'] = 'Login error: ' . $e->getMessage();
            return $this->redirectToLogin();
        }
    }
    
    /**
     * Handle admin logout
     */
    public function logout(RequestInterface $request): Response
    {
        // Log admin logout
        if ($this->auth->isAuthenticated()) {
            $user = $this->auth->getUser();
            error_log("Admin logout: " . ($user['email'] ?? 'unknown'));
        }
        
        // Clear admin session
        unset($_SESSION['is_admin']);
        unset($_SESSION['admin_login_time']);
        
        // Logout user
        $this->auth->logout();
        
        return $this->redirectToLogin();
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
     * Redirect to login
     */
    private function redirectToLogin(): Response
    {
        $body = new Stream('php://memory', 'w+');
        return new Response(302, ['Location' => '/admin/login'], $body);
    }
    
    /**
     * Redirect to dashboard
     */
    private function redirectToDashboard(): Response
    {
        $body = new Stream('php://memory', 'w+');
        return new Response(302, ['Location' => '/admin'], $body);
    }
    
    /**
     * Error response
     */
    private function errorResponse(string $message): Response
    {
        $body = new Stream('php://memory', 'w+');
        $body->write('<h1>Error</h1><p>' . htmlspecialchars($message) . '</p>');
        return new Response(500, ['Content-Type' => 'text/html'], $body);
    }
}