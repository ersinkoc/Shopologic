<?php

declare(strict_types=1);

namespace Shopologic\Core\Seo;

use Shopologic\Core\Events\EventDispatcherInterface;

/**
 * Manages SEO meta tags and structured data
 */
class MetaManager
{
    private EventDispatcherInterface $eventDispatcher;
    private array $meta = [];
    private array $structuredData = [];
    private array $config;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        array $config = []
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->config = array_merge([
            'default_title' => 'Shopologic',
            'title_separator' => ' | ',
            'default_description' => '',
            'default_keywords' => '',
            'default_author' => '',
            'default_robots' => 'index,follow',
            'og_enabled' => true,
            'twitter_enabled' => true,
            'twitter_site' => '',
            'facebook_app_id' => ''
        ], $config);
        
        $this->setDefaults();
    }

    /**
     * Set page title
     */
    public function setTitle(string $title, bool $append = true): self
    {
        if ($append && !empty($this->config['default_title'])) {
            $title = $title . $this->config['title_separator'] . $this->config['default_title'];
        }
        
        $this->meta['title'] = $title;
        $this->meta['og:title'] = $title;
        $this->meta['twitter:title'] = $title;
        
        return $this;
    }

    /**
     * Set meta description
     */
    public function setDescription(string $description): self
    {
        $this->meta['description'] = $this->truncate($description, 160);
        $this->meta['og:description'] = $this->truncate($description, 200);
        $this->meta['twitter:description'] = $this->truncate($description, 200);
        
        return $this;
    }

    /**
     * Set meta keywords
     */
    public function setKeywords($keywords): self
    {
        if (is_array($keywords)) {
            $keywords = implode(', ', $keywords);
        }
        
        $this->meta['keywords'] = $keywords;
        
        return $this;
    }

    /**
     * Set canonical URL
     */
    public function setCanonical(string $url): self
    {
        $this->meta['canonical'] = $url;
        $this->meta['og:url'] = $url;
        
        return $this;
    }

    /**
     * Set meta image
     */
    public function setImage(string $url, int $width = 0, int $height = 0, string $alt = ''): self
    {
        $this->meta['og:image'] = $url;
        $this->meta['twitter:image'] = $url;
        
        if ($width > 0) {
            $this->meta['og:image:width'] = $width;
        }
        
        if ($height > 0) {
            $this->meta['og:image:height'] = $height;
        }
        
        if ($alt) {
            $this->meta['og:image:alt'] = $alt;
        }
        
        return $this;
    }

    /**
     * Set robots directive
     */
    public function setRobots(string $robots): self
    {
        $this->meta['robots'] = $robots;
        
        return $this;
    }

    /**
     * Set author
     */
    public function setAuthor(string $author): self
    {
        $this->meta['author'] = $author;
        
        return $this;
    }

    /**
     * Set Open Graph type
     */
    public function setType(string $type): self
    {
        $this->meta['og:type'] = $type;
        
        return $this;
    }

    /**
     * Set Twitter card type
     */
    public function setTwitterCard(string $type = 'summary'): self
    {
        $this->meta['twitter:card'] = $type;
        
        return $this;
    }

    /**
     * Add custom meta tag
     */
    public function addMeta(string $name, string $content, string $type = 'name'): self
    {
        $key = $type === 'property' ? $name : $type . ':' . $name;
        $this->meta[$key] = $content;
        
        return $this;
    }

    /**
     * Add structured data
     */
    public function addStructuredData(array $data): self
    {
        $this->structuredData[] = $data;
        
        return $this;
    }

    /**
     * Generate product structured data
     */
    public function setProductData(array $product): self
    {
        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product['name'],
            'description' => $product['description'] ?? '',
            'sku' => $product['sku'] ?? '',
            'brand' => [
                '@type' => 'Brand',
                'name' => $product['brand'] ?? $this->config['default_title']
            ],
            'offers' => [
                '@type' => 'Offer',
                'price' => $product['price'],
                'priceCurrency' => $product['currency'] ?? 'USD',
                'availability' => $product['in_stock'] ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url' => $product['url'] ?? ''
            ]
        ];
        
        if (isset($product['image'])) {
            $structuredData['image'] = $product['image'];
        }
        
        if (isset($product['rating']) && isset($product['review_count'])) {
            $structuredData['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $product['rating'],
                'reviewCount' => $product['review_count']
            ];
        }
        
        $this->addStructuredData($structuredData);
        
        return $this;
    }

    /**
     * Generate organization structured data
     */
    public function setOrganizationData(array $organization): self
    {
        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $organization['name'] ?? $this->config['default_title'],
            'url' => $organization['url'] ?? '',
            'logo' => $organization['logo'] ?? ''
        ];
        
        if (isset($organization['social'])) {
            $structuredData['sameAs'] = $organization['social'];
        }
        
        if (isset($organization['contact'])) {
            $structuredData['contactPoint'] = [
                '@type' => 'ContactPoint',
                'telephone' => $organization['contact']['phone'] ?? '',
                'contactType' => 'customer service',
                'areaServed' => $organization['contact']['area'] ?? '',
                'availableLanguage' => $organization['contact']['languages'] ?? []
            ];
        }
        
        $this->addStructuredData($structuredData);
        
        return $this;
    }

    /**
     * Generate breadcrumb structured data
     */
    public function setBreadcrumbData(array $breadcrumbs): self
    {
        $items = [];
        $position = 1;
        
        foreach ($breadcrumbs as $breadcrumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $breadcrumb['name'],
                'item' => $breadcrumb['url']
            ];
        }
        
        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items
        ];
        
        $this->addStructuredData($structuredData);
        
        return $this;
    }

    /**
     * Generate search action structured data
     */
    public function setSearchActionData(string $searchUrl): self
    {
        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'url' => $this->meta['canonical'] ?? '',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $searchUrl . '?q={search_term_string}'
                ],
                'query-input' => 'required name=search_term_string'
            ]
        ];
        
        $this->addStructuredData($structuredData);
        
        return $this;
    }

    /**
     * Render meta tags
     */
    public function render(): string
    {
        $output = '';
        
        // Apply filters
        $this->meta = $this->eventDispatcher->filter('seo.meta_tags', $this->meta);
        
        // Title tag
        if (isset($this->meta['title'])) {
            $output .= sprintf('<title>%s</title>' . PHP_EOL, htmlspecialchars($this->meta['title']));
            unset($this->meta['title']);
        }
        
        // Canonical link
        if (isset($this->meta['canonical'])) {
            $output .= sprintf('<link rel="canonical" href="%s">' . PHP_EOL, htmlspecialchars($this->meta['canonical']));
            unset($this->meta['canonical']);
        }
        
        // Meta tags
        foreach ($this->meta as $name => $content) {
            if (empty($content)) {
                continue;
            }
            
            if (strpos($name, 'og:') === 0 || strpos($name, 'fb:') === 0) {
                // Open Graph property
                $output .= sprintf(
                    '<meta property="%s" content="%s">' . PHP_EOL,
                    htmlspecialchars($name),
                    htmlspecialchars($content)
                );
            } elseif (strpos($name, 'twitter:') === 0) {
                // Twitter card
                $output .= sprintf(
                    '<meta name="%s" content="%s">' . PHP_EOL,
                    htmlspecialchars($name),
                    htmlspecialchars($content)
                );
            } else {
                // Standard meta tag
                $output .= sprintf(
                    '<meta name="%s" content="%s">' . PHP_EOL,
                    htmlspecialchars($name),
                    htmlspecialchars($content)
                );
            }
        }
        
        // Structured data
        if (!empty($this->structuredData)) {
            $structuredData = $this->eventDispatcher->filter('seo.structured_data', $this->structuredData);
            
            foreach ($structuredData as $data) {
                $output .= sprintf(
                    '<script type="application/ld+json">%s</script>' . PHP_EOL,
                    json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                );
            }
        }
        
        return $output;
    }

    /**
     * Get all meta tags
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * Get structured data
     */
    public function getStructuredData(): array
    {
        return $this->structuredData;
    }

    /**
     * Reset meta tags
     */
    public function reset(): self
    {
        $this->meta = [];
        $this->structuredData = [];
        $this->setDefaults();
        
        return $this;
    }

    // Private methods

    private function setDefaults(): void
    {
        if ($this->config['default_description']) {
            $this->setDescription($this->config['default_description']);
        }
        
        if ($this->config['default_keywords']) {
            $this->setKeywords($this->config['default_keywords']);
        }
        
        if ($this->config['default_author']) {
            $this->setAuthor($this->config['default_author']);
        }
        
        $this->setRobots($this->config['default_robots']);
        
        if ($this->config['og_enabled']) {
            $this->meta['og:site_name'] = $this->config['default_title'];
            $this->meta['og:type'] = 'website';
            
            if ($this->config['facebook_app_id']) {
                $this->meta['fb:app_id'] = $this->config['facebook_app_id'];
            }
        }
        
        if ($this->config['twitter_enabled']) {
            $this->setTwitterCard();
            
            if ($this->config['twitter_site']) {
                $this->meta['twitter:site'] = $this->config['twitter_site'];
            }
        }
    }

    private function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        
        $truncated = mb_substr($text, 0, $length - 3);
        $lastSpace = mb_strrpos($truncated, ' ');
        
        if ($lastSpace !== false) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }
        
        return $truncated . '...';
    }
}