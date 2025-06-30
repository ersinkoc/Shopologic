<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Models;

use Shopologic\Core\Database\Model;

class EmailSend extends Model
{
    protected string $table = 'email_sends';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'subscriber_id',
        'campaign_id',
        'automation_id',
        'automation_step_id',
        'message_id',
        'provider',
        'provider_message_id',
        'status',
        'to_email',
        'from_email',
        'from_name',
        'subject',
        'sent_at',
        'delivered_at',
        'opened_at',
        'clicked_at',
        'bounced_at',
        'complained_at',
        'unsubscribed_at',
        'bounce_type',
        'bounce_reason',
        'metadata'
    ];

    protected array $casts = [
        'subscriber_id' => 'integer',
        'campaign_id' => 'integer',
        'automation_id' => 'integer',
        'automation_step_id' => 'integer',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'bounced_at' => 'datetime',
        'complained_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'metadata' => 'json'
    ];

    /**
     * Get subscriber
     */
    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class, 'subscriber_id');
    }

    /**
     * Get campaign
     */
    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    /**
     * Get automation
     */
    public function automation()
    {
        return $this->belongsTo(Automation::class, 'automation_id');
    }

    /**
     * Get automation step
     */
    public function automationStep()
    {
        return $this->belongsTo(AutomationStep::class, 'automation_step_id');
    }

    /**
     * Get opens
     */
    public function opens()
    {
        return $this->hasMany(EmailOpen::class, 'send_id');
    }

    /**
     * Get clicks
     */
    public function clicks()
    {
        return $this->hasMany(EmailClick::class, 'send_id');
    }

    /**
     * Get unsubscribes
     */
    public function unsubscribes()
    {
        return $this->hasMany(EmailUnsubscribe::class, 'send_id');
    }

    /**
     * Scope delivered emails
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    /**
     * Scope bounced emails
     */
    public function scopeBounced($query)
    {
        return $query->where('status', 'bounced');
    }

    /**
     * Scope opened emails
     */
    public function scopeOpened($query)
    {
        return $query->whereNotNull('opened_at');
    }

    /**
     * Scope clicked emails
     */
    public function scopeClicked($query)
    {
        return $query->whereNotNull('clicked_at');
    }

    /**
     * Check if email was delivered
     */
    public function wasDelivered(): bool
    {
        return $this->status === 'delivered' || $this->delivered_at !== null;
    }

    /**
     * Check if email was opened
     */
    public function wasOpened(): bool
    {
        return $this->opened_at !== null;
    }

    /**
     * Check if email was clicked
     */
    public function wasClicked(): bool
    {
        return $this->clicked_at !== null;
    }

    /**
     * Check if email bounced
     */
    public function bounced(): bool
    {
        return $this->status === 'bounced' || $this->bounced_at !== null;
    }

    /**
     * Check if email was complained
     */
    public function wasComplained(): bool
    {
        return $this->status === 'complained' || $this->complained_at !== null;
    }

    /**
     * Check if resulted in unsubscribe
     */
    public function resultedInUnsubscribe(): bool
    {
        return $this->unsubscribed_at !== null;
    }

    /**
     * Mark as delivered
     */
    public function markAsDelivered(array $details = []): void
    {
        $this->status = 'delivered';
        $this->delivered_at = now();
        
        if (!empty($details)) {
            $this->metadata = array_merge($this->metadata ?? [], ['delivery' => $details]);
        }
        
        $this->save();
    }

    /**
     * Mark as opened
     */
    public function markAsOpened(array $details = []): void
    {
        if (!$this->opened_at) {
            $this->opened_at = now();
        }
        
        // Create open record
        $this->opens()->create([
            'subscriber_id' => $this->subscriber_id,
            'opened_at' => now(),
            'ip_address' => $details['ip_address'] ?? null,
            'user_agent' => $details['user_agent'] ?? null,
            'device_type' => $details['device_type'] ?? null,
            'country' => $details['country'] ?? null,
            'city' => $details['city'] ?? null
        ]);
        
        $this->save();
    }

    /**
     * Mark as clicked
     */
    public function markAsClicked(string $url, array $details = []): void
    {
        if (!$this->clicked_at) {
            $this->clicked_at = now();
        }
        
        // Create click record
        $this->clicks()->create([
            'subscriber_id' => $this->subscriber_id,
            'url' => $url,
            'clicked_at' => now(),
            'ip_address' => $details['ip_address'] ?? null,
            'user_agent' => $details['user_agent'] ?? null,
            'device_type' => $details['device_type'] ?? null,
            'country' => $details['country'] ?? null,
            'city' => $details['city'] ?? null
        ]);
        
        $this->save();
    }

    /**
     * Mark as bounced
     */
    public function markAsBounced(string $type, string $reason, array $details = []): void
    {
        $this->status = 'bounced';
        $this->bounced_at = now();
        $this->bounce_type = $type;
        $this->bounce_reason = $reason;
        
        if (!empty($details)) {
            $this->metadata = array_merge($this->metadata ?? [], ['bounce' => $details]);
        }
        
        $this->save();
    }

    /**
     * Mark as complained
     */
    public function markAsComplained(array $details = []): void
    {
        $this->status = 'complained';
        $this->complained_at = now();
        
        if (!empty($details)) {
            $this->metadata = array_merge($this->metadata ?? [], ['complaint' => $details]);
        }
        
        $this->save();
    }

    /**
     * Mark as unsubscribed
     */
    public function markAsUnsubscribed(string $reason = null): void
    {
        $this->unsubscribed_at = now();
        
        // Create unsubscribe record
        $this->unsubscribes()->create([
            'subscriber_id' => $this->subscriber_id,
            'campaign_id' => $this->campaign_id,
            'reason' => $reason,
            'unsubscribed_at' => now()
        ]);
        
        $this->save();
    }

    /**
     * Get engagement score
     */
    public function getEngagementScore(): int
    {
        $score = 0;
        
        if ($this->wasDelivered()) $score += 10;
        if ($this->wasOpened()) $score += 30;
        if ($this->wasClicked()) $score += 50;
        if ($this->bounced()) $score -= 20;
        if ($this->wasComplained()) $score -= 50;
        if ($this->resultedInUnsubscribe()) $score -= 30;
        
        return max(0, $score);
    }

    /**
     * Get time to open
     */
    public function getTimeToOpen(): ?int
    {
        if (!$this->sent_at || !$this->opened_at) {
            return null;
        }
        
        return $this->sent_at->diffInMinutes($this->opened_at);
    }

    /**
     * Get time to click
     */
    public function getTimeToClick(): ?int
    {
        if (!$this->sent_at || !$this->clicked_at) {
            return null;
        }
        
        return $this->sent_at->diffInMinutes($this->clicked_at);
    }

    /**
     * Get click through rate
     */
    public function getClickThroughRate(): float
    {
        if (!$this->wasOpened()) {
            return 0.0;
        }
        
        return $this->wasClicked() ? 100.0 : 0.0;
    }

    /**
     * Get email type
     */
    public function getEmailType(): string
    {
        if ($this->campaign_id) {
            return 'campaign';
        }
        
        if ($this->automation_id) {
            return 'automation';
        }
        
        return 'transactional';
    }

    /**
     * Get source name
     */
    public function getSourceName(): string
    {
        if ($this->campaign) {
            return $this->campaign->name;
        }
        
        if ($this->automation) {
            return $this->automation->name;
        }
        
        return 'Direct Send';
    }
}