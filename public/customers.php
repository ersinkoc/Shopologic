<?php
// Demo customers data
$customers = [
    [
        'id' => 1,
        'name' => 'John Doe',
        'email' => 'john.doe@email.com',
        'phone' => '+1 (555) 123-4567',
        'location' => 'New York, USA',
        'joined' => '2024-01-15',
        'orders' => 12,
        'spent' => 2456.78,
        'status' => 'active',
        'avatar' => '👤',
        'tags' => ['VIP', 'Newsletter']
    ],
    [
        'id' => 2,
        'name' => 'Jane Smith',
        'email' => 'jane.smith@email.com',
        'phone' => '+1 (555) 987-6543',
        'location' => 'Los Angeles, USA',
        'joined' => '2024-03-22',
        'orders' => 8,
        'spent' => 1234.56,
        'status' => 'active',
        'avatar' => '👩',
        'tags' => ['Loyal']
    ],
    [
        'id' => 3,
        'name' => 'Bob Johnson',
        'email' => 'bob.johnson@email.com',
        'phone' => '+44 20 7123 4567',
        'location' => 'London, UK',
        'joined' => '2024-02-10',
        'orders' => 5,
        'spent' => 567.89,
        'status' => 'active',
        'avatar' => '👨',
        'tags' => ['New']
    ],
    [
        'id' => 4,
        'name' => 'Alice Brown',
        'email' => 'alice.brown@email.com',
        'phone' => '+1 (555) 456-7890',
        'location' => 'Chicago, USA',
        'joined' => '2023-12-05',
        'orders' => 23,
        'spent' => 5678.90,
        'status' => 'active',
        'avatar' => '👩‍💼',
        'tags' => ['VIP', 'Wholesale']
    ],
    [
        'id' => 5,
        'name' => 'Charlie Wilson',
        'email' => 'charlie.w@email.com',
        'phone' => '+1 (555) 321-0987',
        'location' => 'Miami, USA',
        'joined' => '2024-04-18',
        'orders' => 2,
        'spent' => 123.45,
        'status' => 'inactive',
        'avatar' => '🧑',
        'tags' => ['At Risk']
    ]
];

// Customer segments
$segments = [
    ['name' => 'VIP Customers', 'count' => 45, 'icon' => '⭐', 'color' => '#ffc107'],
    ['name' => 'New Customers', 'count' => 128, 'icon' => '🆕', 'color' => '#28a745'],
    ['name' => 'Loyal Customers', 'count' => 89, 'icon' => '💎', 'color' => '#007bff'],
    ['name' => 'At Risk', 'count' => 23, 'icon' => '⚠️', 'color' => '#dc3545'],
    ['name' => 'Newsletter Subscribers', 'count' => 567, 'icon' => '📧', 'color' => '#17a2b8']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - Shopologic Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; }
        
        /* Header */
        .header { background: #1a1d23; color: white; padding: 1rem 0; position: sticky; top: 0; z-index: 100; }
        .container { max-width: 1400px; margin: 0 auto; padding: 0 2rem; }
        .header-content { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: bold; }
        .header-nav { display: flex; gap: 2rem; }
        .header-nav a { color: rgba(255,255,255,0.8); text-decoration: none; }
        
        /* Page Header */
        .page-header { background: white; padding: 2rem 0; border-bottom: 1px solid #dee2e6; }
        .page-title { font-size: 1.8rem; color: #343a40; display: flex; align-items: center; gap: 0.5rem; }
        .page-actions { display: flex; gap: 1rem; margin-top: 1rem; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 5px; font-weight: 500; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: white; color: #495057; border: 1px solid #dee2e6; }
        
        /* Stats Overview */
        .stats-overview { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin: 2rem 0; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); text-align: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-icon { font-size: 2rem; margin-bottom: 0.5rem; }
        .stat-value { font-size: 1.8rem; font-weight: bold; color: #343a40; }
        .stat-label { color: #6c757d; font-size: 0.9rem; }
        .stat-change { font-size: 0.85rem; margin-top: 0.5rem; }
        .change-positive { color: #28a745; }
        .change-negative { color: #dc3545; }
        
        /* Main Layout */
        .main-layout { display: grid; grid-template-columns: 1fr 300px; gap: 2rem; margin: 2rem 0; }
        
        /* Customers Table */
        .customers-section { background: white; border-radius: 10px; padding: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .search-filters { display: flex; gap: 1rem; align-items: center; }
        .search-box { position: relative; }
        .search-input { padding: 0.5rem 1rem 0.5rem 2.5rem; border: 1px solid #dee2e6; border-radius: 5px; width: 300px; }
        .search-icon { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #6c757d; }
        .filter-select { padding: 0.5rem 1rem; border: 1px solid #dee2e6; border-radius: 5px; background: white; }
        
        /* Table */
        .table { width: 100%; border-collapse: collapse; }
        .table th { text-align: left; padding: 1rem; border-bottom: 2px solid #dee2e6; color: #6c757d; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; }
        .table td { padding: 1rem; border-bottom: 1px solid #f8f9fa; }
        .table tr:hover { background: #f8f9fa; }
        
        /* Customer Info */
        .customer-info { display: flex; align-items: center; gap: 1rem; }
        .customer-avatar { width: 45px; height: 45px; background: #e9ecef; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .customer-details { flex: 1; }
        .customer-name { font-weight: 600; color: #343a40; margin-bottom: 0.25rem; }
        .customer-email { color: #6c757d; font-size: 0.85rem; }
        .customer-tags { display: flex; gap: 0.5rem; margin-top: 0.25rem; }
        .tag { padding: 0.15rem 0.5rem; background: #e9ecef; border-radius: 20px; font-size: 0.75rem; color: #495057; }
        .tag-vip { background: #fff3cd; color: #856404; }
        .tag-new { background: #d4edda; color: #155724; }
        .tag-risk { background: #f8d7da; color: #721c24; }
        
        /* Status */
        .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 0.5rem; }
        .status-active { background: #28a745; }
        .status-inactive { background: #dc3545; }
        
        /* Actions */
        .customer-actions { display: flex; gap: 0.5rem; }
        .action-btn { padding: 0.25rem 0.5rem; background: none; border: 1px solid #dee2e6; border-radius: 5px; cursor: pointer; color: #495057; font-size: 0.85rem; }
        .action-btn:hover { background: #f8f9fa; }
        
        /* Sidebar */
        .sidebar { display: flex; flex-direction: column; gap: 1.5rem; }
        .segment-card { background: white; border-radius: 10px; padding: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .segment-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem; color: #343a40; }
        .segment-list { list-style: none; }
        .segment-item { display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; margin-bottom: 0.5rem; background: #f8f9fa; border-radius: 5px; cursor: pointer; transition: all 0.3s; }
        .segment-item:hover { background: #e9ecef; transform: translateX(5px); }
        .segment-info { display: flex; align-items: center; gap: 0.75rem; }
        .segment-icon { font-size: 1.5rem; }
        .segment-name { font-weight: 500; }
        .segment-count { background: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem; }
        
        /* Activity Feed */
        .activity-card { background: white; border-radius: 10px; padding: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .activity-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem; color: #343a40; }
        .activity-item { display: flex; gap: 1rem; padding: 0.75rem 0; border-bottom: 1px solid #f8f9fa; }
        .activity-item:last-child { border-bottom: none; }
        .activity-icon { width: 35px; height: 35px; background: #e7f3ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; }
        .activity-content { flex: 1; }
        .activity-text { color: #495057; font-size: 0.9rem; }
        .activity-time { color: #6c757d; font-size: 0.8rem; margin-top: 0.25rem; }
        
        /* Customer Detail Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal-content { background: white; max-width: 900px; margin: 2rem auto; border-radius: 10px; max-height: 90vh; overflow-y: auto; }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; }
        .close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6c757d; }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .main-layout { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .search-filters { flex-direction: column; gap: 0.5rem; }
            .search-input { width: 100%; }
            .table { font-size: 0.9rem; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">🛒 Shopologic Admin</div>
                <nav class="header-nav">
                    <a href="/admin.php">Dashboard</a>
                    <a href="/orders.php">Orders</a>
                    <a href="/products-admin.php">Products</a>
                    <a href="/">View Store</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1 class="page-title">👥 Customers</h1>
            <div class="page-actions">
                <button class="btn btn-secondary">📥 Import Customers</button>
                <button class="btn btn-secondary">📤 Export List</button>
                <button class="btn btn-primary">➕ Add Customer</button>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Stats Overview -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-value">1,234</div>
                <div class="stat-label">Total Customers</div>
                <div class="stat-change change-positive">↑ 12% vs last month</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🆕</div>
                <div class="stat-value">128</div>
                <div class="stat-label">New This Month</div>
                <div class="stat-change change-positive">↑ 23% growth</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value">$125.43</div>
                <div class="stat-label">Average Order Value</div>
                <div class="stat-change change-negative">↓ 5% vs last month</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🔄</div>
                <div class="stat-value">67%</div>
                <div class="stat-label">Retention Rate</div>
                <div class="stat-change change-positive">↑ 3% improvement</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-value">$456.78</div>
                <div class="stat-label">Lifetime Value</div>
                <div class="stat-change change-positive">↑ 8% increase</div>
            </div>
        </div>

        <div class="main-layout">
            <!-- Customers Table -->
            <div class="customers-section">
                <div class="section-header">
                    <h2 style="font-size: 1.3rem; color: #343a40;">Customer List</h2>
                    <div class="search-filters">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" class="search-input" placeholder="Search customers...">
                        </div>
                        <select class="filter-select">
                            <option>All Customers</option>
                            <option>Active</option>
                            <option>Inactive</option>
                            <option>VIP</option>
                            <option>New</option>
                        </select>
                    </div>
                </div>

                <table class="table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Location</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td>
                                <div class="customer-info">
                                    <div class="customer-avatar"><?php echo $customer['avatar']; ?></div>
                                    <div class="customer-details">
                                        <div class="customer-name"><?php echo htmlspecialchars($customer['name']); ?></div>
                                        <div class="customer-email"><?php echo htmlspecialchars($customer['email']); ?></div>
                                        <div class="customer-tags">
                                            <?php foreach ($customer['tags'] as $tag): ?>
                                            <span class="tag <?php echo strtolower($tag) === 'vip' ? 'tag-vip' : (strtolower($tag) === 'new' ? 'tag-new' : (strtolower($tag) === 'at risk' ? 'tag-risk' : '')); ?>">
                                                <?php echo $tag; ?>
                                            </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($customer['location']); ?></div>
                                <div style="color: #6c757d; font-size: 0.85rem;"><?php echo htmlspecialchars($customer['phone']); ?></div>
                            </td>
                            <td>
                                <strong><?php echo $customer['orders']; ?></strong> orders
                                <div style="color: #6c757d; font-size: 0.85rem;">Joined <?php echo date('M Y', strtotime($customer['joined'])); ?></div>
                            </td>
                            <td>
                                <strong>$<?php echo number_format($customer['spent'], 2); ?></strong>
                            </td>
                            <td>
                                <span class="status-dot status-<?php echo $customer['status']; ?>"></span>
                                <?php echo ucfirst($customer['status']); ?>
                            </td>
                            <td>
                                <div class="customer-actions">
                                    <button class="action-btn" onclick="viewCustomer(<?php echo $customer['id']; ?>)">View</button>
                                    <button class="action-btn">Edit</button>
                                    <button class="action-btn">Email</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Sidebar -->
            <aside class="sidebar">
                <!-- Customer Segments -->
                <div class="segment-card">
                    <h3 class="segment-title">Customer Segments</h3>
                    <ul class="segment-list">
                        <?php foreach ($segments as $segment): ?>
                        <li class="segment-item">
                            <div class="segment-info">
                                <span class="segment-icon" style="color: <?php echo $segment['color']; ?>">
                                    <?php echo $segment['icon']; ?>
                                </span>
                                <span class="segment-name"><?php echo $segment['name']; ?></span>
                            </div>
                            <span class="segment-count"><?php echo $segment['count']; ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Recent Activity -->
                <div class="activity-card">
                    <h3 class="activity-title">Recent Activity</h3>
                    <div class="activity-list">
                        <div class="activity-item">
                            <div class="activity-icon" style="background: #d4edda; color: #28a745;">🆕</div>
                            <div class="activity-content">
                                <div class="activity-text">New customer registration</div>
                                <div class="activity-time">2 minutes ago</div>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon" style="background: #cce5ff; color: #007bff;">📧</div>
                            <div class="activity-content">
                                <div class="activity-text">Email campaign sent to VIP customers</div>
                                <div class="activity-time">1 hour ago</div>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon" style="background: #fff3cd; color: #ffc107;">⭐</div>
                            <div class="activity-content">
                                <div class="activity-text">Customer upgraded to VIP status</div>
                                <div class="activity-time">3 hours ago</div>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <!-- Customer Detail Modal -->
    <div class="modal" id="customerModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Customer Details</h2>
                <button class="close-btn" onclick="closeModal()">×</button>
            </div>
            <div class="modal-body" style="padding: 2rem;">
                <p>Customer details would be displayed here</p>
            </div>
        </div>
    </div>

    <script>
        function viewCustomer(customerId) {
            document.getElementById('customerModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('customerModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('customerModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>