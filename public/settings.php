<?php
// Settings sections
$settingsSections = [
    'general' => ['icon' => '⚙️', 'title' => 'General Settings', 'active' => true],
    'store' => ['icon' => '🏪', 'title' => 'Store Information'],
    'payment' => ['icon' => '💳', 'title' => 'Payment Methods'],
    'shipping' => ['icon' => '📦', 'title' => 'Shipping & Delivery'],
    'tax' => ['icon' => '🧾', 'title' => 'Tax Settings'],
    'email' => ['icon' => '✉️', 'title' => 'Email Configuration'],
    'security' => ['icon' => '🔐', 'title' => 'Security & Privacy'],
    'api' => ['icon' => '🔌', 'title' => 'API & Integrations'],
    'backup' => ['icon' => '💾', 'title' => 'Backup & Restore'],
    'advanced' => ['icon' => '🔧', 'title' => 'Advanced Settings']
];

// Store data
$storeData = [
    'name' => 'Shopologic Store',
    'email' => 'store@shopologic.com',
    'phone' => '1-800-SHOP-123',
    'address' => '123 Commerce Street, Suite 100',
    'city' => 'New York, NY 10001',
    'country' => 'United States',
    'currency' => 'USD',
    'timezone' => 'America/New_York'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Shopologic Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; }
        
        /* Header */
        .header { background: #1a1d23; color: white; padding: 1rem 0; position: sticky; top: 0; z-index: 100; }
        .container { max-width: 1400px; margin: 0 auto; padding: 0 1rem; }
        .header-content { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: bold; display: flex; align-items: center; gap: 0.5rem; }
        .header-nav { display: flex; gap: 2rem; align-items: center; }
        .nav-link { color: rgba(255,255,255,0.8); text-decoration: none; transition: color 0.3s; }
        .nav-link:hover { color: white; }
        .user-menu { display: flex; align-items: center; gap: 1rem; }
        .notification-btn { background: none; border: none; color: white; font-size: 1.2rem; cursor: pointer; position: relative; }
        .notification-badge { position: absolute; top: -5px; right: -5px; background: #dc3545; width: 18px; height: 18px; border-radius: 50%; font-size: 0.7rem; display: flex; align-items: center; justify-content: center; }
        
        /* Layout */
        .main-layout { display: grid; grid-template-columns: 280px 1fr; gap: 2rem; margin: 2rem 0; }
        
        /* Sidebar */
        .sidebar { background: white; border-radius: 10px; padding: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.05); height: fit-content; position: sticky; top: 80px; }
        .settings-menu { list-style: none; }
        .menu-item { margin-bottom: 0.5rem; }
        .menu-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; text-decoration: none; color: #495057; border-radius: 8px; transition: all 0.3s; }
        .menu-link:hover { background: #f8f9fa; }
        .menu-link.active { background: #007bff; color: white; }
        .menu-icon { font-size: 1.2rem; }
        
        /* Content Area */
        .content-area { background: white; border-radius: 10px; padding: 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 2px solid #f8f9fa; }
        .section-title { font-size: 1.5rem; color: #343a40; display: flex; align-items: center; gap: 0.5rem; }
        .save-btn { padding: 0.75rem 2rem; background: #28a745; color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; }
        .save-btn:hover { background: #218838; }
        
        /* Form Styles */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; margin-bottom: 0.5rem; color: #495057; font-weight: 500; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 0.75rem; border: 1px solid #ced4da; border-radius: 5px; font-size: 1rem; transition: border-color 0.3s; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: #007bff; }
        .form-help { font-size: 0.85rem; color: #6c757d; margin-top: 0.25rem; }
        .form-textarea { resize: vertical; min-height: 100px; }
        
        /* Toggle Switch */
        .toggle-switch { position: relative; display: inline-block; width: 50px; height: 24px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #ccc; transition: .4s; border-radius: 34px; }
        .toggle-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 4px; bottom: 4px; background: white; transition: .4s; border-radius: 50%; }
        .toggle-switch input:checked + .toggle-slider { background: #007bff; }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(26px); }
        
        /* Settings Cards */
        .settings-card { background: #f8f9fa; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .card-title { font-weight: 600; color: #343a40; }
        .card-status { display: flex; align-items: center; gap: 0.5rem; }
        .status-indicator { width: 8px; height: 8px; border-radius: 50%; }
        .status-active { background: #28a745; }
        .status-inactive { background: #dc3545; }
        
        /* Action Buttons */
        .action-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: 5px; cursor: pointer; font-weight: 500; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-outline { background: white; border: 1px solid #dee2e6; color: #495057; }
        .btn-danger { background: #dc3545; color: white; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
        
        /* Info Box */
        .info-box { background: #e7f3ff; border: 1px solid #b8daff; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; display: flex; gap: 1rem; }
        .info-icon { color: #004085; font-size: 1.5rem; }
        .info-content { flex: 1; }
        .info-title { font-weight: 600; color: #004085; margin-bottom: 0.25rem; }
        .info-text { color: #004085; font-size: 0.9rem; }
        
        /* Tab Navigation */
        .tab-nav { display: flex; gap: 1rem; margin-bottom: 2rem; border-bottom: 2px solid #dee2e6; }
        .tab-btn { padding: 0.75rem 1.5rem; background: none; border: none; cursor: pointer; font-weight: 500; color: #6c757d; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.3s; }
        .tab-btn:hover { color: #495057; }
        .tab-btn.active { color: #007bff; border-bottom-color: #007bff; }
        
        /* Status Cards */
        .status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .status-card { background: white; border: 1px solid #dee2e6; border-radius: 8px; padding: 1.5rem; text-align: center; }
        .status-icon { font-size: 2rem; margin-bottom: 0.5rem; }
        .status-title { font-weight: 600; margin-bottom: 0.25rem; }
        .status-value { color: #6c757d; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-layout { grid-template-columns: 1fr; }
            .sidebar { position: static; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    ⚙️ Shopologic Settings
                </div>
                <nav class="header-nav">
                    <a href="/" class="nav-link">View Store</a>
                    <a href="/admin.php" class="nav-link">Dashboard</a>
                    <a href="/analytics.php" class="nav-link">Analytics</a>
                    <div class="user-menu">
                        <button class="notification-btn">
                            🔔
                            <span class="notification-badge">3</span>
                        </button>
                        <span>Admin User</span>
                    </div>
                </nav>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="main-layout">
            <!-- Sidebar -->
            <aside class="sidebar">
                <ul class="settings-menu">
                    <?php foreach ($settingsSections as $key => $section): ?>
                    <li class="menu-item">
                        <a href="#<?php echo $key; ?>" class="menu-link <?php echo isset($section['active']) ? 'active' : ''; ?>" onclick="showSection('<?php echo $key; ?>')">
                            <span class="menu-icon"><?php echo $section['icon']; ?></span>
                            <span><?php echo $section['title']; ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </aside>

            <!-- Content Area -->
            <main class="content-area">
                <!-- General Settings -->
                <section id="general-section" class="settings-section">
                    <div class="section-header">
                        <h1 class="section-title">
                            ⚙️ General Settings
                        </h1>
                        <button class="save-btn">Save Changes</button>
                    </div>

                    <div class="info-box">
                        <div class="info-icon">ℹ️</div>
                        <div class="info-content">
                            <div class="info-title">Basic Store Configuration</div>
                            <div class="info-text">Configure your store's basic information and display preferences.</div>
                        </div>
                    </div>

                    <form>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Store Name</label>
                                <input type="text" class="form-input" value="<?php echo $storeData['name']; ?>">
                                <div class="form-help">This name appears in emails and invoices</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Store Email</label>
                                <input type="email" class="form-input" value="<?php echo $storeData['email']; ?>">
                                <div class="form-help">Primary contact email for customers</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Store Phone</label>
                                <input type="tel" class="form-input" value="<?php echo $storeData['phone']; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Time Zone</label>
                                <select class="form-select">
                                    <option selected><?php echo $storeData['timezone']; ?></option>
                                    <option>America/Los_Angeles</option>
                                    <option>America/Chicago</option>
                                    <option>Europe/London</option>
                                    <option>Asia/Tokyo</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Store Address</label>
                            <input type="text" class="form-input" value="<?php echo $storeData['address']; ?>" style="margin-bottom: 0.5rem;">
                            <input type="text" class="form-input" value="<?php echo $storeData['city']; ?>">
                        </div>

                        <h3 style="margin: 2rem 0 1rem;">Display Settings</h3>
                        
                        <div class="settings-card">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Maintenance Mode</div>
                                    <div style="color: #6c757d; font-size: 0.9rem;">Temporarily disable store for visitors</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="settings-card">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Customer Registration</div>
                                    <div style="color: #6c757d; font-size: 0.9rem;">Allow customers to create accounts</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="settings-card">
                            <div class="card-header">
                                <div>
                                    <div class="card-title">Guest Checkout</div>
                                    <div style="color: #6c757d; font-size: 0.9rem;">Allow checkout without registration</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </form>
                </section>

                <!-- Payment Settings (Hidden by default) -->
                <section id="payment-section" class="settings-section" style="display: none;">
                    <div class="section-header">
                        <h1 class="section-title">
                            💳 Payment Methods
                        </h1>
                        <button class="save-btn">Save Changes</button>
                    </div>

                    <div class="tab-nav">
                        <button class="tab-btn active">Active Gateways</button>
                        <button class="tab-btn">Available Gateways</button>
                        <button class="tab-btn">Settings</button>
                    </div>

                    <div class="status-grid">
                        <div class="status-card">
                            <div class="status-icon">💳</div>
                            <div class="status-title">Stripe</div>
                            <div class="status-value">Active</div>
                            <div class="action-buttons" style="margin-top: 1rem;">
                                <button class="btn btn-outline">Configure</button>
                                <button class="btn btn-danger">Disable</button>
                            </div>
                        </div>
                        
                        <div class="status-card">
                            <div class="status-icon">🅿️</div>
                            <div class="status-title">PayPal</div>
                            <div class="status-value">Active</div>
                            <div class="action-buttons" style="margin-top: 1rem;">
                                <button class="btn btn-outline">Configure</button>
                                <button class="btn btn-danger">Disable</button>
                            </div>
                        </div>
                        
                        <div class="status-card">
                            <div class="status-icon">🏦</div>
                            <div class="status-title">Bank Transfer</div>
                            <div class="status-value">Inactive</div>
                            <div class="action-buttons" style="margin-top: 1rem;">
                                <button class="btn btn-primary">Enable</button>
                            </div>
                        </div>
                        
                        <div class="status-card">
                            <div class="status-icon">💵</div>
                            <div class="status-title">Cash on Delivery</div>
                            <div class="status-value">Inactive</div>
                            <div class="action-buttons" style="margin-top: 1rem;">
                                <button class="btn btn-primary">Enable</button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Security Settings (Hidden by default) -->
                <section id="security-section" class="settings-section" style="display: none;">
                    <div class="section-header">
                        <h1 class="section-title">
                            🔐 Security & Privacy
                        </h1>
                        <button class="save-btn">Save Changes</button>
                    </div>

                    <div class="info-box" style="background: #f8d7da; border-color: #f5c6cb;">
                        <div class="info-icon" style="color: #721c24;">⚠️</div>
                        <div class="info-content">
                            <div class="info-title" style="color: #721c24;">Security Configuration</div>
                            <div class="info-text" style="color: #721c24;">These settings affect your store's security. Change with caution.</div>
                        </div>
                    </div>

                    <h3 style="margin-bottom: 1rem;">Authentication Settings</h3>
                    
                    <div class="settings-card">
                        <div class="card-header">
                            <div>
                                <div class="card-title">Two-Factor Authentication</div>
                                <div style="color: #6c757d; font-size: 0.9rem;">Require 2FA for admin accounts</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="settings-card">
                        <div class="card-header">
                            <div>
                                <div class="card-title">SSL/HTTPS</div>
                                <div style="color: #6c757d; font-size: 0.9rem;">Force secure connections</div>
                            </div>
                            <div class="card-status">
                                <span class="status-indicator status-active"></span>
                                <span style="color: #28a745;">Enabled</span>
                            </div>
                        </div>
                    </div>

                    <h3 style="margin: 2rem 0 1rem;">Password Policy</h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Minimum Password Length</label>
                            <input type="number" class="form-input" value="8" min="6" max="32">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Password Expiry (days)</label>
                            <input type="number" class="form-input" value="90" min="0">
                            <div class="form-help">Set to 0 to disable password expiry</div>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script>
        function showSection(sectionId) {
            // Hide all sections
            document.querySelectorAll('.settings-section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Show selected section
            const section = document.getElementById(sectionId + '-section');
            if (section) {
                section.style.display = 'block';
            }
            
            // Update active menu item
            document.querySelectorAll('.menu-link').forEach(link => {
                link.classList.remove('active');
            });
            event.target.closest('.menu-link').classList.add('active');
        }

        // Save settings simulation
        document.querySelectorAll('.save-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                this.textContent = 'Saving...';
                this.disabled = true;
                
                setTimeout(() => {
                    this.textContent = 'Saved!';
                    this.style.background = '#28a745';
                    
                    setTimeout(() => {
                        this.textContent = 'Save Changes';
                        this.style.background = '';
                        this.disabled = false;
                    }, 2000);
                }, 1000);
            });
        });

        // Tab navigation
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>