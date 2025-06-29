<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Extension;

use Shopologic\Core\Theme\Component\ComponentManager;

/**
 * Component management template functions
 */
class ComponentExtension implements ExtensionInterface
{
    private ComponentManager $componentManager;

    public function __construct(ComponentManager $componentManager)
    {
        $this->componentManager = $componentManager;
    }

    public function getFilters(): array
    {
        return [];
    }

    public function getFunctions(): array
    {
        return [
            'component' => [$this, 'component'],
            'render_component' => [$this, 'renderComponent'],
            'component_exists' => [$this, 'componentExists'],
            'component_slot' => [$this, 'componentSlot'],
            'component_props' => [$this, 'componentProps'],
            'editable_region' => [$this, 'editableRegion'],
        ];
    }

    public function getGlobals(): array
    {
        return [];
    }

    // Function implementations

    public function component(string $name, array $props = [], array $slots = []): string
    {
        return $this->componentManager->render($name, $props, $slots);
    }

    public function renderComponent(string $name, array $props = [], array $slots = []): string
    {
        return $this->componentManager->render($name, $props, $slots);
    }

    public function componentExists(string $name): bool
    {
        return $this->componentManager->exists($name);
    }

    public function componentSlot(string $name, string $default = ''): string
    {
        // Get slots from current context
        $slots = $GLOBALS['_component_slots'] ?? [];
        return $slots[$name] ?? $default;
    }

    public function componentProps(string $key = null, $default = null)
    {
        // Get props from current context
        $props = $GLOBALS['_component_props'] ?? [];
        
        if ($key === null) {
            return $props;
        }
        
        return $props[$key] ?? $default;
    }

    public function editableRegion(string $id, array $options = []): string
    {
        $isPreview = $GLOBALS['_preview_mode'] ?? false;
        
        if (!$isPreview) {
            return '';
        }
        
        $attributes = [
            'data-editable-region' => $id,
            'data-accepts' => implode(',', $options['accepts'] ?? ['*']),
            'data-max-items' => $options['max_items'] ?? '',
            'data-min-items' => $options['min_items'] ?? ''
        ];
        
        $attrs = '';
        foreach ($attributes as $key => $value) {
            if (!empty($value)) {
                $attrs .= sprintf(' %s="%s"', $key, htmlspecialchars((string)$value));
            }
        }
        
        return $attrs;
    }
}