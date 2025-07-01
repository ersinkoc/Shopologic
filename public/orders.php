<?php
// Demo orders data
$orders = [
    [
        'id' => 'SH-2025-1234',
        'date' => '2025-06-30 14:23:15',
        'customer' => ['name' => 'John Doe', 'email' => 'john.doe@email.com', 'avatar' => '👤'],
        'items' => 3,
        'total' => 234.56,
        'payment' => 'Credit Card',
        'status' => 'processing',
        'shipping' => ['method' => 'Express', 'tracking' => 'TRK123456789']
    ],
    [
        'id' => 'SH-2025-1233',
        'date' => '2025-06-30 13:45:22',
        'customer' => ['name' => 'Jane Smith', 'email' => 'jane.smith@email.com', 'avatar' => '👩'],
        'items' => 5,
        'total' => 567.89,
        'payment' => 'PayPal',
        'status' => 'completed',
        'shipping' => ['method' => 'Standard', 'tracking' => 'TRK987654321']
    ],
    [
        'id' => 'SH-2025-1232',
        'date' => '2025-06-30 11:30:45',
        'customer' => ['name' => 'Bob Johnson', 'email' => 'bob.j@email.com', 'avatar' => '👨'],
        'items' => 1,
        'total' => 123.45,
        'payment' => 'Bank Transfer',
        'status' => 'shipped',
        'shipping' => ['method' => 'Express', 'tracking' => 'TRK456789123']
    ],
    [
        'id' => 'SH-2025-1231',
        'date' => '2025-06-30 10:15:33',
        'customer' => ['name' => 'Alice Brown', 'email' => 'alice.b@email.com', 'avatar' => '👩‍💼'],
        'items' => 2,
        'total' => 89.99,
        'payment' => 'Credit Card',
        'status' => 'pending',
        'shipping' => ['method' => 'Standard', 'tracking' => '']
    ],
    [
        'id' => 'SH-2025-1230',
        'date' => '2025-06-30 09:45:12',
        'customer' => ['name' => 'Charlie Wilson', 'email' => 'charlie.w@email.com', 'avatar' => '🧑'],
        'items' => 4,
        'total' => 456.78,
        'payment' => 'Apple Pay',
        'status' => 'cancelled',
        'shipping' => ['method' => 'Express', 'tracking' => '']
    ]
];

// Status configuration
$statusConfig = [
    'pending' => ['color' => '#ffc107', 'bg' => '#fff3cd', 'text' => '#856404', 'icon' => '⏳'],
    'processing' => ['color' => '#17a2b8', 'bg' => '#d1ecf1', 'text' => '#0c5460', 'icon' => '⚙️'],
    'shipped' => ['color' => '#007bff', 'bg' => '#cce5ff', 'text' => '#004085', 'icon' => '🚚'],
    'completed' => ['color' => '#28a745', 'bg' => '#d4edda', 'text' => '#155724', 'icon' => '✅'],
    'cancelled' => ['color' => '#dc3545', 'bg' => '#f8d7da', 'text' => '#721c24', 'icon' => '❌'],
    'refunded' => ['color' => '#6c757d', 'bg' => '#e2e3e5', 'text' => '#383d41', 'icon' => '↩️']
];

// Filters
$selectedStatus = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - Shopologic Admin</title>
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
        .page-header { background: white; padding: 2rem 0; border-bottom: 1px solid #dee2e6; margin-bottom: 2rem; }
        .page-header-content { display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 1.8rem; color: #343a40; display: flex; align-items: center; gap: 0.5rem; }
        .page-actions { display: flex; gap: 1rem; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 5px; font-weight: 500; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: white; color: #495057; border: 1px solid #dee2e6; }
        
        /* Filters Bar */
        .filters-bar { background: white; padding: 1.5rem 0; border-radius: 10px; margin-bottom: 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .filters-content { display: flex; justify-content: space-between; align-items: center; gap: 2rem; }
        .filter-tabs { display: flex; gap: 1rem; }
        .filter-tab { padding: 0.5rem 1rem; background: none; border: none; cursor: pointer; color: #6c757d; font-weight: 500; border-radius: 5px; transition: all 0.3s; }
        .filter-tab:hover { background: #f8f9fa; }
        .filter-tab.active { background: #007bff; color: white; }
        .search-box { position: relative; }
        .search-input { padding: 0.5rem 1rem 0.5rem 2.5rem; border: 1px solid #dee2e6; border-radius: 5px; width: 300px; }
        .search-icon { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #6c757d; }
        
        /* Orders Table */
        .orders-table { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .table { width: 100%; border-collapse: collapse; }
        .table th { background: #f8f9fa; padding: 1rem; text-align: left; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6; }
        .table td { padding: 1rem; border-bottom: 1px solid #f8f9fa; }
        .table tr:hover { background: #f8f9fa; }
        
        /* Order Info */
        .order-id { font-weight: 600; color: #007bff; text-decoration: none; }
        .order-id:hover { text-decoration: underline; }
        .order-date { color: #6c757d; font-size: 0.85rem; }
        
        /* Customer Info */
        .customer-info { display: flex; align-items: center; gap: 0.75rem; }
        .customer-avatar { width: 40px; height: 40px; background: #e9ecef; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .customer-details { flex: 1; }
        .customer-name { font-weight: 500; color: #343a40; }
        .customer-email { color: #6c757d; font-size: 0.85rem; }
        
        /* Status Badge */
        .status-badge { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem; font-weight: 500; }
        
        /* Actions */
        .order-actions { display: flex; gap: 0.5rem; }
        .action-btn { padding: 0.25rem 0.5rem; background: none; border: 1px solid #dee2e6; border-radius: 5px; cursor: pointer; color: #495057; font-size: 0.85rem; transition: all 0.3s; }
        .action-btn:hover { background: #f8f9fa; }
        
        /* Order Details Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal-content { background: white; max-width: 800px; margin: 2rem auto; border-radius: 10px; max-height: 90vh; overflow-y: auto; }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; }
        .modal-title { font-size: 1.3rem; font-weight: 600; }
        .close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6c757d; }
        .modal-body { padding: 1.5rem; }
        
        /* Stats Cards */
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .stat-value { font-size: 2rem; font-weight: bold; color: #343a40; }
        .stat-label { color: #6c757d; font-size: 0.9rem; }
        
        /* Pagination */
        .pagination { display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 2rem; }
        .page-btn { padding: 0.5rem 0.75rem; background: white; border: 1px solid #dee2e6; border-radius: 5px; cursor: pointer; color: #495057; }
        .page-btn:hover { background: #f8f9fa; }
        .page-btn.active { background: #007bff; color: white; border-color: #007bff; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .filters-content { flex-direction: column; align-items: stretch; }
            .filter-tabs { overflow-x: auto; }
            .search-input { width: 100%; }
            .table { font-size: 0.9rem; }
            .order-actions { flex-direction: column; }
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
                    <a href="/analytics.php">Analytics</a>
                    <a href="/settings.php">Settings</a>
                    <a href="/">View Store</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="page-header-content">
                <h1 class="page-title">
                    🛍️ Orders Management
                </h1>
                <div class="page-actions">
                    <button class="btn btn-secondary">📥 Export Orders</button>
                    <button class="btn btn-primary">➕ Create Order</button>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Stats Cards -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value">156</div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">23</div>
                <div class="stat-label">Pending Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">45</div>
                <div class="stat-label">Processing</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">82</div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">$12,456</div>
                <div class="stat-label">Today's Revenue</div>
            </div>
        </div>

        <!-- Filters Bar -->
        <div class="filters-bar">
            <div class="filters-content">
                <div class="filter-tabs">
                    <button class="filter-tab <?php echo $selectedStatus === 'all' ? 'active' : ''; ?>">All Orders</button>
                    <button class="filter-tab <?php echo $selectedStatus === 'pending' ? 'active' : ''; ?>">Pending</button>
                    <button class="filter-tab <?php echo $selectedStatus === 'processing' ? 'active' : ''; ?>">Processing</button>
                    <button class="filter-tab <?php echo $selectedStatus === 'shipped' ? 'active' : ''; ?>">Shipped</button>
                    <button class="filter-tab <?php echo $selectedStatus === 'completed' ? 'active' : ''; ?>">Completed</button>
                    <button class="filter-tab <?php echo $selectedStatus === 'cancelled' ? 'active' : ''; ?>">Cancelled</button>
                </div>
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" class="search-input" placeholder="Search orders..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                </div>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="orders-table">
            <table class="table">
                <thead>
                    <tr>
                        <th width="15%">Order ID</th>
                        <th width="25%">Customer</th>
                        <th width="10%">Items</th>
                        <th width="12%">Total</th>
                        <th width="12%">Payment</th>
                        <th width="12%">Status</th>
                        <th width="14%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): 
                        $status = $statusConfig[$order['status']];
                    ?>
                    <tr>
                        <td>
                            <a href="#" class="order-id" onclick="showOrderDetails('<?php echo $order['id']; ?>')"><?php echo $order['id']; ?></a>
                            <div class="order-date"><?php echo date('M d, Y', strtotime($order['date'])); ?></div>
                        </td>
                        <td>
                            <div class="customer-info">
                                <div class="customer-avatar"><?php echo $order['customer']['avatar']; ?></div>
                                <div class="customer-details">
                                    <div class="customer-name"><?php echo htmlspecialchars($order['customer']['name']); ?></div>
                                    <div class="customer-email"><?php echo htmlspecialchars($order['customer']['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?php echo $order['items']; ?> items</td>
                        <td><strong>$<?php echo number_format($order['total'], 2); ?></strong></td>
                        <td><?php echo $order['payment']; ?></td>
                        <td>
                            <span class="status-badge" style="background: <?php echo $status['bg']; ?>; color: <?php echo $status['text']; ?>;">
                                <?php echo $status['icon']; ?> <?php echo ucfirst($order['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="order-actions">
                                <button class="action-btn" onclick="showOrderDetails('<?php echo $order['id']; ?>')">View</button>
                                <button class="action-btn">Edit</button>
                                <button class="action-btn">Print</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination">
            <button class="page-btn">Previous</button>
            <button class="page-btn active">1</button>
            <button class="page-btn">2</button>
            <button class="page-btn">3</button>
            <button class="page-btn">4</button>
            <button class="page-btn">5</button>
            <button class="page-btn">Next</button>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div class="modal" id="orderModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Order Details</h2>
                <button class="close-btn" onclick="closeModal()">×</button>
            </div>
            <div class="modal-body">
                <!-- Order details will be loaded here -->
                <div style="text-align: center; padding: 3rem;">
                    <p>Order details would be displayed here</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showOrderDetails(orderId) {
            document.getElementById('orderModal').style.display = 'block';
            // In a real app, this would load order details via AJAX
        }

        function closeModal() {
            document.getElementById('orderModal').style.display = 'none';
        }

        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('orderModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Filter tabs
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // In a real app, this would filter the orders
                document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>