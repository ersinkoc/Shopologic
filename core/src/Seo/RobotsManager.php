<?php

declare(strict_types=1);

namespace Shopologic\Core\Seo;

use Shopologic\Core\MultiStore\Store;

/**
 * Manages robots.txt generation and rules
 */
class RobotsManager
{
    private array $rules = [];
    private array $sitemaps = [];
    private array $config;
    private ?Store $store = null;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'crawl_delay' => 0,
            'default_rules' => true,
            'disallow_paths' => [
                '/admin',
                '/api',
                '/cart',
                '/checkout',
                '/account',
                '/login',
                '/register',
                '/search',
                '/ajax'
            ]
        ], $config);
        
        if ($this->config['default_rules']) {
            $this->addDefaultRules();
        }
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
     * Add rule for specific user agent
     */
    public function addRule(string $userAgent, string $directive, string $value = ''): self
    {
        if (!isset($this->rules[$userAgent])) {
            $this->rules[$userAgent] = [];
        }
        
        $this->rules[$userAgent][] = [
            'directive' => $directive,
            'value' => $value
        ];
        
        return $this;
    }

    /**
     * Allow path for user agent
     */
    public function allow(string $path, string $userAgent = '*'): self
    {
        return $this->addRule($userAgent, 'Allow', $path);
    }

    /**
     * Disallow path for user agent
     */
    public function disallow(string $path, string $userAgent = '*'): self
    {
        return $this->addRule($userAgent, 'Disallow', $path);
    }

    /**
     * Set crawl delay
     */
    public function setCrawlDelay(int $seconds, string $userAgent = '*'): self
    {
        return $this->addRule($userAgent, 'Crawl-delay', (string)$seconds);
    }

    /**
     * Add sitemap URL
     */
    public function addSitemap(string $url): self
    {
        $this->sitemaps[] = $url;
        return $this;
    }

    /**
     * Block specific bot
     */
    public function blockBot(string $botName): self
    {
        return $this->disallow('/', $botName);
    }

    /**
     * Generate robots.txt content
     */
    public function generate(): string
    {
        $output = "# Robots.txt for " . ($this->store ? $this->store->name : 'Shopologic') . "\n";
        $output .= "# Generated on " . date('Y-m-d H:i:s') . "\n\n";
        
        // Add rules for each user agent
        foreach ($this->rules as $userAgent => $rules) {
            $output .= "User-agent: {$userAgent}\n";
            
            foreach ($rules as $rule) {
                $output .= "{$rule['directive']}: {$rule['value']}\n";
            }
            
            $output .= "\n";
        }
        
        // Add sitemaps
        if (!empty($this->sitemaps)) {
            $output .= "# Sitemaps\n";
            foreach ($this->sitemaps as $sitemap) {
                $output .= "Sitemap: {$sitemap}\n";
            }
        }
        
        return $output;
    }

    /**
     * Save robots.txt file
     */
    public function save(string $path = null): bool
    {
        if ($path === null) {
            $path = $_SERVER['DOCUMENT_ROOT'] . '/robots.txt';
        }
        
        $content = $this->generate();
        
        return file_put_contents($path, $content) !== false;
    }

    /**
     * Parse existing robots.txt
     */
    public function parse(string $content): self
    {
        $lines = explode("\n", $content);
        $currentUserAgent = '*';
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip comments and empty lines
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            // Parse directive
            if (strpos($line, ':') !== false) {
                list($directive, $value) = explode(':', $line, 2);
                $directive = trim($directive);
                $value = trim($value);
                
                if (strcasecmp($directive, 'User-agent') === 0) {
                    $currentUserAgent = $value;
                } elseif (strcasecmp($directive, 'Sitemap') === 0) {
                    $this->addSitemap($value);
                } else {
                    $this->addRule($currentUserAgent, $directive, $value);
                }
            }
        }
        
        return $this;
    }

    /**
     * Check if path is allowed for user agent
     */
    public function isAllowed(string $path, string $userAgent = '*'): bool
    {
        $rules = $this->getRulesForUserAgent($userAgent);
        
        $allowed = true;
        $matchLength = 0;
        
        foreach ($rules as $rule) {
            if ($rule['directive'] !== 'Allow' && $rule['directive'] !== 'Disallow') {
                continue;
            }
            
            $pattern = $this->pathToRegex($rule['value']);
            
            if (preg_match($pattern, $path)) {
                $currentLength = strlen($rule['value']);
                
                if ($currentLength > $matchLength) {
                    $matchLength = $currentLength;
                    $allowed = $rule['directive'] === 'Allow';
                }
            }
        }
        
        return $allowed;
    }

    /**
     * Get rules for specific user agent
     */
    public function getRulesForUserAgent(string $userAgent): array
    {
        $rules = [];
        
        // Get specific rules
        if (isset($this->rules[$userAgent])) {
            $rules = array_merge($rules, $this->rules[$userAgent]);
        }
        
        // Get wildcard rules
        if ($userAgent !== '*' && isset($this->rules['*'])) {
            $rules = array_merge($rules, $this->rules['*']);
        }
        
        return $rules;
    }

    // Private methods

    private function addDefaultRules(): void
    {
        // Allow all by default
        $this->allow('/');
        
        // Disallow common paths
        foreach ($this->config['disallow_paths'] as $path) {
            $this->disallow($path);
        }
        
        // Block bad bots
        $badBots = ['AhrefsBot', 'SemrushBot', 'DotBot', 'MJ12bot'];
        foreach ($badBots as $bot) {
            $this->blockBot($bot);
        }
        
        // Set crawl delay if configured
        if ($this->config['crawl_delay'] > 0) {
            $this->setCrawlDelay($this->config['crawl_delay']);
        }
    }

    private function pathToRegex(string $path): string
    {
        // Escape special regex characters except * and $
        $path = preg_quote($path, '/');
        
        // Replace * with .*
        $path = str_replace('\\*', '.*', $path);
        
        // Handle $ at end
        if (substr($path, -2) === '\\$') {
            $path = substr($path, 0, -2) . '$';
        } else {
            $path .= '.*';
        }
        
        return '/^' . $path . '/';
    }
}