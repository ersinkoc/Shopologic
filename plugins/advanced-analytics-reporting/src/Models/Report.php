<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedAnalyticsReporting\Models;

use Shopologic\Core\Database\Model;

class Report extends Model
{
    protected string $table = 'analytics_reports';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'name',
        'description',
        'type',
        'query_definition',
        'filters',
        'columns',
        'visualizations',
        'schedule_config',
        'output_format',
        'recipients',
        'is_scheduled',
        'is_active',
        'created_by',
        'last_run_at',
        'next_run_at',
        'status',
        'parameters',
        'metadata'
    ];

    protected array $casts = [
        'query_definition' => 'json',
        'filters' => 'json',
        'columns' => 'json',
        'visualizations' => 'json',
        'schedule_config' => 'json',
        'recipients' => 'json',
        'is_scheduled' => 'boolean',
        'is_active' => 'boolean',
        'created_by' => 'integer',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'parameters' => 'json',
        'metadata' => 'json'
    ];

    /**
     * Report types
     */
    const TYPE_SALES = 'sales';
    const TYPE_CUSTOMERS = 'customers';
    const TYPE_PRODUCTS = 'products';
    const TYPE_INVENTORY = 'inventory';
    const TYPE_FINANCIAL = 'financial';
    const TYPE_TRAFFIC = 'traffic';
    const TYPE_CONVERSION = 'conversion';
    const TYPE_COHORT = 'cohort';
    const TYPE_FUNNEL = 'funnel';
    const TYPE_CUSTOM = 'custom';

    /**
     * Report statuses
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_ACTIVE = 'active';
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_ARCHIVED = 'archived';

    /**
     * Output formats
     */
    const FORMAT_TABLE = 'table';
    const FORMAT_CHART = 'chart';
    const FORMAT_CSV = 'csv';
    const FORMAT_EXCEL = 'excel';
    const FORMAT_PDF = 'pdf';
    const FORMAT_JSON = 'json';

    /**
     * Get report executions
     */
    public function executions()
    {
        return $this->hasMany(ReportExecution::class, 'report_id');
    }

    /**
     * Get creator
     */
    public function creator()
    {
        return $this->belongsTo('Shopologic\Core\Models\User', 'created_by');
    }

    /**
     * Get recent executions
     */
    public function recentExecutions()
    {
        return $this->executions()->orderBy('created_at', 'desc')->limit(10);
    }

    /**
     * Scope active reports
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope scheduled reports
     */
    public function scopeScheduled($query)
    {
        return $query->where('is_scheduled', true)->where('is_active', true);
    }

    /**
     * Scope by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Check if report is scheduled
     */
    public function isScheduled(): bool
    {
        return $this->is_scheduled && $this->is_active;
    }

    /**
     * Check if report can be executed
     */
    public function canExecute(): bool
    {
        return $this->is_active && in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_COMPLETED, self::STATUS_FAILED]);
    }

    /**
     * Get schedule frequency
     */
    public function getScheduleFrequency(): ?string
    {
        $config = $this->schedule_config ?? [];
        return $config['frequency'] ?? null;
    }

    /**
     * Get next run time
     */
    public function calculateNextRunTime(): ?\DateTime
    {
        if (!$this->isScheduled()) {
            return null;
        }

        $config = $this->schedule_config ?? [];
        $frequency = $config['frequency'] ?? null;

        if (!$frequency) {
            return null;
        }

        $lastRun = $this->last_run_at ?? now();
        
        switch ($frequency) {
            case 'hourly':
                return $lastRun->addHour();
            case 'daily':
                return $lastRun->addDay();
            case 'weekly':
                return $lastRun->addWeek();
            case 'monthly':
                return $lastRun->addMonth();
            default:
                return null;
        }
    }

    /**
     * Update next run time
     */
    public function updateNextRunTime(): void
    {
        $this->next_run_at = $this->calculateNextRunTime();
        $this->save();
    }

    /**
     * Mark as running
     */
    public function markAsRunning(): void
    {
        $this->status = self::STATUS_RUNNING;
        $this->save();
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->last_run_at = now();
        $this->updateNextRunTime();
        $this->save();
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $error = null): void
    {
        $this->status = self::STATUS_FAILED;
        
        if ($error) {
            $metadata = $this->metadata ?? [];
            $metadata['last_error'] = $error;
            $this->metadata = $metadata;
        }
        
        $this->save();
    }

    /**
     * Get parameter value
     */
    public function getParameter(string $key, $default = null)
    {
        $parameters = $this->parameters ?? [];
        return $parameters[$key] ?? $default;
    }

    /**
     * Set parameter value
     */
    public function setParameter(string $key, $value): void
    {
        $parameters = $this->parameters ?? [];
        $parameters[$key] = $value;
        $this->parameters = $parameters;
        $this->save();
    }

    /**
     * Get column configuration
     */
    public function getColumns(): array
    {
        return $this->columns ?? [];
    }

    /**
     * Get filter configuration
     */
    public function getFilters(): array
    {
        return $this->filters ?? [];
    }

    /**
     * Get visualization configuration
     */
    public function getVisualizations(): array
    {
        return $this->visualizations ?? [];
    }

    /**
     * Get recipients list
     */
    public function getRecipients(): array
    {
        return $this->recipients ?? [];
    }

    /**
     * Add recipient
     */
    public function addRecipient(string $email, string $name = null): void
    {
        $recipients = $this->getRecipients();
        $recipients[] = [
            'email' => $email,
            'name' => $name,
            'added_at' => now()->toISOString()
        ];
        $this->recipients = $recipients;
        $this->save();
    }

    /**
     * Remove recipient
     */
    public function removeRecipient(string $email): void
    {
        $recipients = array_filter($this->getRecipients(), function($recipient) use ($email) {
            return $recipient['email'] !== $email;
        });
        $this->recipients = array_values($recipients);
        $this->save();
    }

    /**
     * Get execution statistics
     */
    public function getExecutionStatistics(): array
    {
        $executions = $this->executions()->get();
        
        return [
            'total_executions' => $executions->count(),
            'successful_executions' => $executions->where('status', 'completed')->count(),
            'failed_executions' => $executions->where('status', 'failed')->count(),
            'average_duration' => $executions->where('status', 'completed')->avg('duration_seconds'),
            'last_execution' => $this->last_run_at?->format('Y-m-d H:i:s'),
            'success_rate' => $executions->count() > 0 ? 
                ($executions->where('status', 'completed')->count() / $executions->count()) * 100 : 0
        ];
    }

    /**
     * Duplicate report
     */
    public function duplicate(string $newName = null): self
    {
        $data = $this->toArray();
        unset($data['id'], $data['created_at'], $data['updated_at']);
        
        $data['name'] = $newName ?? $this->name . ' (Copy)';
        $data['status'] = self::STATUS_DRAFT;
        $data['is_active'] = false;
        $data['last_run_at'] = null;
        $data['next_run_at'] = null;
        
        return self::create($data);
    }

    /**
     * Archive report
     */
    public function archive(): void
    {
        $this->status = self::STATUS_ARCHIVED;
        $this->is_active = false;
        $this->is_scheduled = false;
        $this->save();
    }

    /**
     * Get type label
     */
    public function getTypeLabel(): string
    {
        $labels = [
            self::TYPE_SALES => 'Sales Report',
            self::TYPE_CUSTOMERS => 'Customer Report',
            self::TYPE_PRODUCTS => 'Product Report',
            self::TYPE_INVENTORY => 'Inventory Report',
            self::TYPE_FINANCIAL => 'Financial Report',
            self::TYPE_TRAFFIC => 'Traffic Report',
            self::TYPE_CONVERSION => 'Conversion Report',
            self::TYPE_COHORT => 'Cohort Analysis',
            self::TYPE_FUNNEL => 'Funnel Analysis',
            self::TYPE_CUSTOM => 'Custom Report'
        ];
        
        return $labels[$this->type] ?? ucfirst($this->type);
    }

    /**
     * Get status label
     */
    public function getStatusLabel(): string
    {
        $labels = [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_RUNNING => 'Running',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_ARCHIVED => 'Archived'
        ];
        
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Format for display
     */
    public function formatForDisplay(): array
    {
        $stats = $this->getExecutionStatistics();
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'output_format' => $this->output_format,
            'is_scheduled' => $this->is_scheduled,
            'is_active' => $this->is_active,
            'schedule_frequency' => $this->getScheduleFrequency(),
            'last_run_at' => $this->last_run_at?->format('Y-m-d H:i:s'),
            'next_run_at' => $this->next_run_at?->format('Y-m-d H:i:s'),
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'statistics' => $stats,
            'recipient_count' => count($this->getRecipients())
        ];
    }
}