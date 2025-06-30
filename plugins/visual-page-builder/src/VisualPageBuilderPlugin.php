<?php

declare(strict_types=1);

namespace Shopologic\Plugins\VisualPageBuilder;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\HookSystem;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use VisualPageBuilder\Services\{
    BlockRegistry,
    PageRenderer,
    TemplateManager,
    AssetOptimizer,
    AILayoutEngine,;
    VersionControl;
};
use VisualPageBuilder\Components\BlockFactory;

class VisualPageBuilderPlugin extends AbstractPlugin
{
    private BlockRegistry $blockRegistry;
    private PageRenderer $pageRenderer;
    private TemplateManager $templateManager;
    private AssetOptimizer $assetOptimizer;
    private AILayoutEngine $aiEngine;
    private VersionControl $versionControl;
    
    /**
     * Plugin activation
     */
    public function activate(): void
    {
        // Run migrations
        $this->runMigrations();
        
        // Register default blocks
        $this->registerDefaultBlocks();
        
        // Install default templates
        $this->installDefaultTemplates();
        
        // Set default options
        $this->setDefaultOptions();
        
        // Create required directories
        $this->createDirectories();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        // Clear caches
        $this->clearCaches();
        
        // Cleanup temporary files
        $this->cleanupTempFiles();
    }
    
    /**
     * Register hooks
     */
    protected function registerHooks(): void
    {
        // Core page builder functionality
        HookSystem::addAction('init', [$this, 'initializePageBuilder']);
        HookSystem::addFilter('content_render', [$this, 'renderPageBuilderContent'], 10);
        
        // Admin interface
        HookSystem::addAction('admin_menu', [$this, 'registerAdminMenu']);
        HookSystem::addAction('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        
        // Frontend rendering
        HookSystem::addAction('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
        HookSystem::addFilter('the_content', [$this, 'processPageContent'], 5);
        
        // Block registration
        HookSystem::addAction('pagebuilder_register_blocks', [$this, 'registerCustomBlocks']);
        
        // API endpoints
        $this->registerApiEndpoints();
        
        // AJAX handlers
        HookSystem::addAction('wp_ajax_pagebuilder_save', [$this, 'handleAjaxSave']);
        HookSystem::addAction('wp_ajax_pagebuilder_preview', [$this, 'handleAjaxPreview']);
        
        // Template hooks
        HookSystem::addFilter('pagebuilder_templates', [$this, 'loadTemplates']);
        
        // Asset optimization
        HookSystem::addFilter('pagebuilder_assets', [$this, 'optimizeAssets']);
        
        // AI suggestions
        if ($this->getOption('enable_ai_suggestions', true)) {
            HookSystem::addAction('pagebuilder_ai_analyze', [$this, 'analyzeContentForAI']);
        }
        
        // Version control
        if ($this->getOption('enable_versioning', true)) {
            HookSystem::addAction('pagebuilder_save_page', [$this, 'createVersion']);
        }
    }
    
    /**
     * Initialize page builder services
     */
    public function initializePageBuilder(): void
    {
        // Initialize services
        $this->blockRegistry = new BlockRegistry($this->container);
        $this->pageRenderer = new PageRenderer($this->blockRegistry);
        $this->templateManager = new TemplateManager($this->getPluginPath() . '/templates');
        $this->assetOptimizer = new AssetOptimizer($this->getPluginPath() . '/assets');
        $this->aiEngine = new AILayoutEngine($this->container);
        $this->versionControl = new VersionControl($this->container);
        
        // Register block types
        $this->registerCoreBlocks();
        
        // Load user templates
        $this->loadUserTemplates();
        
        // Initialize autosave
        if ($this->getOption('autosave_interval', 30) > 0) {
            $this->initializeAutosave();
        }
    }
    
    /**
     * Register core block types
     */
    private function registerCoreBlocks(): void
    {
        $blocks = $this->getBlockDefinitions();
        
        foreach ($blocks as $blockId => $blockConfig) {
            $this->blockRegistry->register($blockId, $blockConfig);
        }
        
        // Allow third-party block registration
        HookSystem::doAction('pagebuilder_register_blocks', $this->blockRegistry);
    }
    
    /**
     * Render page builder content
     */
    public function renderPageBuilderContent(string $content, array $context = []): string
    {
        // Check if content contains page builder data
        if (!$this->isPageBuilderContent($content)) {
            return $content;
        }
        
        // Parse page builder data
        $pageData = $this->parsePageData($content);
        
        // Apply responsive settings
        $pageData = $this->applyResponsiveSettings($pageData);
        
        // Render blocks
        $rendered = $this->pageRenderer->render($pageData, $context);
        
        // Optimize output
        if ($this->getOption('enable_optimization', true)) {
            $rendered = $this->assetOptimizer->optimizeHtml($rendered);
        }
        
        // Add wrapper
        return $this->wrapContent($rendered, $pageData);
    }
    
    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        add_menu_page(
            'Page Builder',
            'Page Builder',
            'pagebuilder.access',
            'visual-page-builder',
            [$this, 'renderAdminInterface'],
            'dashicons-layout',
            25
        );
        
        add_submenu_page(
            'visual-page-builder',
            'Templates',
            'Templates',
            'pagebuilder.manage_templates',
            'pagebuilder-templates',
            [$this, 'renderTemplatesPage']
        );
        
        add_submenu_page(
            'visual-page-builder',
            'Blocks',
            'Blocks',
            'pagebuilder.manage_blocks',
            'pagebuilder-blocks',
            [$this, 'renderBlocksPage']
        );
        
        add_submenu_page(
            'visual-page-builder',
            'Settings',
            'Settings',
            'pagebuilder.access',
            'pagebuilder-settings',
            [$this, 'renderSettingsPage']
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets(string $hook): void
    {
        if (!$this->isPageBuilderScreen($hook)) {
            return;
        }
        
        // Core dependencies
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-resizable');
        wp_enqueue_media();
        
        // Vue.js for reactive UI
        wp_enqueue_script(
            'vue',
            'https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.prod.js',
            [],
            '3.0.0'
        );
        
        // Page builder scripts
        wp_enqueue_script(
            'pagebuilder-admin',
            $this->getAssetUrl('js/pagebuilder.js'),
            ['jquery', 'vue'],
            $this->getVersion(),
            true
        );
        
        // Localize script
        wp_localize_script('pagebuilder-admin', 'pagebuilderData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'apiUrl' => rest_url('pagebuilder/v1'),
            'nonce' => wp_create_nonce('pagebuilder-nonce'),
            'blocks' => $this->blockRegistry->getAllBlocks(),
            'templates' => $this->templateManager->getTemplates(),
            'settings' => $this->getSettings(),
            'i18n' => $this->getTranslations()
        ]);
        
        // Styles
        wp_enqueue_style(
            'pagebuilder-admin',
            $this->getAssetUrl('css/pagebuilder.css'),
            [],
            $this->getVersion()
        );
        
        // Code editor for advanced users
        if ($this->getOption('enable_code_editor', true)) {
            wp_enqueue_code_editor(['type' => 'text/html']);
            wp_enqueue_script('csslint');
            wp_enqueue_script('jshint');
        }
    }
    
    /**
     * Handle AJAX save request
     */
    public function handleAjaxSave(): void
    {
        // Verify nonce
        if (!check_ajax_referer('pagebuilder-nonce', 'nonce', false)) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('pagebuilder.edit_pages')) {
            wp_die('Insufficient permissions');
        }
        
        // Get page data
        $pageId = intval($request->input('page_id'] ?? 0);
        $pageData = json_decode(stripslashes($request->input('page_data'] ?? '{}'), true);
        
        // Validate data
        $validation = $this->validatePageData($pageData);
        if (!$validation['valid']) {
            wp_send_json_error(['message' => $validation['error']]);
            return;
        }
        
        // Create version if enabled
        if ($this->getOption('enable_versioning', true)) {
            $this->versionControl->createVersion($pageId, $pageData);
        }
        
        // Save page
        $result = $this->savePage($pageId, $pageData);
        
        if ($result) {
            // Clear caches
            $this->clearPageCache($pageId);
            
            // Trigger save hook
            HookSystem::doAction('pagebuilder_page_saved', $pageId, $pageData);
            
            wp_send_json_success([
                'message' => 'Page saved successfully',
                'page_id' => $pageId,
                'preview_url' => $this->getPreviewUrl($pageId)
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to save page']);
        }
    }
    
    /**
     * Get block definitions
     */
    private function getBlockDefinitions(): array
    {
        return [
            'heading' => [
                'name' => 'Heading',
                'category' => 'Basic',
                'icon' => 'dashicons-heading',
                'component' => 'Blocks\\HeadingBlock',
                'settings' => [
                    'text' => ['type' => 'text', 'default' => 'Heading'],
                    'level' => ['type' => 'select', 'options' => ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], 'default' => 'h2'],
                    'alignment' => ['type' => 'select', 'options' => ['left', 'center', 'right'], 'default' => 'left']
                ]
            ],
            'text' => [
                'name' => 'Text',
                'category' => 'Basic',
                'icon' => 'dashicons-text',
                'component' => 'Blocks\\TextBlock',
                'settings' => [
                    'content' => ['type' => 'richtext', 'default' => ''],
                    'columns' => ['type' => 'number', 'min' => 1, 'max' => 4, 'default' => 1]
                ]
            ],
            'image' => [
                'name' => 'Image',
                'category' => 'Media',
                'icon' => 'dashicons-format-image',
                'component' => 'Blocks\\ImageBlock',
                'settings' => [
                    'src' => ['type' => 'media', 'default' => ''],
                    'alt' => ['type' => 'text', 'default' => ''],
                    'caption' => ['type' => 'text', 'default' => ''],
                    'link' => ['type' => 'url', 'default' => ''],
                    'lightbox' => ['type' => 'toggle', 'default' => false]
                ]
            ],
            'columns' => [
                'name' => 'Columns',
                'category' => 'Layout',
                'icon' => 'dashicons-columns',
                'component' => 'Blocks\\ColumnsBlock',
                'settings' => [
                    'columns' => ['type' => 'number', 'min' => 2, 'max' => 6, 'default' => 2],
                    'gap' => ['type' => 'select', 'options' => ['none', 'small', 'medium', 'large'], 'default' => 'medium'],
                    'stackOn' => ['type' => 'select', 'options' => ['mobile', 'tablet', 'never'], 'default' => 'mobile']
                ]
            ],
            'button' => [
                'name' => 'Button',
                'category' => 'Basic',
                'icon' => 'dashicons-button',
                'component' => 'Blocks\\ButtonBlock',
                'settings' => [
                    'text' => ['type' => 'text', 'default' => 'Click Me'],
                    'link' => ['type' => 'url', 'default' => '#'],
                    'style' => ['type' => 'select', 'options' => ['primary', 'secondary', 'outline', 'ghost'], 'default' => 'primary'],
                    'size' => ['type' => 'select', 'options' => ['small', 'medium', 'large'], 'default' => 'medium'],
                    'icon' => ['type' => 'icon', 'default' => ''],
                    'iconPosition' => ['type' => 'select', 'options' => ['left', 'right'], 'default' => 'left']
                ]
            ],
            'product-grid' => [
                'name' => 'Product Grid',
                'category' => 'Commerce',
                'icon' => 'dashicons-grid-view',
                'component' => 'Blocks\\ProductGridBlock',
                'settings' => [
                    'products' => ['type' => 'products', 'default' => []],
                    'columns' => ['type' => 'number', 'min' => 2, 'max' => 6, 'default' => 4],
                    'rows' => ['type' => 'number', 'min' => 1, 'max' => 10, 'default' => 2],
                    'showPrice' => ['type' => 'toggle', 'default' => true],
                    'showAddToCart' => ['type' => 'toggle', 'default' => true],
                    'showQuickView' => ['type' => 'toggle', 'default' => false]
                ]
            ]
            // Additional blocks would be defined here...
        ];
    }
    
    /**
     * Create required directories
     */
    private function createDirectories(): void
    {
        $dirs = [
            $this->getPluginPath() . '/cache',
            $this->getPluginPath() . '/cache/pages',
            $this->getPluginPath() . '/cache/assets',
            $this->getPluginPath() . '/exports',
            $this->getPluginPath() . '/user-templates',
            $this->getPluginPath() . '/user-blocks'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
        }
    }
    
    /**
     * Install default templates
     */
    private function installDefaultTemplates(): void
    {
        $templates = [
            'hero-section' => [
                'name' => 'Hero Section',
                'category' => 'Landing Pages',
                'thumbnail' => 'hero-section.jpg',
                'data' => $this->getDefaultTemplateData('hero-section')
            ],
            'features-grid' => [
                'name' => 'Features Grid',
                'category' => 'Landing Pages',
                'thumbnail' => 'features-grid.jpg',
                'data' => $this->getDefaultTemplateData('features-grid')
            ],
            'testimonials' => [
                'name' => 'Testimonials',
                'category' => 'Commerce',
                'thumbnail' => 'testimonials.jpg',
                'data' => $this->getDefaultTemplateData('testimonials')
            ],
            'pricing-table' => [
                'name' => 'Pricing Table',
                'category' => 'Commerce',
                'thumbnail' => 'pricing-table.jpg',
                'data' => $this->getDefaultTemplateData('pricing-table')
            ],
            'contact-form' => [
                'name' => 'Contact Form',
                'category' => 'Contact Pages',
                'thumbnail' => 'contact-form.jpg',
                'data' => $this->getDefaultTemplateData('contact-form')
            ]
        ];
        
        foreach ($templates as $templateId => $template) {
            $this->templateManager->installTemplate($templateId, $template);
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