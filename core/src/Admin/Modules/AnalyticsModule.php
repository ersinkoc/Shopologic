<?php

declare(strict_types=1);

namespace Shopologic\Core\Admin\Modules;

use Shopologic\Core\Admin\AdminPanel;
use Shopologic\Core\Admin\AdminModuleInterface;

/**
 * Analytics module for admin panel
 */
class AnalyticsModule implements AdminModuleInterface
{
    public function register(AdminPanel $admin): void
    {
        // Register menu items
        $menuBuilder = $admin->getMenu();
        
        $menuBuilder->addItem([
            'title' => 'Analytics',
            'url' => '/admin/analytics',
            'icon' => 'chart-line',
            'permission' => 'admin.analytics.view',
            'order' => 50,
            'group' => 'analytics',
            'children' => [
                [
                    'title' => 'Overview',
                    'url' => '/admin/analytics',
                    'permission' => 'admin.analytics.view'
                ],
                [
                    'title' => 'Sales Reports',
                    'url' => '/admin/analytics/sales',
                    'permission' => 'admin.analytics.sales'
                ],
                [
                    'title' => 'Product Analytics',
                    'url' => '/admin/analytics/products',
                    'permission' => 'admin.analytics.products'
                ],
                [
                    'title' => 'Customer Analytics',
                    'url' => '/admin/analytics/customers',
                    'permission' => 'admin.analytics.customers'
                ],
                [
                    'title' => 'Traffic Analytics',
                    'url' => '/admin/analytics/traffic',
                    'permission' => 'admin.analytics.traffic'
                ],
                [
                    'title' => 'Performance',
                    'url' => '/admin/analytics/performance',
                    'permission' => 'admin.analytics.performance'
                ],
                [
                    'title' => 'Custom Reports',
                    'url' => '/admin/analytics/custom',
                    'permission' => 'admin.analytics.custom'
                ]
            ]
        ]);
    }
    
    public function getName(): string
    {
        return 'analytics';
    }
    
    public function getRoutes(): array
    {
        return [
            // Overview
            ['GET', '/admin/analytics', 'AnalyticsController@index'],
            ['GET', '/admin/analytics/data', 'AnalyticsController@getData'],
            
            // Sales Reports
            ['GET', '/admin/analytics/sales', 'SalesAnalyticsController@index'],
            ['GET', '/admin/analytics/sales/revenue', 'SalesAnalyticsController@revenue'],
            ['GET', '/admin/analytics/sales/orders', 'SalesAnalyticsController@orders'],
            ['GET', '/admin/analytics/sales/average-order', 'SalesAnalyticsController@averageOrder'],
            ['GET', '/admin/analytics/sales/conversion-rate', 'SalesAnalyticsController@conversionRate'],
            ['GET', '/admin/analytics/sales/by-channel', 'SalesAnalyticsController@byChannel'],
            ['GET', '/admin/analytics/sales/by-region', 'SalesAnalyticsController@byRegion'],
            
            // Product Analytics
            ['GET', '/admin/analytics/products', 'ProductAnalyticsController@index'],
            ['GET', '/admin/analytics/products/best-sellers', 'ProductAnalyticsController@bestSellers'],
            ['GET', '/admin/analytics/products/low-stock', 'ProductAnalyticsController@lowStock'],
            ['GET', '/admin/analytics/products/views', 'ProductAnalyticsController@views'],
            ['GET', '/admin/analytics/products/abandoned', 'ProductAnalyticsController@abandoned'],
            ['GET', '/admin/analytics/products/profitability', 'ProductAnalyticsController@profitability'],
            
            // Customer Analytics
            ['GET', '/admin/analytics/customers', 'CustomerAnalyticsController@index'],
            ['GET', '/admin/analytics/customers/acquisition', 'CustomerAnalyticsController@acquisition'],
            ['GET', '/admin/analytics/customers/retention', 'CustomerAnalyticsController@retention'],
            ['GET', '/admin/analytics/customers/lifetime-value', 'CustomerAnalyticsController@lifetimeValue'],
            ['GET', '/admin/analytics/customers/segments', 'CustomerAnalyticsController@segments'],
            ['GET', '/admin/analytics/customers/behavior', 'CustomerAnalyticsController@behavior'],
            
            // Traffic Analytics
            ['GET', '/admin/analytics/traffic', 'TrafficAnalyticsController@index'],
            ['GET', '/admin/analytics/traffic/sources', 'TrafficAnalyticsController@sources'],
            ['GET', '/admin/analytics/traffic/pages', 'TrafficAnalyticsController@pages'],
            ['GET', '/admin/analytics/traffic/devices', 'TrafficAnalyticsController@devices'],
            ['GET', '/admin/analytics/traffic/locations', 'TrafficAnalyticsController@locations'],
            ['GET', '/admin/analytics/traffic/real-time', 'TrafficAnalyticsController@realTime'],
            
            // Performance Analytics
            ['GET', '/admin/analytics/performance', 'PerformanceAnalyticsController@index'],
            ['GET', '/admin/analytics/performance/page-speed', 'PerformanceAnalyticsController@pageSpeed'],
            ['GET', '/admin/analytics/performance/api', 'PerformanceAnalyticsController@api'],
            ['GET', '/admin/analytics/performance/database', 'PerformanceAnalyticsController@database'],
            ['GET', '/admin/analytics/performance/cache', 'PerformanceAnalyticsController@cache'],
            ['GET', '/admin/analytics/performance/errors', 'PerformanceAnalyticsController@errors'],
            
            // Custom Reports
            ['GET', '/admin/analytics/custom', 'CustomReportsController@index'],
            ['GET', '/admin/analytics/custom/create', 'CustomReportsController@create'],
            ['POST', '/admin/analytics/custom', 'CustomReportsController@store'],
            ['GET', '/admin/analytics/custom/{id}', 'CustomReportsController@show'],
            ['GET', '/admin/analytics/custom/{id}/edit', 'CustomReportsController@edit'],
            ['PUT', '/admin/analytics/custom/{id}', 'CustomReportsController@update'],
            ['DELETE', '/admin/analytics/custom/{id}', 'CustomReportsController@destroy'],
            ['GET', '/admin/analytics/custom/{id}/run', 'CustomReportsController@run'],
            ['GET', '/admin/analytics/custom/{id}/export', 'CustomReportsController@export'],
            ['POST', '/admin/analytics/custom/{id}/schedule', 'CustomReportsController@schedule'],
            
            // Exports
            ['POST', '/admin/analytics/export', 'AnalyticsController@export'],
            ['GET', '/admin/analytics/scheduled-reports', 'AnalyticsController@scheduledReports']
        ];
    }
    
    public function getMenuItems(): array
    {
        return [
            [
                'title' => 'Analytics',
                'url' => '/admin/analytics',
                'icon' => 'chart-line',
                'permission' => 'admin.analytics.view',
                'order' => 50,
                'children' => [
                    [
                        'title' => 'Overview',
                        'url' => '/admin/analytics',
                        'permission' => 'admin.analytics.view'
                    ],
                    [
                        'title' => 'Sales Reports',
                        'url' => '/admin/analytics/sales',
                        'permission' => 'admin.analytics.sales'
                    ],
                    [
                        'title' => 'Product Analytics',
                        'url' => '/admin/analytics/products',
                        'permission' => 'admin.analytics.products'
                    ],
                    [
                        'title' => 'Customer Analytics',
                        'url' => '/admin/analytics/customers',
                        'permission' => 'admin.analytics.customers'
                    ],
                    [
                        'title' => 'Traffic Analytics',
                        'url' => '/admin/analytics/traffic',
                        'permission' => 'admin.analytics.traffic'
                    ],
                    [
                        'title' => 'Performance',
                        'url' => '/admin/analytics/performance',
                        'permission' => 'admin.analytics.performance'
                    ],
                    [
                        'title' => 'Custom Reports',
                        'url' => '/admin/analytics/custom',
                        'permission' => 'admin.analytics.custom'
                    ]
                ]
            ]
        ];
    }
    
    public function getPermissions(): array
    {
        return [
            // General
            'admin.analytics.view' => 'View analytics overview',
            'admin.analytics.export' => 'Export analytics data',
            
            // Sales
            'admin.analytics.sales' => 'View sales analytics',
            'admin.analytics.sales.revenue' => 'View revenue reports',
            'admin.analytics.sales.conversion' => 'View conversion analytics',
            
            // Products
            'admin.analytics.products' => 'View product analytics',
            'admin.analytics.products.profitability' => 'View product profitability',
            
            // Customers
            'admin.analytics.customers' => 'View customer analytics',
            'admin.analytics.customers.segments' => 'View customer segments',
            'admin.analytics.customers.ltv' => 'View customer lifetime value',
            
            // Traffic
            'admin.analytics.traffic' => 'View traffic analytics',
            'admin.analytics.traffic.realtime' => 'View real-time analytics',
            
            // Performance
            'admin.analytics.performance' => 'View performance analytics',
            'admin.analytics.performance.errors' => 'View error logs',
            
            // Custom Reports
            'admin.analytics.custom' => 'View custom reports',
            'admin.analytics.custom.create' => 'Create custom reports',
            'admin.analytics.custom.update' => 'Update custom reports',
            'admin.analytics.custom.delete' => 'Delete custom reports',
            'admin.analytics.custom.schedule' => 'Schedule report generation'
        ];
    }
}