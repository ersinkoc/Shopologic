<?php
// Demo campaign data
$campaigns = [
    [
        'id' => 1,
        'name' => 'Summer Sale 2025',
        'type' => 'email',
        'status' => 'active',
        'sent' => 5234,
        'opened' => 2156,
        'clicked' => 567,
        'conversions' => 89,
        'revenue' => 12456.78,
        'start_date' => '2025-06-15',
        'end_date' => '2025-07-15'
    ],
    [
        'id' => 2,
        'name' => 'VIP Customer Rewards',
        'type' => 'email',
        'status' => 'scheduled',
        'sent' => 0,
        'opened' => 0,
        'clicked' => 0,
        'conversions' => 0,
        'revenue' => 0,
        'start_date' => '2025-07-01',
        'end_date' => '2025-07-31'
    ],
    [
        'id' => 3,
        'name' => 'Abandoned Cart Recovery',
        'type' => 'automation',
        'status' => 'active',
        'sent' => 1234,
        'opened' => 678,
        'clicked' => 234,
        'conversions' => 45,
        'revenue' => 5678.90,
        'start_date' => '2025-01-01',
        'end_date' => null
    ]
];

// Marketing channels
$channels = [
    ['name' => 'Email Marketing', 'icon' => '📧', 'campaigns' => 12, 'revenue' => 45678],
    ['name' => 'SMS Marketing', 'icon' => '💬', 'campaigns' => 5, 'revenue' => 12345],
    ['name' => 'Social Media', 'icon' => '📱', 'campaigns' => 8, 'revenue' => 23456],
    ['name' => 'Push Notifications', 'icon' => '🔔', 'campaigns' => 15, 'revenue' => 8901]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketing - Shopologic Admin</title>
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
        .page-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 3rem 0; }
        .page-title { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .page-subtitle { font-size: 1.1rem; opacity: 0.9; }
        
        /* Stats Overview */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin: -2rem 0 2rem; position: relative; z-index: 10; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .stat-info h3 { color: #6c757d; font-size: 0.9rem; margin-bottom: 0.5rem; }
        .stat-value { font-size: 1.8rem; font-weight: bold; color: #343a40; }
        .stat-icon { font-size: 2.5rem; opacity: 0.8; }
        
        /* Tabs */
        .tabs { background: white; border-radius: 10px 10px 0 0; overflow: hidden; margin-bottom: 0; }
        .tab-nav { display: flex; border-bottom: 2px solid #dee2e6; }
        .tab-btn { padding: 1rem 2rem; background: none; border: none; cursor: pointer; font-weight: 500; color: #6c757d; position: relative; transition: all 0.3s; }
        .tab-btn:hover { background: #f8f9fa; }
        .tab-btn.active { color: #667eea; }
        .tab-btn.active::after { content: ''; position: absolute; bottom: -2px; left: 0; right: 0; height: 2px; background: #667eea; }
        
        /* Main Content */
        .content-area { background: white; padding: 2rem; border-radius: 0 0 10px 10px; min-height: 400px; }
        
        /* Campaign Grid */
        .campaign-grid { display: grid; gap: 1.5rem; }
        .campaign-card { border: 1px solid #dee2e6; border-radius: 10px; padding: 1.5rem; transition: all 0.3s; }
        .campaign-card:hover { box-shadow: 0 5px 15px rgba(0,0,0,0.1); transform: translateY(-2px); }
        .campaign-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; }
        .campaign-title { font-size: 1.2rem; font-weight: 600; color: #343a40; }
        .campaign-type { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 500; }
        .type-email { background: #cce5ff; color: #004085; }
        .type-automation { background: #d4edda; color: #155724; }
        .type-sms { background: #fff3cd; color: #856404; }
        
        /* Campaign Stats */
        .campaign-stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem; margin: 1rem 0; }
        .stat-item { text-align: center; }
        .stat-label { color: #6c757d; font-size: 0.85rem; margin-bottom: 0.25rem; }
        .stat-number { font-weight: 600; color: #343a40; }
        
        /* Progress Bar */
        .progress-bar { height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden; margin: 1rem 0; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #667eea, #764ba2); transition: width 1s ease; }
        
        /* Campaign Actions */
        .campaign-actions { display: flex; gap: 0.5rem; justify-content: flex-end; }
        .action-btn { padding: 0.5rem 1rem; border: none; border-radius: 5px; cursor: pointer; font-weight: 500; transition: all 0.3s; }
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: white; color: #495057; border: 1px solid #dee2e6; }
        
        /* Channel Cards */
        .channels-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
        .channel-card { background: #f8f9fa; border-radius: 10px; padding: 2rem; text-align: center; transition: all 0.3s; cursor: pointer; }
        .channel-card:hover { background: white; box-shadow: 0 5px 15px rgba(0,0,0,0.1); transform: translateY(-5px); }
        .channel-icon { font-size: 3rem; margin-bottom: 1rem; }
        .channel-name { font-size: 1.2rem; font-weight: 600; margin-bottom: 0.5rem; }
        .channel-stats { color: #6c757d; }
        
        /* Create Campaign */
        .create-section { text-align: center; padding: 3rem; }
        .create-icon { font-size: 4rem; margin-bottom: 1rem; opacity: 0.5; }
        .create-title { font-size: 1.5rem; margin-bottom: 0.5rem; color: #343a40; }
        .create-subtitle { color: #6c757d; margin-bottom: 2rem; }
        .template-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 2rem; }
        .template-card { border: 2px solid #dee2e6; border-radius: 10px; padding: 1.5rem; cursor: pointer; transition: all 0.3s; }
        .template-card:hover { border-color: #667eea; background: #f8f9fa; }
        .template-icon { font-size: 2rem; margin-bottom: 0.5rem; }
        
        /* Automation Builder */
        .automation-canvas { background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 10px; min-height: 400px; display: flex; align-items: center; justify-content: center; }
        .automation-placeholder { text-align: center; color: #6c757d; }
        
        /* Analytics */
        .analytics-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; }
        .chart-container { background: #f8f9fa; border-radius: 10px; padding: 2rem; height: 400px; display: flex; align-items: center; justify-content: center; }
        .metrics-list { list-style: none; }
        .metric-item { padding: 1rem; background: white; border-radius: 8px; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; }
        .metric-name { font-weight: 500; color: #343a40; }
        .metric-value { font-size: 1.2rem; font-weight: bold; color: #667eea; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; }
            .campaign-stats { grid-template-columns: repeat(2, 1fr); }
            .analytics-grid { grid-template-columns: 1fr; }
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
                    <a href="/customers.php">Customers</a>
                    <a href="/analytics.php">Analytics</a>
                    <a href="/">View Store</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1 class="page-title">📣 Marketing Center</h1>
            <p class="page-subtitle">Create and manage your marketing campaigns across all channels</p>
        </div>
    </div>

    <div class="container">
        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Revenue</h3>
                    <div class="stat-value">$89,234</div>
                </div>
                <div class="stat-icon">💰</div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Active Campaigns</h3>
                    <div class="stat-value">12</div>
                </div>
                <div class="stat-icon">🚀</div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Email Subscribers</h3>
                    <div class="stat-value">5,678</div>
                </div>
                <div class="stat-icon">📧</div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Conversion Rate</h3>
                    <div class="stat-value">3.45%</div>
                </div>
                <div class="stat-icon">📈</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <div class="tab-nav">
                <button class="tab-btn active" onclick="showTab('campaigns')">📢 Campaigns</button>
                <button class="tab-btn" onclick="showTab('automation')">🤖 Automation</button>
                <button class="tab-btn" onclick="showTab('channels')">📡 Channels</button>
                <button class="tab-btn" onclick="showTab('analytics')">📊 Analytics</button>
                <button class="tab-btn" onclick="showTab('create')">➕ Create New</button>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Campaigns Tab -->
            <div id="campaigns-tab" class="tab-content">
                <div class="campaign-grid">
                    <?php foreach ($campaigns as $campaign): ?>
                    <div class="campaign-card">
                        <div class="campaign-header">
                            <div>
                                <h3 class="campaign-title"><?php echo htmlspecialchars($campaign['name']); ?></h3>
                                <span class="campaign-type type-<?php echo $campaign['type']; ?>">
                                    <?php echo ucfirst($campaign['type']); ?>
                                </span>
                            </div>
                            <span style="color: <?php echo $campaign['status'] === 'active' ? '#28a745' : '#ffc107'; ?>;">
                                <?php echo ucfirst($campaign['status']); ?>
                            </span>
                        </div>
                        
                        <div class="campaign-stats">
                            <div class="stat-item">
                                <div class="stat-label">Sent</div>
                                <div class="stat-number"><?php echo number_format($campaign['sent']); ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Opened</div>
                                <div class="stat-number"><?php echo number_format($campaign['opened']); ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Clicked</div>
                                <div class="stat-number"><?php echo number_format($campaign['clicked']); ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Conversions</div>
                                <div class="stat-number"><?php echo $campaign['conversions']; ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Revenue</div>
                                <div class="stat-number">$<?php echo number_format($campaign['revenue'], 0); ?></div>
                            </div>
                        </div>
                        
                        <?php if ($campaign['opened'] > 0): ?>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo ($campaign['opened'] / $campaign['sent']) * 100; ?>%"></div>
                        </div>
                        <p style="text-align: center; color: #6c757d; font-size: 0.85rem; margin-top: 0.5rem;">
                            <?php echo round(($campaign['opened'] / $campaign['sent']) * 100, 1); ?>% Open Rate
                        </p>
                        <?php endif; ?>
                        
                        <div class="campaign-actions">
                            <button class="action-btn btn-secondary">View Report</button>
                            <button class="action-btn btn-primary">Edit</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Automation Tab -->
            <div id="automation-tab" class="tab-content" style="display: none;">
                <div class="automation-canvas">
                    <div class="automation-placeholder">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🤖</div>
                        <h3>Marketing Automation Builder</h3>
                        <p>Drag and drop to create automated marketing workflows</p>
                        <button class="action-btn btn-primary" style="margin-top: 1rem;">Start Building</button>
                    </div>
                </div>
            </div>

            <!-- Channels Tab -->
            <div id="channels-tab" class="tab-content" style="display: none;">
                <div class="channels-grid">
                    <?php foreach ($channels as $channel): ?>
                    <div class="channel-card">
                        <div class="channel-icon"><?php echo $channel['icon']; ?></div>
                        <h3 class="channel-name"><?php echo $channel['name']; ?></h3>
                        <div class="channel-stats">
                            <p><?php echo $channel['campaigns']; ?> Active Campaigns</p>
                            <p style="font-size: 1.2rem; font-weight: bold; margin-top: 0.5rem;">
                                $<?php echo number_format($channel['revenue']); ?> Revenue
                            </p>
                        </div>
                        <button class="action-btn btn-primary" style="margin-top: 1rem; width: 100%;">
                            Configure Channel
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Analytics Tab -->
            <div id="analytics-tab" class="tab-content" style="display: none;">
                <div class="analytics-grid">
                    <div class="chart-container">
                        <div style="text-align: center; color: #6c757d;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">📊</div>
                            <p>Campaign Performance Chart</p>
                        </div>
                    </div>
                    <div>
                        <h3 style="margin-bottom: 1rem;">Key Metrics</h3>
                        <ul class="metrics-list">
                            <li class="metric-item">
                                <span class="metric-name">Average Open Rate</span>
                                <span class="metric-value">24.5%</span>
                            </li>
                            <li class="metric-item">
                                <span class="metric-name">Click-through Rate</span>
                                <span class="metric-value">3.2%</span>
                            </li>
                            <li class="metric-item">
                                <span class="metric-name">Conversion Rate</span>
                                <span class="metric-value">1.8%</span>
                            </li>
                            <li class="metric-item">
                                <span class="metric-name">ROI</span>
                                <span class="metric-value">425%</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Create Tab -->
            <div id="create-tab" class="tab-content" style="display: none;">
                <div class="create-section">
                    <div class="create-icon">✨</div>
                    <h2 class="create-title">Create New Campaign</h2>
                    <p class="create-subtitle">Choose a template to get started</p>
                    
                    <div class="template-grid">
                        <div class="template-card">
                            <div class="template-icon">📧</div>
                            <h4>Email Campaign</h4>
                            <p style="color: #6c757d; font-size: 0.9rem;">Send targeted emails</p>
                        </div>
                        <div class="template-card">
                            <div class="template-icon">💬</div>
                            <h4>SMS Campaign</h4>
                            <p style="color: #6c757d; font-size: 0.9rem;">Text message marketing</p>
                        </div>
                        <div class="template-card">
                            <div class="template-icon">🔔</div>
                            <h4>Push Notification</h4>
                            <p style="color: #6c757d; font-size: 0.9rem;">Browser notifications</p>
                        </div>
                        <div class="template-card">
                            <div class="template-icon">🤖</div>
                            <h4>Automation Flow</h4>
                            <p style="color: #6c757d; font-size: 0.9rem;">Automated workflows</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').style.display = 'block';
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }
    </script>
</body>
</html>