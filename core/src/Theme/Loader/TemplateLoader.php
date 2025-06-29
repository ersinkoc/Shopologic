<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Loader;

/**
 * Loads templates from the filesystem
 */
class TemplateLoader
{
    private array $paths = [];
    private array $cache = [];
    private array $stringTemplates = [];
    private array $blocks = [];
    private ?string $currentTheme = null;

    public function __construct(array $paths = [], ?string $currentTheme = null)
    {
        $this->paths = $paths;
        $this->currentTheme = $currentTheme;
    }

    /**
     * Add a template path
     */
    public function addPath(string $path, string $namespace = '__main__'): void
    {
        if (!isset($this->paths[$namespace])) {
            $this->paths[$namespace] = [];
        }
        
        $this->paths[$namespace][] = rtrim($path, '/\\');
    }

    /**
     * Get template source
     */
    public function getSource(string $name): string
    {
        // Check string templates first
        if (isset($this->stringTemplates[$name])) {
            return $this->stringTemplates[$name];
        }

        $path = $this->findTemplate($name);
        
        if (!$path) {
            throw new LoaderException(sprintf('Template "%s" not found', $name));
        }

        $source = file_get_contents($path);
        
        if ($source === false) {
            throw new LoaderException(sprintf('Unable to read template "%s"', $name));
        }

        return $source;
    }

    /**
     * Check if template exists
     */
    public function exists(string $name): bool
    {
        if (isset($this->stringTemplates[$name])) {
            return true;
        }

        return $this->findTemplate($name) !== null;
    }

    /**
     * Get last modified time
     */
    public function getLastModified(string $name): int
    {
        if (isset($this->stringTemplates[$name])) {
            return time();
        }

        $path = $this->findTemplate($name);
        
        if (!$path) {
            return 0;
        }

        return filemtime($path) ?: 0;
    }

    /**
     * Set a string template
     */
    public function setStringTemplate(string $name, string $source): void
    {
        $this->stringTemplates[$name] = $source;
    }

    /**
     * Set parent template
     */
    public function setParentTemplate(string $parent): void
    {
        // This is handled by the compiler
    }

    /**
     * Set a block
     */
    public function setBlock(string $name, string $content): void
    {
        $this->blocks[$name] = $content;
    }

    /**
     * Get a block
     */
    public function getBlock(string $name): ?string
    {
        return $this->blocks[$name] ?? null;
    }

    /**
     * Clear blocks
     */
    public function clearBlocks(): void
    {
        $this->blocks = [];
    }

    /**
     * Find template file
     */
    private function findTemplate(string $name): ?string
    {
        // Check cache
        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        // Parse namespace
        $namespace = '__main__';
        if (strpos($name, '@') === 0) {
            $pos = strpos($name, '/');
            if ($pos !== false) {
                $namespace = substr($name, 1, $pos - 1);
                $name = substr($name, $pos + 1);
            }
        }

        // Normalize template name
        $name = $this->normalizeName($name);

        // Search in paths
        $paths = $this->paths[$namespace] ?? [];
        
        // Add theme-specific paths
        if ($this->currentTheme && $namespace === '__main__') {
            array_unshift($paths, $this->getThemePath($this->currentTheme));
        }

        foreach ($paths as $path) {
            $fullPath = $path . '/' . $name;
            
            if (is_file($fullPath)) {
                $this->cache[$name] = $fullPath;
                return $fullPath;
            }
        }

        return null;
    }

    /**
     * Normalize template name
     */
    private function normalizeName(string $name): string
    {
        // Add .twig extension if not present
        if (!preg_match('/\.(twig|html)$/', $name)) {
            $name .= '.twig';
        }

        // Convert dots to slashes for directory structure
        $name = str_replace('.', '/', $name);

        return $name;
    }

    /**
     * Get theme path
     */
    private function getThemePath(string $theme): string
    {
        return dirname(__DIR__, 4) . '/themes/' . $theme . '/templates';
    }

    /**
     * Clear cache
     */
    public function clearCache(): void
    {
        $this->cache = [];
        $this->stringTemplates = [];
        $this->blocks = [];
    }
}

class LoaderException extends \Exception {}