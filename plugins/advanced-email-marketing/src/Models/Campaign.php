<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Models;

use Shopologic\Core\Database\Model;

class Campaign extends Model
{
    protected string $table = 'email_campaigns';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'name',
        'subject',
        'from_name',
        'from_email',
        'reply_to',
        'template_id',
        'type',
        'status',
        'content',
        'content_text',
        'settings',
        'scheduled_at',
        'sent_at',
        'completed_at',
        'recipients_count',
        'sent_count',
        'failed_count',
        'ab_test_config',
        'winner_variant'
    ];

    protected array $casts = [
        'template_id' => 'integer',
        'content' => 'json',
        'settings' => 'json',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'recipients_count' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'ab_test_config' => 'json'
    ];

    /**
     * Get template
     */
    public function template()
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    /**
     * Get segments
     */
    public function segments()
    {
        return $this->belongsToMany(Segment::class, 'campaign_segments', 'campaign_id', 'segment_id')
            ->withTimestamps();
    }

    /**
     * Get email sends
     */
    public function emailSends()
    {
        return $this->hasMany(EmailSend::class, 'campaign_id');
    }

    /**
     * Get analytics
     */
    public function analytics()
    {
        return $this->hasMany(EmailAnalytics::class, 'campaign_id');
    }

    /**
     * Get AB test variants
     */
    public function variants()
    {
        return $this->hasMany(CampaignVariant::class, 'campaign_id');
    }

    /**
     * Scope draft campaigns
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope scheduled campaigns
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Scope sent campaigns
     */
    public function scopeSent($query)
    {
        return $query->whereIn('status', ['sending', 'sent']);
    }

    /**
     * Scope active campaigns
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['scheduled', 'sending']);
    }

    /**
     * Check if campaign is draft
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if campaign is scheduled
     */
    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    /**
     * Check if campaign is sending
     */
    public function isSending(): bool
    {
        return $this->status === 'sending';
    }

    /**
     * Check if campaign is sent
     */
    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    /**
     * Check if campaign is editable
     */
    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'scheduled']);
    }

    /**
     * Check if campaign is AB test
     */
    public function isABTest(): bool
    {
        return $this->type === 'ab_test';
    }

    /**
     * Get delivery rate
     */
    public function getDeliveryRate(): float
    {
        if ($this->sent_count === 0) {
            return 0;
        }
        
        return (($this->sent_count - $this->failed_count) / $this->sent_count) * 100;
    }

    /**
     * Get open rate
     */
    public function getOpenRate(): float
    {
        if ($this->sent_count === 0) {
            return 0;
        }
        
        $opens = $this->emailSends()
            ->whereHas('opens')
            ->count();
            
        return ($opens / $this->sent_count) * 100;
    }

    /**
     * Get click rate
     */
    public function getClickRate(): float
    {
        if ($this->sent_count === 0) {
            return 0;
        }
        
        $clicks = $this->emailSends()
            ->whereHas('clicks')
            ->count();
            
        return ($clicks / $this->sent_count) * 100;
    }

    /**
     * Get bounce rate
     */
    public function getBounceRate(): float
    {
        if ($this->sent_count === 0) {
            return 0;
        }
        
        $bounces = $this->emailSends()
            ->where('status', 'bounced')
            ->count();
            
        return ($bounces / $this->sent_count) * 100;
    }

    /**
     * Get unsubscribe rate
     */
    public function getUnsubscribeRate(): float
    {
        if ($this->sent_count === 0) {
            return 0;
        }
        
        $unsubscribes = $this->emailSends()
            ->whereHas('unsubscribes')
            ->count();
            
        return ($unsubscribes / $this->sent_count) * 100;
    }

    /**
     * Get complaint rate
     */
    public function getComplaintRate(): float
    {
        if ($this->sent_count === 0) {
            return 0;
        }
        
        $complaints = $this->emailSends()
            ->where('status', 'complained')
            ->count();
            
        return ($complaints / $this->sent_count) * 100;
    }

    /**
     * Get engagement score
     */
    public function getEngagementScore(): float
    {
        $openRate = $this->getOpenRate();
        $clickRate = $this->getClickRate();
        
        // Weighted engagement score
        return ($openRate * 0.3) + ($clickRate * 0.7);
    }

    /**
     * Get campaign statistics
     */
    public function getStatistics(): array
    {
        return [
            'recipients' => $this->recipients_count,
            'sent' => $this->sent_count,
            'delivered' => $this->sent_count - $this->failed_count,
            'opens' => $this->emailSends()->whereHas('opens')->count(),
            'unique_opens' => $this->emailSends()->whereHas('opens')->distinct('subscriber_id')->count(),
            'clicks' => $this->emailSends()->whereHas('clicks')->count(),
            'unique_clicks' => $this->emailSends()->whereHas('clicks')->distinct('subscriber_id')->count(),
            'bounces' => $this->emailSends()->where('status', 'bounced')->count(),
            'complaints' => $this->emailSends()->where('status', 'complained')->count(),
            'unsubscribes' => $this->emailSends()->whereHas('unsubscribes')->count(),
            'delivery_rate' => $this->getDeliveryRate(),
            'open_rate' => $this->getOpenRate(),
            'click_rate' => $this->getClickRate(),
            'bounce_rate' => $this->getBounceRate(),
            'complaint_rate' => $this->getComplaintRate(),
            'unsubscribe_rate' => $this->getUnsubscribeRate(),
            'engagement_score' => $this->getEngagementScore()
        ];
    }

    /**
     * Mark as scheduled
     */
    public function markAsScheduled(\DateTime $scheduledAt): void
    {
        $this->status = 'scheduled';
        $this->scheduled_at = $scheduledAt;
        $this->save();
    }

    /**
     * Mark as sending
     */
    public function markAsSending(): void
    {
        $this->status = 'sending';
        $this->sent_at = now();
        $this->save();
    }

    /**
     * Mark as sent
     */
    public function markAsSent(): void
    {
        $this->status = 'sent';
        $this->completed_at = now();
        $this->save();
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $reason = null): void
    {
        $this->status = 'failed';
        $this->settings = array_merge($this->settings ?? [], [
            'failure_reason' => $reason,
            'failed_at' => now()
        ]);
        $this->save();
    }

    /**
     * Update send progress
     */
    public function updateSendProgress(int $sent, int $failed = 0): void
    {
        $this->sent_count = $sent;
        $this->failed_count = $failed;
        $this->save();
    }

    /**
     * Set winner variant for AB test
     */
    public function setWinnerVariant(string $variant): void
    {
        if ($this->type !== 'ab_test') {
            throw new \RuntimeException('Campaign is not an AB test');
        }
        
        $this->winner_variant = $variant;
        $this->save();
    }
}