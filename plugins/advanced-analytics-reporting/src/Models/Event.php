<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedAnalyticsReporting\Models;

use Shopologic\Core\Database\Model;

class Event extends Model
{
    protected string $table = 'analytics_events';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'event_type',
        'event_category',
        'event_action',
        'event_label',
        'event_value',
        'session_id',
        'user_id',
        'customer_id',
        'page_url',
        'referrer_url',
        'user_agent',
        'ip_address',
        'device_type',
        'browser',
        'os',
        'country',
        'region',
        'city',
        'custom_data',
        'timestamp'
    ];

    protected array $casts = [
        'event_value' => 'float',
        'custom_data' => 'json',
        'timestamp' => 'datetime'
    ];

    /**
     * Get session
     */
    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
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
     * Scope by event type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('event_category', $category);
    }

    /**
     * Scope by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('timestamp', [$startDate, $endDate]);
    }

    /**
     * Scope by session
     */
    public function scopeBySession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * Get formatted timestamp
     */
    public function getFormattedTimestamp(): string
    {
        return $this->timestamp->format('Y-m-d H:i:s');
    }

    /**
     * Get event duration (if applicable)
     */
    public function getDuration(): ?float
    {
        $customData = $this->custom_data;
        return isset($customData['duration']) ? (float)$customData['duration'] : null;
    }

    /**
     * Check if event is conversion
     */
    public function isConversion(): bool
    {
        return in_array($this->event_type, ['purchase', 'signup', 'lead', 'goal']);
    }

    /**
     * Get conversion value
     */
    public function getConversionValue(): float
    {
        if (!$this->isConversion()) {
            return 0.0;
        }

        return $this->event_value ?? 0.0;
    }

    /**
     * Get device info
     */
    public function getDeviceInfo(): array
    {
        return [
            'type' => $this->device_type,
            'browser' => $this->browser,
            'os' => $this->os
        ];
    }

    /**
     * Get location info
     */
    public function getLocationInfo(): array
    {
        return [
            'country' => $this->country,
            'region' => $this->region,
            'city' => $this->city
        ];
    }

    /**
     * Check if event is from mobile
     */
    public function isMobile(): bool
    {
        return in_array($this->device_type, ['mobile', 'tablet']);
    }

    /**
     * Get event properties
     */
    public function getProperties(): array
    {
        return [
            'category' => $this->event_category,
            'action' => $this->event_action,
            'label' => $this->event_label,
            'value' => $this->event_value,
            'custom' => $this->custom_data
        ];
    }

    /**
     * Track similar events
     */
    public function getSimilarEvents(int $limit = 10)
    {
        return self::where('event_type', $this->event_type)
            ->where('event_category', $this->event_category)
            ->where('id', '!=', $this->id)
            ->orderBy('timestamp', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get next event in session
     */
    public function getNextEventInSession()
    {
        return self::where('session_id', $this->session_id)
            ->where('timestamp', '>', $this->timestamp)
            ->orderBy('timestamp', 'asc')
            ->first();
    }

    /**
     * Get previous event in session
     */
    public function getPreviousEventInSession()
    {
        return self::where('session_id', $this->session_id)
            ->where('timestamp', '<', $this->timestamp)
            ->orderBy('timestamp', 'desc')
            ->first();
    }

    /**
     * Calculate time to next event
     */
    public function getTimeToNextEvent(): ?int
    {
        $nextEvent = $this->getNextEventInSession();
        
        if (!$nextEvent) {
            return null;
        }

        return $nextEvent->timestamp->timestamp - $this->timestamp->timestamp;
    }

    /**
     * Is entrance event
     */
    public function isEntrance(): bool
    {
        return self::where('session_id', $this->session_id)
            ->where('timestamp', '<', $this->timestamp)
            ->count() === 0;
    }

    /**
     * Is exit event
     */
    public function isExit(): bool
    {
        return self::where('session_id', $this->session_id)
            ->where('timestamp', '>', $this->timestamp)
            ->count() === 0;
    }

    /**
     * Get event path
     */
    public function getEventPath(): array
    {
        $events = self::where('session_id', $this->session_id)
            ->orderBy('timestamp')
            ->get();

        return $events->map(function($event) {
            return [
                'type' => $event->event_type,
                'category' => $event->event_category,
                'action' => $event->event_action,
                'timestamp' => $event->timestamp
            ];
        })->toArray();
    }
}