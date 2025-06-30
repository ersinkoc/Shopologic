<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AiContentGenerator;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\HookSystem;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use AiContentGenerator\Services\{
    AIModelManager,
    ContentGenerator,
    PromptEngine,
    QualityChecker,
    SEOOptimizer,
    TranslationService,
    BrandVoiceTrainer,;
    ContentCache,;
    UsageTracker;
};
use AiContentGenerator\Models\{
    GeneratedContent,;
    ContentTemplate,;
    AIModel;
};

class AiContentGeneratorPlugin extends AbstractPlugin
{
    private AIModelManager $modelManager;
    private ContentGenerator $contentGenerator;
    private PromptEngine $promptEngine;
    private QualityChecker $qualityChecker;
    private SEOOptimizer $seoOptimizer;
    private TranslationService $translationService;
    private BrandVoiceTrainer $brandVoiceTrainer;
    private ContentCache $contentCache;
    private UsageTracker $usageTracker;
    
    /**
     * Plugin activation
     */
    public function activate(): void
    {
        // Run migrations
        $this->runMigrations();
        
        // Install default templates
        $this->installDefaultTemplates();
        
        // Initialize AI models
        $this->initializeAIModels();
        
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
        // Cancel any pending generation jobs
        $this->cancelPendingJobs();
        
        // Save usage statistics
        $this->saveUsageStatistics();
        
        // Clear temporary cache
        $this->clearTempCache();
    }
    
    /**
     * Register hooks
     */
    protected function registerHooks(): void
    {
        // Initialize services
        HookSystem::addAction('init', [$this, 'initializeServices']);
        
        // Admin interface
        HookSystem::addAction('admin_menu', [$this, 'registerAdminMenu']);
        HookSystem::addAction('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        
        // Editor integration
        HookSystem::addAction('enqueue_block_editor_assets', [$this, 'enqueueEditorAssets']);
        HookSystem::addFilter('mce_buttons', [$this, 'addTinyMCEButtons']);
        HookSystem::addFilter('mce_external_plugins', [$this, 'addTinyMCEPlugins']);
        
        // Content generation hooks
        HookSystem::addAction('wp_ajax_ai_generate_content', [$this, 'handleAjaxGenerate']);
        HookSystem::addAction('wp_ajax_ai_improve_content', [$this, 'handleAjaxImprove']);
        HookSystem::addAction('wp_ajax_ai_suggest_content', [$this, 'handleAjaxSuggest']);
        
        // Product description automation
        HookSystem::addAction('product.created', [$this, 'autoGenerateProductDescription']);
        HookSystem::addFilter('product.save_data', [$this, 'enhanceProductData']);
        
        // SEO integration
        HookSystem::addFilter('seo.meta_description', [$this, 'generateMetaDescription']);
        HookSystem::addFilter('seo.title', [$this, 'optimizeTitle']);
        
        // API endpoints
        $this->registerApiEndpoints();
        
        // Scheduled tasks
        HookSystem::addAction('ai_content_update_models', [$this, 'updateModelCapabilities']);
        HookSystem::addAction('ai_content_cleanup', [$this, 'cleanupCache']);
        HookSystem::addAction('ai_content_fine_tune', [$this, 'processFineTuning']);
        
        // Quality control hooks
        HookSystem::addFilter('content.before_save', [$this, 'checkContentQuality']);
        HookSystem::addFilter('content.before_publish', [$this, 'finalQualityCheck']);
    }
    
    /**
     * Initialize services
     */
    public function initializeServices(): void
    {
        // Initialize core services
        $this->modelManager = new AIModelManager($this->container, $this->getApiKeys());
        $this->promptEngine = new PromptEngine($this->container);
        $this->contentGenerator = new ContentGenerator($this->modelManager, $this->promptEngine);
        $this->qualityChecker = new QualityChecker($this->container);
        $this->seoOptimizer = new SEOOptimizer($this->container);
        $this->translationService = new TranslationService($this->modelManager);
        $this->brandVoiceTrainer = new BrandVoiceTrainer($this->container);
        $this->contentCache = new ContentCache($this->container);
        $this->usageTracker = new UsageTracker($this->container);
        
        // Load custom models if any
        $this->loadCustomModels();
        
        // Initialize brand voice if enabled
        if ($this->getOption('enable_brand_voice', true)) {
            $this->brandVoiceTrainer->initialize();
        }
    }
    
    /**
     * Handle AJAX content generation
     */
    public function handleAjaxGenerate(): void
    {
        // Verify nonce
        if (!check_ajax_referer('ai-content-nonce', 'nonce', false)) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('ai_content.generate')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }
        
        // Check rate limits
        if (!$this->checkRateLimits()) {
            wp_send_json_error(['message' => 'Rate limit exceeded']);
            return;
        }
        
        // Get generation parameters
        $params = [
            'type' => sanitize_text_field($request->input('type'] ?? 'general'),
            'prompt' => sanitize_textarea_field($request->input('prompt'] ?? ''),
            'template' => sanitize_text_field($request->input('template'] ?? ''),
            'variables' => $request->input('variables'] ?? [],
            'options' => $request->input('options'] ?? [],
            'language' => sanitize_text_field($request->input('language'] ?? 'en'),
            'tone' => sanitize_text_field($request->input('tone'] ?? 'professional')
        ];
        
        try {
            // Generate content
            $result = $this->generateContent($params);
            
            if ($result['success']) {
                wp_send_json_success([
                    'content' => $result['content'],
                    'metadata' => $result['metadata'],
                    'quality_score' => $result['quality_score'],
                    'seo_analysis' => $result['seo_analysis']
                ]);
            } else {
                wp_send_json_error(['message' => $result['error']]);
            }
        } catch (\RuntimeException $e) {
            wp_send_json_error(['message' => 'Generation failed: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Generate content
     */
    private function generateContent(array $params): array
    {
        // Check cache first
        $cacheKey = $this->generateCacheKey($params);
        $cached = $this->contentCache->get($cacheKey);
        
        if ($cached && !isset($params['options']['skip_cache'])) {
            return $cached;
        }
        
        // Prepare prompt
        if (!empty($params['template'])) {
            $prompt = $this->promptEngine->buildFromTemplate($params['template'], $params['variables']);
        } else {
            $prompt = $this->promptEngine->enhance($params['prompt'], $params);
        }
        
        // Apply brand voice if enabled
        if ($this->getOption('enable_brand_voice', true)) {
            $prompt = $this->brandVoiceTrainer->applyBrandVoice($prompt);
        }
        
        // Select model
        $model = $params['options']['model'] ?? $this->getOption('default_model', 'gpt-4');
        
        // Generate content
        $startTime = microtime(true);
        $generated = $this->contentGenerator->generate($prompt, $model, $params['options']);
        $generationTime = microtime(true) - $startTime;
        
        // Post-process content
        $content = $this->postProcessContent($generated, $params);
        
        // Quality checks
        $qualityScore = $this->qualityChecker->analyze($content);
        
        // SEO optimization if enabled
        $seoAnalysis = null;
        if ($this->getOption('enable_auto_seo', true)) {
            $optimized = $this->seoOptimizer->optimize($content, $params['variables']['keywords'] ?? []);
            $content = $optimized['content'];
            $seoAnalysis = $optimized['analysis'];
        }
        
        // Translation if needed
        if ($params['language'] !== 'en') {
            $content = $this->translationService->translate($content, 'en', $params['language']);
        }
        
        // Save to history
        $this->saveGenerationHistory([
            'prompt' => $prompt,
            'content' => $content,
            'params' => $params,
            'model' => $model,
            'quality_score' => $qualityScore,
            'seo_analysis' => $seoAnalysis,
            'generation_time' => $generationTime
        ]);
        
        // Update usage statistics
        $this->usageTracker->track('generation', [
            'model' => $model,
            'type' => $params['type'],
            'tokens' => $generated['usage']['total_tokens'] ?? 0
        ]);
        
        // Cache result
        $result = [
            'success' => true,
            'content' => $content,
            'metadata' => [
                'model' => $model,
                'generation_time' => $generationTime,
                'tokens_used' => $generated['usage']['total_tokens'] ?? 0
            ],
            'quality_score' => $qualityScore,
            'seo_analysis' => $seoAnalysis
        ];
        
        $this->contentCache->set($cacheKey, $result, $this->getOption('cache_duration', 24) * 3600);
        
        return $result;
    }
    
    /**
     * Auto-generate product description
     */
    public function autoGenerateProductDescription($productId): void
    {
        if (!$this->getOption('auto_generate_products', true)) {
            return;
        }
        
        $product = $this->getProduct($productId);
        if (!$product || !empty($product->description)) {
            return;
        }
        
        // Prepare product data
        $variables = [
            'product_name' => $product->name,
            'category' => $product->category->name ?? '',
            'features' => implode(', ', $product->features ?? []),
            'price' => $product->price,
            'brand' => $product->brand ?? '',
            'target_audience' => $this->inferTargetAudience($product)
        ];
        
        // Generate description
        $result = $this->generateContent([
            'type' => 'product_description',
            'template' => 'product_description',
            'variables' => $variables,
            'options' => [
                'max_length' => 300,
                'include_benefits' => true,
                'seo_keywords' => $this->extractProductKeywords($product)
            ]
        ]);
        
        if ($result['success']) {
            // Update product
            $product->description = $result['content'];
            $product->meta_description = $this->generateMetaFromContent($result['content']);
            $product->save();
            
            // Log generation
            $this->log('Auto-generated description for product: ' . $product->name);
        }
    }
    
    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        add_menu_page(
            'AI Content Generator',
            'AI Content',
            'ai_content.access',
            'ai-content-generator',
            [$this, 'renderDashboard'],
            'dashicons-edit-large',
            30
        );
        
        add_submenu_page(
            'ai-content-generator',
            'Generate Content',
            'Generate',
            'ai_content.generate',
            'ai-content-generate',
            [$this, 'renderGenerator']
        );
        
        add_submenu_page(
            'ai-content-generator',
            'Templates',
            'Templates',
            'ai_content.access',
            'ai-content-templates',
            [$this, 'renderTemplates']
        );
        
        add_submenu_page(
            'ai-content-generator',
            'History',
            'History',
            'ai_content.view_history',
            'ai-content-history',
            [$this, 'renderHistory']
        );
        
        add_submenu_page(
            'ai-content-generator',
            'Brand Voice',
            'Brand Voice',
            'ai_content.manage_models',
            'ai-content-brand-voice',
            [$this, 'renderBrandVoice']
        );
        
        add_submenu_page(
            'ai-content-generator',
            'Settings',
            'Settings',
            'ai_content.manage_models',
            'ai-content-settings',
            [$this, 'renderSettings']
        );
    }
    
    /**
     * Install default templates
     */
    private function installDefaultTemplates(): void
    {
        $templates = $this->config['templates'] ?? [];
        
        foreach ($templates as $slug => $template) {
            ContentTemplate::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $template['name'],
                    'prompt' => $template['prompt'],
                    'variables' => json_encode($template['variables']),
                    'settings' => json_encode($template['settings']),
                    'is_active' => true
                ]
            );
        }
    }
    
    /**
     * Initialize AI models
     */
    private function initializeAIModels(): void
    {
        $models = [
            [
                'slug' => 'gpt-4',
                'name' => 'GPT-4',
                'provider' => 'openai',
                'capabilities' => ['text', 'chat', 'completion'],
                'max_tokens' => 8192,
                'is_active' => true
            ],
            [
                'slug' => 'gpt-3.5-turbo',
                'name' => 'GPT-3.5 Turbo',
                'provider' => 'openai',
                'capabilities' => ['text', 'chat', 'completion'],
                'max_tokens' => 4096,
                'is_active' => true
            ],
            [
                'slug' => 'claude-3-opus',
                'name' => 'Claude 3 Opus',
                'provider' => 'anthropic',
                'capabilities' => ['text', 'chat', 'analysis'],
                'max_tokens' => 200000,
                'is_active' => true
            ]
        ];
        
        foreach ($models as $model) {
            AIModel::firstOrCreate(
                ['slug' => $model['slug']],
                $model
            );
        }
    }
    
    /**
     * Get API keys
     */
    private function getApiKeys(): array
    {
        $keys = $this->getOption('api_keys', []);
        return [
            'openai' => $keys['openai'] ?? '',
            'anthropic' => $keys['anthropic'] ?? '',
            'custom' => $keys['custom'] ?? ''
        ];
    }
    
    /**
     * Create required directories
     */
    private function createDirectories(): void
    {
        $dirs = [
            $this->getPluginPath() . '/cache',
            $this->getPluginPath() . '/exports',
            $this->getPluginPath() . '/training-data',
            $this->getPluginPath() . '/logs'
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