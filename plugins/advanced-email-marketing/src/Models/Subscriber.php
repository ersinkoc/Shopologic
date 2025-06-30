<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Models;

use Shopologic\Core\Database\Model;

class Subscriber extends Model
{
    protected string $table = 'email_subscribers';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'email',
        'customer_id',
        'first_name',
        'last_name',
        'status',
        'source',
        'tags',
        'custom_fields',
        'engagement_score',
        'confirmed_at',
        'unsubscribed_at',
        'last_activity_at',
        'ip_address',
        'user_agent',
        'timezone',
        'language',
        'double_opt_in',
        'unsubscribe_reason',
        'confirmation_token',
        'preferences',
        'behavior_data',
        'bounce_count',
        'country',
        'region',
        'city'
    ];

    protected array $casts = [
        'customer_id' => 'integer',
        'tags' => 'json',
        'custom_fields' => 'json',
        'engagement_score' => 'float',
        'confirmed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'double_opt_in' => 'boolean',
        'preferences' => 'json',
        'behavior_data' => 'json',
        'bounce_count' => 'integer'
    ];

    /**
     * Get customer
     */
    public function customer()
    {
        return $this->belongsTo('Shopologic\Commerce\Models\Customer', 'customer_id');
    }

    /**
     * Get email sends
     */
    public function emailSends()
    {
        return $this->hasMany(EmailSend::class, 'subscriber_id');
    }

    /**
     * Get segments
     */
    public function segments()
    {
        return $this->belongsToMany(Segment::class, 'segment_members', 'subscriber_id', 'segment_id')
            ->withTimestamps()
            ->withPivot('criteria_match');
    }

    /**
     * Get point transactions
     */
    public function pointTransactions()
    {
        return $this->hasMany(PointTransaction::class, 'subscriber_id');
    }

    /**
     * Scope active subscribers
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'subscribed');
    }

    /**
     * Scope confirmed subscribers
     */
    public function scopeConfirmed($query)
    {
        return $query->whereNotNull('confirmed_at');
    }

    /**
     * Scope by engagement level
     */
    public function scopeByEngagementLevel($query, string $level)
    {
        return match($level) {
            'high' => $query->where('engagement_score', '>=', 80),
            'medium' => $query->whereBetween('engagement_score', [50, 79]),
            'low' => $query->where('engagement_score', '<', 50),
            default => $query
        };
    }

    /**
     * Check if subscribed
     */
    public function isSubscribed(): bool
    {
        return $this->status === 'subscribed';
    }

    /**
     * Check if confirmed
     */
    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    /**
     * Check if bounced
     */
    public function isBounced(): bool
    {
        return $this->status === 'bounced';
    }

    /**
     * Get full name
     */
    public function getFullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get display name
     */
    public function getDisplayName(): string
    {
        if ($this->first_name) {
            return $this->first_name;
        }
        
        return explode('@', $this->email)[0];
    }

    /**
     * Get engagement level
     */
    public function getEngagementLevel(): string
    {
        if ($this->engagement_score >= 80) return 'high';
        if ($this->engagement_score >= 50) return 'medium';
        return 'low';
    }

    /**
     * Add tag
     */
    public function addTag(string $tag): void
    {
        $tags = $this->tags ?? [];
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
        $tags = $this->tags ?? [];
        $tags = array_filter($tags, fn($t) => $t !== $tag);
        $this->tags = array_values($tags);
        $this->save();
    }

    /**
     * Has tag
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags ?? []);
    }

    /**
     * Update custom field
     */
    public function updateCustomField(string $key, $value): void
    {
        $customFields = $this->custom_fields ?? [];
        $customFields[$key] = $value;
        $this->custom_fields = $customFields;
        $this->save();
    }

    /**
     * Get custom field
     */
    public function getCustomField(string $key, $default = null)
    {
        return $this->custom_fields[$key] ?? $default;
    }

    /**
     * Update preference
     */
    public function updatePreference(string $key, $value): void
    {
        $preferences = $this->preferences ?? [];
        $preferences[$key] = $value;
        $this->preferences = $preferences;
        $this->save();
    }

    /**
     * Get preference
     */
    public function getPreference(string $key, $default = null)
    {
        return $this->preferences[$key] ?? $default;
    }

    /**
     * Track activity
     */
    public function trackActivity(): void
    {
        $this->last_activity_at = now();
        $this->save();
    }

    /**
     * Subscribe
     */
    public function subscribe(): void
    {
        $this->status = 'subscribed';
        $this->unsubscribed_at = null;
        $this->unsubscribe_reason = null;
        $this->save();
    }

    /**
     * Unsubscribe
     */
    public function unsubscribe(string $reason = null): void
    {
        $this->status = 'unsubscribed';
        $this->unsubscribed_at = now();
        $this->unsubscribe_reason = $reason;
        $this->save();
    }

    /**
     * Mark as bounced
     */
    public function markAsBounced(): void
    {
        $this->status = 'bounced';
        $this->bounce_count++;
        $this->save();
    }

    /**
     * Mark as complained
     */
    public function markAsComplained(): void
    {
        $this->status = 'complained';
        $this->save();
    }

    /**
     * Confirm subscription
     */
    public function confirm(): void
    {
        $this->confirmed_at = now();
        $this->confirmation_token = null;
        if ($this->status === 'pending') {
            $this->status = 'subscribed';
        }
        $this->save();
    }

    /**
     * Generate confirmation token
     */
    public function generateConfirmationToken(): string
    {
        $this->confirmation_token = hash('sha256', uniqid() . $this->email . time());
        $this->save();
        return $this->confirmation_token;
    }

    /**
     * Calculate engagement score
     */
    public function calculateEngagementScore(): float
    {
        // This would be implemented based on opens, clicks, etc.
        return 50.0;
    }

    /**
     * Get recent activity
     */
    public function getRecentActivity(int $limit = 10)
    {
        return $this->emailSends()
            ->with(['opens', 'clicks'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get email statistics
     */
    public function getEmailStatistics(): array
    {
        $sends = $this->emailSends()->count();
        $opens = $this->emailSends()->whereHas('opens')->count();
        $clicks = $this->emailSends()->whereHas('clicks')->count();

        return [
            'total_sent' => $sends,
            'total_opened' => $opens,
            'total_clicked' => $clicks,
            'open_rate' => $sends > 0 ? ($opens / $sends) * 100 : 0,
            'click_rate' => $opens > 0 ? ($clicks / $opens) * 100 : 0
        ];
    }

    /**
     * Can receive emails
     */
    public function canReceiveEmails(): bool
    {
        return $this->isSubscribed() && 
               !$this->isBounced() && 
               $this->status !== 'complained';
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
}