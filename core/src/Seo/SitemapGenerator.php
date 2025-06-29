<?php

declare(strict_types=1);

namespace Shopologic\Core\Seo;

use Shopologic\Core\Cache\CacheInterface;
use Shopologic\Core\Events\EventDispatcherInterface;
use Shopologic\Core\MultiStore\Store;

/**
 * Generates XML sitemaps for search engines
 */
class SitemapGenerator
{
    private CacheInterface $cache;
    private EventDispatcherInterface $eventDispatcher;
    private array $config;
    private array $urls = [];
    private ?Store $store = null;

    public function __construct(
        CacheInterface $cache,
        EventDispatcherInterface $eventDispatcher,
        array $config = []
    ) {
        $this->cache = $cache;
        $this->eventDispatcher = $eventDispatcher;
        $this->config = array_merge([
            'max_urls' => 50000,
            'max_filesize' => 50 * 1024 * 1024, // 50MB
            'default_changefreq' => 'weekly',
            'default_priority' => 0.5,
            'cache_ttl' => 86400, // 24 hours
            'include_images' => true,
            'include_alternates' => true,
            'output_path' => null
        ], $config);
    }

    /**
     * Set store context
     */
    public function setStore(Store $store): self
    {
        $this->store = $store;
        return $this;
    }

    /**
     * Add URL to sitemap
     */
    public function addUrl(
        string $loc,
        ?\DateTimeInterface $lastmod = null,
        ?string $changefreq = null,
        ?float $priority = null,
        array $images = [],
        array $alternates = []
    ): self {
        $url = [
            'loc' => $this->normalizeUrl($loc),
            'lastmod' => $lastmod ? $lastmod->format('Y-m-d') : date('Y-m-d'),
            'changefreq' => $changefreq ?? $this->config['default_changefreq'],
            'priority' => $priority ?? $this->config['default_priority']
        ];
        
        if ($this->config['include_images'] && !empty($images)) {
            $url['images'] = $images;
        }
        
        if ($this->config['include_alternates'] && !empty($alternates)) {
            $url['alternates'] = $alternates;
        }
        
        $this->urls[] = $url;
        
        return $this;
    }

    /**
     * Add multiple URLs
     */
    public function addUrls(array $urls): self
    {
        foreach ($urls as $url) {
            $this->addUrl(
                $url['loc'],
                $url['lastmod'] ?? null,
                $url['changefreq'] ?? null,
                $url['priority'] ?? null,
                $url['images'] ?? [],
                $url['alternates'] ?? []
            );
        }
        
        return $this;
    }

    /**
     * Generate sitemap for products
     */
    public function addProducts(array $products): self
    {
        foreach ($products as $product) {
            $images = [];
            
            if ($this->config['include_images'] && isset($product['images'])) {
                foreach ($product['images'] as $image) {
                    $images[] = [
                        'loc' => $image['url'],
                        'title' => $image['alt'] ?? $product['name'],
                        'caption' => $image['caption'] ?? null
                    ];
                }
            }
            
            $this->addUrl(
                $product['url'],
                $product['updated_at'] ?? null,
                'daily',
                0.8,
                $images
            );
        }
        
        return $this;
    }

    /**
     * Generate sitemap for categories
     */
    public function addCategories(array $categories): self
    {
        foreach ($categories as $category) {
            $this->addUrl(
                $category['url'],
                $category['updated_at'] ?? null,
                'weekly',
                0.7
            );
        }
        
        return $this;
    }

    /**
     * Generate sitemap for pages
     */
    public function addPages(array $pages): self
    {
        foreach ($pages as $page) {
            $this->addUrl(
                $page['url'],
                $page['updated_at'] ?? null,
                $page['changefreq'] ?? 'monthly',
                $page['priority'] ?? 0.5
            );
        }
        
        return $this;
    }

    /**
     * Generate sitemap XML
     */
    public function generate(): string
    {
        // Apply filters
        $this->urls = $this->eventDispatcher->filter('seo.sitemap_urls', $this->urls);
        
        // Check cache
        $cacheKey = 'sitemap_' . ($this->store ? $this->store->id : 'default');
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null && !empty($cached)) {
            return $cached;
        }
        
        // Generate XML
        $xml = $this->generateXml();
        
        // Cache result
        $this->cache->set($cacheKey, $xml, $this->config['cache_ttl']);
        
        return $xml;
    }

    /**
     * Generate sitemap index
     */
    public function generateIndex(array $sitemaps): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
        
        foreach ($sitemaps as $sitemap) {
            $xml .= '  <sitemap>' . PHP_EOL;
            $xml .= '    <loc>' . htmlspecialchars($sitemap['loc']) . '</loc>' . PHP_EOL;
            
            if (isset($sitemap['lastmod'])) {
                $lastmod = $sitemap['lastmod'] instanceof \DateTimeInterface 
                    ? $sitemap['lastmod']->format('Y-m-d') 
                    : $sitemap['lastmod'];
                $xml .= '    <lastmod>' . $lastmod . '</lastmod>' . PHP_EOL;
            }
            
            $xml .= '  </sitemap>' . PHP_EOL;
        }
        
        $xml .= '</sitemapindex>';
        
        return $xml;
    }

    /**
     * Save sitemap to file
     */
    public function save(string $filename = 'sitemap.xml'): bool
    {
        $path = $this->config['output_path'] ?? $_SERVER['DOCUMENT_ROOT'];
        $filepath = rtrim($path, '/') . '/' . $filename;
        
        $xml = $this->generate();
        
        // Create directory if it doesn't exist
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Save file
        $result = file_put_contents($filepath, $xml);
        
        if ($result !== false) {
            // Trigger event
            $this->eventDispatcher->dispatch('seo.sitemap_generated', [
                'file' => $filepath,
                'urls' => count($this->urls)
            ]);
            
            return true;
        }
        
        return false;
    }

    /**
     * Clear sitemap cache
     */
    public function clearCache(): void
    {
        $this->cache->deleteByPrefix('sitemap_');
    }

    /**
     * Get URLs count
     */
    public function getUrlCount(): int
    {
        return count($this->urls);
    }

    /**
     * Reset generator
     */
    public function reset(): self
    {
        $this->urls = [];
        return $this;
    }

    // Private methods

    private function generateXml(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        
        if ($this->config['include_images']) {
            $xml .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
        }
        
        if ($this->config['include_alternates']) {
            $xml .= ' xmlns:xhtml="http://www.w3.org/1999/xhtml"';
        }
        
        $xml .= '>' . PHP_EOL;
        
        foreach ($this->urls as $url) {
            $xml .= $this->generateUrlXml($url);
        }
        
        $xml .= '</urlset>';
        
        return $xml;
    }

    private function generateUrlXml(array $url): string
    {
        $xml = '  <url>' . PHP_EOL;
        $xml .= '    <loc>' . htmlspecialchars($url['loc']) . '</loc>' . PHP_EOL;
        $xml .= '    <lastmod>' . $url['lastmod'] . '</lastmod>' . PHP_EOL;
        $xml .= '    <changefreq>' . $url['changefreq'] . '</changefreq>' . PHP_EOL;
        $xml .= '    <priority>' . number_format($url['priority'], 1) . '</priority>' . PHP_EOL;
        
        // Add images
        if (isset($url['images'])) {
            foreach ($url['images'] as $image) {
                $xml .= '    <image:image>' . PHP_EOL;
                $xml .= '      <image:loc>' . htmlspecialchars($image['loc']) . '</image:loc>' . PHP_EOL;
                
                if (isset($image['title'])) {
                    $xml .= '      <image:title>' . htmlspecialchars($image['title']) . '</image:title>' . PHP_EOL;
                }
                
                if (isset($image['caption'])) {
                    $xml .= '      <image:caption>' . htmlspecialchars($image['caption']) . '</image:caption>' . PHP_EOL;
                }
                
                $xml .= '    </image:image>' . PHP_EOL;
            }
        }
        
        // Add alternate languages
        if (isset($url['alternates'])) {
            foreach ($url['alternates'] as $lang => $altUrl) {
                $xml .= '    <xhtml:link rel="alternate" hreflang="' . $lang . '" href="' . htmlspecialchars($altUrl) . '"/>' . PHP_EOL;
            }
        }
        
        $xml .= '  </url>' . PHP_EOL;
        
        return $xml;
    }

    private function normalizeUrl(string $url): string
    {
        // Ensure absolute URL
        if (!preg_match('/^https?:\/\//i', $url)) {
            $baseUrl = $this->store ? $this->store->getUrl() : $this->config['base_url'] ?? '';
            $url = rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
        }
        
        return $url;
    }
}