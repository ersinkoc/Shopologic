<?php
// Demo analytics data
$stats = [
    'revenue' => ['today' => 2847.50, 'yesterday' => 2156.75, 'week' => 18945.25, 'month' => 84562.40],
    'orders' => ['today' => 23, 'yesterday' => 19, 'week' => 156, 'month' => 687],
    'visitors' => ['today' => 1234, 'yesterday' => 987, 'week' => 8765, 'month' => 34521],
    'conversion' => ['today' => 1.86, 'yesterday' => 1.93, 'week' => 1.78, 'month' => 1.99]
];

// Sales chart data
$salesData = [
    ['date' => 'Mon', 'sales' => 2150],
    ['date' => 'Tue', 'sales' => 2890],
    ['date' => 'Wed', 'sales' => 2456],
    ['date' => 'Thu', 'sales' => 3234],
    ['date' => 'Fri', 'sales' => 3890],
    ['date' => 'Sat', 'sales' => 4567],
    ['date' => 'Sun', 'sales' => 3987]
];

// Top products
$topProducts = [
    ['name' => 'Premium Laptop Pro', 'sales' => 45, 'revenue' => 58495.55],
    ['name' => 'Wireless Headphones', 'sales' => 112, 'revenue' => 17918.88],
    ['name' => 'Smart Watch Elite', 'sales' => 67, 'revenue' => 23449.33],
    ['name' => 'Gaming Keyboard', 'sales' => 89, 'revenue' => 11569.11],
    ['name' => 'Professional Camera', 'sales' => 23, 'revenue' => 20699.77]
];

// Traffic sources
$trafficSources = [
    ['source' => 'Direct', 'visits' => 4532, 'percentage' => 35],
    ['source' => 'Organic Search', 'visits' => 3876, 'percentage' => 30],
    ['source' => 'Social Media', 'visits' => 2584, 'percentage' => 20],
    ['source' => 'Email', 'visits' => 1292, 'percentage' => 10],
    ['source' => 'Referral', 'visits' => 646, 'percentage' => 5]
];

// Customer insights
$customerInsights = [
    'new_customers' => 234,
    'returning_customers' => 567,
    'average_order_value' => 123.15,
    'customer_lifetime_value' => 456.78
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - Shopologic</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; }
        
        /* Header */
        .header { background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 1rem 0; position: sticky; top: 0; z-index: 100; }
        .container { max-width: 1400px; margin: 0 auto; padding: 0 1rem; }
        .header-content { display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 1.5rem; color: #343a40; display: flex; align-items: center; gap: 0.5rem; }
        .header-actions { display: flex; gap: 1rem; align-items: center; }
        .date-picker { padding: 0.5rem 1rem; border: 1px solid #dee2e6; border-radius: 5px; background: white; }
        .export-btn { padding: 0.5rem 1rem; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 500; }
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin: 2rem 0; }
        .stat-card { background: white; border-radius: 10px; padding: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .stat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .stat-title { color: #6c757d; font-size: 0.9rem; }
        .stat-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .icon-revenue { background: #e3f2fd; color: #1976d2; }
        .icon-orders { background: #e8f5e9; color: #388e3c; }
        .icon-visitors { background: #fff3e0; color: #f57c00; }
        .icon-conversion { background: #f3e5f5; color: #7b1fa2; }
        .stat-value { font-size: 2rem; font-weight: bold; color: #343a40; margin-bottom: 0.5rem; }
        .stat-change { font-size: 0.85rem; display: flex; align-items: center; gap: 0.25rem; }
        .change-positive { color: #28a745; }
        .change-negative { color: #dc3545; }
        
        /* Charts Section */
        .charts-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
        .chart-card { background: white; border-radius: 10px; padding: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .chart-title { font-size: 1.2rem; color: #343a40; }
        .chart-filters { display: flex; gap: 0.5rem; }
        .filter-btn { padding: 0.25rem 0.75rem; border: 1px solid #dee2e6; background: white; border-radius: 5px; cursor: pointer; font-size: 0.85rem; }
        .filter-btn.active { background: #007bff; color: white; border-color: #007bff; }
        
        /* Sales Chart */
        .sales-chart { height: 300px; position: relative; }
        .chart-bars { display: flex; align-items: flex-end; height: 250px; gap: 10px; margin-bottom: 10px; }
        .chart-bar { flex: 1; background: linear-gradient(to top, #007bff, #42a5f5); border-radius: 5px 5px 0 0; position: relative; cursor: pointer; transition: all 0.3s; }
        .chart-bar:hover { opacity: 0.8; }
        .chart-bar-value { position: absolute; top: -25px; left: 50%; transform: translateX(-50%); font-size: 0.75rem; font-weight: bold; opacity: 0; transition: opacity 0.3s; }
        .chart-bar:hover .chart-bar-value { opacity: 1; }
        .chart-labels { display: flex; gap: 10px; }
        .chart-label { flex: 1; text-align: center; font-size: 0.85rem; color: #6c757d; }
        
        /* Traffic Sources */
        .traffic-list { list-style: none; }
        .traffic-item { padding: 0.75rem 0; border-bottom: 1px solid #f8f9fa; }
        .traffic-header { display: flex; justify-content: space-between; margin-bottom: 0.5rem; }
        .traffic-source { font-weight: 500; color: #343a40; }
        .traffic-visits { color: #6c757d; font-size: 0.9rem; }
        .traffic-bar { height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden; }
        .traffic-progress { height: 100%; background: #007bff; transition: width 1s ease; }
        
        /* Tables */
        .table-card { background: white; border-radius: 10px; padding: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 0.75rem; border-bottom: 2px solid #dee2e6; color: #6c757d; font-weight: 600; }
        .data-table td { padding: 0.75rem; border-bottom: 1px solid #f8f9fa; }
        .data-table tr:hover { background: #f8f9fa; }
        
        /* Real-time Section */
        .realtime-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px; padding: 2rem; margin-bottom: 2rem; }
        .realtime-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .realtime-title { font-size: 1.5rem; display: flex; align-items: center; gap: 0.5rem; }
        .live-indicator { width: 10px; height: 10px; background: #4caf50; border-radius: 50%; animation: pulse 2s infinite; }
        .realtime-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; }
        .realtime-stat { text-align: center; }
        .realtime-number { font-size: 2.5rem; font-weight: bold; margin-bottom: 0.5rem; }
        .realtime-label { opacity: 0.9; }
        
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        /* Insights */
        .insights-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
        .insight-card { background: white; border-radius: 10px; padding: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-left: 4px solid #007bff; }
        .insight-title { font-weight: 600; color: #343a40; margin-bottom: 0.5rem; }
        .insight-description { color: #6c757d; font-size: 0.9rem; margin-bottom: 1rem; }
        .insight-action { color: #007bff; text-decoration: none; font-weight: 500; font-size: 0.9rem; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .charts-grid { grid-template-columns: 1fr; }
            .header-actions { flex-direction: column; gap: 0.5rem; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <h1 class="page-title">
                    📊 Analytics Dashboard
                </h1>
                <div class="header-actions">
                    <select class="date-picker">
                        <option>Last 7 days</option>
                        <option>Last 30 days</option>
                        <option>Last 90 days</option>
                        <option>Custom range</option>
                    </select>
                    <button class="export-btn">📥 Export Report</button>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Total Revenue</div>
                    <div class="stat-icon icon-revenue">💰</div>
                </div>
                <div class="stat-value">$<?php echo number_format($stats['revenue']['today'], 2); ?></div>
                <div class="stat-change change-positive">
                    ↑ <?php echo round((($stats['revenue']['today'] - $stats['revenue']['yesterday']) / $stats['revenue']['yesterday']) * 100, 1); ?>% vs yesterday
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Total Orders</div>
                    <div class="stat-icon icon-orders">📦</div>
                </div>
                <div class="stat-value"><?php echo $stats['orders']['today']; ?></div>
                <div class="stat-change change-positive">
                    ↑ <?php echo $stats['orders']['today'] - $stats['orders']['yesterday']; ?> vs yesterday
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Visitors</div>
                    <div class="stat-icon icon-visitors">👥</div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['visitors']['today']); ?></div>
                <div class="stat-change change-positive">
                    ↑ <?php echo round((($stats['visitors']['today'] - $stats['visitors']['yesterday']) / $stats['visitors']['yesterday']) * 100, 1); ?>% vs yesterday
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Conversion Rate</div>
                    <div class="stat-icon icon-conversion">📈</div>
                </div>
                <div class="stat-value"><?php echo $stats['conversion']['today']; ?>%</div>
                <div class="stat-change change-negative">
                    ↓ <?php echo abs($stats['conversion']['today'] - $stats['conversion']['yesterday']); ?>% vs yesterday
                </div>
            </div>
        </div>

        <!-- Real-time Stats -->
        <div class="realtime-section">
            <div class="realtime-header">
                <h2 class="realtime-title">
                    <span class="live-indicator"></span>
                    Real-time Activity
                </h2>
                <span>Last updated: just now</span>
            </div>
            <div class="realtime-stats">
                <div class="realtime-stat">
                    <div class="realtime-number" id="active-visitors">127</div>
                    <div class="realtime-label">Active Visitors</div>
                </div>
                <div class="realtime-stat">
                    <div class="realtime-number" id="carts-active">23</div>
                    <div class="realtime-label">Active Carts</div>
                </div>
                <div class="realtime-stat">
                    <div class="realtime-number">$<span id="revenue-minute">45.67</span></div>
                    <div class="realtime-label">Revenue/Minute</div>
                </div>
                <div class="realtime-stat">
                    <div class="realtime-number" id="page-views">342</div>
                    <div class="realtime-label">Page Views/Hour</div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <!-- Sales Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Sales Overview</h3>
                    <div class="chart-filters">
                        <button class="filter-btn active">Week</button>
                        <button class="filter-btn">Month</button>
                        <button class="filter-btn">Year</button>
                    </div>
                </div>
                <div class="sales-chart">
                    <div class="chart-bars">
                        <?php 
                        $maxSales = max(array_column($salesData, 'sales'));
                        foreach ($salesData as $day): 
                            $height = ($day['sales'] / $maxSales) * 100;
                        ?>
                        <div class="chart-bar" style="height: <?php echo $height; ?>%">
                            <div class="chart-bar-value">$<?php echo number_format($day['sales']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="chart-labels">
                        <?php foreach ($salesData as $day): ?>
                        <div class="chart-label"><?php echo $day['date']; ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Traffic Sources -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Traffic Sources</h3>
                </div>
                <ul class="traffic-list">
                    <?php foreach ($trafficSources as $source): ?>
                    <li class="traffic-item">
                        <div class="traffic-header">
                            <span class="traffic-source"><?php echo $source['source']; ?></span>
                            <span class="traffic-visits"><?php echo number_format($source['visits']); ?> visits</span>
                        </div>
                        <div class="traffic-bar">
                            <div class="traffic-progress" style="width: <?php echo $source['percentage']; ?>%"></div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Top Products -->
        <div class="table-card">
            <div class="chart-header">
                <h3 class="chart-title">🏆 Top Selling Products</h3>
                <a href="#" style="color: #007bff; text-decoration: none; font-size: 0.9rem;">View All →</a>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Units Sold</th>
                        <th>Revenue</th>
                        <th>Trend</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topProducts as $product): ?>
                    <tr>
                        <td><?php echo $product['name']; ?></td>
                        <td><?php echo $product['sales']; ?></td>
                        <td>$<?php echo number_format($product['revenue'], 2); ?></td>
                        <td><span style="color: #28a745;">↑ <?php echo rand(5, 25); ?>%</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Insights -->
        <h2 style="margin-bottom: 1.5rem;">💡 Insights & Recommendations</h2>
        <div class="insights-grid">
            <div class="insight-card">
                <h4 class="insight-title">🎯 High Conversion Product</h4>
                <p class="insight-description">
                    "Wireless Headphones" has a 35% higher conversion rate than average. Consider featuring it more prominently.
                </p>
                <a href="#" class="insight-action">View Product Analytics →</a>
            </div>
            
            <div class="insight-card">
                <h4 class="insight-title">📱 Mobile Traffic Growing</h4>
                <p class="insight-description">
                    Mobile traffic increased 25% this week. Ensure your mobile checkout experience is optimized.
                </p>
                <a href="#" class="insight-action">Mobile Analytics →</a>
            </div>
            
            <div class="insight-card">
                <h4 class="insight-title">🛒 Cart Abandonment Alert</h4>
                <p class="insight-description">
                    Cart abandonment rate is 68%. Consider implementing exit-intent popups or email reminders.
                </p>
                <a href="#" class="insight-action">Configure Recovery →</a>
            </div>
        </div>
    </div>

    <script>
        // Simulate real-time updates
        function updateRealtime() {
            // Update active visitors
            const visitors = document.getElementById('active-visitors');
            visitors.textContent = Math.floor(Math.random() * 50) + 100;
            
            // Update active carts
            const carts = document.getElementById('carts-active');
            carts.textContent = Math.floor(Math.random() * 10) + 20;
            
            // Update revenue per minute
            const revenue = document.getElementById('revenue-minute');
            revenue.textContent = (Math.random() * 100 + 20).toFixed(2);
            
            // Update page views
            const pageViews = document.getElementById('page-views');
            pageViews.textContent = Math.floor(Math.random() * 100) + 300;
        }
        
        // Update every 3 seconds
        setInterval(updateRealtime, 3000);
        
        // Animate progress bars on load
        window.addEventListener('load', () => {
            document.querySelectorAll('.traffic-progress').forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.width = width;
                }, 100);
            });
        });
        
        // Chart hover effects
        document.querySelectorAll('.chart-bar').forEach(bar => {
            bar.addEventListener('click', function() {
                const value = this.querySelector('.chart-bar-value').textContent;
                alert('Sales details: ' + value);
            });
        });
    </script>
</body>
</html>