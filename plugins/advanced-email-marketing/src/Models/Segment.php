<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Models;

use Shopologic\Core\Database\Model;

class Segment extends Model
{
    protected string $table = 'email_segments';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'name',
        'description',
        'type',
        'conditions',
        'member_count',
        'tags',
        'is_active',
        'last_calculated_at',
        'calculation_status',
        'calculation_error'
    ];

    protected array $casts = [
        'conditions' => 'json',
        'member_count' => 'integer',
        'tags' => 'json',
        'is_active' => 'boolean',
        'last_calculated_at' => 'datetime'
    ];

    /**
     * Get members
     */
    public function members()
    {
        return $this->belongsToMany(Subscriber::class, 'segment_members', 'segment_id', 'subscriber_id')
            ->withTimestamps()
            ->withPivot('criteria_match', 'added_at');
    }

    /**
     * Get campaigns using this segment
     */
    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class, 'campaign_segments', 'segment_id', 'campaign_id')
            ->withTimestamps();
    }

    /**
     * Get automations using this segment
     */
    public function automations()
    {
        return $this->belongsToMany(Automation::class, 'automation_segments', 'segment_id', 'automation_id')
            ->withTimestamps();
    }

    /**
     * Scope active segments
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope dynamic segments
     */
    public function scopeDynamic($query)
    {
        return $query->where('type', 'dynamic');
    }

    /**
     * Scope static segments
     */
    public function scopeStatic($query)
    {
        return $query->where('type', 'static');
    }

    /**
     * Check if segment is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Check if segment is dynamic
     */
    public function isDynamic(): bool
    {
        return $this->type === 'dynamic';
    }

    /**
     * Check if segment is static
     */
    public function isStatic(): bool
    {
        return $this->type === 'static';
    }

    /**
     * Check if segment needs recalculation
     */
    public function needsRecalculation(): bool
    {
        if (!$this->isDynamic()) {
            return false;
        }
        
        if (!$this->last_calculated_at) {
            return true;
        }
        
        // Recalculate if older than 24 hours
        return $this->last_calculated_at->addHours(24)->isPast();
    }

    /**
     * Get conditions
     */
    public function getConditions(): array
    {
        return $this->conditions ?? [];
    }

    /**
     * Set conditions
     */
    public function setConditions(array $conditions): void
    {
        $this->conditions = $conditions;
        $this->save();
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
     * Add member
     */
    public function addMember(int $subscriberId, array $criteriaMatch = []): void
    {
        if (!$this->members()->where('subscriber_id', $subscriberId)->exists()) {
            $this->members()->attach($subscriberId, [
                'criteria_match' => json_encode($criteriaMatch),
                'added_at' => now()
            ]);
            $this->incrementMemberCount();
        }
    }

    /**
     * Remove member
     */
    public function removeMember(int $subscriberId): void
    {
        if ($this->members()->where('subscriber_id', $subscriberId)->exists()) {
            $this->members()->detach($subscriberId);
            $this->decrementMemberCount();
        }
    }

    /**
     * Has member
     */
    public function hasMember(int $subscriberId): bool
    {
        return $this->members()->where('subscriber_id', $subscriberId)->exists();
    }

    /**
     * Clear all members
     */
    public function clearMembers(): void
    {
        $this->members()->detach();
        $this->member_count = 0;
        $this->save();
    }

    /**
     * Update member count
     */
    public function updateMemberCount(): void
    {
        $this->member_count = $this->members()->count();
        $this->save();
    }

    /**
     * Increment member count
     */
    public function incrementMemberCount(): void
    {
        $this->member_count++;
        $this->save();
    }

    /**
     * Decrement member count
     */
    public function decrementMemberCount(): void
    {
        if ($this->member_count > 0) {
            $this->member_count--;
            $this->save();
        }
    }

    /**
     * Mark as calculated
     */
    public function markAsCalculated(): void
    {
        $this->last_calculated_at = now();
        $this->calculation_status = 'completed';
        $this->calculation_error = null;
        $this->save();
    }

    /**
     * Mark as calculating
     */
    public function markAsCalculating(): void
    {
        $this->calculation_status = 'processing';
        $this->save();
    }

    /**
     * Mark calculation as failed
     */
    public function markCalculationFailed(string $error): void
    {
        $this->calculation_status = 'failed';
        $this->calculation_error = $error;
        $this->save();
    }

    /**
     * Activate segment
     */
    public function activate(): void
    {
        $this->is_active = true;
        $this->save();
    }

    /**
     * Deactivate segment
     */
    public function deactivate(): void
    {
        $this->is_active = false;
        $this->save();
    }

    /**
     * Clone segment
     */
    public function cloneSegment(string $newName = null): self
    {
        $clone = $this->replicate();
        $clone->name = $newName ?? $this->name . ' (Copy)';
        $clone->member_count = 0;
        $clone->last_calculated_at = null;
        $clone->calculation_status = null;
        $clone->save();
        
        // Clone members for static segments
        if ($this->isStatic()) {
            $members = $this->members()->get();
            foreach ($members as $member) {
                $clone->addMember($member->id, json_decode($member->pivot->criteria_match, true) ?? []);
            }
        }
        
        return $clone;
    }

    /**
     * Get growth rate
     */
    public function getGrowthRate(int $days = 30): float
    {
        $startDate = now()->subDays($days);
        
        $startCount = $this->members()
            ->wherePivot('added_at', '<=', $startDate)
            ->count();
            
        if ($startCount === 0) {
            return $this->member_count > 0 ? 100.0 : 0.0;
        }
        
        return (($this->member_count - $startCount) / $startCount) * 100;
    }

    /**
     * Get condition summary
     */
    public function getConditionSummary(): string
    {
        if ($this->isStatic()) {
            return "Static segment with {$this->member_count} members";
        }
        
        $conditions = $this->getConditions();
        if (empty($conditions)) {
            return "No conditions defined";
        }
        
        // Build a human-readable summary
        $summary = [];
        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? 'unknown';
            $operator = $condition['operator'] ?? 'equals';
            $value = $condition['value'] ?? '';
            
            $summary[] = "{$field} {$operator} {$value}";
        }
        
        return implode(' AND ', $summary);
    }
}