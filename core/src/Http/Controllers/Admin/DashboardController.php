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
 * Admin Dashboard Controller
 */
class DashboardController
{
    private TemplateEngine $template;
    private AuthService $auth;
    
    public function __construct(TemplateEngine $template)
    {
        $this->template = $template;
        $this->auth = new AuthService();
    }
    
    /**
     * Display admin dashboard
     */
    public function index(RequestInterface $request): Response
    {
        try {
            // Check admin authentication
            if (!$this->auth->isAuthenticated() || !$this->isAdmin()) {
                return $this->redirectToLogin();
            }
            
            // Get dashboard stats
            $stats = $this->getDashboardStats();
            
            // Get recent activities
            $recentOrders = $this->getRecentOrders();
            $recentCustomers = $this->getRecentCustomers();
            $lowStockProducts = $this->getLowStockProducts();
            
            $data = [
                'title' => 'Admin Dashboard - Shopologic',
                'user' => $this->auth->getUser(),
                'stats' => $stats,
                'recent_orders' => $recentOrders,
                'recent_customers' => $recentCustomers,
                'low_stock_products' => $lowStockProducts,
                'sales_chart_data' => $this->getSalesChartData(),
                'popular_products' => $this->getPopularProducts(),
                'notifications' => $this->getAdminNotifications()
            ];
            
            $content = $this->template->render('admin/dashboard', $data);
            
            $body = new Stream('php://memory', 'w+');
            $body->write($content);
            
            return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
            
        } catch (\Exception $e) {
            return $this->errorResponse('Dashboard error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get dashboard statistics
     */
    private function getDashboardStats(): array
    {
        // Simulated stats for now
        return [
            'total_revenue' => 125430.50,
            'total_orders' => 1234,
            'total_customers' => 3456,
            'total_products' => 567,
            'revenue_today' => 4532.00,
            'orders_today' => 23,
            'new_customers_today' => 12,
            'pending_orders' => 8,
            'revenue_change' => 12.5, // percentage
            'orders_change' => 8.3,
            'customers_change' => 5.7,
            'conversion_rate' => 3.2
        ];
    }
    
    /**
     * Get recent orders
     */
    private function getRecentOrders(): array
    {
        return [
            [
                'id' => 'ORD-001234',
                'customer' => 'John Doe',
                'total' => 156.99,
                'status' => 'processing',
                'date' => date('Y-m-d H:i:s', strtotime('-2 hours'))
            ],
            [
                'id' => 'ORD-001233',
                'customer' => 'Jane Smith',
                'total' => 89.50,
                'status' => 'shipped',
                'date' => date('Y-m-d H:i:s', strtotime('-4 hours'))
            ],
            [
                'id' => 'ORD-001232',
                'customer' => 'Bob Johnson',
                'total' => 234.00,
                'status' => 'pending',
                'date' => date('Y-m-d H:i:s', strtotime('-6 hours'))
            ],
            [
                'id' => 'ORD-001231',
                'customer' => 'Alice Brown',
                'total' => 67.99,
                'status' => 'delivered',
                'date' => date('Y-m-d H:i:s', strtotime('-8 hours'))
            ],
            [
                'id' => 'ORD-001230',
                'customer' => 'Charlie Wilson',
                'total' => 445.00,
                'status' => 'processing',
                'date' => date('Y-m-d H:i:s', strtotime('-10 hours'))
            ]
        ];
    }
    
    /**
     * Get recent customers
     */
    private function getRecentCustomers(): array
    {
        return [
            [
                'id' => 456,
                'name' => 'Emma Davis',
                'email' => 'emma@example.com',
                'orders' => 1,
                'total_spent' => 156.99,
                'joined' => date('Y-m-d', strtotime('-1 day'))
            ],
            [
                'id' => 455,
                'name' => 'Michael Brown',
                'email' => 'michael@example.com',
                'orders' => 3,
                'total_spent' => 445.50,
                'joined' => date('Y-m-d', strtotime('-2 days'))
            ],
            [
                'id' => 454,
                'name' => 'Sarah Johnson',
                'email' => 'sarah@example.com',
                'orders' => 2,
                'total_spent' => 234.00,
                'joined' => date('Y-m-d', strtotime('-3 days'))
            ]
        ];
    }
    
    /**
     * Get low stock products
     */
    private function getLowStockProducts(): array
    {
        return [
            [
                'id' => 123,
                'name' => 'Wireless Bluetooth Headphones',
                'sku' => 'WBH-001',
                'stock' => 5,
                'threshold' => 10
            ],
            [
                'id' => 124,
                'name' => 'Smart LED TV 55 inch',
                'sku' => 'TV-055',
                'stock' => 2,
                'threshold' => 5
            ],
            [
                'id' => 125,
                'name' => 'Gaming Mechanical Keyboard',
                'sku' => 'GMK-001',
                'stock' => 8,
                'threshold' => 15
            ]
        ];
    }
    
    /**
     * Get sales chart data
     */
    private function getSalesChartData(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $data[] = [
                'date' => $date,
                'sales' => rand(3000, 8000) + ($i * 100),
                'orders' => rand(15, 40)
            ];
        }
        return $data;
    }
    
    /**
     * Get popular products
     */
    private function getPopularProducts(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'MacBook Pro 16-inch',
                'sales' => 234,
                'revenue' => 584766.00
            ],
            [
                'id' => 2,
                'name' => 'iPhone 15 Pro',
                'sales' => 567,
                'revenue' => 566433.00
            ],
            [
                'id' => 3,
                'name' => 'Samsung Galaxy S24 Ultra',
                'sales' => 345,
                'revenue' => 379155.00
            ],
            [
                'id' => 4,
                'name' => 'Nike Air Jordan 1 Retro',
                'sales' => 890,
                'revenue' => 132110.00
            ],
            [
                'id' => 5,
                'name' => 'Dyson V15 Detect',
                'sales' => 123,
                'revenue' => 85977.00
            ]
        ];
    }
    
    /**
     * Get admin notifications
     */
    private function getAdminNotifications(): array
    {
        return [
            [
                'type' => 'warning',
                'message' => '3 products are low in stock',
                'time' => '5 minutes ago',
                'link' => '/admin/products?filter=low_stock'
            ],
            [
                'type' => 'info',
                'message' => '8 orders pending processing',
                'time' => '15 minutes ago',
                'link' => '/admin/orders?status=pending'
            ],
            [
                'type' => 'success',
                'message' => 'Daily backup completed successfully',
                'time' => '1 hour ago',
                'link' => '/admin/system/backups'
            ],
            [
                'type' => 'alert',
                'message' => '5 new customer reviews awaiting moderation',
                'time' => '2 hours ago',
                'link' => '/admin/reviews?status=pending'
            ]
        ];
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
     * Error response
     */
    private function errorResponse(string $message): Response
    {
        $body = new Stream('php://memory', 'w+');
        $body->write('<h1>Error</h1><p>' . htmlspecialchars($message) . '</p>');
        return new Response(500, ['Content-Type' => 'text/html'], $body);
    }
}