<?php

declare(strict_types=1);

namespace Shopologic\Tests\E2E;

use Shopologic\Tests\E2E\TestFramework\E2ETestCase;
use Shopologic\Tests\E2E\TestFramework\Browser;

/**
 * Admin Journey E2E Test
 * 
 * Tests administrative workflows and management features
 */
class AdminJourneyTest extends E2ETestCase
{
    /**
     * Test admin login and dashboard access
     */
    public function testAdminLoginAndDashboard(): void
    {
        $browser = $this->createBrowser();
        
        // Visit admin login
        $browser->visit('/admin/login');
        $browser->waitForElement('.admin-login-form');
        
        // Verify security features
        $this->assertElementExists($browser, 'input[name="_token"]'); // CSRF token
        $this->assertElementExists($browser, '.captcha-container'); // Captcha
        
        // Login with admin credentials
        $browser->type('#email', 'admin@shopologic.com');
        $browser->type('#password', 'AdminSecure123!');
        $browser->click('.login-button');
        
        // Wait for dashboard
        $browser->waitForElement('.admin-dashboard');
        
        // Verify dashboard elements
        $this->assertPageContains($browser, 'Dashboard');
        $this->assertElementExists($browser, '.stats-overview');
        $this->assertElementExists($browser, '.recent-orders');
        $this->assertElementExists($browser, '.quick-actions');
        
        // Verify admin menu items
        $menuItems = [
            'Dashboard', 'Orders', 'Products', 'Customers', 
            'Analytics', 'Marketing', 'Settings', 'Plugins'
        ];
        
        foreach ($menuItems as $item) {
            $this->assertPageContains($browser, $item);
        }
    }
    
    /**
     * Test product management workflow
     */
    public function testProductManagement(): void
    {
        $browser = $this->createBrowser();
        $this->loginAsAdmin($browser);
        
        // Navigate to products
        $browser->click('a[href="/admin/products"]');
        $browser->waitForElement('.products-list');
        
        // Create new product
        $browser->click('.create-product-button');
        $browser->waitForElement('.product-form');
        
        // Fill product details
        $productName = 'Test Product ' . time();
        
        $browser->type('#name', $productName);
        $browser->type('#slug', 'test-product-' . time());
        $browser->type('#description', 'This is a test product description');
        $browser->type('#short_description', 'Test product');
        $browser->type('#sku', 'TEST-' . time());
        $browser->type('#price', '99.99');
        $browser->type('#compare_price', '149.99');
        $browser->type('#cost_price', '50.00');
        $browser->type('#quantity', '100');
        $browser->type('#weight', '500');
        
        // Select category
        $browser->select('#category_id', '1');
        
        // Upload image
        $browser->attach('#product_images', __DIR__ . '/fixtures/product-image.jpg');
        $browser->waitForElement('.image-preview');
        
        // Add variant
        $browser->click('.add-variant-button');
        $browser->waitForElement('.variant-form');
        
        $browser->type('.variant-name', 'Large - Blue');
        $browser->type('.variant-sku', 'TEST-L-BLUE');
        $browser->type('.variant-price', '109.99');
        $browser->type('.variant-quantity', '50');
        
        // SEO settings
        $browser->click('.seo-tab');
        $browser->type('#seo_title', $productName . ' - Best Deal');
        $browser->type('#seo_description', 'Buy ' . $productName . ' at the best price');
        $browser->type('#seo_keywords', 'test, product, best deal');
        
        // Save product
        $browser->click('.save-product-button');
        $browser->waitForElement('.success-notification');
        
        $this->assertPageContains($browser, 'Product created successfully');
        
        // Verify product in list
        $browser->visit('/admin/products');
        $browser->waitForElement('.products-list');
        
        $this->assertPageContains($browser, $productName);
        
        // Edit product
        $browser->click("tr:contains('$productName') .edit-button");
        $browser->waitForElement('.product-form');
        
        $browser->type('#price', '89.99');
        $browser->click('.save-product-button');
        $browser->waitForElement('.success-notification');
        
        // Bulk operations
        $browser->visit('/admin/products');
        $browser->check("tr:contains('$productName') input[type='checkbox']");
        $browser->select('.bulk-actions', 'deactivate');
        $browser->click('.apply-bulk-action');
        
        $browser->waitForElement('.confirmation-modal');
        $browser->click('.confirm-button');
        $browser->waitForElement('.success-notification');
        
        $this->assertPageContains($browser, 'Products updated successfully');
    }
    
    /**
     * Test order management workflow
     */
    public function testOrderManagement(): void
    {
        $browser = $this->createBrowser();
        $this->loginAsAdmin($browser);
        
        // Navigate to orders
        $browser->click('a[href="/admin/orders"]');
        $browser->waitForElement('.orders-list');
        
        // Filter orders
        $browser->select('#status_filter', 'processing');
        $browser->click('.apply-filters');
        $browser->waitForElement('.filtered-results');
        
        // View order details
        $browser->click('.orders-list tbody tr:first-child .view-button');
        $browser->waitForElement('.order-details');
        
        // Verify order information sections
        $this->assertElementExists($browser, '.customer-info');
        $this->assertElementExists($browser, '.order-items');
        $this->assertElementExists($browser, '.payment-info');
        $this->assertElementExists($browser, '.shipping-info');
        $this->assertElementExists($browser, '.order-timeline');
        
        // Update order status
        $browser->select('#order_status', 'shipped');
        $browser->type('#tracking_number', '1Z999AA1234567890');
        $browser->type('#tracking_url', 'https://tracking.example.com/1Z999AA1234567890');
        $browser->type('#status_note', 'Order has been shipped via FedEx');
        $browser->check('#notify_customer');
        
        $browser->click('.update-status-button');
        $browser->waitForElement('.success-notification');
        
        $this->assertPageContains($browser, 'Order status updated');
        $this->assertPageContains($browser, 'Customer notified');
        
        // Add order note
        $browser->click('.add-note-button');
        $browser->waitForElement('.note-form');
        
        $browser->type('#note_content', 'Customer requested expedited shipping');
        $browser->check('#note_private'); // Internal note
        $browser->click('.save-note-button');
        
        $browser->waitForElement('.order-note');
        $this->assertPageContains($browser, 'Customer requested expedited shipping');
        
        // Process refund
        $browser->click('.refund-button');
        $browser->waitForElement('.refund-modal');
        
        $browser->type('#refund_amount', '20.00');
        $browser->type('#refund_reason', 'Product defect');
        $browser->check('#restock_items');
        $browser->click('.process-refund-button');
        
        $browser->waitForElement('.refund-confirmation');
        $browser->click('.confirm-refund');
        $browser->waitForElement('.success-notification');
        
        $this->assertPageContains($browser, 'Refund processed successfully');
    }
    
    /**
     * Test customer management
     */
    public function testCustomerManagement(): void
    {
        $browser = $this->createBrowser();
        $this->loginAsAdmin($browser);
        
        // Navigate to customers
        $browser->click('a[href="/admin/customers"]');
        $browser->waitForElement('.customers-list');
        
        // Search for customer
        $browser->type('#customer_search', 'john.doe@example.com');
        $browser->click('.search-button');
        $browser->waitForElement('.search-results');
        
        // View customer details
        $browser->click('.customers-list tbody tr:first-child .view-button');
        $browser->waitForElement('.customer-details');
        
        // Verify customer information
        $this->assertElementExists($browser, '.customer-overview');
        $this->assertElementExists($browser, '.order-history');
        $this->assertElementExists($browser, '.customer-addresses');
        $this->assertElementExists($browser, '.customer-activity');
        
        // Edit customer
        $browser->click('.edit-customer-button');
        $browser->waitForElement('.customer-form');
        
        // Add customer tag
        $browser->type('#customer_tags', 'VIP');
        $browser->press('Enter');
        
        // Add customer note
        $browser->type('#customer_note', 'Preferred customer - offer special discounts');
        
        // Save changes
        $browser->click('.save-customer-button');
        $browser->waitForElement('.success-notification');
        
        // Send customer email
        $browser->click('.email-customer-button');
        $browser->waitForElement('.email-modal');
        
        $browser->select('#email_template', 'promotional');
        $browser->type('#email_subject', 'Special VIP Offer Just for You!');
        $browser->click('.send-email-button');
        
        $browser->waitForElement('.success-notification');
        $this->assertPageContains($browser, 'Email sent successfully');
        
        // Create customer group
        $browser->visit('/admin/customers/groups');
        $browser->click('.create-group-button');
        $browser->waitForElement('.group-form');
        
        $browser->type('#group_name', 'VIP Customers');
        $browser->type('#group_description', 'High-value customers');
        $browser->type('#discount_percentage', '10');
        $browser->check('#free_shipping');
        
        $browser->click('.save-group-button');
        $browser->waitForElement('.success-notification');
    }
    
    /**
     * Test analytics dashboard
     */
    public function testAnalyticsDashboard(): void
    {
        $browser = $this->createBrowser();
        $this->loginAsAdmin($browser);
        
        // Navigate to analytics
        $browser->click('a[href="/admin/analytics"]');
        $browser->waitForElement('.analytics-dashboard');
        
        // Verify analytics sections
        $this->assertElementExists($browser, '.revenue-chart');
        $this->assertElementExists($browser, '.orders-chart');
        $this->assertElementExists($browser, '.customers-chart');
        $this->assertElementExists($browser, '.products-performance');
        
        // Change date range
        $browser->click('.date-range-selector');
        $browser->click('option[value="last_30_days"]');
        $browser->waitForElement('.charts-updated');
        
        // Export report
        $browser->click('.export-report-button');
        $browser->waitForElement('.export-modal');
        
        $browser->select('#report_type', 'sales_summary');
        $browser->select('#report_format', 'pdf');
        $browser->click('.generate-report-button');
        
        $browser->waitForElement('.download-ready');
        $this->assertElementExists($browser, '.download-report-link');
        
        // View real-time analytics
        $browser->click('.real-time-tab');
        $browser->waitForElement('.real-time-stats');
        
        $this->assertElementExists($browser, '.active-visitors');
        $this->assertElementExists($browser, '.current-carts');
        $this->assertElementExists($browser, '.todays-revenue');
    }
    
    /**
     * Test plugin management
     */
    public function testPluginManagement(): void
    {
        $browser = $this->createBrowser();
        $this->loginAsAdmin($browser);
        
        // Navigate to plugins
        $browser->click('a[href="/admin/plugins"]');
        $browser->waitForElement('.plugins-list');
        
        // Search for plugin
        $browser->type('#plugin_search', 'payment');
        $browser->click('.search-plugins');
        $browser->waitForElement('.search-results');
        
        // Install plugin
        $browser->click('.plugin-card:contains("Stripe Payment") .install-button');
        $browser->waitForElement('.installation-progress');
        
        $browser->waitForElement('.installation-complete', 30);
        $this->assertPageContains($browser, 'Plugin installed successfully');
        
        // Configure plugin
        $browser->click('.configure-button');
        $browser->waitForElement('.plugin-settings');
        
        $browser->type('#stripe_api_key', 'sk_test_...');
        $browser->type('#stripe_publishable_key', 'pk_test_...');
        $browser->check('#stripe_enable_3ds');
        
        $browser->click('.save-settings-button');
        $browser->waitForElement('.success-notification');
        
        // Activate plugin
        $browser->click('.activate-plugin-button');
        $browser->waitForElement('.activation-complete');
        
        $this->assertPageContains($browser, 'Plugin activated');
        $this->assertElementExists($browser, '.deactivate-button');
    }
    
    /**
     * Test store settings management
     */
    public function testStoreSettings(): void
    {
        $browser = $this->createBrowser();
        $this->loginAsAdmin($browser);
        
        // Navigate to settings
        $browser->click('a[href="/admin/settings"]');
        $browser->waitForElement('.settings-page');
        
        // General settings
        $browser->click('.settings-tab[data-tab="general"]');
        $browser->waitForElement('.general-settings');
        
        $browser->clear('#store_name');
        $browser->type('#store_name', 'My Awesome Store');
        $browser->type('#store_email', 'support@mystore.com');
        $browser->type('#store_phone', '+1 (555) 123-4567');
        
        $browser->click('.save-settings-button');
        $browser->waitForElement('.success-notification');
        
        // Shipping settings
        $browser->click('.settings-tab[data-tab="shipping"]');
        $browser->waitForElement('.shipping-settings');
        
        // Add shipping zone
        $browser->click('.add-shipping-zone');
        $browser->waitForElement('.zone-form');
        
        $browser->type('#zone_name', 'United States');
        $browser->select('#zone_countries', 'US');
        
        // Add shipping method
        $browser->click('.add-shipping-method');
        $browser->type('#method_name', 'Standard Shipping');
        $browser->type('#method_rate', '9.99');
        $browser->type('#method_min_order', '0');
        
        $browser->click('.save-zone-button');
        $browser->waitForElement('.success-notification');
        
        // Tax settings
        $browser->click('.settings-tab[data-tab="tax"]');
        $browser->waitForElement('.tax-settings');
        
        $browser->check('#enable_tax');
        $browser->check('#prices_include_tax');
        
        // Add tax rate
        $browser->click('.add-tax-rate');
        $browser->waitForElement('.tax-rate-form');
        
        $browser->type('#tax_name', 'Sales Tax');
        $browser->type('#tax_rate', '8.875');
        $browser->select('#tax_country', 'US');
        $browser->select('#tax_state', 'NY');
        
        $browser->click('.save-tax-rate-button');
        $browser->waitForElement('.success-notification');
        
        // Email settings
        $browser->click('.settings-tab[data-tab="email"]');
        $browser->waitForElement('.email-settings');
        
        // Customize email template
        $browser->select('#email_template_type', 'order_confirmation');
        $browser->click('.customize-template-button');
        $browser->waitForElement('.template-editor');
        
        $browser->type('#email_subject', 'Your Order #{order_number} is Confirmed!');
        $browser->click('.save-template-button');
        $browser->waitForElement('.success-notification');
        
        // Send test email
        $browser->type('#test_email_address', 'test@example.com');
        $browser->click('.send-test-email');
        $browser->waitForElement('.test-email-sent');
        
        $this->assertPageContains($browser, 'Test email sent successfully');
    }
    
    /**
     * Test marketing campaigns
     */
    public function testMarketingCampaigns(): void
    {
        $browser = $this->createBrowser();
        $this->loginAsAdmin($browser);
        
        // Navigate to marketing
        $browser->click('a[href="/admin/marketing"]');
        $browser->waitForElement('.marketing-dashboard');
        
        // Create discount code
        $browser->click('.create-discount-button');
        $browser->waitForElement('.discount-form');
        
        $discountCode = 'SAVE20' . rand(100, 999);
        
        $browser->type('#discount_code', $discountCode);
        $browser->type('#discount_description', '20% off everything');
        $browser->select('#discount_type', 'percentage');
        $browser->type('#discount_value', '20');
        $browser->type('#minimum_amount', '50');
        $browser->type('#usage_limit', '100');
        
        // Set validity period
        $browser->type('#valid_from', date('Y-m-d'));
        $browser->type('#valid_until', date('Y-m-d', strtotime('+30 days')));
        
        $browser->click('.save-discount-button');
        $browser->waitForElement('.success-notification');
        
        // Create email campaign
        $browser->click('.create-campaign-button');
        $browser->waitForElement('.campaign-form');
        
        $browser->type('#campaign_name', 'Summer Sale Campaign');
        $browser->select('#campaign_type', 'promotional');
        $browser->select('#customer_segment', 'all_customers');
        
        // Design email
        $browser->click('.design-email-button');
        $browser->waitForElement('.email-designer');
        
        // Use template
        $browser->click('.template-gallery-button');
        $browser->click('.template-card:first-child');
        $browser->waitForElement('.template-loaded');
        
        // Customize content
        $browser->doubleClick('.editable-heading');
        $browser->clear('.text-editor');
        $browser->type('.text-editor', 'Summer Sale - Up to 50% Off!');
        
        // Add discount code block
        $browser->drag('.discount-block', '.email-content');
        $browser->type('.discount-code-input', $discountCode);
        
        // Schedule campaign
        $browser->click('.schedule-tab');
        $browser->check('#schedule_campaign');
        $browser->type('#send_date', date('Y-m-d', strtotime('+1 day')));
        $browser->type('#send_time', '10:00');
        
        $browser->click('.schedule-campaign-button');
        $browser->waitForElement('.campaign-scheduled');
        
        $this->assertPageContains($browser, 'Campaign scheduled successfully');
    }
    
    /**
     * Test system monitoring
     */
    public function testSystemMonitoring(): void
    {
        $browser = $this->createBrowser();
        $this->loginAsAdmin($browser);
        
        // Navigate to system monitoring
        $browser->click('a[href="/admin/system/monitoring"]');
        $browser->waitForElement('.monitoring-dashboard');
        
        // Verify monitoring sections
        $this->assertElementExists($browser, '.system-health');
        $this->assertElementExists($browser, '.performance-metrics');
        $this->assertElementExists($browser, '.error-logs');
        $this->assertElementExists($browser, '.security-alerts');
        
        // Check system health
        $browser->click('.run-health-check');
        $browser->waitForElement('.health-check-complete');
        
        $this->assertElementExists($browser, '.health-status-indicator');
        
        // View error logs
        $browser->click('.error-logs-tab');
        $browser->waitForElement('.logs-viewer');
        
        // Filter logs
        $browser->select('#log_level', 'error');
        $browser->type('#log_search', 'payment');
        $browser->click('.filter-logs-button');
        
        $browser->waitForElement('.filtered-logs');
        
        // Clear cache
        $browser->click('.maintenance-tab');
        $browser->click('.clear-cache-button');
        
        $browser->waitForElement('.confirmation-modal');
        $browser->click('.confirm-clear-cache');
        $browser->waitForElement('.cache-cleared');
        
        $this->assertPageContains($browser, 'Cache cleared successfully');
    }
    
    /**
     * Helper: Login as admin
     */
    private function loginAsAdmin(Browser $browser): void
    {
        $browser->visit('/admin/login');
        $browser->type('#email', 'admin@shopologic.com');
        $browser->type('#password', 'AdminSecure123!');
        $browser->click('.login-button');
        $browser->waitForElement('.admin-dashboard');
    }
}