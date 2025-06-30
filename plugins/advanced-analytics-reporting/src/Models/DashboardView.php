<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedAnalyticsReporting\Models;

use Shopologic\Core\Database\Model;

class DashboardView extends Model
{
    protected string $table = 'dashboard_views';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'dashboard_id',
        'user_id',
        'session_id',
        'viewed_at',
        'duration_seconds',
        'ip_address',
        'user_agent',
        'referrer_url',
        'screen_resolution',
        'device_type',
        'browser',
        'os',
        'country',
        'city',
        'interactions',
        'widgets_viewed'
    ];

    protected array $casts = [
        'dashboard_id' => 'integer',
        'user_id' => 'integer',
        'viewed_at' => 'datetime',
        'duration_seconds' => 'integer',
        'interactions' => 'json',
        'widgets_viewed' => 'json'
    ];

    /**
     * Device types
     */
    const DEVICE_DESKTOP = 'desktop';
    const DEVICE_TABLET = 'tablet';
    const DEVICE_MOBILE = 'mobile';

    /**
     * Get dashboard
     */
    public function dashboard()
    {
        return $this->belongsTo(Dashboard::class, 'dashboard_id');
    }

    /**
     * Get user
     */
    public function user()
    {
        return $this->belongsTo('Shopologic\Core\Models\User', 'user_id');
    }

    /**
     * Scope by user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('viewed_at', [$startDate, $endDate]);
    }

    /**
     * Scope by device type
     */
    public function scopeByDeviceType($query, string $deviceType)
    {
        return $query->where('device_type', $deviceType);
    }

    /**
     * Scope recent views
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('viewed_at', '>=', now()->subDays($days));
    }

    /**
     * Update duration
     */
    public function updateDuration(int $durationSeconds): void
    {
        $this->duration_seconds = $durationSeconds;
        $this->save();
    }

    /**
     * Add interaction
     */
    public function addInteraction(string $type, array $data = []): void
    {
        $interactions = $this->interactions ?? [];
        $interactions[] = [
            'type' => $type,
            'data' => $data,
            'timestamp' => now()->toISOString()
        ];
        $this->interactions = $interactions;
        $this->save();
    }

    /**
     * Record widget view
     */
    public function recordWidgetView(string $widgetId, int $durationSeconds = null): void
    {
        $widgets = $this->widgets_viewed ?? [];
        
        $existingIndex = null;
        foreach ($widgets as $index => $widget) {
            if ($widget['widget_id'] === $widgetId) {
                $existingIndex = $index;
                break;
            }
        }
        
        if ($existingIndex !== null) {
            $widgets[$existingIndex]['view_count']++;
            if ($durationSeconds !== null) {
                $widgets[$existingIndex]['total_duration'] += $durationSeconds;
            }
        } else {
            $widgets[] = [
                'widget_id' => $widgetId,
                'view_count' => 1,
                'total_duration' => $durationSeconds ?? 0,
                'first_viewed_at' => now()->toISOString()
            ];
        }
        
        $this->widgets_viewed = $widgets;
        $this->save();
    }

    /**
     * Get interaction count by type
     */
    public function getInteractionCount(string $type = null): int
    {
        $interactions = $this->interactions ?? [];
        
        if ($type === null) {
            return count($interactions);
        }
        
        return count(array_filter($interactions, function($interaction) use ($type) {
            return $interaction['type'] === $type;
        }));
    }

    /**
     * Get most viewed widget
     */
    public function getMostViewedWidget(): ?array
    {
        $widgets = $this->widgets_viewed ?? [];
        
        if (empty($widgets)) {
            return null;
        }
        
        usort($widgets, function($a, $b) {
            return $b['view_count'] - $a['view_count'];
        });
        
        return $widgets[0];
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDuration(): string
    {
        if (!$this->duration_seconds) {
            return 'N/A';
        }
        
        $seconds = $this->duration_seconds;
        
        if ($seconds < 60) {
            return $seconds . 's';
        }
        
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        return $minutes . 'm ' . $remainingSeconds . 's';
    }

    /**
     * Parse user agent
     */
    public static function parseUserAgent(string $userAgent): array
    {
        // Basic user agent parsing (in real implementation, use a proper library)
        $result = [
            'browser' => 'Unknown',
            'os' => 'Unknown',
            'device_type' => self::DEVICE_DESKTOP
        ];
        
        // Detect mobile devices
        if (preg_match('/(Mobile|Android|iPhone|iPad|BlackBerry|Opera Mini)/i', $userAgent)) {
            if (preg_match('/iPad/i', $userAgent)) {
                $result['device_type'] = self::DEVICE_TABLET;
            } else {
                $result['device_type'] = self::DEVICE_MOBILE;
            }
        }
        
        // Detect browser
        if (preg_match('/Chrome/i', $userAgent)) {
            $result['browser'] = 'Chrome';
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            $result['browser'] = 'Firefox';
        } elseif (preg_match('/Safari/i', $userAgent)) {
            $result['browser'] = 'Safari';
        } elseif (preg_match('/Edge/i', $userAgent)) {
            $result['browser'] = 'Edge';
        }
        
        // Detect OS
        if (preg_match('/Windows/i', $userAgent)) {
            $result['os'] = 'Windows';
        } elseif (preg_match('/Mac/i', $userAgent)) {
            $result['os'] = 'macOS';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $result['os'] = 'Linux';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $result['os'] = 'Android';
        } elseif (preg_match('/iOS/i', $userAgent)) {
            $result['os'] = 'iOS';
        }
        
        return $result;
    }

    /**
     * Format for display
     */
    public function formatForDisplay(): array
    {
        return [
            'id' => $this->id,
            'dashboard_name' => $this->dashboard->name ?? 'Unknown',
            'user_name' => $this->user?->name ?? 'Anonymous',
            'viewed_at' => $this->viewed_at->format('Y-m-d H:i:s'),
            'duration' => $this->getFormattedDuration(),
            'device_type' => $this->device_type,
            'browser' => $this->browser,
            'os' => $this->os,
            'country' => $this->country,
            'city' => $this->city,
            'interaction_count' => $this->getInteractionCount(),
            'widgets_viewed_count' => count($this->widgets_viewed ?? []),
            'most_viewed_widget' => $this->getMostViewedWidget(),
            'referrer_url' => $this->referrer_url
        ];
    }

    /**
     * Get view statistics for period
     */
    public static function getStatisticsForPeriod($startDate, $endDate): array
    {
        $views = self::whereBetween('viewed_at', [$startDate, $endDate])->get();
        
        return [
            'total_views' => $views->count(),
            'unique_users' => $views->whereNotNull('user_id')->unique('user_id')->count(),
            'unique_sessions' => $views->whereNotNull('session_id')->unique('session_id')->count(),
            'average_duration' => $views->avg('duration_seconds'),
            'total_duration' => $views->sum('duration_seconds'),
            'mobile_views' => $views->where('device_type', self::DEVICE_MOBILE)->count(),
            'tablet_views' => $views->where('device_type', self::DEVICE_TABLET)->count(),
            'desktop_views' => $views->where('device_type', self::DEVICE_DESKTOP)->count(),
            'top_browsers' => $views->groupBy('browser')->map->count()->sortDesc()->take(5)->toArray(),
            'top_os' => $views->groupBy('os')->map->count()->sortDesc()->take(5)->toArray(),
            'top_countries' => $views->whereNotNull('country')->groupBy('country')->map->count()->sortDesc()->take(10)->toArray(),
            'bounce_rate' => $views->where('duration_seconds', '<', 30)->count() / max(1, $views->count()) * 100
        ];
    }
}