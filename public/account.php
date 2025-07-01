<?php
// Demo user data
$user = [
    'name' => 'John Doe',
    'email' => 'john.doe@example.com',
    'member_since' => '2023-01-15',
    'orders' => 12,
    'wishlist' => 5,
    'rewards_points' => 1250
];

// Recent orders
$orders = [
    [
        'id' => '#SH-2025-0612',
        'date' => '2025-06-12',
        'items' => 3,
        'total' => 589.97,
        'status' => 'Delivered',
        'status_color' => '#28a745'
    ],
    [
        'id' => '#SH-2025-0605',
        'date' => '2025-06-05',
        'items' => 1,
        'total' => 159.99,
        'status' => 'In Transit',
        'status_color' => '#007bff'
    ],
    [
        'id' => '#SH-2025-0528',
        'date' => '2025-05-28',
        'items' => 2,
        'total' => 429.98,
        'status' => 'Processing',
        'status_color' => '#ffc107'
    ]
];

// Address book
$addresses = [
    [
        'type' => 'Home',
        'name' => 'John Doe',
        'address' => '123 Main Street, Apt 4B',
        'city' => 'New York, NY 10001',
        'phone' => '(555) 123-4567',
        'default' => true
    ],
    [
        'type' => 'Office',
        'name' => 'John Doe',
        'address' => '456 Business Ave, Suite 200',
        'city' => 'New York, NY 10002',
        'phone' => '(555) 987-6543',
        'default' => false
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - Shopologic</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8f9fa; }
        
        /* Header */
        .header { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 1rem 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 1rem; }
        .header-content { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.8rem; font-weight: bold; color: #007bff; text-decoration: none; }
        .nav-links { display: flex; gap: 2rem; list-style: none; }
        .nav-links a { text-decoration: none; color: #495057; padding: 0.5rem 1rem; }
        
        /* Account Header */
        .account-header { background: linear-gradient(135deg, #007bff, #0056b3); color: white; padding: 3rem 0; }
        .account-info { display: flex; align-items: center; gap: 2rem; }
        .avatar { width: 80px; height: 80px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: #007bff; }
        .user-details h1 { font-size: 2rem; margin-bottom: 0.5rem; }
        .user-meta { opacity: 0.9; }
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin: -2rem 0 2rem; position: relative; z-index: 10; }
        .stat-card { background: white; border-radius: 10px; padding: 1.5rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .stat-icon { font-size: 2rem; margin-bottom: 0.5rem; }
        .stat-value { font-size: 2rem; font-weight: bold; color: #343a40; }
        .stat-label { color: #6c757d; }
        
        /* Account Layout */
        .account-layout { display: grid; grid-template-columns: 250px 1fr; gap: 2rem; margin: 2rem 0; }
        
        /* Sidebar */
        .sidebar { background: white; border-radius: 10px; padding: 1.5rem; height: fit-content; }
        .sidebar-menu { list-style: none; }
        .sidebar-menu li { margin-bottom: 0.5rem; }
        .sidebar-menu a { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; text-decoration: none; color: #495057; border-radius: 5px; transition: all 0.3s; }
        .sidebar-menu a:hover { background: #f8f9fa; }
        .sidebar-menu a.active { background: #007bff; color: white; }
        
        /* Content Sections */
        .content-section { background: white; border-radius: 10px; padding: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: none; }
        .content-section.active { display: block; }
        .section-title { font-size: 1.5rem; margin-bottom: 1.5rem; color: #343a40; }
        
        /* Orders Table */
        .orders-table { width: 100%; border-collapse: collapse; }
        .orders-table th { text-align: left; padding: 1rem; border-bottom: 2px solid #dee2e6; color: #6c757d; }
        .orders-table td { padding: 1rem; border-bottom: 1px solid #f8f9fa; }
        .order-status { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem; font-weight: bold; color: white; }
        .order-actions { display: flex; gap: 0.5rem; }
        .action-btn { padding: 0.25rem 0.75rem; border: 1px solid #dee2e6; border-radius: 5px; text-decoration: none; color: #495057; font-size: 0.85rem; }
        .action-btn:hover { background: #f8f9fa; }
        
        /* Address Cards */
        .address-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
        .address-card { border: 2px solid #dee2e6; border-radius: 10px; padding: 1.5rem; position: relative; }
        .address-card.default { border-color: #007bff; }
        .default-badge { position: absolute; top: 10px; right: 10px; background: #007bff; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; }
        .address-type { font-weight: bold; margin-bottom: 0.5rem; color: #343a40; }
        .address-actions { margin-top: 1rem; display: flex; gap: 0.5rem; }
        
        /* Profile Form */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: #495057; font-weight: 500; }
        .form-group input { width: 100%; padding: 0.75rem; border: 1px solid #ced4da; border-radius: 5px; }
        
        /* Buttons */
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-outline { background: white; border: 1px solid #dee2e6; color: #495057; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .account-layout { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="/" class="logo">🛒 Shopologic</a>
                <nav>
                    <ul class="nav-links">
                        <li><a href="/">Home</a></li>
                        <li><a href="/category.php">Categories</a></li>
                        <li><a href="/cart.php">Cart</a></li>
                        <li><a href="/account.php" style="color: #007bff;">Account</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Account Header -->
    <section class="account-header">
        <div class="container">
            <div class="account-info">
                <div class="avatar">👤</div>
                <div class="user-details">
                    <h1><?php echo htmlspecialchars($user['name']); ?></h1>
                    <div class="user-meta">
                        <?php echo htmlspecialchars($user['email']); ?> • Member since <?php echo date('F Y', strtotime($user['member_since'])); ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Cards -->
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-value"><?php echo $user['orders']; ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">❤️</div>
                <div class="stat-value"><?php echo $user['wishlist']; ?></div>
                <div class="stat-label">Wishlist Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎁</div>
                <div class="stat-value"><?php echo number_format($user['rewards_points']); ?></div>
                <div class="stat-label">Reward Points</div>
            </div>
        </div>
    </div>

    <!-- Account Content -->
    <div class="container">
        <div class="account-layout">
            <!-- Sidebar -->
            <aside class="sidebar">
                <ul class="sidebar-menu">
                    <li><a href="#dashboard" class="active" onclick="switchTab('dashboard')">📊 Dashboard</a></li>
                    <li><a href="#orders" onclick="switchTab('orders')">📦 My Orders</a></li>
                    <li><a href="#addresses" onclick="switchTab('addresses')">📍 Address Book</a></li>
                    <li><a href="#profile" onclick="switchTab('profile')">👤 Profile Settings</a></li>
                    <li><a href="#wishlist" onclick="switchTab('wishlist')">❤️ Wishlist</a></li>
                    <li><a href="#rewards" onclick="switchTab('rewards')">🎁 Rewards</a></li>
                    <li><a href="#" style="color: #dc3545;">🚪 Sign Out</a></li>
                </ul>
            </aside>

            <!-- Content -->
            <main>
                <!-- Dashboard -->
                <section class="content-section active" id="dashboard">
                    <h2 class="section-title">Dashboard</h2>
                    <div style="display: grid; gap: 1.5rem;">
                        <div style="padding: 1.5rem; background: #e3f2fd; border-radius: 10px;">
                            <h3 style="margin-bottom: 1rem;">Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h3>
                            <p>From your account dashboard, you can view your recent orders, manage your shipping and billing addresses, and edit your password and account details.</p>
                        </div>
                        
                        <div>
                            <h3 style="margin-bottom: 1rem;">Recent Orders</h3>
                            <table class="orders-table">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($orders, 0, 2) as $order): ?>
                                    <tr>
                                        <td><?php echo $order['id']; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($order['date'])); ?></td>
                                        <td><?php echo $order['items']; ?> items</td>
                                        <td>$<?php echo number_format($order['total'], 2); ?></td>
                                        <td>
                                            <span class="order-status" style="background: <?php echo $order['status_color']; ?>">
                                                <?php echo $order['status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Orders -->
                <section class="content-section" id="orders">
                    <h2 class="section-title">My Orders</h2>
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?php echo $order['id']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($order['date'])); ?></td>
                                <td><?php echo $order['items']; ?> items</td>
                                <td>$<?php echo number_format($order['total'], 2); ?></td>
                                <td>
                                    <span class="order-status" style="background: <?php echo $order['status_color']; ?>">
                                        <?php echo $order['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="order-actions">
                                        <a href="#" class="action-btn">View</a>
                                        <a href="#" class="action-btn">Track</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>

                <!-- Address Book -->
                <section class="content-section" id="addresses">
                    <h2 class="section-title">Address Book</h2>
                    <div class="address-grid">
                        <?php foreach ($addresses as $address): ?>
                        <div class="address-card <?php echo $address['default'] ? 'default' : ''; ?>">
                            <?php if ($address['default']): ?>
                            <div class="default-badge">Default</div>
                            <?php endif; ?>
                            <div class="address-type"><?php echo $address['type']; ?></div>
                            <p><?php echo htmlspecialchars($address['name']); ?></p>
                            <p><?php echo htmlspecialchars($address['address']); ?></p>
                            <p><?php echo htmlspecialchars($address['city']); ?></p>
                            <p><?php echo htmlspecialchars($address['phone']); ?></p>
                            <div class="address-actions">
                                <button class="btn btn-outline">Edit</button>
                                <?php if (!$address['default']): ?>
                                <button class="btn btn-outline">Delete</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="address-card" style="border-style: dashed; display: flex; align-items: center; justify-content: center; min-height: 200px; cursor: pointer;">
                            <div style="text-align: center;">
                                <div style="font-size: 2rem; margin-bottom: 0.5rem;">➕</div>
                                <div>Add New Address</div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Profile Settings -->
                <section class="content-section" id="profile">
                    <h2 class="section-title">Profile Settings</h2>
                    <form>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="profile-first-name">First Name</label>
                                <input type="text" id="profile-first-name" value="John">
                            </div>
                            <div class="form-group">
                                <label for="profile-last-name">Last Name</label>
                                <input type="text" id="profile-last-name" value="Doe">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="profile-email">Email Address</label>
                            <input type="email" id="profile-email" value="john.doe@example.com">
                        </div>
                        
                        <div class="form-group">
                            <label for="profile-phone">Phone Number</label>
                            <input type="tel" id="profile-phone" value="(555) 123-4567">
                        </div>
                        
                        <h3 style="margin: 2rem 0 1rem;">Change Password</h3>
                        
                        <div class="form-group">
                            <label for="current-password">Current Password</label>
                            <input type="password" id="current-password">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="new-password">New Password</label>
                                <input type="password" id="new-password">
                            </div>
                            <div class="form-group">
                                <label for="confirm-password">Confirm Password</label>
                                <input type="password" id="confirm-password">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </section>

                <!-- Wishlist -->
                <section class="content-section" id="wishlist">
                    <h2 class="section-title">My Wishlist</h2>
                    <p>You have <?php echo $user['wishlist']; ?> items in your wishlist.</p>
                    <div style="text-align: center; padding: 3rem;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">❤️</div>
                        <p>Your wishlist items will appear here</p>
                        <a href="/" class="btn btn-primary" style="margin-top: 1rem;">Continue Shopping</a>
                    </div>
                </section>

                <!-- Rewards -->
                <section class="content-section" id="rewards">
                    <h2 class="section-title">Reward Points</h2>
                    <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 10px; padding: 2rem; text-align: center; margin-bottom: 2rem;">
                        <div style="font-size: 3rem; margin-bottom: 0.5rem;">🎁</div>
                        <div style="font-size: 2.5rem; font-weight: bold; color: #856404;"><?php echo number_format($user['rewards_points']); ?></div>
                        <div style="color: #856404;">Available Points</div>
                    </div>
                    
                    <h3 style="margin-bottom: 1rem;">How to Earn Points</h3>
                    <ul style="list-style: none;">
                        <li style="padding: 0.5rem 0;">✓ Earn 1 point for every $1 spent</li>
                        <li style="padding: 0.5rem 0;">✓ Get 500 bonus points on your birthday</li>
                        <li style="padding: 0.5rem 0;">✓ Refer a friend and earn 200 points</li>
                        <li style="padding: 0.5rem 0;">✓ Write a product review for 50 points</li>
                    </ul>
                </section>
            </main>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Update sidebar
            document.querySelectorAll('.sidebar-menu a').forEach(link => {
                link.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Update content
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });
            document.getElementById(tabName).classList.add('active');
        }
    </script>
</body>
</html>