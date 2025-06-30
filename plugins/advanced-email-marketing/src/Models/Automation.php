<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Models;

use Shopologic\Core\Database\Model;

class Automation extends Model
{
    protected string $table = 'email_automations';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'name',
        'description',
        'trigger_type',
        'trigger_settings',
        'status',
        'priority',
        'max_runs_per_subscriber',
        'cooldown_period',
        'start_date',
        'end_date',
        'settings',
        'statistics'
    ];

    protected array $casts = [
        'trigger_settings' => 'json',
        'priority' => 'integer',
        'max_runs_per_subscriber' => 'integer',
        'cooldown_period' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'settings' => 'json',
        'statistics' => 'json'
    ];

    /**
     * Get workflow steps
     */
    public function steps()
    {
        return $this->hasMany(AutomationStep::class, 'automation_id')
            ->orderBy('order');
    }

    /**
     * Get segments
     */
    public function segments()
    {
        return $this->belongsToMany(Segment::class, 'automation_segments', 'automation_id', 'segment_id')
            ->withTimestamps();
    }

    /**
     * Get subscriber automations
     */
    public function subscriberAutomations()
    {
        return $this->hasMany(SubscriberAutomation::class, 'automation_id');
    }

    /**
     * Get active subscriber count
     */
    public function activeSubscribers()
    {
        return $this->subscriberAutomations()
            ->where('status', 'active');
    }

    /**
     * Get completed subscriber count
     */
    public function completedSubscribers()
    {
        return $this->subscriberAutomations()
            ->where('status', 'completed');
    }

    /**
     * Scope active automations
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function($q) {
                $q->whereNull('start_date')
                  ->orWhere('start_date', '<=', now());
            })
            ->where(function($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>', now());
            });
    }

    /**
     * Scope by trigger type
     */
    public function scopeByTriggerType($query, string $type)
    {
        return $query->where('trigger_type', $type);
    }

    /**
     * Check if automation is active
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }
        
        if ($this->start_date && $this->start_date->isFuture()) {
            return false;
        }
        
        if ($this->end_date && $this->end_date->isPast()) {
            return false;
        }
        
        return true;
    }

    /**
     * Check if automation is paused
     */
    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    /**
     * Check if automation is draft
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Get trigger type label
     */
    public function getTriggerTypeLabel(): string
    {
        $labels = [
            'subscriber_added' => 'Subscriber Added',
            'tag_added' => 'Tag Added',
            'tag_removed' => 'Tag Removed',
            'field_changed' => 'Field Changed',
            'date_based' => 'Date Based',
            'behavior' => 'Behavioral',
            'custom' => 'Custom Event',
            'welcome' => 'Welcome Series',
            'abandoned_cart' => 'Abandoned Cart',
            'post_purchase' => 'Post Purchase',
            'win_back' => 'Win Back',
            'birthday' => 'Birthday'
        ];
        
        return $labels[$this->trigger_type] ?? ucfirst(str_replace('_', ' ', $this->trigger_type));
    }

    /**
     * Get trigger settings
     */
    public function getTriggerSettings(): array
    {
        return $this->trigger_settings ?? [];
    }

    /**
     * Get specific trigger setting
     */
    public function getTriggerSetting(string $key, $default = null)
    {
        return $this->trigger_settings[$key] ?? $default;
    }

    /**
     * Can subscriber enter automation
     */
    public function canSubscriberEnter(int $subscriberId): bool
    {
        if (!$this->isActive()) {
            return false;
        }
        
        // Check max runs
        if ($this->max_runs_per_subscriber > 0) {
            $runCount = $this->subscriberAutomations()
                ->where('subscriber_id', $subscriberId)
                ->count();
                
            if ($runCount >= $this->max_runs_per_subscriber) {
                return false;
            }
        }
        
        // Check cooldown period
        if ($this->cooldown_period > 0) {
            $lastRun = $this->subscriberAutomations()
                ->where('subscriber_id', $subscriberId)
                ->orderBy('created_at', 'desc')
                ->first();
                
            if ($lastRun && $lastRun->created_at->addMinutes($this->cooldown_period)->isFuture()) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Add subscriber to automation
     */
    public function addSubscriber(int $subscriberId, array $context = []): SubscriberAutomation
    {
        if (!$this->canSubscriberEnter($subscriberId)) {
            throw new \RuntimeException('Subscriber cannot enter this automation');
        }
        
        return $this->subscriberAutomations()->create([
            'subscriber_id' => $subscriberId,
            'status' => 'active',
            'current_step_id' => $this->steps()->orderBy('order')->first()->id ?? null,
            'context' => $context,
            'started_at' => now()
        ]);
    }

    /**
     * Activate automation
     */
    public function activate(): void
    {
        $this->status = 'active';
        $this->save();
    }

    /**
     * Pause automation
     */
    public function pause(): void
    {
        $this->status = 'paused';
        $this->save();
    }

    /**
     * Deactivate automation
     */
    public function deactivate(): void
    {
        $this->status = 'inactive';
        $this->save();
    }

    /**
     * Update statistics
     */
    public function updateStatistics(): void
    {
        $stats = [
            'total_subscribers' => $this->subscriberAutomations()->count(),
            'active_subscribers' => $this->subscriberAutomations()->where('status', 'active')->count(),
            'completed_subscribers' => $this->subscriberAutomations()->where('status', 'completed')->count(),
            'emails_sent' => $this->steps()->sum('emails_sent'),
            'last_trigger' => $this->subscriberAutomations()->max('started_at'),
            'avg_completion_time' => $this->calculateAverageCompletionTime()
        ];
        
        $this->statistics = $stats;
        $this->save();
    }

    /**
     * Calculate average completion time
     */
    private function calculateAverageCompletionTime(): ?float
    {
        $completedAutomations = $this->subscriberAutomations()
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->get();
            
        if ($completedAutomations->isEmpty()) {
            return null;
        }
        
        $totalMinutes = 0;
        foreach ($completedAutomations as $automation) {
            $totalMinutes += $automation->started_at->diffInMinutes($automation->completed_at);
        }
        
        return $totalMinutes / $completedAutomations->count();
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        $totalSubscribers = $this->subscriberAutomations()->count();
        $completedSubscribers = $this->subscriberAutomations()->where('status', 'completed')->count();
        
        return [
            'completion_rate' => $totalSubscribers > 0 ? ($completedSubscribers / $totalSubscribers) * 100 : 0,
            'avg_emails_per_subscriber' => $this->calculateAverageEmailsPerSubscriber(),
            'total_revenue' => $this->calculateTotalRevenue(),
            'conversion_rate' => $this->calculateConversionRate()
        ];
    }

    /**
     * Calculate average emails per subscriber
     */
    private function calculateAverageEmailsPerSubscriber(): float
    {
        $totalSubscribers = $this->subscriberAutomations()->count();
        if ($totalSubscribers === 0) {
            return 0;
        }
        
        $totalEmails = $this->steps()->sum('emails_sent');
        return $totalEmails / $totalSubscribers;
    }

    /**
     * Calculate total revenue
     */
    private function calculateTotalRevenue(): float
    {
        // This would be implemented with actual revenue tracking
        return 0.0;
    }

    /**
     * Calculate conversion rate
     */
    private function calculateConversionRate(): float
    {
        // This would be implemented with actual conversion tracking
        return 0.0;
    }

    /**
     * Clone automation
     */
    public function cloneAutomation(string $newName = null): self
    {
        $clone = $this->replicate();
        $clone->name = $newName ?? $this->name . ' (Copy)';
        $clone->status = 'draft';
        $clone->statistics = null;
        $clone->save();
        
        // Clone steps
        foreach ($this->steps as $step) {
            $clonedStep = $step->replicate();
            $clonedStep->automation_id = $clone->id;
            $clonedStep->save();
        }
        
        // Clone segment associations
        $clone->segments()->sync($this->segments->pluck('id'));
        
        return $clone;
    }
}