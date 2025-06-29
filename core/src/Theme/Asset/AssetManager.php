<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Asset;

use Shopologic\Core\Cache\CacheInterface;
use Shopologic\Core\Events\EventDispatcherInterface;
use Shopologic\Core\Hook\HookSystem;

/**
 * Manages theme assets (CSS, JS, images)
 */
class AssetManager
{
    private CacheInterface $cache;
    private EventDispatcherInterface $eventDispatcher;
    private array $config;
    private array $assets = [];
    private array $bundles = [];
    private bool $debug = false;
    private string $publicPath;
    private string $themesPath;

    public function __construct(
        CacheInterface $cache,
        EventDispatcherInterface $eventDispatcher,
        array $config = []
    ) {
        $this->cache = $cache;
        $this->eventDispatcher = $eventDispatcher;
        $this->config = array_merge([
            'public_path' => '/assets',
            'themes_path' => dirname(__DIR__, 4) . '/themes',
            'cache_path' => dirname(__DIR__, 4) . '/storage/cache/assets',
            'minify' => true,
            'combine' => true,
            'versioning' => true
        ], $config);
        
        $this->publicPath = $this->config['public_path'];
        $this->themesPath = $this->config['themes_path'];
        $this->debug = $config['debug'] ?? false;
        
        $this->ensureCacheDirectory();
    }

    /**
     * Add CSS asset
     */
    public function addStylesheet(string $path, array $options = []): self
    {
        $this->addAsset('css', $path, $options);
        return $this;
    }

    /**
     * Add JavaScript asset
     */
    public function addScript(string $path, array $options = []): self
    {
        $this->addAsset('js', $path, $options);
        return $this;
    }

    /**
     * Add inline CSS
     */
    public function addInlineStyle(string $id, string $css, array $options = []): self
    {
        $this->assets['css'][$id] = array_merge([
            'type' => 'inline',
            'content' => $css,
            'priority' => 50
        ], $options);
        
        return $this;
    }

    /**
     * Add inline JavaScript
     */
    public function addInlineScript(string $id, string $js, array $options = []): self
    {
        $this->assets['js'][$id] = array_merge([
            'type' => 'inline',
            'content' => $js,
            'priority' => 50,
            'position' => 'footer'
        ], $options);
        
        return $this;
    }

    /**
     * Create asset bundle
     */
    public function createBundle(string $name, array $assets, array $options = []): self
    {
        $this->bundles[$name] = array_merge([
            'assets' => $assets,
            'version' => '1.0.0',
            'dependencies' => []
        ], $options);
        
        return $this;
    }

    /**
     * Get theme styles
     */
    public function getThemeStyles(string $theme): array
    {
        $themePath = $this->themesPath . '/' . $theme;
        $styles = [];
        
        // Load theme manifest
        $manifest = $this->loadThemeManifest($theme);
        
        // Add theme styles
        if (isset($manifest['styles'])) {
            foreach ($manifest['styles'] as $style) {
                $styles[] = $this->publicPath . '/themes/' . $theme . '/' . $style;
            }
        }
        
        // Add auto-discovered styles
        $cssPath = $themePath . '/assets/css';
        if (is_dir($cssPath)) {
            foreach (glob($cssPath . '/*.css') as $file) {
                $styles[] = $this->publicPath . '/themes/' . $theme . '/assets/css/' . basename($file);
            }
        }
        
        return $styles;
    }

    /**
     * Get theme scripts
     */
    public function getThemeScripts(string $theme): array
    {
        $themePath = $this->themesPath . '/' . $theme;
        $scripts = [];
        
        // Load theme manifest
        $manifest = $this->loadThemeManifest($theme);
        
        // Add theme scripts
        if (isset($manifest['scripts'])) {
            foreach ($manifest['scripts'] as $script) {
                $scripts[] = $this->publicPath . '/themes/' . $theme . '/' . $script;
            }
        }
        
        // Add auto-discovered scripts
        $jsPath = $themePath . '/assets/js';
        if (is_dir($jsPath)) {
            foreach (glob($jsPath . '/*.js') as $file) {
                $scripts[] = $this->publicPath . '/themes/' . $theme . '/assets/js/' . basename($file);
            }
        }
        
        return $scripts;
    }

    /**
     * Render CSS tags
     */
    public function renderStyles(): string
    {
        $output = '';
        $assets = $this->getAssetsForRendering('css');
        
        // Apply filters
        $assets = HookSystem::applyFilters('assets.styles', $assets);
        
        foreach ($assets as $asset) {
            if ($asset['type'] === 'inline') {
                $output .= sprintf(
                    '<style%s>%s</style>' . PHP_EOL,
                    $this->renderAttributes($asset['attributes'] ?? []),
                    $asset['content']
                );
            } else {
                $url = $this->processAssetUrl($asset['path'], 'css');
                $output .= sprintf(
                    '<link rel="stylesheet" href="%s"%s>' . PHP_EOL,
                    $url,
                    $this->renderAttributes($asset['attributes'] ?? [])
                );
            }
        }
        
        return $output;
    }

    /**
     * Render JavaScript tags
     */
    public function renderScripts(string $position = 'footer'): string
    {
        $output = '';
        $assets = $this->getAssetsForRendering('js', $position);
        
        // Apply filters
        $assets = HookSystem::applyFilters('assets.scripts', $assets);
        
        foreach ($assets as $asset) {
            if ($asset['type'] === 'inline') {
                $output .= sprintf(
                    '<script%s>%s</script>' . PHP_EOL,
                    $this->renderAttributes($asset['attributes'] ?? []),
                    $asset['content']
                );
            } else {
                $url = $this->processAssetUrl($asset['path'], 'js');
                $output .= sprintf(
                    '<script src="%s"%s></script>' . PHP_EOL,
                    $url,
                    $this->renderAttributes(array_merge(
                        ['defer' => true],
                        $asset['attributes'] ?? []
                    ))
                );
            }
        }
        
        return $output;
    }

    /**
     * Compile SCSS to CSS
     */
    public function compileScss(string $source, array $options = []): string
    {
        $cacheKey = 'scss_' . md5($source . json_encode($options));
        
        if (!$this->debug) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        // Simple SCSS compiler (basic features)
        $compiler = new ScssCompiler($options);
        $css = $compiler->compile($source);
        
        if ($this->config['minify']) {
            $css = $this->minifyCss($css);
        }
        
        $this->cache->set($cacheKey, $css, 3600);
        
        return $css;
    }

    /**
     * Minify CSS
     */
    public function minifyCss(string $css): string
    {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remove whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Remove unnecessary spaces
        $css = str_replace([': ', ' {', '{ ', ' }', '} ', '; ', ' ;'], [':', '{', '{', '}', '}', ';', ';'], $css);
        
        return trim($css);
    }

    /**
     * Minify JavaScript
     */
    public function minifyJs(string $js): string
    {
        // Basic JS minification (remove comments and whitespace)
        // Remove single-line comments
        $js = preg_replace('/\/\/.*$/m', '', $js);
        
        // Remove multi-line comments
        $js = preg_replace('/\/\*[^*]*\*+([^/][^*]*\*+)*\//', '', $js);
        
        // Remove unnecessary whitespace
        $js = preg_replace('/\s+/', ' ', $js);
        $js = preg_replace('/\s*([{}()\[\];,:<>~!%^*+=\-|&?])\s*/', '$1', $js);
        
        return trim($js);
    }

    /**
     * Get asset URL with versioning
     */
    public function getAssetUrl(string $path): string
    {
        if ($this->config['versioning']) {
            $version = $this->getAssetVersion($path);
            $separator = strpos($path, '?') !== false ? '&' : '?';
            return $path . $separator . 'v=' . $version;
        }
        
        return $path;
    }

    /**
     * Clear asset cache
     */
    public function clearCache(): void
    {
        $cacheDir = $this->config['cache_path'];
        
        if (is_dir($cacheDir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }
        }
        
        $this->cache->deleteByPrefix('assets_');
    }

    // Private methods

    private function addAsset(string $type, string $path, array $options): void
    {
        $id = $options['id'] ?? md5($path);
        
        $this->assets[$type][$id] = array_merge([
            'type' => 'file',
            'path' => $path,
            'priority' => 50,
            'dependencies' => [],
            'attributes' => [],
            'position' => 'footer'
        ], $options);
    }

    private function getAssetsForRendering(string $type, string $position = null): array
    {
        $assets = $this->assets[$type] ?? [];
        
        // Filter by position for scripts
        if ($type === 'js' && $position !== null) {
            $assets = array_filter($assets, function($asset) use ($position) {
                return ($asset['position'] ?? 'footer') === $position;
            });
        }
        
        // Resolve dependencies
        $assets = $this->resolveDependencies($assets);
        
        // Sort by priority
        uasort($assets, function($a, $b) {
            return ($b['priority'] ?? 50) <=> ($a['priority'] ?? 50);
        });
        
        return $assets;
    }

    private function resolveDependencies(array $assets): array
    {
        $resolved = [];
        $seen = [];
        
        foreach ($assets as $id => $asset) {
            $this->resolveDependency($id, $assets, $resolved, $seen);
        }
        
        return $resolved;
    }

    private function resolveDependency(string $id, array $assets, array &$resolved, array &$seen): void
    {
        if (isset($resolved[$id])) {
            return;
        }
        
        if (isset($seen[$id])) {
            throw new AssetException('Circular dependency detected: ' . $id);
        }
        
        $seen[$id] = true;
        
        if (isset($assets[$id])) {
            $asset = $assets[$id];
            
            foreach ($asset['dependencies'] ?? [] as $dep) {
                $this->resolveDependency($dep, $assets, $resolved, $seen);
            }
            
            $resolved[$id] = $asset;
        }
        
        unset($seen[$id]);
    }

    private function processAssetUrl(string $path, string $type): string
    {
        // Check if it's an external URL
        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }
        
        // Check if combining is enabled
        if ($this->config['combine'] && !$this->debug) {
            return $this->getCombinedAssetUrl([$path], $type);
        }
        
        return $this->getAssetUrl($path);
    }

    private function getCombinedAssetUrl(array $paths, string $type): string
    {
        $hash = md5(implode('|', $paths));
        $filename = $hash . '.' . $type;
        $cachePath = $this->config['cache_path'] . '/' . $filename;
        
        if (!file_exists($cachePath) || $this->debug) {
            $content = '';
            
            foreach ($paths as $path) {
                $fullPath = $_SERVER['DOCUMENT_ROOT'] . $path;
                if (file_exists($fullPath)) {
                    $content .= file_get_contents($fullPath) . "\n";
                }
            }
            
            if ($this->config['minify']) {
                $content = $type === 'css' ? $this->minifyCss($content) : $this->minifyJs($content);
            }
            
            file_put_contents($cachePath, $content);
        }
        
        return $this->publicPath . '/cache/' . $filename . '?v=' . filemtime($cachePath);
    }

    private function getAssetVersion(string $path): string
    {
        $fullPath = $_SERVER['DOCUMENT_ROOT'] . $path;
        
        if (file_exists($fullPath)) {
            return substr(md5_file($fullPath), 0, 8);
        }
        
        return '1.0.0';
    }

    private function renderAttributes(array $attributes): string
    {
        $output = '';
        
        foreach ($attributes as $key => $value) {
            if ($value === true) {
                $output .= ' ' . $key;
            } elseif ($value !== false && $value !== null) {
                $output .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
            }
        }
        
        return $output;
    }

    private function loadThemeManifest(string $theme): array
    {
        $manifestPath = $this->themesPath . '/' . $theme . '/theme.json';
        
        if (!file_exists($manifestPath)) {
            return [];
        }
        
        $manifest = json_decode(file_get_contents($manifestPath), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }
        
        return $manifest;
    }

    private function ensureCacheDirectory(): void
    {
        $cacheDir = $this->config['cache_path'];
        
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
    }
}

class AssetException extends \Exception {}