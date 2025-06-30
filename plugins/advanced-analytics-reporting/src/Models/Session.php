<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedAnalyticsReporting\Models;

use Shopologic\Core\Database\Model;

class Session extends Model
{
    protected string $table = 'analytics_sessions';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'session_id',
        'user_id',
        'customer_id',
        'started_at',
        'ended_at',
        'duration',
        'page_views',
        'events_count',
        'bounce',
        'entry_page',
        'exit_page',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'device_type',
        'browser',
        'os',
        'ip_address',
        'country',
        'region',
        'city',
        'revenue',
        'conversions',
        'goals_completed'
    ];

    protected array $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration' => 'integer',
        'page_views' => 'integer',
        'events_count' => 'integer',
        'bounce' => 'boolean',
        'revenue' => 'float',
        'conversions' => 'integer',
        'goals_completed' => 'json'
    ];

    /**
     * Get events
     */
    public function events()
    {
        return $this->hasMany(Event::class, 'session_id', 'session_id');
    }

    /**
     * Get user
     */
    public function user()
    {
        return $this->belongsTo('Shopologic\Core\Models\User', 'user_id');
    }

    /**
     * Get customer
     */
    public function customer()
    {
        return $this->belongsTo('Shopologic\Commerce\Models\Customer', 'customer_id');
    }

    /**
     * Scope active sessions
     */
    public function scopeActive($query)
    {
        return $query->whereNull('ended_at')
            ->where('started_at', '>', now()->subMinutes(30));
    }

    /**
     * Scope completed sessions
     */
    public function scopeCompleted($query)
    {
        return $query->whereNotNull('ended_at');
    }

    /**
     * Scope bounced sessions
     */
    public function scopeBounced($query)
    {
        return $query->where('bounce', true);
    }

    /**
     * Scope sessions with conversions
     */
    public function scopeWithConversions($query)
    {
        return $query->where('conversions', '>', 0);
    }

    /**
     * Get session duration in minutes
     */
    public function getDurationInMinutes(): float
    {
        return round($this->duration / 60, 2);
    }

    /**
     * Get average time per page
     */
    public function getAverageTimePerPage(): float
    {
        if ($this->page_views === 0) {
            return 0;
        }

        return round($this->duration / $this->page_views, 2);
    }

    /**
     * Check if session is active
     */
    public function isActive(): bool
    {
        return $this->ended_at === null && 
               $this->started_at->diffInMinutes(now()) < 30;
    }

    /**
     * Check if session has conversion
     */
    public function hasConversion(): bool
    {
        return $this->conversions > 0;
    }

    /**
     * Get traffic source
     */
    public function getTrafficSource(): string
    {
        if ($this->utm_source) {
            return $this->utm_source;
        }

        if ($this->referrer) {
            $domain = parse_url($this->referrer, PHP_URL_HOST);
            if ($domain) {
                return $domain;
            }
        }

        return 'direct';
    }

    /**
     * Get traffic medium
     */
    public function getTrafficMedium(): string
    {
        if ($this->utm_medium) {
            return $this->utm_medium;
        }

        if ($this->referrer) {
            return 'referral';
        }

        return 'direct';
    }

    /**
     * Get device category
     */
    public function getDeviceCategory(): string
    {
        return match($this->device_type) {
            'mobile' => 'Mobile',
            'tablet' => 'Tablet',
            'desktop' => 'Desktop',
            default => 'Unknown'
        };
    }

    /**
     * Get location string
     */
    public function getLocation(): string
    {
        $parts = array_filter([
            $this->city,
            $this->region,
            $this->country
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get engagement level
     */
    public function getEngagementLevel(): string
    {
        if ($this->bounce) {
            return 'low';
        }

        if ($this->duration > 300 || $this->page_views > 5) {
            return 'high';
        }

        return 'medium';
    }

    /**
     * Calculate session value
     */
    public function calculateValue(): float
    {
        $value = $this->revenue;

        // Add value for engagement
        $value += ($this->page_views * 0.1);
        $value += ($this->duration / 60 * 0.05);

        // Add value for conversions
        $value += ($this->conversions * 10);

        return round($value, 2);
    }

    /**
     * Get page path
     */
    public function getPagePath(): array
    {
        return $this->events()
            ->where('event_type', 'pageview')
            ->orderBy('timestamp')
            ->pluck('page_url')
            ->toArray();
    }

    /**
     * Get conversion funnel
     */
    public function getConversionFunnel(): array
    {
        $events = $this->events()
            ->whereIn('event_type', ['pageview', 'add_to_cart', 'checkout', 'purchase'])
            ->orderBy('timestamp')
            ->get();

        $funnel = [];
        foreach ($events as $event) {
            $funnel[] = [
                'step' => $event->event_type,
                'timestamp' => $event->timestamp,
                'page' => $event->page_url
            ];
        }

        return $funnel;
    }

    /**
     * End session
     */
    public function end(): void
    {
        if (!$this->ended_at) {
            $this->ended_at = now();
            $this->duration = $this->started_at->diffInSeconds($this->ended_at);
            $this->save();
        }
    }

    /**
     * Mark as bounce
     */
    public function markAsBounce(): void
    {
        $this->bounce = true;
        $this->save();
    }

    /**
     * Add conversion
     */
    public function addConversion(float $value = 0): void
    {
        $this->conversions++;
        $this->revenue += $value;
        $this->save();
    }

    /**
     * Update metrics
     */
    public function updateMetrics(): void
    {
        $events = $this->events;
        
        $this->page_views = $events->where('event_type', 'pageview')->count();
        $this->events_count = $events->count();
        
        if ($this->page_views <= 1 && $this->duration < 10) {
            $this->bounce = true;
        }
        
        $this->save();
    }
}