# 📊 Advanced Analytics & Reporting Plugin

![Quality Badge](https://img.shields.io/badge/Quality-71%25%20(C)-yellow)


Enterprise-grade analytics and reporting with real-time dashboards, custom report generation, and comprehensive business intelligence capabilities.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

This plugin is part of the enhanced Shopologic ecosystem featuring 47 advanced models, cross-plugin integration, real-time events, performance monitoring, and automated testing.

## 🚀 Quick Start

```bash
# Initialize plugin ecosystem
php bootstrap_plugins.php

# Run with integration demonstration
php bootstrap_plugins.php --demo
```

## ✨ Key Features

### 📈 Real-Time Analytics
- **Live Dashboards** - Real-time data visualization with auto-refresh
- **Custom Metrics** - User-defined KPIs with trend analysis
- **Performance Tracking** - System and business performance monitoring
- **Data Quality Scoring** - Automatic data integrity validation
- **Trend Analysis** - Historical data patterns and forecasting

### 📋 Advanced Reporting
- **Scheduled Reports** - Automated report generation and distribution
- **Custom Report Builder** - Drag-drop report creation interface
- **Multiple Export Formats** - PDF, Excel, CSV, JSON export options
- **Report Templates** - Pre-built and custom report templates
- **Performance Optimization** - Efficient query execution and caching

### 🎯 Business Intelligence
- **Executive Dashboards** - High-level business overview widgets
- **Department Analytics** - Role-specific dashboard views
- **User Interaction Tracking** - Dashboard usage and engagement analytics
- **Collaborative Features** - Dashboard sharing and commenting

## 🏗️ Plugin Architecture

### Models
- **`Report.php`** - Comprehensive report management with scheduling
- **`ReportExecution.php`** - Execution tracking with performance metrics
- **`Dashboard.php`** - Advanced dashboard system with widget management
- **`DashboardView.php`** - View analytics with interaction tracking
- **`Metric.php`** - Real-time metrics with trend analysis
- **`MetricValue.php`** - Historical data with quality scoring
- **`Event.php`** - Event tracking and analytics
- **`Session.php`** - User session and behavior analytics

### Services
- **`AnalyticsEngine.php`** - Core analytics processing and calculations
- **`ReportGenerator.php`** - Report creation and execution engine
- **`MetricsCalculator.php`** - Real-time metric calculations and aggregations
- **`EventTracker.php`** - Event capture and processing

### Controllers
- **`AnalyticsController.php`** - REST API endpoints for analytics operations

### Repositories
- **`EventRepository.php`** - Event data access and querying
- **`MetricsRepository.php`** - Metrics storage and retrieval
- **`ReportRepository.php`** - Report management and history
- **`SessionRepository.php`** - Session data and analytics

## 🔗 Cross-Plugin Integration

### Provider Interface
Implements `AnalyticsProviderInterface` for seamless integration:

```php
interface AnalyticsProviderInterface {
    public function trackEvent(string $eventName, array $properties): void;
    public function getMetricData(string $metricKey, array $filters = []): array;
    public function subscribeToMetric(string $metricKey, callable $callback): void;
    public function generateReport(string $reportType, array $parameters = []): array;
    public function createDashboard(array $dashboardConfig): Dashboard;
}
```

### Integration Examples

```php
// Get analytics provider
$analyticsProvider = $integrationManager->getAnalyticsProvider();

// Track events
$analyticsProvider->trackEvent('product_viewed', [
    'product_id' => 'PROD-123',
    'customer_id' => 12345,
    'category' => 'electronics'
]);

// Get metric data
$revenueData = $analyticsProvider->getMetricData('daily_revenue', [
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31'
]);

// Generate reports
$salesReport = $analyticsProvider->generateReport('sales_summary', [
    'period' => 'monthly',
    'year' => 2024
]);
```

## 📊 Advanced Features

### Real-Time Metrics Management

```php
// Create and update metrics
$metric = Metric::create([
    'key' => 'daily_revenue',
    'name' => 'Daily Revenue',
    'category' => 'sales',
    'description' => 'Total revenue for the current day',
    'calculation_method' => 'sum',
    'target_value' => 10000.00
]);

// Update metric value with trend analysis
$metric->updateValue(12345.67);
$trend = $metric->getHistoricalTrend(30); // Last 30 days
$performance = $metric->getPerformanceVsTarget();

// Get metric insights
$insights = $metric->generateInsights([
    'compare_period' => 'previous_month',
    'include_forecast' => true
]);
```

### Dynamic Dashboard Management

```php
// Create interactive dashboards
$dashboard = Dashboard::create([
    'name' => 'Executive Dashboard',
    'description' => 'High-level business metrics overview',
    'layout' => 'grid',
    'refresh_interval' => 300, // 5 minutes
    'is_public' => false
]);

// Add widgets with real-time data
$dashboard->addWidget([
    'type' => 'metric_card',
    'title' => 'Today\'s Revenue',
    'metric_key' => 'daily_revenue',
    'size' => 'medium',
    'position' => ['row' => 1, 'col' => 1]
]);

// Share dashboard with users
$dashboard->shareWith($userId, ['view', 'comment']);

// Track dashboard interactions
$dashboard->recordView($userId);
$dashboard->recordInteraction($userId, 'widget_click', ['widget_id' => 'revenue_card']);
```

### Advanced Report Generation

```php
// Create scheduled reports
$report = Report::create([
    'name' => 'Monthly Sales Report',
    'type' => 'sales_summary',
    'description' => 'Comprehensive monthly sales analysis',
    'is_scheduled' => true,
    'schedule_config' => [
        'frequency' => 'monthly',
        'day_of_month' => 1,
        'time' => '09:00:00'
    ],
    'export_formats' => ['pdf', 'excel'],
    'recipients' => ['sales@company.com', 'management@company.com']
]);

// Execute reports with performance tracking
$execution = $report->execute([
    'month' => '2024-01',
    'include_charts' => true,
    'comparison_period' => 'previous_month'
]);

// Monitor execution performance
$executionTime = $execution->getExecutionTime();
$dataQuality = $execution->getDataQualityScore();
$reportSize = $execution->getReportSize();
```

## ⚡ Real-Time Events

### Event Listeners

```php
// Metric threshold alerts
$eventDispatcher->listen('analytics.threshold_exceeded', function($event) {
    $data = $event->getData();
    // Send alert notifications
    $alertManager = app(AlertManager::class);
    $alertManager->sendThresholdAlert($data['metric_name'], $data['value'], $data['threshold']);
});

// Report generation completion
$eventDispatcher->listen('analytics.report_generated', function($event) {
    $data = $event->getData();
    // Distribute report to recipients
    $distributionService = app(ReportDistributionService::class);
    $distributionService->distributeReport($data['report_id']);
});
```

### Event Dispatching

```php
// Dispatch analytics events
$eventDispatcher->dispatch('analytics.metric_updated', [
    'metric_key' => 'daily_revenue',
    'old_value' => 10000.00,
    'new_value' => 12345.67,
    'change_percent' => 23.46,
    'timestamp' => now()->toISOString()
]);
```

## 📈 Performance Monitoring

### Health Checks

```php
// Register analytics-specific health checks
$healthMonitor->registerHealthCheck('analytics', 'data_processing', function() {
    // Check data processing pipeline health
    return $this->checkDataProcessingHealth();
});

$healthMonitor->registerHealthCheck('analytics', 'report_generation', function() {
    // Verify report generation performance
    return $this->validateReportGenerationSpeed();
});
```

### Metrics Tracking

```php
// Record analytics performance metrics
$healthMonitor->recordResponseTime('analytics', 'metric_calculation', 125.3);
$healthMonitor->recordMemoryUsage('analytics', 18.7);
$healthMonitor->recordDatabaseQueryTime('analytics', 'SELECT * FROM events', 45.2);
```

## 🧪 Automated Testing

### Test Coverage
- **Unit Tests** - Metric calculations and report logic
- **Integration Tests** - Cross-plugin data aggregation
- **Performance Tests** - Large dataset processing benchmarks
- **Security Tests** - Data access and privacy protection

### Example Tests

```php
class AnalyticsTestSuite extends PluginTestSuite
{
    public function getUnitTests(): array
    {
        return [
            'test_metric_calculation' => [$this, 'testMetricCalculation'],
            'test_report_generation' => [$this, 'testReportGeneration'],
            'test_dashboard_creation' => [$this, 'testDashboardCreation']
        ];
    }
    
    public function testMetricCalculation(): void
    {
        $metric = new Metric(['calculation_method' => 'average']);
        $result = $metric->calculateValue([100, 200, 300]);
        Assert::assertEquals(200, $result);
    }
}
```

## 🛠️ Configuration

### Plugin Settings

```json
{
    "real_time_updates": true,
    "data_retention_days": 365,
    "metric_calculation_interval": 60,
    "report_cache_ttl": 3600,
    "dashboard_refresh_interval": 300,
    "export_formats": ["pdf", "excel", "csv", "json"],
    "max_dashboard_widgets": 20,
    "enable_data_quality_checks": true
}
```

### Database Tables
- `analytics_events` - Event tracking and storage
- `analytics_metrics` - Metric definitions and settings
- `analytics_metric_values` - Historical metric data
- `analytics_reports` - Report configurations
- `analytics_report_executions` - Execution history
- `analytics_dashboards` - Dashboard definitions
- `analytics_dashboard_views` - View tracking and analytics
- `analytics_sessions` - User session data

## 📚 API Endpoints

### REST API
- `GET /api/v1/analytics/metrics` - List available metrics
- `GET /api/v1/analytics/metrics/{key}/data` - Get metric data
- `POST /api/v1/analytics/events` - Track custom events
- `GET /api/v1/analytics/reports` - List reports
- `POST /api/v1/analytics/reports/generate` - Generate report
- `GET /api/v1/analytics/dashboards` - List dashboards
- `POST /api/v1/analytics/dashboards` - Create dashboard

### Usage Examples

```bash
# Get metric data
curl -X GET "/api/v1/analytics/metrics/daily_revenue/data?start_date=2024-01-01&end_date=2024-01-31" \
  -H "Authorization: Bearer {token}"

# Track custom event
curl -X POST /api/v1/analytics/events \
  -H "Content-Type: application/json" \
  -d '{"event": "product_viewed", "properties": {"product_id": "123"}}'

# Generate report
curl -X POST /api/v1/analytics/reports/generate \
  -H "Content-Type: application/json" \
  -d '{"report_type": "sales_summary", "parameters": {"period": "monthly"}}'
```

## 🔧 Installation & Setup

### Requirements
- PHP 8.3+
- PostgreSQL database
- Shopologic Core Framework

### Installation

```bash
# Activate plugin
php cli/plugin.php activate advanced-analytics-reporting

# Run migrations
php cli/migrate.php up

# Initialize plugin ecosystem
php bootstrap_plugins.php
```

## 📖 Documentation

- **Analytics Guide** - Complete setup and configuration
- **Report Builder Manual** - Custom report creation
- **Dashboard Design Guide** - Best practices for dashboard creation
- **API Integration** - Developer integration examples

## 🚀 Production Ready

This plugin is part of the enhanced Shopologic ecosystem and is production-ready with:
- ✅ Comprehensive model layer with sophisticated business logic
- ✅ Cross-plugin integration via standardized interfaces
- ✅ Real-time event system with middleware support
- ✅ Performance monitoring and health checks
- ✅ Automated testing framework
- ✅ Complete documentation and examples

---

**Advanced Analytics & Reporting** - Enterprise business intelligence for Shopologic