<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme;

use Shopologic\Core\Cache\CacheInterface;
use Shopologic\Core\Hook\HookSystem;
use Shopologic\Core\Theme\Compiler\TemplateCompiler;
use Shopologic\Core\Theme\Loader\TemplateLoader;
use Shopologic\Core\Theme\Extension\ExtensionInterface;

/**
 * Twig-like template engine implementation
 */
class TemplateEngine
{
    private TemplateLoader $loader;
    private TemplateCompiler $compiler;
    private CacheInterface $cache;
    private array $globals = [];
    private array $filters = [];
    private array $functions = [];
    private array $extensions = [];
    private array $currentContext = [];
    private bool $debug = false;
    private bool $autoReload = true;
    private ?Component\ComponentManager $componentManager = null;

    public function __construct(
        TemplateLoader $loader,
        TemplateCompiler $compiler,
        CacheInterface $cache,
        bool $debug = false
    ) {
        $this->loader = $loader;
        $this->compiler = $compiler;
        $this->cache = $cache;
        $this->debug = $debug;
        
        $this->registerDefaultExtensions();
    }

    /**
     * Render a template
     */
    public function render(string $template, array $context = []): string
    {
        try {
            // Merge contexts
            $context = array_merge($this->globals, $context);
            $this->currentContext = $context;

            // Get compiled template
            $compiledTemplate = $this->getCompiledTemplate($template);

            // Create sandbox environment
            $sandbox = new TemplateSandbox($this, $context);
            
            // Execute template
            ob_start();
            $sandbox->execute($compiledTemplate);
            $output = ob_get_clean();

            // Apply output filters
            $output = $this->applyOutputFilters($output);

            // Allow plugins to modify output
            $output = HookSystem::applyFilter('template.render.output', $output, [
                'template' => $template,
                'context' => $context
            ]);

            return $output;

        } catch (\Exception $e) {
            if ($this->debug) {
                throw new TemplateException(
                    sprintf('Error rendering template "%s": %s', $template, $e->getMessage()),
                    0,
                    $e
                );
            }
            
            // In production, return error placeholder
            return '<!-- Template Error -->';
        } finally {
            $this->currentContext = [];
        }
    }

    /**
     * Render a string template
     */
    public function renderString(string $source, array $context = []): string
    {
        $template = '__string_template_' . md5($source);
        $this->loader->setStringTemplate($template, $source);
        
        return $this->render($template, $context);
    }

    /**
     * Check if template exists
     */
    public function exists(string $template): bool
    {
        return $this->loader->exists($template);
    }

    /**
     * Add a global variable
     */
    public function addGlobal(string $name, $value): void
    {
        $this->globals[$name] = $value;
    }

    /**
     * Add a filter
     */
    public function addFilter(string $name, callable $filter): void
    {
        $this->filters[$name] = $filter;
    }

    /**
     * Add a function
     */
    public function addFunction(string $name, callable $function): void
    {
        $this->functions[$name] = $function;
    }

    /**
     * Add an extension
     */
    public function addExtension(ExtensionInterface $extension): void
    {
        $this->extensions[get_class($extension)] = $extension;
        
        // Register extension filters
        foreach ($extension->getFilters() as $name => $filter) {
            $this->addFilter($name, $filter);
        }
        
        // Register extension functions
        foreach ($extension->getFunctions() as $name => $function) {
            $this->addFunction($name, $function);
        }
        
        // Register extension globals
        foreach ($extension->getGlobals() as $name => $value) {
            $this->addGlobal($name, $value);
        }
    }

    /**
     * Get a filter
     */
    public function getFilter(string $name): ?callable
    {
        return $this->filters[$name] ?? null;
    }

    /**
     * Get a function
     */
    public function getFunction(string $name): ?callable
    {
        return $this->functions[$name] ?? null;
    }

    /**
     * Include another template
     */
    public function includeTemplate(string $template, array $variables = []): string
    {
        $context = array_merge($this->currentContext, $variables);
        return $this->render($template, $context);
    }

    /**
     * Extend a parent template
     */
    public function extendTemplate(string $parent): void
    {
        $this->loader->setParentTemplate($parent);
    }

    /**
     * Start a block
     */
    public function startBlock(string $name): void
    {
        ob_start();
    }

    /**
     * End a block
     */
    public function endBlock(string $name): void
    {
        $content = ob_get_clean();
        $this->loader->setBlock($name, $content);
    }

    /**
     * Output a block
     */
    public function block(string $name, string $default = ''): string
    {
        return $this->loader->getBlock($name) ?? $default;
    }

    /**
     * Get compiled template
     */
    private function getCompiledTemplate(string $template): string
    {
        $cacheKey = 'template_' . md5($template);
        
        // Check cache if not in debug mode
        if (!$this->debug && !$this->autoReload) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Check if recompilation is needed
        if ($this->autoReload && $this->cache->has($cacheKey)) {
            $sourceTime = $this->loader->getLastModified($template);
            $cacheTime = $this->cache->getModifiedTime($cacheKey);
            
            if ($sourceTime <= $cacheTime) {
                return $this->cache->get($cacheKey);
            }
        }

        // Load template source
        $source = $this->loader->getSource($template);
        
        // Compile template
        $compiled = $this->compiler->compile($source, $template);
        
        // Cache compiled template
        if (!$this->debug) {
            $this->cache->set($cacheKey, $compiled, 3600 * 24); // Cache for 24 hours
        }
        
        return $compiled;
    }

    /**
     * Apply output filters
     */
    private function applyOutputFilters(string $output): string
    {
        // Minify HTML in production
        if (!$this->debug) {
            $output = preg_replace('/\s+/', ' ', $output);
            $output = preg_replace('/>\s+</', '><', $output);
        }
        
        return trim($output);
    }

    /**
     * Register default extensions
     */
    private function registerDefaultExtensions(): void
    {
        // Note: Extensions that require dependencies will be added by the service provider
    }

    /**
     * Set debug mode
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * Set auto reload
     */
    public function setAutoReload(bool $autoReload): void
    {
        $this->autoReload = $autoReload;
    }

    /**
     * Clear template cache
     */
    public function clearCache(): void
    {
        $this->cache->flush();
    }

    /**
     * Set component manager
     */
    public function setComponentManager(Component\ComponentManager $componentManager): void
    {
        $this->componentManager = $componentManager;
    }

    /**
     * Get component manager
     */
    public function getComponentManager(): ?Component\ComponentManager
    {
        return $this->componentManager;
    }

    /**
     * Add template path
     */
    public function addPath(string $path, string $namespace = 'default'): void
    {
        $this->loader->addPath($path, $namespace);
    }
}