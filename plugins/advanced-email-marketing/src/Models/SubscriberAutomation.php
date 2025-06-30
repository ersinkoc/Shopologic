<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Models;

use Shopologic\Core\Database\Model;

class SubscriberAutomation extends Model
{
    protected string $table = 'subscriber_automations';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'subscriber_id',
        'automation_id',
        'status',
        'current_step_id',
        'context',
        'started_at',
        'completed_at',
        'paused_at',
        'failed_at',
        'failure_reason',
        'steps_completed',
        'emails_sent',
        'last_activity_at'
    ];

    protected array $casts = [
        'subscriber_id' => 'integer',
        'automation_id' => 'integer',
        'current_step_id' => 'integer',
        'context' => 'json',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'paused_at' => 'datetime',
        'failed_at' => 'datetime',
        'steps_completed' => 'integer',
        'emails_sent' => 'integer',
        'last_activity_at' => 'datetime'
    ];

    /**
     * Get subscriber
     */
    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class, 'subscriber_id');
    }

    /**
     * Get automation
     */
    public function automation()
    {
        return $this->belongsTo(Automation::class, 'automation_id');
    }

    /**
     * Get current step
     */
    public function currentStep()
    {
        return $this->belongsTo(AutomationStep::class, 'current_step_id');
    }

    /**
     * Get step history
     */
    public function stepHistory()
    {
        return $this->hasMany(SubscriberAutomationHistory::class, 'subscriber_automation_id')
            ->orderBy('executed_at');
    }

    /**
     * Scope active automations
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope completed automations
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope paused automations
     */
    public function scopePaused($query)
    {
        return $query->where('status', 'paused');
    }

    /**
     * Scope failed automations
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Check if automation is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if automation is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if automation is paused
     */
    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    /**
     * Check if automation has failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get context value
     */
    public function getContextValue(string $key, $default = null)
    {
        return $this->context[$key] ?? $default;
    }

    /**
     * Update context
     */
    public function updateContext(array $data): void
    {
        $this->context = array_merge($this->context ?? [], $data);
        $this->save();
    }

    /**
     * Move to next step
     */
    public function moveToNextStep(): ?AutomationStep
    {
        if (!$this->currentStep) {
            return null;
        }
        
        $nextStep = $this->currentStep->nextStep()->first();
        
        if (!$nextStep) {
            $this->complete();
            return null;
        }
        
        // Record step completion
        $this->recordStepCompletion($this->current_step_id);
        
        // Update current step
        $this->current_step_id = $nextStep->id;
        $this->steps_completed++;
        $this->last_activity_at = now();
        $this->save();
        
        return $nextStep;
    }

    /**
     * Skip to specific step
     */
    public function skipToStep(int $stepId): void
    {
        $step = AutomationStep::find($stepId);
        
        if (!$step || $step->automation_id !== $this->automation_id) {
            throw new \InvalidArgumentException('Invalid step ID');
        }
        
        $this->current_step_id = $stepId;
        $this->last_activity_at = now();
        $this->save();
    }

    /**
     * Pause automation
     */
    public function pause(): void
    {
        $this->status = 'paused';
        $this->paused_at = now();
        $this->save();
    }

    /**
     * Resume automation
     */
    public function resume(): void
    {
        if ($this->status !== 'paused') {
            throw new \RuntimeException('Cannot resume automation that is not paused');
        }
        
        $this->status = 'active';
        $this->paused_at = null;
        $this->save();
    }

    /**
     * Complete automation
     */
    public function complete(): void
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->current_step_id = null;
        $this->save();
    }

    /**
     * Fail automation
     */
    public function fail(string $reason): void
    {
        $this->status = 'failed';
        $this->failed_at = now();
        $this->failure_reason = $reason;
        $this->save();
    }

    /**
     * Restart automation
     */
    public function restart(): void
    {
        $firstStep = $this->automation->steps()->orderBy('order')->first();
        
        if (!$firstStep) {
            throw new \RuntimeException('Automation has no steps');
        }
        
        $this->status = 'active';
        $this->current_step_id = $firstStep->id;
        $this->completed_at = null;
        $this->failed_at = null;
        $this->paused_at = null;
        $this->failure_reason = null;
        $this->steps_completed = 0;
        $this->emails_sent = 0;
        $this->last_activity_at = now();
        $this->save();
        
        // Clear step history
        $this->stepHistory()->delete();
    }

    /**
     * Record step completion
     */
    private function recordStepCompletion(int $stepId): void
    {
        $this->stepHistory()->create([
            'step_id' => $stepId,
            'executed_at' => now(),
            'result' => 'completed',
            'context' => $this->context
        ]);
    }

    /**
     * Record email sent
     */
    public function recordEmailSent(): void
    {
        $this->emails_sent++;
        $this->last_activity_at = now();
        $this->save();
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentage(): float
    {
        $totalSteps = $this->automation->steps()->count();
        
        if ($totalSteps === 0) {
            return 0;
        }
        
        if ($this->isCompleted()) {
            return 100;
        }
        
        return ($this->steps_completed / $totalSteps) * 100;
    }

    /**
     * Get time in automation
     */
    public function getTimeInAutomation(): ?int
    {
        if (!$this->started_at) {
            return null;
        }
        
        $endTime = $this->completed_at ?? $this->failed_at ?? now();
        
        return $this->started_at->diffInMinutes($endTime);
    }

    /**
     * Get average time between steps
     */
    public function getAverageTimeBetweenSteps(): ?float
    {
        if ($this->steps_completed === 0) {
            return null;
        }
        
        $timeInAutomation = $this->getTimeInAutomation();
        if (!$timeInAutomation) {
            return null;
        }
        
        return $timeInAutomation / $this->steps_completed;
    }

    /**
     * Can proceed to next step
     */
    public function canProceed(): bool
    {
        if (!$this->isActive()) {
            return false;
        }
        
        if (!$this->currentStep) {
            return false;
        }
        
        // Check if enough time has passed for delay steps
        if ($this->currentStep->delay_minutes > 0) {
            $lastActivity = $this->last_activity_at ?? $this->started_at;
            $delayUntil = $lastActivity->addMinutes($this->currentStep->delay_minutes);
            
            if ($delayUntil->isFuture()) {
                return false;
            }
        }
        
        return true;
    }
}