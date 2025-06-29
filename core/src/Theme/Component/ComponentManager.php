<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Component;

use Shopologic\Core\Theme\TemplateEngine;
use Shopologic\Core\Cache\CacheInterface;
use Shopologic\Core\Events\EventDispatcherInterface;

/**
 * Manages reusable theme components
 */
class ComponentManager
{
    private TemplateEngine $templateEngine;
    private CacheInterface $cache;
    private EventDispatcherInterface $eventDispatcher;
    private array $components = [];
    private array $componentPaths = [];
    private bool $debug = false;

    public function __construct(
        TemplateEngine $templateEngine,
        CacheInterface $cache,
        EventDispatcherInterface $eventDispatcher,
        bool $debug = false
    ) {
        $this->templateEngine = $templateEngine;
        $this->cache = $cache;
        $this->eventDispatcher = $eventDispatcher;
        $this->debug = $debug;
        
        $this->loadBuiltInComponents();
    }

    /**
     * Register a component
     */
    public function register(string $name, array $config): void
    {
        $this->validateComponentConfig($config);
        
        $this->components[$name] = array_merge([
            'name' => $name,
            'category' => 'general',
            'icon' => 'component',
            'cacheable' => true,
            'cache_ttl' => 3600,
            'props' => [],
            'slots' => [],
            'events' => []
        ], $config);
        
        // Trigger registration event
        $this->eventDispatcher->dispatch('component.registered', [
            'name' => $name,
            'config' => $this->components[$name]
        ]);
    }

    /**
     * Register component directory
     */
    public function addPath(string $path, string $namespace = 'default'): void
    {
        if (!is_dir($path)) {
            throw new ComponentException('Component path does not exist: ' . $path);
        }
        
        $this->componentPaths[$namespace] = rtrim($path, '/\\');
        
        // Auto-discover components in path
        $this->discoverComponents($path, $namespace);
    }

    /**
     * Render a component
     */
    public function render(string $name, array $props = [], array $slots = []): string
    {
        if (!$this->exists($name)) {
            throw new ComponentException('Component not found: ' . $name);
        }
        
        $component = $this->components[$name];
        
        // Validate props
        $props = $this->validateProps($component, $props);
        
        // Check cache
        if ($component['cacheable'] && !$this->debug) {
            $cacheKey = $this->getCacheKey($name, $props);
            $cached = $this->cache->get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }
        }
        
        // Trigger before render event
        $this->eventDispatcher->dispatch('component.before_render', [
            'name' => $name,
            'props' => $props,
            'slots' => $slots
        ]);
        
        // Render component
        $output = $this->doRender($component, $props, $slots);
        
        // Trigger after render event
        $this->eventDispatcher->dispatch('component.after_render', [
            'name' => $name,
            'props' => $props,
            'output' => &$output
        ]);
        
        // Cache output
        if ($component['cacheable'] && !$this->debug) {
            $this->cache->set($cacheKey, $output, $component['cache_ttl']);
        }
        
        return $output;
    }

    /**
     * Check if component exists
     */
    public function exists(string $name): bool
    {
        return isset($this->components[$name]);
    }

    /**
     * Get component configuration
     */
    public function getComponent(string $name): ?array
    {
        return $this->components[$name] ?? null;
    }

    /**
     * Get all available components
     */
    public function getAvailableComponents(): array
    {
        $components = [];
        
        foreach ($this->components as $name => $config) {
            $components[] = [
                'name' => $name,
                'label' => $config['label'] ?? ucfirst(str_replace('-', ' ', $name)),
                'description' => $config['description'] ?? '',
                'category' => $config['category'],
                'icon' => $config['icon'],
                'props' => $this->getComponentPropsSchema($config),
                'preview' => $config['preview'] ?? null
            ];
        }
        
        return $components;
    }

    /**
     * Get components by category
     */
    public function getComponentsByCategory(string $category): array
    {
        return array_filter($this->components, function($component) use ($category) {
            return $component['category'] === $category;
        });
    }

    /**
     * Create component instance
     */
    public function createInstance(string $name, array $props = []): ComponentInstance
    {
        if (!$this->exists($name)) {
            throw new ComponentException('Component not found: ' . $name);
        }
        
        $component = $this->components[$name];
        $props = $this->validateProps($component, $props);
        
        return new ComponentInstance($name, $props, $this);
    }

    // Private methods

    private function doRender(array $component, array $props, array $slots): string
    {
        // Add component context
        $context = array_merge($props, [
            '_component' => $component['name'],
            '_props' => $props,
            '_slots' => $slots
        ]);
        
        // Render template
        $template = $this->getComponentTemplate($component);
        
        return $this->templateEngine->render($template, $context);
    }

    private function getComponentTemplate(array $component): string
    {
        // Check for explicit template
        if (isset($component['template'])) {
            return $component['template'];
        }
        
        // Look for template file
        $templateFile = $component['name'] . '.twig';
        
        foreach ($this->componentPaths as $namespace => $path) {
            $fullPath = $path . '/' . $templateFile;
            if (file_exists($fullPath)) {
                return '@' . $namespace . '/' . $templateFile;
            }
        }
        
        throw new ComponentException('Template not found for component: ' . $component['name']);
    }

    private function validateComponentConfig(array $config): void
    {
        $required = ['label', 'description'];
        
        foreach ($required as $field) {
            if (!isset($config[$field])) {
                throw new ComponentException('Missing required field: ' . $field);
            }
        }
        
        // Validate props schema
        if (isset($config['props'])) {
            foreach ($config['props'] as $propName => $propConfig) {
                $this->validatePropConfig($propName, $propConfig);
            }
        }
    }

    private function validatePropConfig(string $name, array $config): void
    {
        $validTypes = ['string', 'number', 'boolean', 'array', 'object', 'select', 'color', 'image'];
        
        if (!isset($config['type']) || !in_array($config['type'], $validTypes)) {
            throw new ComponentException(sprintf('Invalid prop type for "%s"', $name));
        }
        
        if ($config['type'] === 'select' && !isset($config['options'])) {
            throw new ComponentException(sprintf('Select prop "%s" must have options', $name));
        }
    }

    private function validateProps(array $component, array $props): array
    {
        $validated = [];
        $propConfigs = $component['props'] ?? [];
        
        foreach ($propConfigs as $propName => $propConfig) {
            $value = $props[$propName] ?? $propConfig['default'] ?? null;
            
            // Check required
            if (($propConfig['required'] ?? false) && $value === null) {
                throw new ComponentException(sprintf('Required prop missing: %s', $propName));
            }
            
            // Validate type
            if ($value !== null) {
                $value = $this->validatePropValue($value, $propConfig);
            }
            
            $validated[$propName] = $value;
        }
        
        return $validated;
    }

    private function validatePropValue($value, array $config)
    {
        switch ($config['type']) {
            case 'string':
                return (string) $value;
                
            case 'number':
                return is_numeric($value) ? (float) $value : 0;
                
            case 'boolean':
                return (bool) $value;
                
            case 'array':
                return is_array($value) ? $value : [];
                
            case 'object':
                return is_array($value) || is_object($value) ? $value : [];
                
            case 'select':
                $options = $config['options'] ?? [];
                return in_array($value, $options) ? $value : ($config['default'] ?? null);
                
            case 'color':
                return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? $value : '#000000';
                
            case 'image':
                return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
                
            default:
                return $value;
        }
    }

    private function getCacheKey(string $name, array $props): string
    {
        return 'component_' . $name . '_' . md5(json_encode($props));
    }

    private function getComponentPropsSchema(array $component): array
    {
        $schema = [];
        
        foreach ($component['props'] ?? [] as $propName => $propConfig) {
            $schema[$propName] = [
                'type' => $propConfig['type'],
                'label' => $propConfig['label'] ?? ucfirst($propName),
                'description' => $propConfig['description'] ?? '',
                'default' => $propConfig['default'] ?? null,
                'required' => $propConfig['required'] ?? false
            ];
            
            if ($propConfig['type'] === 'select') {
                $schema[$propName]['options'] = $propConfig['options'];
            }
            
            if (isset($propConfig['min'])) {
                $schema[$propName]['min'] = $propConfig['min'];
            }
            
            if (isset($propConfig['max'])) {
                $schema[$propName]['max'] = $propConfig['max'];
            }
        }
        
        return $schema;
    }

    private function discoverComponents(string $path, string $namespace): void
    {
        $configFiles = glob($path . '/*/component.json');
        
        foreach ($configFiles as $configFile) {
            $componentName = basename(dirname($configFile));
            $config = json_decode(file_get_contents($configFile), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }
            
            $config['namespace'] = $namespace;
            $this->register($componentName, $config);
        }
    }

    private function loadBuiltInComponents(): void
    {
        // Register built-in components
        $this->register('hero-banner', [
            'label' => 'Hero Banner',
            'description' => 'Full-width banner with text overlay',
            'category' => 'content',
            'icon' => 'image',
            'props' => [
                'title' => [
                    'type' => 'string',
                    'label' => 'Title',
                    'default' => 'Welcome to Our Store'
                ],
                'subtitle' => [
                    'type' => 'string',
                    'label' => 'Subtitle',
                    'default' => ''
                ],
                'background_image' => [
                    'type' => 'image',
                    'label' => 'Background Image',
                    'required' => true
                ],
                'text_color' => [
                    'type' => 'color',
                    'label' => 'Text Color',
                    'default' => '#ffffff'
                ],
                'overlay_opacity' => [
                    'type' => 'number',
                    'label' => 'Overlay Opacity',
                    'default' => 0.5,
                    'min' => 0,
                    'max' => 1
                ]
            ]
        ]);

        $this->register('product-grid', [
            'label' => 'Product Grid',
            'description' => 'Display products in a responsive grid',
            'category' => 'products',
            'icon' => 'grid',
            'props' => [
                'columns' => [
                    'type' => 'number',
                    'label' => 'Columns',
                    'default' => 3,
                    'min' => 1,
                    'max' => 6
                ],
                'limit' => [
                    'type' => 'number',
                    'label' => 'Products to Show',
                    'default' => 12
                ],
                'category' => [
                    'type' => 'string',
                    'label' => 'Category Filter',
                    'default' => ''
                ],
                'show_price' => [
                    'type' => 'boolean',
                    'label' => 'Show Price',
                    'default' => true
                ],
                'show_add_to_cart' => [
                    'type' => 'boolean',
                    'label' => 'Show Add to Cart',
                    'default' => true
                ]
            ]
        ]);

        $this->register('navigation-menu', [
            'label' => 'Navigation Menu',
            'description' => 'Site navigation menu',
            'category' => 'navigation',
            'icon' => 'menu',
            'props' => [
                'menu_id' => [
                    'type' => 'string',
                    'label' => 'Menu',
                    'required' => true
                ],
                'style' => [
                    'type' => 'select',
                    'label' => 'Style',
                    'options' => ['horizontal', 'vertical', 'dropdown'],
                    'default' => 'horizontal'
                ],
                'align' => [
                    'type' => 'select',
                    'label' => 'Alignment',
                    'options' => ['left', 'center', 'right'],
                    'default' => 'left'
                ]
            ]
        ]);
    }
}

class ComponentException extends \Exception {}

class ComponentInstance
{
    private string $name;
    private array $props;
    private ComponentManager $manager;
    private string $id;

    public function __construct(string $name, array $props, ComponentManager $manager)
    {
        $this->name = $name;
        $this->props = $props;
        $this->manager = $manager;
        $this->id = uniqid($name . '_');
    }

    public function render(array $slots = []): string
    {
        return $this->manager->render($this->name, $this->props, $slots);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getProps(): array
    {
        return $this->props;
    }

    public function setProp(string $key, $value): self
    {
        $this->props[$key] = $value;
        return $this;
    }
}