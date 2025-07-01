<?php $this->layout('admin/layouts/admin'); ?>

<?php $this->section('content'); ?>
<div class="admin-dashboard">
    <div class="page-header">
        <h1>Dashboard</h1>
        <div class="header-actions">
            <button class="btn btn-primary" onclick="location.href='/admin/products/create'">
                <span class="icon">➕</span> Add Product
            </button>
            <button class="btn btn-secondary" onclick="location.href='/admin/reports'">
                <span class="icon">📊</span> View Reports
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon revenue">💰</div>
            <div class="stat-content">
                <h3>Total Revenue</h3>
                <p class="stat-value"><?php echo $this->money($stats['total_revenue']); ?></p>
                <p class="stat-change <?php echo $stats['revenue_change'] > 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $stats['revenue_change'] > 0 ? '↑' : '↓'; ?> 
                    <?php echo abs($stats['revenue_change']); ?>% from last month
                </p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon orders">🛒</div>
            <div class="stat-content">
                <h3>Total Orders</h3>
                <p class="stat-value"><?php echo number_format($stats['total_orders']); ?></p>
                <p class="stat-change <?php echo $stats['orders_change'] > 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $stats['orders_change'] > 0 ? '↑' : '↓'; ?> 
                    <?php echo abs($stats['orders_change']); ?>% from last month
                </p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon customers">👥</div>
            <div class="stat-content">
                <h3>Total Customers</h3>
                <p class="stat-value"><?php echo number_format($stats['total_customers']); ?></p>
                <p class="stat-change <?php echo $stats['customers_change'] > 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $stats['customers_change'] > 0 ? '↑' : '↓'; ?> 
                    <?php echo abs($stats['customers_change']); ?>% from last month
                </p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon products">📦</div>
            <div class="stat-content">
                <h3>Total Products</h3>
                <p class="stat-value"><?php echo number_format($stats['total_products']); ?></p>
                <p class="stat-secondary">
                    <?php echo $stats['pending_orders']; ?> orders pending
                </p>
            </div>
        </div>
    </div>

    <!-- Today's Stats -->
    <div class="today-stats">
        <h2>Today's Performance</h2>
        <div class="today-stats-grid">
            <div class="today-stat">
                <span class="label">Revenue</span>
                <span class="value"><?php echo $this->money($stats['revenue_today']); ?></span>
            </div>
            <div class="today-stat">
                <span class="label">Orders</span>
                <span class="value"><?php echo $stats['orders_today']; ?></span>
            </div>
            <div class="today-stat">
                <span class="label">New Customers</span>
                <span class="value"><?php echo $stats['new_customers_today']; ?></span>
            </div>
            <div class="today-stat">
                <span class="label">Conversion Rate</span>
                <span class="value"><?php echo $stats['conversion_rate']; ?>%</span>
            </div>
        </div>
    </div>

    <!-- Notifications -->
    <?php if (!empty($notifications)): ?>
    <div class="notifications-section">
        <h2>Notifications</h2>
        <div class="notifications-list">
            <?php foreach ($notifications as $notification): ?>
            <div class="notification <?php echo $notification['type']; ?>">
                <span class="notification-icon">
                    <?php 
                    switch($notification['type']) {
                        case 'warning': echo '⚠️'; break;
                        case 'info': echo 'ℹ️'; break;
                        case 'success': echo '✅'; break;
                        case 'alert': echo '🔔'; break;
                        default: echo '📌';
                    }
                    ?>
                </span>
                <div class="notification-content">
                    <p><?php echo $this->e($notification['message']); ?></p>
                    <span class="notification-time"><?php echo $notification['time']; ?></span>
                </div>
                <a href="<?php echo $notification['link']; ?>" class="notification-action">View</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <!-- Recent Orders -->
        <div class="dashboard-section recent-orders">
            <div class="section-header">
                <h2>Recent Orders</h2>
                <a href="/admin/orders" class="view-all">View All →</a>
            </div>
            <div class="orders-table">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_orders as $order): ?>
                        <tr>
                            <td><a href="/admin/orders/<?php echo $order['id']; ?>"><?php echo $order['id']; ?></a></td>
                            <td><?php echo $this->e($order['customer']); ?></td>
                            <td><?php echo $this->money($order['total']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, H:i', strtotime($order['date'])); ?></td>
                            <td>
                                <a href="/admin/orders/<?php echo $order['id']; ?>" class="btn-link">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Popular Products -->
        <div class="dashboard-section popular-products">
            <div class="section-header">
                <h2>Popular Products</h2>
                <a href="/admin/reports/products" class="view-all">View Report →</a>
            </div>
            <div class="products-list">
                <?php foreach ($popular_products as $product): ?>
                <div class="popular-product">
                    <div class="product-info">
                        <h4><?php echo $this->e($product['name']); ?></h4>
                        <p><?php echo $product['sales']; ?> sales</p>
                    </div>
                    <div class="product-revenue">
                        <?php echo $this->money($product['revenue']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert -->
    <?php if (!empty($low_stock_products)): ?>
    <div class="dashboard-section low-stock-alert">
        <div class="section-header">
            <h2>Low Stock Alert</h2>
            <a href="/admin/inventory?filter=low_stock" class="view-all">Manage Inventory →</a>
        </div>
        <div class="low-stock-table">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Current Stock</th>
                        <th>Threshold</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($low_stock_products as $product): ?>
                    <tr>
                        <td><?php echo $this->e($product['name']); ?></td>
                        <td><?php echo $this->e($product['sku']); ?></td>
                        <td class="stock-level"><?php echo $product['stock']; ?></td>
                        <td><?php echo $product['threshold']; ?></td>
                        <td>
                            <a href="/admin/products/<?php echo $product['id']; ?>/edit" class="btn-link">Update Stock</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Sales Chart -->
    <div class="dashboard-section sales-chart-section">
        <div class="section-header">
            <h2>Sales Overview (Last 7 Days)</h2>
            <a href="/admin/analytics" class="view-all">View Analytics →</a>
        </div>
        <div class="sales-chart">
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    <!-- Recent Customers -->
    <div class="dashboard-section recent-customers">
        <div class="section-header">
            <h2>New Customers</h2>
            <a href="/admin/customers" class="view-all">View All →</a>
        </div>
        <div class="customers-list">
            <?php foreach ($recent_customers as $customer): ?>
            <div class="customer-item">
                <div class="customer-avatar">
                    <?php echo strtoupper(substr($customer['name'], 0, 1)); ?>
                </div>
                <div class="customer-info">
                    <h4><?php echo $this->e($customer['name']); ?></h4>
                    <p><?php echo $this->e($customer['email']); ?></p>
                    <p class="customer-stats">
                        <?php echo $customer['orders']; ?> orders • 
                        <?php echo $this->money($customer['total_spent']); ?> spent
                    </p>
                </div>
                <div class="customer-joined">
                    Joined <?php echo $customer['joined']; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
// Sales Chart
document.addEventListener('DOMContentLoaded', function() {
    const chartData = <?php echo json_encode($sales_chart_data); ?>;
    const ctx = document.getElementById('salesChart').getContext('2d');
    
    // Simple chart rendering (in real app, use Chart.js or similar)
    const canvas = ctx.canvas;
    const width = canvas.width = canvas.offsetWidth;
    const height = canvas.height = 300;
    
    // Draw axes
    ctx.strokeStyle = '#e0e0e0';
    ctx.beginPath();
    ctx.moveTo(40, height - 40);
    ctx.lineTo(width - 20, height - 40);
    ctx.moveTo(40, 20);
    ctx.lineTo(40, height - 40);
    ctx.stroke();
    
    // Plot data
    const maxSales = Math.max(...chartData.map(d => d.sales));
    const xStep = (width - 80) / (chartData.length - 1);
    const yScale = (height - 80) / maxSales;
    
    ctx.strokeStyle = '#007bff';
    ctx.lineWidth = 2;
    ctx.beginPath();
    
    chartData.forEach((data, index) => {
        const x = 40 + (index * xStep);
        const y = height - 40 - (data.sales * yScale);
        
        if (index === 0) {
            ctx.moveTo(x, y);
        } else {
            ctx.lineTo(x, y);
        }
        
        // Draw data points
        ctx.fillStyle = '#007bff';
        ctx.beginPath();
        ctx.arc(x, y, 4, 0, Math.PI * 2);
        ctx.fill();
        
        // Labels
        ctx.fillStyle = '#666';
        ctx.font = '12px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(new Date(data.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }), x, height - 20);
    });
    
    ctx.stroke();
});
</script>
<?php $this->endSection(); ?>