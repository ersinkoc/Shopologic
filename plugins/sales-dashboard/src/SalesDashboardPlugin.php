<?php
declare(strict_types=1);

namespace SalesDashboard;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\PluginInterface;
use Shopologic\Core\Hook\HookSystem;
use SalesDashboard\Services\DashboardService;
use SalesDashboard\Services\ReportService;
use SalesDashboard\Services\MetricsService;

/**
 * Sales Dashboard Plugin
 * 
 * Comprehensive sales analytics dashboard with real-time metrics,
 * customizable reports, and business intelligence features
 */
class SalesDashboardPlugin extends AbstractPlugin implements PluginInterface
{
    protected string $name = 'sales-dashboard';
    protected string $version = '1.0.0';
    
    public function install(): bool
    {
        $this->runMigrations();
        $this->setDefaultConfig();
        return true;
    }
    
    public function activate(): bool
    {
        $this->initializeDashboard();
        $this->scheduleReportGeneration();
        return true;
    }
    
    public function deactivate(): bool
    {
        $this->pauseReportGeneration();
        return true;
    }
    
    public function uninstall(): bool
    {
        if ($this->confirmDataRemoval()) {
            $this->dropTables();
            $this->removeConfig();
        }
        return true;
    }
    
    public function update(string $previousVersion): bool
    {
        $this->runUpdateMigrations($previousVersion);
        return true;
    }
    
    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->registerCronJobs();
        $this->registerWidgets();
    }
    
    protected function registerServices(): void
    {
        $this->container->singleton(DashboardService::class, function ($container) {
            return new DashboardService(
                $container->get('db'),
                $container->get('cache'),
                $this->getConfig()
            );
        });
        
        $this->container->singleton(ReportService::class, function ($container) {
            return new ReportService(
                $container->get('db'),
                $this->getConfig('report_storage_path', 'storage/reports')
            );
        });
        
        $this->container->singleton(MetricsService::class, function ($container) {
            return new MetricsService(
                $container->get('db'),
                $container->get('cache')
            );
        });
    }
    
    protected function registerHooks(): void
    {
        // Track sales events
        HookSystem::addAction('order.completed', [$this, 'trackSale'], 10);
        HookSystem::addAction('order.cancelled', [$this, 'trackCancellation'], 10);
        HookSystem::addAction('order.refunded', [$this, 'trackRefund'], 10);
        
        // Admin dashboard integration
        HookSystem::addAction('admin.dashboard', [$this, 'displaySalesDashboard'], 5);
        HookSystem::addAction('admin.menu', [$this, 'addDashboardMenu'], 20);
    }
    
    protected function registerRoutes(): void
    {
        $this->registerRoute('GET', '/api/v1/dashboard/sales', 
            'SalesDashboard\\Controllers\\DashboardController@getSalesData');
        $this->registerRoute('GET', '/api/v1/dashboard/metrics', 
            'SalesDashboard\\Controllers\\MetricsController@getMetrics');
        $this->registerRoute('GET', '/api/v1/reports', 
            'SalesDashboard\\Controllers\\ReportController@getReports');
        $this->registerRoute('POST', '/api/v1/reports/generate', 
            'SalesDashboard\\Controllers\\ReportController@generateReport');
    }
    
    protected function registerCronJobs(): void
    {
        $this->scheduleJob('0 1 * * *', [$this, 'generateDailyReports']);
        $this->scheduleJob('0 2 * * MON', [$this, 'generateWeeklyReports']);
        $this->scheduleJob('0 3 1 * *', [$this, 'generateMonthlyReports']);
    }
    
    protected function registerWidgets(): void
    {
        $this->registerWidget('sales_overview', Widgets\SalesOverviewWidget::class);
        $this->registerWidget('revenue_chart', Widgets\RevenueChartWidget::class);
        $this->registerWidget('top_products', Widgets\TopProductsWidget::class);
        $this->registerWidget('conversion_metrics', Widgets\ConversionMetricsWidget::class);
    }
    
    public function trackSale(array $data): void
    {
        $order = $data['order'];
        $metricsService = $this->container->get(MetricsService::class);
        
        $metricsService->recordSale([
            'order_id' => $order->id,
            'amount' => $order->total,
            'customer_id' => $order->customer_id,
            'products' => $order->items->pluck('product_id')->toArray(),
            'timestamp' => time()
        ]);
    }
    
    public function displaySalesDashboard(): void
    {
        $dashboardService = $this->container->get(DashboardService::class);
        $data = $dashboardService->getDashboardData();
        
        echo $this->render('dashboard/sales-overview', $data);
    }
    
    public function generateDailyReports(): void
    {
        $reportService = $this->container->get(ReportService::class);
        $reportService->generateDailyReport(date('Y-m-d', strtotime('-1 day')));
    }
    
    protected function initializeDashboard(): void
    {
        $dashboardService = $this->container->get(DashboardService::class);
        $dashboardService->initialize();
    }
    
    protected function scheduleReportGeneration(): void
    {
        $this->enableCronJob('generateDailyReports');
        $this->enableCronJob('generateWeeklyReports');
        $this->enableCronJob('generateMonthlyReports');
    }
    
    protected function pauseReportGeneration(): void
    {
        $this->disableCronJob('generateDailyReports');
        $this->disableCronJob('generateWeeklyReports');
        $this->disableCronJob('generateMonthlyReports');
    }
    
    protected function runMigrations(): void
    {
        $migrations = [
            'create_sales_metrics_table.php',
            'create_dashboard_widgets_table.php',
            'create_sales_reports_table.php'
        ];
        
        foreach ($migrations as $migration) {
            $this->api->runMigration($this->getPath('migrations/' . $migration));
        }
    }
    
    protected function setDefaultConfig(): void
    {
        $defaults = [
            'dashboard_refresh_interval' => 30,
            'report_storage_path' => 'storage/reports',
            'enable_real_time_updates' => true,
            'default_date_range' => '30_days'
        ];
        
        foreach ($defaults as $key => $value) {
            if ($this->getConfig($key) === null) {
                $this->setConfig($key, $value);
            }
        }
    }
}