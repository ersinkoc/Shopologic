<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Extension;

use Shopologic\Core\Theme\Asset\AssetManager;

/**
 * Asset management template functions
 */
class AssetExtension implements ExtensionInterface
{
    private AssetManager $assetManager;

    public function __construct(AssetManager $assetManager)
    {
        $this->assetManager = $assetManager;
    }

    public function getFilters(): array
    {
        return [
            'asset' => [$this, 'asset'],
            'version' => [$this, 'version'],
        ];
    }

    public function getFunctions(): array
    {
        return [
            'asset' => [$this, 'asset'],
            'add_css' => [$this, 'addCss'],
            'add_js' => [$this, 'addJs'],
            'add_inline_css' => [$this, 'addInlineCss'],
            'add_inline_js' => [$this, 'addInlineJs'],
            'render_styles' => [$this, 'renderStyles'],
            'render_scripts' => [$this, 'renderScripts'],
            'preload' => [$this, 'preload'],
            'prefetch' => [$this, 'prefetch'],
            'theme_asset' => [$this, 'themeAsset'],
        ];
    }

    public function getGlobals(): array
    {
        return [];
    }

    // Filter implementations

    public function asset(string $path, array $options = []): string
    {
        return $this->assetManager->getAssetUrl($path);
    }

    public function version(string $path): string
    {
        return $this->assetManager->getAssetUrl($path);
    }

    // Function implementations

    public function addCss(string $path, array $options = []): void
    {
        $this->assetManager->addStylesheet($path, $options);
    }

    public function addJs(string $path, array $options = []): void
    {
        $this->assetManager->addScript($path, $options);
    }

    public function addInlineCss(string $id, string $css, array $options = []): void
    {
        $this->assetManager->addInlineStyle($id, $css, $options);
    }

    public function addInlineJs(string $id, string $js, array $options = []): void
    {
        $this->assetManager->addInlineScript($id, $js, $options);
    }

    public function renderStyles(): string
    {
        return $this->assetManager->renderStyles();
    }

    public function renderScripts(string $position = 'footer'): string
    {
        return $this->assetManager->renderScripts($position);
    }

    public function preload(string $url, string $as = 'script', array $attributes = []): string
    {
        $attrs = $this->buildAttributes(array_merge([
            'rel' => 'preload',
            'href' => $url,
            'as' => $as
        ], $attributes));
        
        return sprintf('<link%s>', $attrs);
    }

    public function prefetch(string $url, array $attributes = []): string
    {
        $attrs = $this->buildAttributes(array_merge([
            'rel' => 'prefetch',
            'href' => $url
        ], $attributes));
        
        return sprintf('<link%s>', $attrs);
    }

    public function themeAsset(string $path, string $theme = null): string
    {
        if ($theme === null) {
            $theme = $_GLOBALS['_current_theme'] ?? 'default';
        }
        
        return '/themes/' . $theme . '/' . ltrim($path, '/');
    }

    // Helper methods

    private function buildAttributes(array $attributes): string
    {
        $attrs = '';
        
        foreach ($attributes as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            
            if ($value === true) {
                $attrs .= ' ' . $key;
            } else {
                $attrs .= sprintf(' %s="%s"', $key, htmlspecialchars((string)$value));
            }
        }
        
        return $attrs;
    }
}