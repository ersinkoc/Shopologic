<?php

declare(strict_types=1);

namespace Shopologic\Core\Seo;

use Shopologic\Core\MultiStore\Store;
use Shopologic\Core\I18n\Locale\LocaleManager;

/**
 * SEO-friendly URL generator
 */
class UrlGenerator
{
    private array $config;
    private ?Store $store = null;
    private ?LocaleManager $localeManager = null;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'use_trailing_slash' => false,
            'lowercase_urls' => true,
            'max_slug_length' => 100,
            'slug_separator' => '-',
            'transliterate' => true,
            'remove_stop_words' => true,
            'stop_words' => ['a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for']
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
     * Set locale manager
     */
    public function setLocaleManager(LocaleManager $localeManager): self
    {
        $this->localeManager = $localeManager;
        return $this;
    }

    /**
     * Generate URL for product
     */
    public function forProduct(array $product, array $options = []): string
    {
        $segments = [];
        
        if ($options['include_category'] ?? true) {
            if (isset($product['category_slug'])) {
                $segments[] = $product['category_slug'];
            }
        }
        
        $segments[] = $product['slug'] ?? $this->generateSlug($product['name']);
        
        return $this->buildUrl($segments, $options);
    }

    /**
     * Generate URL for category
     */
    public function forCategory(array $category, array $options = []): string
    {
        $segments = ['category'];
        
        if (isset($category['parent_slug'])) {
            $segments[] = $category['parent_slug'];
        }
        
        $segments[] = $category['slug'] ?? $this->generateSlug($category['name']);
        
        return $this->buildUrl($segments, $options);
    }

    /**
     * Generate URL for page
     */
    public function forPage(array $page, array $options = []): string
    {
        $segments = [];
        
        if (isset($page['parent_slug'])) {
            $segments[] = $page['parent_slug'];
        }
        
        $segments[] = $page['slug'] ?? $this->generateSlug($page['title']);
        
        return $this->buildUrl($segments, $options);
    }

    /**
     * Generate URL for blog post
     */
    public function forBlogPost(array $post, array $options = []): string
    {
        $segments = ['blog'];
        
        if ($options['include_date'] ?? false) {
            $date = $post['published_at'] ?? new \DateTime();
            if ($date instanceof \DateTimeInterface) {
                $segments[] = $date->format('Y');
                $segments[] = $date->format('m');
            }
        }
        
        $segments[] = $post['slug'] ?? $this->generateSlug($post['title']);
        
        return $this->buildUrl($segments, $options);
    }

    /**
     * Generate URL with query parameters
     */
    public function withQuery(string $url, array $params): string
    {
        if (empty($params)) {
            return $url;
        }
        
        $query = http_build_query($params);
        $separator = strpos($url, '?') !== false ? '&' : '?';
        
        return $url . $separator . $query;
    }

    /**
     * Generate absolute URL
     */
    public function absolute(string $path): string
    {
        $baseUrl = $this->getBaseUrl();
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Generate slug from text
     */
    public function generateSlug(string $text): string
    {
        // Convert to lowercase if configured
        if ($this->config['lowercase_urls']) {
            $text = mb_strtolower($text);
        }
        
        // Transliterate if configured
        if ($this->config['transliterate']) {
            $text = $this->transliterate($text);
        }
        
        // Remove stop words if configured
        if ($this->config['remove_stop_words']) {
            $text = $this->removeStopWords($text);
        }
        
        // Replace non-alphanumeric characters with separator
        $separator = $this->config['slug_separator'];
        $text = preg_replace('/[^a-z0-9]+/i', $separator, $text);
        
        // Remove duplicate separators
        $text = preg_replace('/' . preg_quote($separator) . '+/', $separator, $text);
        
        // Trim separators from ends
        $text = trim($text, $separator);
        
        // Limit length
        if (mb_strlen($text) > $this->config['max_slug_length']) {
            $text = mb_substr($text, 0, $this->config['max_slug_length']);
            $lastSeparator = mb_strrpos($text, $separator);
            if ($lastSeparator !== false) {
                $text = mb_substr($text, 0, $lastSeparator);
            }
        }
        
        return $text;
    }

    /**
     * Generate canonical URL
     */
    public function canonical(string $path, array $params = []): string
    {
        // Remove tracking parameters
        $allowedParams = ['page', 'sort', 'filter', 'category', 'brand'];
        $params = array_intersect_key($params, array_flip($allowedParams));
        
        // Sort parameters for consistency
        ksort($params);
        
        $url = $this->absolute($path);
        
        if (!empty($params)) {
            $url = $this->withQuery($url, $params);
        }
        
        return $url;
    }

    /**
     * Generate localized URL
     */
    public function localized(string $path, string $locale): string
    {
        $segments = explode('/', trim($path, '/'));
        
        // Add locale prefix if not default
        if ($this->localeManager) {
            $defaultLocale = $this->localeManager->getDefaultLocale();
            if ($locale !== $defaultLocale) {
                array_unshift($segments, $locale);
            }
        }
        
        return $this->buildUrl($segments);
    }

    // Private methods

    private function buildUrl(array $segments, array $options = []): string
    {
        $path = implode('/', array_filter($segments));
        
        // Add trailing slash if configured
        if ($this->config['use_trailing_slash'] && !empty($path)) {
            $path .= '/';
        }
        
        // Make absolute if requested
        if ($options['absolute'] ?? false) {
            return $this->absolute($path);
        }
        
        return '/' . $path;
    }

    private function getBaseUrl(): string
    {
        if ($this->store) {
            return $this->store->getUrl();
        }
        
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        return $protocol . '://' . $host;
    }

    private function transliterate(string $text): string
    {
        // Basic transliteration map
        $map = [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'Æ' => 'AE', 'Ç' => 'C', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ð' => 'D', 'Ñ' => 'N',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'TH',
            'ß' => 'ss', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'å' => 'a', 'æ' => 'ae', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e',
            'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'd',
            'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y',
            'þ' => 'th', 'ÿ' => 'y'
        ];
        
        return strtr($text, $map);
    }

    private function removeStopWords(string $text): string
    {
        $words = explode(' ', $text);
        $filtered = [];
        
        foreach ($words as $word) {
            if (!in_array(mb_strtolower($word), $this->config['stop_words'])) {
                $filtered[] = $word;
            }
        }
        
        return implode(' ', $filtered);
    }
}