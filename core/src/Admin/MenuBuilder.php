<?php

declare(strict_types=1);

namespace Shopologic\Core\Admin;

/**
 * Admin menu builder
 */
class MenuBuilder
{
    protected array $groups = [];
    protected array $items = [];

    /**
     * Add a menu group
     */
    public function addGroup(string $id, array $config): self
    {
        $this->groups[$id] = array_merge([
            'id' => $id,
            'title' => ucfirst($id),
            'order' => 0,
            'icon' => null,
            'items' => []
        ], $config);

        return $this;
    }

    /**
     * Add a menu item
     */
    public function addItem(string $groupId, string $id, array $config): self
    {
        if (!isset($this->groups[$groupId])) {
            throw new \InvalidArgumentException("Menu group '$groupId' does not exist");
        }

        $this->items[$groupId][$id] = array_merge([
            'id' => $id,
            'title' => ucfirst($id),
            'url' => '#',
            'icon' => null,
            'order' => 0,
            'permissions' => [],
            'badge' => null
        ], $config);

        return $this;
    }

    /**
     * Get all menu groups
     */
    public function getGroups(): array
    {
        // Sort groups by order
        uasort($this->groups, function($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        return $this->groups;
    }

    /**
     * Get items for a specific group
     */
    public function getItems(string $groupId): array
    {
        if (!isset($this->items[$groupId])) {
            return [];
        }

        // Sort items by order
        uasort($this->items[$groupId], function($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        return $this->items[$groupId];
    }

    /**
     * Get the complete menu structure
     */
    public function getMenu(): array
    {
        $menu = [];

        foreach ($this->getGroups() as $groupId => $group) {
            $group['items'] = $this->getItems($groupId);
            $menu[] = $group;
        }

        return $menu;
    }

    /**
     * Remove a group
     */
    public function removeGroup(string $id): self
    {
        unset($this->groups[$id]);
        unset($this->items[$id]);

        return $this;
    }

    /**
     * Remove an item
     */
    public function removeItem(string $groupId, string $itemId): self
    {
        unset($this->items[$groupId][$itemId]);

        return $this;
    }

    /**
     * Check if a group exists
     */
    public function hasGroup(string $id): bool
    {
        return isset($this->groups[$id]);
    }

    /**
     * Check if an item exists
     */
    public function hasItem(string $groupId, string $itemId): bool
    {
        return isset($this->items[$groupId][$itemId]);
    }
}