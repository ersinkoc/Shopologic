<?php

declare(strict_types=1);

namespace Shopologic\Core\Template;

use Shopologic\Core\Plugin\HookSystem;
use Exception;

/**
 * Simple PHP template engine with support for layouts, partials, and secure variable escaping
 */
class TemplateEngine
{
    /**
     * @var array Template paths indexed by namespace
     */
    private array $paths = [];
    
    /**
     * @var array Global variables available in all templates
     */
    private array $globals = [];
    
    /**
     * @var array Template functions
     */
    private array $functions = [];
    
    /**
     * @var array Current template data stack
     */
    private array $dataStack = [];
    
    /**
     * @var array Layout stack for nested layouts
     */
    private array $layoutStack = [];
    
    /**
     * @var array Content blocks for layouts
     */
    private array $blocks = [];
    
    /**
     * @var string|null Current block being captured
     */
    private ?string $currentBlock = null;
    
    /**
     * @var array Template sections for layout system
     */
    private array $sections = [];
    
    /**
     * @var string|null Current section being captured
     */
    private ?string $currentSection = null;
    
    /**
     * @var bool Debug mode
     */
    private bool $debug;
    
    /**
     * Constructor
     */
    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
        $this->registerDefaultFunctions();
    }
    
    /**
     * Add a template path with optional namespace
     */
    public function addPath(string $path, string $namespace = 'default'): void
    {
        if (!is_dir($path)) {
            throw new Exception("Template path does not exist: {$path}");
        }
        
        $this->paths[$namespace] = rtrim($path, '/');
    }
    
    /**
     * Render a template with data
     */
    public function render(string $template, array $data = []): string
    {
        try {
            // Push data onto stack
            $this->dataStack[] = array_merge($this->globals, $data);
            
            // Reset blocks for this render
            $this->blocks = [];
            
            // Start output buffering
            ob_start();
            
            // Include the template
            $this->includeTemplate($template);
            
            // Get the content
            $content = ob_get_clean();
            
            // Process layout if any
            while (!empty($this->layoutStack)) {
                $layout = array_pop($this->layoutStack);
                $this->blocks['content'] = $content;
                
                ob_start();
                $this->includeTemplate($layout);
                $content = ob_get_clean();
            }
            
            // Allow plugins to modify output
            $content = HookSystem::applyFilters('template.render.output', $content, [
                'template' => $template,
                'data' => $data
            ]);
            
            return $content;
            
        } catch (Exception $e) {
            error_log("Template error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            
            if ($this->debug) {
                throw new TemplateException(
                    "Error rendering template '{$template}': " . $e->getMessage(),
                    0,
                    $e
                );
            }
            
            // In production, return error placeholder
            return '<!-- Template Error: ' . $e->getMessage() . ' -->';
            
        } catch (\Throwable $e) {
            error_log("Template fatal error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            
            if ($this->debug) {
                return '<div style="color: red; border: 1px solid red; padding: 10px; margin: 10px;">' . 
                       '<h3>Template Fatal Error:</h3>' . 
                       '<pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>' .
                       '</div>';
            }
            
            return '<!-- Template Fatal Error -->';
            
        } finally {
            // Pop data stack
            array_pop($this->dataStack);
        }
    }
    
    /**
     * Include a template file
     */
    private function includeTemplate(string $template): void
    {
        $path = $this->resolvePath($template);


        if (!file_exists($path)) {
            throw new Exception("Template not found: {$template}");
        }

        // SECURITY: Make variables available with validation instead of extract()
        // This prevents variable pollution and potential security issues
        if (!empty($this->dataStack)) {
            $data = end($this->dataStack);
            foreach ($data as $key => $value) {
                // Only allow valid PHP variable names (alphanumeric + underscore, starting with letter/underscore)
                if (preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $key)) {
                    $$key = $value;
                }
            }
        }

        // Include the template
        include $path;
    }
    
    /**
     * Include a partial template (without layout processing)
     */
    public function partial(string $template, array $data = []): void
    {
        try {
            // Push merged data onto stack
            $mergedData = array_merge(end($this->dataStack) ?: [], $data);
            $this->dataStack[] = $mergedData;
            
            // Start output buffering
            ob_start();
            
            // Include the template directly (no layout processing)
            $this->includeTemplate($template);
            
            // Get the content and echo it
            $content = ob_get_clean();
            
            // Allow plugins to modify partial output
            $content = HookSystem::applyFilters('template.partial.output', $content, [
                'template' => $template,
                'data' => $data
            ]);
            
            echo $content;
            
        } catch (Exception $e) {
            error_log("Partial template error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            
            if ($this->debug) {
                echo '<div style="color: red; border: 1px solid red; padding: 5px; margin: 5px;">' . 
                     '<strong>Partial Error:</strong> ' . htmlspecialchars($e->getMessage()) .
                     '</div>';
            } else {
                echo '<!-- Partial Error: ' . htmlspecialchars($e->getMessage()) . ' -->';
            }
            
        } finally {
            // Pop data stack
            array_pop($this->dataStack);
        }
    }
    
    /**
     * Set the layout for the current template
     */
    public function layout(string $layout): void
    {
        $this->layoutStack[] = $layout;
    }
    
    /**
     * Start capturing a content block
     */
    public function startBlock(string $name): void
    {
        if ($this->currentBlock !== null) {
            throw new Exception("Cannot start block '{$name}' while block '{$this->currentBlock}' is open");
        }
        
        $this->currentBlock = $name;
        ob_start();
    }
    
    /**
     * End capturing a content block
     */
    public function endBlock(): void
    {
        if ($this->currentBlock === null) {
            throw new Exception("No block is currently open");
        }
        
        $this->blocks[$this->currentBlock] = ob_get_clean();
        $this->currentBlock = null;
    }
    
    /**
     * Output a content block
     */
    public function block(string $name, string $default = ''): void
    {
        echo $this->blocks[$name] ?? $default;
    }
    
    /**
     * Escape HTML output
     */
    public function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Output escaped HTML
     */
    public function escape(?string $value): void
    {
        echo $this->e($value);
    }
    
    /**
     * Add a global variable
     */
    public function addGlobal(string $name, $value): void
    {
        $this->globals[$name] = $value;
    }
    
    /**
     * Set the layout template
     */
    public function setLayout(string $layout): void
    {
        $this->layoutStack[] = $layout;
    }
    
    /**
     * Start capturing a section
     */
    public function startSection(string $name): void
    {
        $this->currentSection = $name;
        ob_start();
    }
    
    /**
     * End capturing a section
     */
    public function endSection(): void
    {
        if ($this->currentSection === null) {
            throw new Exception('No section started');
        }
        
        $content = ob_get_clean();
        $this->sections[$this->currentSection] = $content;
        $this->currentSection = null;
    }
    
    /**
     * Set section content directly
     */
    public function setSection(string $name, string $content): void
    {
        $this->sections[$name] = $content;
    }
    
    /**
     * Get section content
     */
    public function getSection(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }
    
    /**
     * Add a template function
     */
    public function addFunction(string $name, callable $callback): void
    {
        $this->functions[$name] = $callback;
    }
    
    /**
     * Call a template function
     */
    public function __call(string $name, array $arguments)
    {
        if (isset($this->functions[$name])) {
            return call_user_func_array($this->functions[$name], $arguments);
        }
        
        throw new Exception("Unknown template function: {$name}");
    }
    
    /**
     * Register default template functions
     */
    private function registerDefaultFunctions(): void
    {
        // SECURITY: Use secure helper method instead of direct superglobal access
        $getBaseUrl = function(): string {
            return $this->getBaseUrl();
        };
        
        // URL generation
        $this->addFunction('url', function(string $path = '', array $params = []) use ($getBaseUrl): string {
            $baseUrl = rtrim($_ENV['APP_URL'] ?? $getBaseUrl(), '/');
            $path = ltrim($path, '/');
            $url = $baseUrl . '/' . $path;
            
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }
            
            return $url;
        });
        
        // Asset URL generation
        $this->addFunction('asset', function(string $path) use ($getBaseUrl): string {
            $baseUrl = rtrim($_ENV['APP_URL'] ?? $getBaseUrl(), '/');
            $path = ltrim($path, '/');
            return $baseUrl . '/assets/' . $path;
        });
        
        // Theme asset URL generation
        $this->addFunction('theme_asset', function(string $path) use ($getBaseUrl): string {
            $baseUrl = rtrim($_ENV['APP_URL'] ?? $getBaseUrl(), '/');
            $theme = $_ENV['THEME'] ?? 'default';
            $path = ltrim($path, '/');
            return $baseUrl . '/themes/' . $theme . '/assets/' . $path;
        });
        
        // CSRF token
        $this->addFunction('csrf_token', function(): string {
            return $_SESSION['csrf_token'] ?? '';
        });
        
        // CSRF field
        $this->addFunction('csrf_field', function(): string {
            $token = $_SESSION['csrf_token'] ?? '';
            return '<input type="hidden" name="_token" value="' . $this->e($token) . '">';
        });
        
        // Old form input
        $this->addFunction('old', function(string $key, $default = ''): string {
            return $_SESSION['old_input'][$key] ?? $default;
        });
        
        // Check if user is authenticated
        $this->addFunction('auth', function(): bool {
            return isset($_SESSION['user_id']);
        });
        
        // Get authenticated user
        $this->addFunction('user', function() {
            return $_SESSION['user'] ?? null;
        });
        
        // Format currency
        $this->addFunction('money', function($amount, string $currency = 'USD'): string {
            $symbols = [
                'USD' => '$',
                'EUR' => '€',
                'GBP' => '£',
            ];
            
            $symbol = $symbols[$currency] ?? $currency . ' ';
            return $symbol . number_format((float)$amount, 2);
        });
        
        // Format date
        $this->addFunction('date_format', function($date, string $format = 'Y-m-d H:i:s'): string {
            if (is_string($date)) {
                $date = new \DateTime($date);
            }
            return $date->format($format);
        });
        
        // Truncate text
        $this->addFunction('truncate', function(string $text, int $length = 100, string $suffix = '...'): string {
            if (strlen($text) <= $length) {
                return $text;
            }
            return substr($text, 0, $length - strlen($suffix)) . $suffix;
        });
        
        // Generate route URL
        $this->addFunction('route', function(string $name, array $params = []): string {
            // This would integrate with the router
            // For now, just return a simple URL
            return $this->url($name, $params);
        });
        
        // Hook integration
        $this->addFunction('do_action', function(string $hook, ...$args): void {
            HookSystem::doAction($hook, ...$args);
        });
        
        $this->addFunction('apply_filter', function(string $hook, $value, ...$args) {
            return HookSystem::applyFilter($hook, $value, ...$args);
        });
        
        // Layout system functions
        $this->addFunction('layout', function(string $layout): void {
            $this->setLayout($layout);
        });
        
        $this->addFunction('section', function(string $name, string $content = null): void {
            if ($content !== null) {
                $this->setSection($name, $content);
            } else {
                $this->startSection($name);
            }
        });
        
        $this->addFunction('endSection', function(): void {
            $this->endSection();
        });
        
        $this->addFunction('yield', function(string $name, string $default = ''): string {
            return $this->getSection($name, $default);
        });
        
        // URL helper functions for filtering and pagination
        $this->addFunction('removeFilterUrl', function($filters = null): string {
            // SECURITY: Use secure helpers instead of direct superglobal access
            $url = $this->getSecureRequestUri();
            $params = $this->getSecureQueryString();
            
            if (is_string($filters)) {
                unset($params[$filters]);
            } elseif (is_array($filters)) {
                foreach ($filters as $filter) {
                    unset($params[$filter]);
                }
            }
            
            unset($params['page']); // Reset pagination
            
            return $url . (!empty($params) ? '?' . http_build_query($params) : '');
        });
        
        $this->addFunction('addPageParam', function(int $page): string {
            // SECURITY: Use secure helpers instead of direct superglobal access
            $url = $this->getSecureRequestUri();
            $params = $this->getSecureQueryString();
            
            $params['page'] = $page;
            
            return $url . '?' . http_build_query($params);
        });
        
        // Theme asset helper
        $this->addFunction('theme_asset', function(string $path): string {
            return '/themes/default/assets/' . ltrim($path, '/');
        });
        
        // URL manipulation helpers
        $this->addFunction('url_with_params', function(array $params = []): string {
            // SECURITY: Use secure helpers instead of direct superglobal access
            $currentUrl = $this->getSecureRequestUri();
            $currentQuery = $this->getSecureQueryString();
            
            // Merge new params with existing
            $mergedParams = array_merge($currentQuery, $params);
            
            // Remove empty params
            $mergedParams = array_filter($mergedParams, function($value) {
                return $value !== null && $value !== '';
            });
            
            if (empty($mergedParams)) {
                return $currentUrl;
            }
            
            return $currentUrl . '?' . http_build_query($mergedParams);
        });
        
        $this->addFunction('url_without_params', function(array $params = []): string {
            // SECURITY: Use secure helpers instead of direct superglobal access
            $currentUrl = $this->getSecureRequestUri();
            $currentQuery = $this->getSecureQueryString();
            
            // Remove specified params
            foreach ($params as $param) {
                unset($currentQuery[$param]);
            }
            
            if (empty($currentQuery)) {
                return $currentUrl;
            }
            
            return $currentUrl . '?' . http_build_query($currentQuery);
        });
        
        // Check if current admin route is active
        $this->addFunction('isActive', function(string $route): string {
            // SECURITY: Use secure helper instead of direct superglobal access
            $currentPath = $this->getSecureRequestUri();
            
            // Handle dashboard special case
            if ($route === 'dashboard' && $currentPath === '/admin') {
                return 'active';
            }
            
            // Handle other routes
            $routeMap = [
                'products' => '/admin/products',
                'orders' => '/admin/orders',
                'customers' => '/admin/customers',
                'reports' => '/admin/reports',
                'settings' => '/admin/settings',
                'categories' => '/admin/categories',
                'inventory' => '/admin/inventory',
                'promotions' => '/admin/promotions',
                'coupons' => '/admin/coupons',
                'email-campaigns' => '/admin/email-campaigns',
                'reviews' => '/admin/reviews',
                'analytics' => '/admin/analytics',
                'performance' => '/admin/performance',
                'settings-general' => '/admin/settings/general',
                'settings-payment' => '/admin/settings/payment',
                'settings-shipping' => '/admin/settings/shipping',
                'plugins' => '/admin/plugins',
                'themes' => '/admin/themes',
            ];
            
            if (isset($routeMap[$route]) && strpos($currentPath, $routeMap[$route]) === 0) {
                return 'active';
            }
            
            return '';
        });
    }
    
    /**
     * Resolve template path
     */
    private function resolvePath(string $template): string
    {
        // Check if template has namespace
        if (strpos($template, '@') === 0) {
            list($namespace, $path) = explode('/', substr($template, 1), 2);
        } else {
            $namespace = 'default';
            $path = $template;
        }
        
        if (!isset($this->paths[$namespace])) {
            throw new Exception("Template namespace not found: {$namespace}");
        }
        
        // Add .php extension if not present
        if (!str_ends_with($path, '.php')) {
            $path .= '.php';
        }
        
        return $this->paths[$namespace] . '/' . $path;
    }
    
    /**
     * Get base URL for the current request
     * SECURITY: Validate HTTP_HOST against whitelist
     */
    private function getBaseUrl(): string
    {
        $protocol = $this->getSecureProtocol();
        $host = $this->getSecureHost();

        return $protocol . '://' . $host;
    }

    /**
     * Get secure protocol from request
     * SECURITY: Validate HTTPS header
     */
    private function getSecureProtocol(): string
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    }

    /**
     * Get secure host from request
     * SECURITY: Validate host against whitelist to prevent Host header injection
     */
    private function getSecureHost(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:17000';

        // Sanitize host - only allow alphanumeric, dots, hyphens, colons, and brackets (for IPv6)
        if (!preg_match('/^[a-zA-Z0-9\.\-\:\[\]]+$/', $host)) {
            return 'localhost:17000'; // Default on invalid host
        }

        // Check against whitelist if configured
        $allowedHosts = !empty($_ENV['ALLOWED_HOSTS'])
            ? array_map('trim', explode(',', $_ENV['ALLOWED_HOSTS']))
            : [];

        if (!empty($allowedHosts) && !in_array($host, $allowedHosts, true)) {
            // If whitelist is configured and host not in it, use first allowed host
            return $allowedHosts[0] ?? 'localhost:17000';
        }

        return $host;
    }

    /**
     * Get secure request URI
     * SECURITY: Sanitize REQUEST_URI to prevent injection
     */
    private function getSecureRequestUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Parse and validate URI
        $parsed = parse_url($uri);
        if ($parsed === false || !isset($parsed['path'])) {
            return '/';
        }

        return $parsed['path'];
    }

    /**
     * Get secure query string parameters
     * SECURITY: Parse query string safely
     */
    private function getSecureQueryString(): array
    {
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        $params = [];
        parse_str($queryString, $params);
        return $params;
    }
    
    /**
     * Clear all template data
     */
    public function clear(): void
    {
        $this->dataStack = [];
        $this->layoutStack = [];
        $this->blocks = [];
        $this->currentBlock = null;
    }
}

/**
 * Template exception
 */
class TemplateException extends Exception
{
}