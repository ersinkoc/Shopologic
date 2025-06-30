<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedAnalyticsReporting\Models;

use Shopologic\Core\Database\Model;

class Dashboard extends Model
{
    protected string $table = 'analytics_dashboards';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'name',
        'description',
        'layout',
        'widgets',
        'filters',
        'settings',
        'is_default',
        'is_public',
        'is_active',
        'created_by',
        'shared_with',
        'refresh_interval',
        'date_range_default',
        'permissions',
        'tags',
        'metadata'
    ];

    protected array $casts = [
        'layout' => 'json',
        'widgets' => 'json',
        'filters' => 'json',
        'settings' => 'json',
        'is_default' => 'boolean',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'created_by' => 'integer',
        'shared_with' => 'json',
        'refresh_interval' => 'integer',
        'permissions' => 'json',
        'tags' => 'json',
        'metadata' => 'json'
    ];

    /**
     * Date range options
     */
    const DATE_RANGE_TODAY = 'today';
    const DATE_RANGE_YESTERDAY = 'yesterday';
    const DATE_RANGE_LAST_7_DAYS = 'last_7_days';
    const DATE_RANGE_LAST_30_DAYS = 'last_30_days';
    const DATE_RANGE_THIS_MONTH = 'this_month';
    const DATE_RANGE_LAST_MONTH = 'last_month';
    const DATE_RANGE_THIS_QUARTER = 'this_quarter';
    const DATE_RANGE_LAST_QUARTER = 'last_quarter';
    const DATE_RANGE_THIS_YEAR = 'this_year';
    const DATE_RANGE_LAST_YEAR = 'last_year';
    const DATE_RANGE_CUSTOM = 'custom';

    /**
     * Widget types
     */
    const WIDGET_METRIC = 'metric';
    const WIDGET_CHART = 'chart';
    const WIDGET_TABLE = 'table';
    const WIDGET_MAP = 'map';
    const WIDGET_PROGRESS = 'progress';
    const WIDGET_LIST = 'list';
    const WIDGET_TEXT = 'text';
    const WIDGET_IFRAME = 'iframe';

    /**
     * Get creator
     */
    public function creator()
    {
        return $this->belongsTo('Shopologic\Core\Models\User', 'created_by');
    }

    /**
     * Get dashboard views
     */
    public function views()
    {
        return $this->hasMany(DashboardView::class, 'dashboard_id');
    }

    /**
     * Scope active dashboards
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope public dashboards
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope default dashboards
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope accessible by user
     */
    public function scopeAccessibleBy($query, int $userId)
    {
        return $query->where(function($q) use ($userId) {
            $q->where('created_by', $userId)
              ->orWhere('is_public', true)
              ->orWhereJsonContains('shared_with', $userId);
        });
    }

    /**
     * Check if user can view dashboard
     */
    public function canBeViewedBy(int $userId): bool
    {
        if ($this->created_by === $userId || $this->is_public) {
            return true;
        }
        
        $sharedWith = $this->shared_with ?? [];
        return in_array($userId, $sharedWith);
    }

    /**
     * Check if user can edit dashboard
     */
    public function canBeEditedBy(int $userId): bool
    {
        if ($this->created_by === $userId) {
            return true;
        }
        
        $permissions = $this->permissions ?? [];
        $editors = $permissions['editors'] ?? [];
        return in_array($userId, $editors);
    }

    /**
     * Get widgets
     */
    public function getWidgets(): array
    {
        return $this->widgets ?? [];
    }

    /**
     * Add widget
     */
    public function addWidget(array $widget): void
    {
        $widgets = $this->getWidgets();
        $widget['id'] = $this->generateWidgetId();
        $widget['created_at'] = now()->toISOString();
        $widgets[] = $widget;
        $this->widgets = $widgets;
        $this->save();
    }

    /**
     * Update widget
     */
    public function updateWidget(string $widgetId, array $updates): bool
    {
        $widgets = $this->getWidgets();
        
        foreach ($widgets as &$widget) {
            if ($widget['id'] === $widgetId) {
                $widget = array_merge($widget, $updates);
                $widget['updated_at'] = now()->toISOString();
                $this->widgets = $widgets;
                $this->save();
                return true;
            }
        }
        
        return false;
    }

    /**
     * Remove widget
     */
    public function removeWidget(string $widgetId): bool
    {
        $widgets = array_filter($this->getWidgets(), function($widget) use ($widgetId) {
            return $widget['id'] !== $widgetId;
        });
        
        $this->widgets = array_values($widgets);
        $this->save();
        return true;
    }

    /**
     * Get widget by ID
     */
    public function getWidget(string $widgetId): ?array
    {
        $widgets = $this->getWidgets();
        
        foreach ($widgets as $widget) {
            if ($widget['id'] === $widgetId) {
                return $widget;
            }
        }
        
        return null;
    }

    /**
     * Get filters
     */
    public function getFilters(): array
    {
        return $this->filters ?? [];
    }

    /**
     * Set filter
     */
    public function setFilter(string $key, $value): void
    {
        $filters = $this->getFilters();
        $filters[$key] = $value;
        $this->filters = $filters;
        $this->save();
    }

    /**
     * Remove filter
     */
    public function removeFilter(string $key): void
    {
        $filters = $this->getFilters();
        unset($filters[$key]);
        $this->filters = $filters;
        $this->save();
    }

    /**
     * Get layout configuration
     */
    public function getLayout(): array
    {
        return $this->layout ?? [
            'columns' => 12,
            'row_height' => 150,
            'margin' => [10, 10],
            'container_padding' => [10, 10]
        ];
    }

    /**
     * Update layout
     */
    public function updateLayout(array $layout): void
    {
        $currentLayout = $this->getLayout();
        $this->layout = array_merge($currentLayout, $layout);
        $this->save();
    }

    /**
     * Get settings
     */
    public function getSettings(): array
    {
        return $this->settings ?? [];
    }

    /**
     * Get setting value
     */
    public function getSetting(string $key, $default = null)
    {
        $settings = $this->getSettings();
        return $settings[$key] ?? $default;
    }

    /**
     * Set setting value
     */
    public function setSetting(string $key, $value): void
    {
        $settings = $this->getSettings();
        $settings[$key] = $value;
        $this->settings = $settings;
        $this->save();
    }

    /**
     * Share with user
     */
    public function shareWith(int $userId, array $permissions = []): void
    {
        $sharedWith = $this->shared_with ?? [];
        if (!in_array($userId, $sharedWith)) {
            $sharedWith[] = $userId;
            $this->shared_with = $sharedWith;
        }
        
        if (!empty($permissions)) {
            $currentPermissions = $this->permissions ?? [];
            foreach ($permissions as $permission) {
                if (!isset($currentPermissions[$permission])) {
                    $currentPermissions[$permission] = [];
                }
                if (!in_array($userId, $currentPermissions[$permission])) {
                    $currentPermissions[$permission][] = $userId;
                }
            }
            $this->permissions = $currentPermissions;
        }
        
        $this->save();
    }

    /**
     * Unshare with user
     */
    public function unshareWith(int $userId): void
    {
        $sharedWith = array_filter($this->shared_with ?? [], function($id) use ($userId) {
            return $id !== $userId;
        });
        $this->shared_with = array_values($sharedWith);
        
        // Remove from all permissions
        $permissions = $this->permissions ?? [];
        foreach ($permissions as $key => $userIds) {
            $permissions[$key] = array_filter($userIds, function($id) use ($userId) {
                return $id !== $userId;
            });
        }
        $this->permissions = $permissions;
        
        $this->save();
    }

    /**
     * Get shared users
     */
    public function getSharedUsers(): array
    {
        return $this->shared_with ?? [];
    }

    /**
     * Record view
     */
    public function recordView(int $userId = null): void
    {
        DashboardView::create([
            'dashboard_id' => $this->id,
            'user_id' => $userId,
            'viewed_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    /**
     * Get view statistics
     */
    public function getViewStatistics(int $days = 30): array
    {
        $views = $this->views()
            ->where('viewed_at', '>=', now()->subDays($days))
            ->get();
        
        return [
            'total_views' => $views->count(),
            'unique_users' => $views->whereNotNull('user_id')->unique('user_id')->count(),
            'views_today' => $views->where('viewed_at', '>=', now()->startOfDay())->count(),
            'views_this_week' => $views->where('viewed_at', '>=', now()->startOfWeek())->count(),
            'most_active_day' => $views->groupBy(function($view) {
                return $view->viewed_at->format('Y-m-d');
            })->map->count()->sortDesc()->keys()->first(),
            'average_daily_views' => $views->count() / max(1, $days)
        ];
    }

    /**
     * Duplicate dashboard
     */
    public function duplicate(string $newName = null, int $newOwnerId = null): self
    {
        $data = $this->toArray();
        unset($data['id'], $data['created_at'], $data['updated_at']);
        
        $data['name'] = $newName ?? $this->name . ' (Copy)';
        $data['created_by'] = $newOwnerId ?? $this->created_by;
        $data['is_default'] = false;
        $data['shared_with'] = [];
        $data['permissions'] = [];
        
        return self::create($data);
    }

    /**
     * Generate unique widget ID
     */
    private function generateWidgetId(): string
    {
        return 'widget_' . uniqid() . '_' . time();
    }

    /**
     * Get tags
     */
    public function getTags(): array
    {
        return $this->tags ?? [];
    }

    /**
     * Add tag
     */
    public function addTag(string $tag): void
    {
        $tags = $this->getTags();
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->tags = $tags;
            $this->save();
        }
    }

    /**
     * Remove tag
     */
    public function removeTag(string $tag): void
    {
        $tags = array_filter($this->getTags(), function($t) use ($tag) {
            return $t !== $tag;
        });
        $this->tags = array_values($tags);
        $this->save();
    }

    /**
     * Format for display
     */
    public function formatForDisplay(): array
    {
        $stats = $this->getViewStatistics();
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_default' => $this->is_default,
            'is_public' => $this->is_public,
            'is_active' => $this->is_active,
            'widget_count' => count($this->getWidgets()),
            'refresh_interval' => $this->refresh_interval,
            'date_range_default' => $this->date_range_default,
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'shared_users_count' => count($this->getSharedUsers()),
            'tags' => $this->getTags(),
            'statistics' => $stats
        ];
    }
}