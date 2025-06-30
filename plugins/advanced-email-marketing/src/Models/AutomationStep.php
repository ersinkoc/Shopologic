<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Models;

use Shopologic\Core\Database\Model;

class AutomationStep extends Model
{
    protected string $table = 'automation_steps';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'automation_id',
        'name',
        'type',
        'order',
        'action',
        'action_settings',
        'delay_minutes',
        'condition_type',
        'conditions',
        'template_id',
        'is_active',
        'emails_sent',
        'last_executed_at'
    ];

    protected array $casts = [
        'automation_id' => 'integer',
        'order' => 'integer',
        'action_settings' => 'json',
        'delay_minutes' => 'integer',
        'conditions' => 'json',
        'template_id' => 'integer',
        'is_active' => 'boolean',
        'emails_sent' => 'integer',
        'last_executed_at' => 'datetime'
    ];

    /**
     * Get automation
     */
    public function automation()
    {
        return $this->belongsTo(Automation::class, 'automation_id');
    }

    /**
     * Get template
     */
    public function template()
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    /**
     * Get subscriber step progress
     */
    public function subscriberProgress()
    {
        return $this->hasMany(SubscriberAutomation::class, 'current_step_id');
    }

    /**
     * Get next step
     */
    public function nextStep()
    {
        return $this->hasOne(self::class, 'automation_id', 'automation_id')
            ->where('order', '>', $this->order)
            ->orderBy('order');
    }

    /**
     * Get previous step
     */
    public function previousStep()
    {
        return $this->hasOne(self::class, 'automation_id', 'automation_id')
            ->where('order', '<', $this->order)
            ->orderBy('order', 'desc');
    }

    /**
     * Scope active steps
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if step is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Check if step is email action
     */
    public function isEmailAction(): bool
    {
        return $this->action === 'send_email';
    }

    /**
     * Check if step is delay action
     */
    public function isDelayAction(): bool
    {
        return $this->action === 'delay';
    }

    /**
     * Check if step is condition
     */
    public function isCondition(): bool
    {
        return in_array($this->action, ['condition', 'split_test']);
    }

    /**
     * Check if step has conditions
     */
    public function hasConditions(): bool
    {
        return !empty($this->conditions);
    }

    /**
     * Get action label
     */
    public function getActionLabel(): string
    {
        $labels = [
            'send_email' => 'Send Email',
            'delay' => 'Wait',
            'condition' => 'Check Condition',
            'split_test' => 'Split Test',
            'tag_add' => 'Add Tag',
            'tag_remove' => 'Remove Tag',
            'field_update' => 'Update Field',
            'webhook' => 'Call Webhook',
            'goal' => 'Check Goal'
        ];
        
        return $labels[$this->action] ?? ucfirst(str_replace('_', ' ', $this->action));
    }

    /**
     * Get delay label
     */
    public function getDelayLabel(): string
    {
        if ($this->delay_minutes === 0) {
            return 'Immediately';
        }
        
        if ($this->delay_minutes < 60) {
            return $this->delay_minutes . ' minute' . ($this->delay_minutes > 1 ? 's' : '');
        }
        
        $hours = $this->delay_minutes / 60;
        if ($hours < 24) {
            return $hours . ' hour' . ($hours > 1 ? 's' : '');
        }
        
        $days = $hours / 24;
        return $days . ' day' . ($days > 1 ? 's' : '');
    }

    /**
     * Should execute for subscriber
     */
    public function shouldExecuteForSubscriber(array $subscriberData, array $context = []): bool
    {
        if (!$this->is_active) {
            return false;
        }
        
        if (!$this->hasConditions()) {
            return true;
        }
        
        return $this->evaluateConditions($subscriberData, $context);
    }

    /**
     * Evaluate conditions
     */
    private function evaluateConditions(array $subscriberData, array $context): bool
    {
        $conditions = $this->conditions;
        $type = $this->condition_type ?? 'all';
        
        if ($type === 'all') {
            foreach ($conditions as $condition) {
                if (!$this->evaluateCondition($condition, $subscriberData, $context)) {
                    return false;
                }
            }
            return true;
        } else { // any
            foreach ($conditions as $condition) {
                if ($this->evaluateCondition($condition, $subscriberData, $context)) {
                    return true;
                }
            }
            return false;
        }
    }

    /**
     * Evaluate single condition
     */
    private function evaluateCondition(array $condition, array $subscriberData, array $context): bool
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? '';
        
        $fieldValue = $this->getFieldValue($field, $subscriberData, $context);
        
        switch ($operator) {
            case '=':
            case 'equals':
                return $fieldValue == $value;
            case '!=':
            case 'not_equals':
                return $fieldValue != $value;
            case '>':
            case 'greater_than':
                return $fieldValue > $value;
            case '<':
            case 'less_than':
                return $fieldValue < $value;
            case '>=':
            case 'greater_than_or_equal':
                return $fieldValue >= $value;
            case '<=':
            case 'less_than_or_equal':
                return $fieldValue <= $value;
            case 'contains':
                return stripos((string)$fieldValue, (string)$value) !== false;
            case 'not_contains':
                return stripos((string)$fieldValue, (string)$value) === false;
            case 'starts_with':
                return stripos((string)$fieldValue, (string)$value) === 0;
            case 'ends_with':
                return substr((string)$fieldValue, -strlen((string)$value)) === (string)$value;
            case 'is_empty':
                return empty($fieldValue);
            case 'is_not_empty':
                return !empty($fieldValue);
            default:
                return false;
        }
    }

    /**
     * Get field value from subscriber data or context
     */
    private function getFieldValue(string $field, array $subscriberData, array $context)
    {
        // Check context first
        if (isset($context[$field])) {
            return $context[$field];
        }
        
        // Check subscriber data
        if (isset($subscriberData[$field])) {
            return $subscriberData[$field];
        }
        
        // Check nested fields
        if (strpos($field, '.') !== false) {
            $parts = explode('.', $field);
            $value = $subscriberData;
            
            foreach ($parts as $part) {
                if (is_array($value) && isset($value[$part])) {
                    $value = $value[$part];
                } else {
                    return null;
                }
            }
            
            return $value;
        }
        
        return null;
    }

    /**
     * Increment emails sent
     */
    public function incrementEmailsSent(): void
    {
        $this->emails_sent++;
        $this->last_executed_at = now();
        $this->save();
    }

    /**
     * Activate step
     */
    public function activate(): void
    {
        $this->is_active = true;
        $this->save();
    }

    /**
     * Deactivate step
     */
    public function deactivate(): void
    {
        $this->is_active = false;
        $this->save();
    }

    /**
     * Update order
     */
    public function updateOrder(int $newOrder): void
    {
        $this->order = $newOrder;
        $this->save();
    }
}