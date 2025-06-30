<?php

declare(strict_types=1);

namespace Shopologic\Plugins\MultiTenantSaas;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\HookSystem;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use Shopologic\Core\Database\DB;
use MultiTenantSaaS\Services\{
    TenantManager,
    SubscriptionManager,
    BillingService,
    QuotaManager,
    DomainManager,
    ProvisioningService,
    IsolationManager,
    UsageTracker,;
    BackupService;
};
use MultiTenantSaaS\Middleware\TenantMiddleware;
use MultiTenantSaaS\Models\{Tenant, Subscription, Plan};

class MultiTenantSaaSPlugin extends AbstractPlugin
{
    private TenantManager $tenantManager;
    private SubscriptionManager $subscriptionManager;
    private BillingService $billingService;
    private QuotaManager $quotaManager;
    private DomainManager $domainManager;
    private ProvisioningService $provisioningService;
    private IsolationManager $isolationManager;
    private UsageTracker $usageTracker;
    private BackupService $backupService;
    
    private ?Tenant $currentTenant = null;
    
    /**
     * Plugin activation
     */
    public function activate(): void
    {
        // Run migrations
        $this->runMigrations();
        
        // Create default plans
        $this->createDefaultPlans();
        
        // Initialize tenant isolation
        $this->initializeTenantIsolation();
        
        // Set default options
        $this->setDefaultOptions();
        
        // Create required directories
        $this->createDirectories();
        
        // Schedule initial tasks
        $this->scheduleInitialTasks();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        // Suspend all active background jobs
        $this->suspendBackgroundJobs();
        
        // Save current state
        $this->saveCurrentState();
    }
    
    /**
     * Register hooks
     */
    protected function registerHooks(): void
    {
        // Initialize services
        HookSystem::addAction('init', [$this, 'initializeServices'], 1);
        
        // Tenant detection and switching
        HookSystem::addAction('init', [$this, 'detectTenant'], 2);
        HookSystem::addFilter('request', [$this, 'applyTenantContext'], 1);
        
        // Middleware registration
        HookSystem::addAction('init', [$this, 'registerMiddleware'], 3);
        
        // Database query filtering for tenant isolation
        HookSystem::addFilter('database.query', [$this, 'filterDatabaseQuery'], 1);
        HookSystem::addFilter('database.table', [$this, 'filterDatabaseTable'], 1);
        
        // Admin interface
        HookSystem::addAction('admin_menu', [$this, 'registerAdminMenu']);
        HookSystem::addAction('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        HookSystem::addAction('admin_bar_menu', [$this, 'addTenantSwitcher'], 100);
        
        // Frontend tenant context
        HookSystem::addAction('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
        HookSystem::addFilter('site_url', [$this, 'filterSiteUrl']);
        HookSystem::addFilter('home_url', [$this, 'filterHomeUrl']);
        
        // API endpoints
        $this->registerApiEndpoints();
        
        // Subscription lifecycle hooks
        HookSystem::addAction('subscription.created', [$this, 'onSubscriptionCreated']);
        HookSystem::addAction('subscription.upgraded', [$this, 'onSubscriptionUpgraded']);
        HookSystem::addAction('subscription.cancelled', [$this, 'onSubscriptionCancelled']);
        
        // Usage tracking hooks
        HookSystem::addAction('api.request.complete', [$this, 'trackApiUsage']);
        HookSystem::addAction('order.created', [$this, 'trackOrderUsage']);
        HookSystem::addAction('product.created', [$this, 'trackProductUsage']);
        HookSystem::addAction('media.uploaded', [$this, 'trackStorageUsage']);
        
        // Quota enforcement hooks
        HookSystem::addFilter('can_create_product', [$this, 'checkProductQuota']);
        HookSystem::addFilter('can_create_order', [$this, 'checkOrderQuota']);
        HookSystem::addFilter('can_add_user', [$this, 'checkUserQuota']);
        
        // Scheduled tasks
        HookSystem::addAction('saas_process_usage', [$this, 'processUsageMetering']);
        HookSystem::addAction('saas_process_renewals', [$this, 'processSubscriptionRenewals']);
        HookSystem::addAction('saas_check_quotas', [$this, 'checkQuotaLimits']);
        HookSystem::addAction('saas_generate_invoices', [$this, 'generateInvoices']);
        
        // Payment webhook handlers
        HookSystem::addAction('payment.webhook.stripe', [$this, 'handleStripeWebhook']);
        HookSystem::addAction('payment.webhook.paypal', [$this, 'handlePayPalWebhook']);
    }
    
    /**
     * Initialize services
     */
    public function initializeServices(): void
    {
        // Initialize core services
        $this->isolationManager = new IsolationManager($this->getOption('tenant_isolation', 'schema'));
        $this->tenantManager = new TenantManager($this->container, $this->isolationManager);
        $this->subscriptionManager = new SubscriptionManager($this->container);
        $this->billingService = new BillingService($this->container);
        $this->quotaManager = new QuotaManager($this->container);
        $this->domainManager = new DomainManager($this->container);
        $this->provisioningService = new ProvisioningService($this->container);
        $this->usageTracker = new UsageTracker($this->container);
        $this->backupService = new BackupService($this->container);
        
        // Set up tenant isolation strategy
        $this->isolationManager->initialize();
    }
    
    /**
     * Detect current tenant
     */
    public function detectTenant(): void
    {
        // Try to detect tenant from domain
        $domain = $_SERVER['HTTP_HOST'] ?? '';
        $tenant = $this->domainManager->getTenantByDomain($domain);
        
        // If not found by domain, check subdomain
        if (!$tenant && $this->isSubdomainSetup($domain)) {
            $subdomain = $this->extractSubdomain($domain);
            $tenant = $this->tenantManager->getBySubdomain($subdomain);
        }
        
        // Check session/cookie for tenant context
        if (!$tenant && isset($_SESSION['tenant_id'])) {
            $tenant = $this->tenantManager->getById($_SESSION['tenant_id']);
        }
        
        // Set current tenant
        if ($tenant && $tenant->isActive()) {
            $this->setCurrentTenant($tenant);
        }
    }
    
    /**
     * Set current tenant context
     */
    private function setCurrentTenant(Tenant $tenant): void
    {
        $this->currentTenant = $tenant;
        
        // Apply tenant context to database
        $this->isolationManager->setTenantContext($tenant);
        
        // Set tenant in container for dependency injection
        $this->container->instance('current_tenant', $tenant);
        
        // Update session
        $_SESSION['tenant_id'] = $tenant->id;
        
        // Trigger tenant switched event
        HookSystem::doAction('tenant.switched', $tenant);
    }
    
    /**
     * Filter database queries for tenant isolation
     */
    public function filterDatabaseQuery(string $query): string
    {
        if (!$this->currentTenant) {
            return $query;
        }
        
        return $this->isolationManager->filterQuery($query, $this->currentTenant);
    }
    
    /**
     * Filter database table names for tenant isolation
     */
    public function filterDatabaseTable(string $table): string
    {
        if (!$this->currentTenant) {
            return $table;
        }
        
        return $this->isolationManager->filterTable($table, $this->currentTenant);
    }
    
    /**
     * Register middleware
     */
    public function registerMiddleware(): void
    {
        // Register tenant middleware
        $tenantMiddleware = new TenantMiddleware($this->tenantManager, $this->quotaManager);
        
        // Apply to all API routes
        add_filter('rest_pre_dispatch', [$tenantMiddleware, 'handle'], 10, 3);
        
        // Apply to admin routes
        add_action('admin_init', [$tenantMiddleware, 'checkAdminAccess']);
    }
    
    /**
     * Process usage metering
     */
    public function processUsageMetering(): void
    {
        // Get all active tenants
        $tenants = $this->tenantManager->getActiveTenants();
        
        foreach ($tenants as $tenant) {
            // Set tenant context
            $this->setCurrentTenant($tenant);
            
            // Collect usage metrics
            $usage = [
                'api_calls' => $this->usageTracker->getApiCalls($tenant, 'hour'),
                'storage_bytes' => $this->usageTracker->getStorageUsage($tenant),
                'bandwidth_bytes' => $this->usageTracker->getBandwidthUsage($tenant, 'hour'),
                'active_users' => $this->usageTracker->getActiveUsers($tenant),
                'products' => $this->usageTracker->getProductCount($tenant),
                'orders' => $this->usageTracker->getOrderCount($tenant, 'hour')
            ];
            
            // Record usage
            $this->usageTracker->recordUsage($tenant, $usage);
            
            // Check for quota violations
            $violations = $this->quotaManager->checkQuotas($tenant, $usage);
            if (!empty($violations)) {
                $this->handleQuotaViolations($tenant, $violations);
            }
            
            // Update metered billing if applicable
            if ($this->getOption('enable_metered_billing', true)) {
                $this->billingService->updateMeteredUsage($tenant, $usage);
            }
        }
    }
    
    /**
     * Process subscription renewals
     */
    public function processSubscriptionRenewals(): void
    {
        // Get subscriptions due for renewal
        $dueSubscriptions = $this->subscriptionManager->getDueForRenewal();
        
        foreach ($dueSubscriptions as $subscription) {
            try {
                // Attempt to renew subscription
                $result = $this->billingService->renewSubscription($subscription);
                
                if ($result['success']) {
                    // Update subscription
                    $this->subscriptionManager->markRenewed($subscription);
                    
                    // Send renewal confirmation
                    $this->sendRenewalConfirmation($subscription);
                } else {
                    // Handle renewal failure
                    $this->handleRenewalFailure($subscription, $result['error']);
                }
            } catch (\RuntimeException $e) {
                $this->log('Subscription renewal failed: ' . $e->getMessage(), 'error');
                $this->handleRenewalFailure($subscription, $e->getMessage());
            }
        }
    }
    
    /**
     * Create tenant
     */
    public function createTenant(array $data): Tenant
    {
        DB::beginTransaction();
        
        try {
            // Create tenant record
            $tenant = $this->tenantManager->create($data);
            
            // Set up tenant isolation
            $this->isolationManager->createTenantEnvironment($tenant);
            
            // Provision resources
            $this->provisioningService->provisionTenant($tenant);
            
            // Create default subscription if trial enabled
            if ($this->getOption('trial_period_days', 14) > 0) {
                $this->createTrialSubscription($tenant);
            }
            
            // Set up custom domain if provided
            if (!empty($data['domain'])) {
                $this->domainManager->addDomain($tenant, $data['domain']);
            }
            
            // Initialize tenant data
            $this->initializeTenantData($tenant);
            
            DB::commit();
            
            // Trigger tenant created event
            HookSystem::doAction('tenant.created', $tenant);
            
            // Send welcome email
            $this->sendWelcomeEmail($tenant);
            
            return $tenant;
            
        } catch (\RuntimeException $e) {
            DB::rollBack();
            throw new \Exception('Failed to create tenant: ' . $e->getMessage());
        }
    }
    
    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        // Only show for super admin
        if (!current_user_can('saas.super_admin')) {
            return;
        }
        
        add_menu_page(
            'SaaS Manager',
            'SaaS Manager',
            'saas.view_tenants',
            'multi-tenant-saas',
            [$this, 'renderDashboard'],
            'dashicons-cloud',
            3
        );
        
        add_submenu_page(
            'multi-tenant-saas',
            'Tenants',
            'Tenants',
            'saas.view_tenants',
            'saas-tenants',
            [$this, 'renderTenants']
        );
        
        add_submenu_page(
            'multi-tenant-saas',
            'Subscriptions',
            'Subscriptions',
            'saas.manage_billing',
            'saas-subscriptions',
            [$this, 'renderSubscriptions']
        );
        
        add_submenu_page(
            'multi-tenant-saas',
            'Plans',
            'Plans',
            'saas.manage_plans',
            'saas-plans',
            [$this, 'renderPlans']
        );
        
        add_submenu_page(
            'multi-tenant-saas',
            'Billing',
            'Billing',
            'saas.manage_billing',
            'saas-billing',
            [$this, 'renderBilling']
        );
        
        add_submenu_page(
            'multi-tenant-saas',
            'Analytics',
            'Analytics',
            'saas.view_analytics',
            'saas-analytics',
            [$this, 'renderAnalytics']
        );
        
        add_submenu_page(
            'multi-tenant-saas',
            'Settings',
            'Settings',
            'saas.super_admin',
            'saas-settings',
            [$this, 'renderSettings']
        );
    }
    
    /**
     * Add tenant switcher to admin bar
     */
    public function addTenantSwitcher(\WP_Admin_Bar $adminBar): void
    {
        if (!current_user_can('saas.super_admin')) {
            return;
        }
        
        // Add main node
        $adminBar->add_node([
            'id' => 'tenant-switcher',
            'title' => $this->currentTenant ? $this->currentTenant->name : 'Select Tenant',
            'href' => '#',
            'meta' => [
                'class' => 'tenant-switcher-menu'
            ]
        ]);
        
        // Add tenant list
        $tenants = $this->tenantManager->getAllTenants();
        foreach ($tenants as $tenant) {
            $adminBar->add_node([
                'id' => 'tenant-' . $tenant->id,
                'parent' => 'tenant-switcher',
                'title' => $tenant->name . ($tenant->id === $this->currentTenant?->id ? ' ✓' : ''),
                'href' => add_query_arg('switch_tenant', $tenant->id, admin_url()),
                'meta' => [
                    'class' => $tenant->isActive() ? '' : 'inactive-tenant'
                ]
            ]);
        }
    }
    
    /**
     * Create default plans
     */
    private function createDefaultPlans(): void
    {
        $plans = $this->config['plans'] ?? [];
        
        foreach ($plans as $slug => $planData) {
            if (!Plan::where('slug', $slug)->exists()) {
                Plan::create([
                    'slug' => $slug,
                    'name' => $planData['name'],
                    'price' => $planData['price'],
                    'currency' => $planData['currency'],
                    'interval' => $planData['interval'],
                    'features' => json_encode($planData['features']),
                    'quotas' => json_encode($planData['quotas']),
                    'is_active' => true
                ]);
            }
        }
    }
    
    /**
     * Initialize tenant isolation
     */
    private function initializeTenantIsolation(): void
    {
        $isolationMethod = $this->getOption('tenant_isolation', 'schema');
        
        switch ($isolationMethod) {
            case 'database':
                // Ensure separate database connections can be created
                $this->validateDatabaseIsolation();
                break;
                
            case 'schema':
                // Ensure PostgreSQL schemas are supported
                $this->validateSchemaIsolation();
                break;
                
            case 'row':
                // Add tenant_id columns to all tables
                $this->addTenantColumns();
                break;
                
            case 'hybrid':
                // Combine approaches as needed
                $this->setupHybridIsolation();
                break;
        }
    }
    
    /**
     * Create required directories
     */
    private function createDirectories(): void
    {
        $dirs = [
            $this->getPluginPath() . '/backups',
            $this->getPluginPath() . '/exports',
            $this->getPluginPath() . '/reports',
            $this->getPluginPath() . '/tenant-data'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
        }
    }

    /**
     * Register Services
     */
    protected function registerServices(): void
    {
        // TODO: Implement registerServices
    }

    /**
     * Register EventListeners
     */
    protected function registerEventListeners(): void
    {
        // TODO: Implement registerEventListeners
    }

    /**
     * Register Routes
     */
    protected function registerRoutes(): void
    {
        // TODO: Implement registerRoutes
    }

    /**
     * Register Permissions
     */
    protected function registerPermissions(): void
    {
        // TODO: Implement registerPermissions
    }

    /**
     * Register ScheduledJobs
     */
    protected function registerScheduledJobs(): void
    {
        // TODO: Implement registerScheduledJobs
    }
}