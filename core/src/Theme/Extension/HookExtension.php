<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Extension;

use Shopologic\Core\Hook\HookSystem;

/**
 * Hook system integration for templates
 */
class HookExtension implements ExtensionInterface
{
    public function getFilters(): array
    {
        return [
            'apply_filters' => [$this, 'applyFilters'],
        ];
    }

    public function getFunctions(): array
    {
        return [
            'do_action' => [$this, 'doAction'],
            'apply_filters' => [$this, 'applyFilters'],
            'has_action' => [$this, 'hasAction'],
            'has_filter' => [$this, 'hasFilter'],
            'hook' => [$this, 'hook'],
        ];
    }

    public function getGlobals(): array
    {
        return [];
    }

    // Function implementations

    public function doAction(string $tag, ...$args): string
    {
        ob_start();
        HookSystem::doAction($tag, ...$args);
        return ob_get_clean();
    }

    public function applyFilters(string $tag, $value, ...$args)
    {
        return HookSystem::applyFilters($tag, $value, ...$args);
    }

    public function hasAction(string $tag): bool
    {
        return HookSystem::hasAction($tag);
    }

    public function hasFilter(string $tag): bool
    {
        return HookSystem::hasFilter($tag);
    }

    public function hook(string $tag, ...$args): string
    {
        // Convenience method that works for both actions and filters
        if (HookSystem::hasFilter($tag)) {
            $value = array_shift($args) ?? '';
            return HookSystem::applyFilters($tag, $value, ...$args);
        }
        
        ob_start();
        HookSystem::doAction($tag, ...$args);
        return ob_get_clean();
    }
}