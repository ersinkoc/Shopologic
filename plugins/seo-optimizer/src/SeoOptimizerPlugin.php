<?php
declare(strict_types=1);

namespace Shopologic\Plugins\SeoOptimizer;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\PluginInterface;
use Shopologic\Core\Hook\HookSystem;
use SeoOptimizer\Services\SeoService;
use SeoOptimizer\Services\SitemapService;
use SeoOptimizer\Services\SchemaService;
use SeoOptimizer\Services\AnalysisService;

/**
 * SEO Optimizer Plugin
 * 
 * Comprehensive SEO optimization with meta tags, sitemaps, schema markup,
 * content analysis, and performance monitoring
 */
class SeoOptimizerPlugin extends AbstractPlugin implements PluginInterface
{
    protected string $name = 'seo-optimizer';
    protected string $version = '1.0.0';
    
    public function install(): bool
    {
        $this->runMigrations();
        $this->setDefaultConfig();
        return true;
    }
    
    public function activate(): bool
    {
        $this->initializeSeo();
        $this->generateSitemaps();
        return true;
    }
    
    public function deactivate(): bool
    {
        // SEO remains active even when plugin is deactivated
        return true;
    }
    
    public function uninstall(): bool
    {
        if ($this->confirmDataRemoval()) {
            $this->dropTables();
            $this->removeConfig();
            $this->removeSitemaps();
        }
        return true;
    }
    
    public function update(string $previousVersion): bool
    {
        $this->runUpdateMigrations($previousVersion);
        return true;
    }
    
    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->registerCronJobs();
        $this->registerWidgets();
    }
    
    protected function registerServices(): void
    {
        $this->container->singleton(SeoService::class, function ($container) {
            return new SeoService(
                $container->get('db'),
                $this->getConfig()
            );
        });
        
        $this->container->singleton(SitemapService::class, function ($container) {
            return new SitemapService(
                $container->get('db'),
                $this->getConfig('sitemap_path', 'public/sitemap.xml')
            );
        });
        
        $this->container->singleton(SchemaService::class, function ($container) {
            return new SchemaService(
                $this->getConfig('enable_schema_markup', true)
            );
        });
        
        $this->container->singleton(AnalysisService::class, function ($container) {
            return new AnalysisService(
                $container->get('db'),
                $this->getConfig()
            );
        });
    }
    
    protected function registerHooks(): void
    {
        // Meta tags and SEO
        HookSystem::addAction('page.head', [$this, 'injectMetaTags'], 1);
        HookSystem::addAction('page.head', [$this, 'injectSchemaMarkup'], 2);
        HookSystem::addFilter('page.title', [$this, 'optimizeTitle'], 10);
        HookSystem::addFilter('page.description', [$this, 'optimizeDescription'], 10);
        
        // Content optimization
        HookSystem::addFilter('product.content', [$this, 'optimizeProductContent'], 10);
        HookSystem::addFilter('category.content', [$this, 'optimizeCategoryContent'], 10);
        
        // Sitemap generation
        HookSystem::addAction('product.created', [$this, 'updateSitemap'], 10);
        HookSystem::addAction('product.updated', [$this, 'updateSitemap'], 10);
        HookSystem::addAction('category.created', [$this, 'updateSitemap'], 10);
        
        // Admin integration
        HookSystem::addAction('admin.product.form', [$this, 'addSeoFields'], 20);
        HookSystem::addAction('admin.category.form', [$this, 'addSeoFields'], 20);
    }
    
    protected function registerRoutes(): void
    {
        $this->registerRoute('GET', '/sitemap.xml', 
            'SeoOptimizer\\Controllers\\SitemapController@serve');
        $this->registerRoute('GET', '/robots.txt', 
            'SeoOptimizer\\Controllers\\RobotsController@serve');
        
        $this->registerRoute('GET', '/api/v1/seo/analyze', 
            'SeoOptimizer\\Controllers\\AnalysisController@analyzePage');
        $this->registerRoute('POST', '/api/v1/seo/optimize', 
            'SeoOptimizer\\Controllers\\OptimizationController@optimizeContent');
    }
    
    protected function registerCronJobs(): void
    {
        $this->scheduleJob('0 2 * * *', [$this, 'generateSitemaps']);
        $this->scheduleJob('0 3 * * *', [$this, 'analyzeContent']);
        $this->scheduleJob('0 4 * * SUN', [$this, 'generateSeoReport']);
    }
    
    protected function registerWidgets(): void
    {
        $this->registerWidget('seo_score', Widgets\SeoScoreWidget::class);
        $this->registerWidget('keyword_rankings', Widgets\KeywordRankingsWidget::class);
        $this->registerWidget('sitemap_status', Widgets\SitemapStatusWidget::class);
    }
    
    public function injectMetaTags(): void
    {
        $seoService = $this->container->get(SeoService::class);
        $metaTags = $seoService->getMetaTags();
        
        foreach ($metaTags as $tag) {
            echo $tag . "\n";
        }
    }
    
    public function injectSchemaMarkup(): void
    {
        if (!$this->getConfig('enable_schema_markup', true)) {
            return;
        }
        
        $schemaService = $this->container->get(SchemaService::class);
        $schema = $schemaService->getPageSchema();
        
        if ($schema) {
            echo '<script type="application/ld+json">' . json_encode($schema) . '</script>' . "\n";
        }
    }
    
    public function optimizeTitle($title, array $data = []): string
    {
        $seoService = $this->container->get(SeoService::class);
        return $seoService->optimizeTitle($title, $data);
    }
    
    public function optimizeDescription($description, array $data = []): string
    {
        $seoService = $this->container->get(SeoService::class);
        return $seoService->optimizeDescription($description, $data);
    }
    
    public function optimizeProductContent($content, array $data): string
    {
        $product = $data['product'];
        $seoService = $this->container->get(SeoService::class);
        
        return $seoService->optimizeProductContent($content, $product);
    }
    
    public function updateSitemap(): void
    {
        $sitemapService = $this->container->get(SitemapService::class);
        $sitemapService->regenerate();
    }
    
    public function addSeoFields(): void
    {
        echo $this->render('admin/seo-fields', [
            'config' => $this->getConfig()
        ]);
    }
    
    public function generateSitemaps(): void
    {
        $sitemapService = $this->container->get(SitemapService::class);
        $generated = $sitemapService->generateAll();
        
        $this->logger->info('Generated sitemaps', ['count' => $generated]);
    }
    
    public function analyzeContent(): void
    {
        $analysisService = $this->container->get(AnalysisService::class);
        $analyzed = $analysisService->analyzeAllContent();
        
        $this->logger->info('Analyzed content for SEO', ['pages' => $analyzed]);
    }
    
    public function generateSeoReport(): void
    {
        $analysisService = $this->container->get(AnalysisService::class);
        $report = $analysisService->generateWeeklyReport();
        
        $this->logger->info('Generated SEO report', ['issues' => $report['issues_count']]);
    }
    
    protected function initializeSeo(): void
    {
        $seoService = $this->container->get(SeoService::class);
        $seoService->initialize();
        
        // Generate robots.txt if it doesn't exist
        $this->generateRobotsTxt();
    }
    
    protected function generateRobotsTxt(): void
    {
        $robotsPath = 'public/robots.txt';
        
        if (!file_exists($robotsPath)) {
            $content = $this->generateRobotsContent();
            file_put_contents($robotsPath, $content);
        }
    }
    
    protected function generateRobotsContent(): string
    {
        $siteUrl = $this->getConfig('site_url', 'https://example.com');
        
        return "User-agent: *\n" .
               "Allow: /\n" .
               "Disallow: /admin/\n" .
               "Disallow: /api/\n" .
               "Sitemap: {$siteUrl}/sitemap.xml\n";
    }
    
    protected function removeSitemaps(): void
    {
        $files = ['public/sitemap.xml', 'public/robots.txt'];
        
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
    
    protected function runMigrations(): void
    {
        $migrations = [
            'create_seo_metadata_table.php',
            'create_seo_redirects_table.php',
            'create_seo_analysis_table.php'
        ];
        
        foreach ($migrations as $migration) {
            $this->api->runMigration($this->getPath('migrations/' . $migration));
        }
    }
    
    protected function setDefaultConfig(): void
    {
        $defaults = [
            'enable_schema_markup' => true,
            'enable_open_graph' => true,
            'enable_twitter_cards' => true,
            'sitemap_path' => 'public/sitemap.xml',
            'auto_generate_meta' => true,
            'optimize_images' => true,
            'enable_breadcrumbs' => true,
            'canonical_urls' => true,
            'noindex_admin' => true,
            'compress_html' => false
        ];
        
        foreach ($defaults as $key => $value) {
            if ($this->getConfig($key) === null) {
                $this->setConfig($key, $value);
            }
        }
    }

    /**
     * Register EventListeners
     */
    protected function registerEventListeners(): void
    {
        // TODO: Implement registerEventListeners
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