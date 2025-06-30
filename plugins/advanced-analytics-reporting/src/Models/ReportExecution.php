<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedAnalyticsReporting\Models;

use Shopologic\Core\Database\Model;

class ReportExecution extends Model
{
    protected string $table = 'report_executions';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'report_id',
        'triggered_by',
        'trigger_type',
        'status',
        'started_at',
        'completed_at',
        'duration_seconds',
        'row_count',
        'file_path',
        'file_size',
        'parameters',
        'error_message',
        'error_details',
        'execution_context',
        'memory_usage',
        'cpu_usage'
    ];

    protected array $casts = [
        'report_id' => 'integer',
        'triggered_by' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_seconds' => 'integer',
        'row_count' => 'integer',
        'file_size' => 'integer',
        'parameters' => 'json',
        'error_details' => 'json',
        'execution_context' => 'json',
        'memory_usage' => 'integer',
        'cpu_usage' => 'decimal:2'
    ];

    /**
     * Execution statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_TIMEOUT = 'timeout';

    /**
     * Trigger types
     */
    const TRIGGER_MANUAL = 'manual';
    const TRIGGER_SCHEDULED = 'scheduled';
    const TRIGGER_API = 'api';
    const TRIGGER_WEBHOOK = 'webhook';
    const TRIGGER_EVENT = 'event';

    /**
     * Get report
     */
    public function report()
    {
        return $this->belongsTo(Report::class, 'report_id');
    }

    /**
     * Get user who triggered execution
     */
    public function triggeredBy()
    {
        return $this->belongsTo('Shopologic\Core\Models\User', 'triggered_by');
    }

    /**
     * Scope by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope completed executions
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope failed executions
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope running executions
     */
    public function scopeRunning($query)
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    /**
     * Scope by trigger type
     */
    public function scopeByTriggerType($query, string $triggerType)
    {
        return $query->where('trigger_type', $triggerType);
    }

    /**
     * Scope recent executions
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('started_at', '>=', now()->subDays($days));
    }

    /**
     * Check if execution is running
     */
    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    /**
     * Check if execution is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if execution failed
     */
    public function isFailed(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_TIMEOUT, self::STATUS_CANCELLED]);
    }

    /**
     * Start execution
     */
    public function start(): void
    {
        $this->status = self::STATUS_RUNNING;
        $this->started_at = now();
        $this->save();
    }

    /**
     * Mark as completed
     */
    public function complete(int $rowCount = null, string $filePath = null, int $fileSize = null): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = now();
        $this->duration_seconds = $this->started_at ? 
            $this->started_at->diffInSeconds($this->completed_at) : 0;
        
        if ($rowCount !== null) {
            $this->row_count = $rowCount;
        }
        
        if ($filePath !== null) {
            $this->file_path = $filePath;
        }
        
        if ($fileSize !== null) {
            $this->file_size = $fileSize;
        }
        
        $this->save();
    }

    /**
     * Mark as failed
     */
    public function fail(string $errorMessage, array $errorDetails = []): void
    {
        $this->status = self::STATUS_FAILED;
        $this->completed_at = now();
        $this->duration_seconds = $this->started_at ? 
            $this->started_at->diffInSeconds($this->completed_at) : 0;
        $this->error_message = $errorMessage;
        $this->error_details = $errorDetails;
        $this->save();
    }

    /**
     * Cancel execution
     */
    public function cancel(string $reason = null): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->completed_at = now();
        $this->duration_seconds = $this->started_at ? 
            $this->started_at->diffInSeconds($this->completed_at) : 0;
        
        if ($reason) {
            $this->error_message = 'Cancelled: ' . $reason;
        }
        
        $this->save();
    }

    /**
     * Mark as timeout
     */
    public function timeout(): void
    {
        $this->status = self::STATUS_TIMEOUT;
        $this->completed_at = now();
        $this->duration_seconds = $this->started_at ? 
            $this->started_at->diffInSeconds($this->completed_at) : 0;
        $this->error_message = 'Execution timeout';
        $this->save();
    }

    /**
     * Get execution duration in human readable format
     */
    public function getFormattedDuration(): string
    {
        if (!$this->duration_seconds) {
            return 'N/A';
        }
        
        $seconds = $this->duration_seconds;
        
        if ($seconds < 60) {
            return $seconds . 's';
        }
        
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        if ($minutes < 60) {
            return $minutes . 'm ' . $remainingSeconds . 's';
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        return $hours . 'h ' . $remainingMinutes . 'm ' . $remainingSeconds . 's';
    }

    /**
     * Get formatted file size
     */
    public function getFormattedFileSize(): string
    {
        if (!$this->file_size) {
            return 'N/A';
        }
        
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
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
     * Get execution context value
     */
    public function getContextValue(string $key, $default = null)
    {
        $context = $this->execution_context ?? [];
        return $context[$key] ?? $default;
    }

    /**
     * Set execution context value
     */
    public function setContextValue(string $key, $value): void
    {
        $context = $this->execution_context ?? [];
        $context[$key] = $value;
        $this->execution_context = $context;
        $this->save();
    }

    /**
     * Update memory usage
     */
    public function updateMemoryUsage(): void
    {
        $this->memory_usage = memory_get_peak_usage(true);
        $this->save();
    }

    /**
     * Get status label
     */
    public function getStatusLabel(): string
    {
        $labels = [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_RUNNING => 'Running',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_TIMEOUT => 'Timeout'
        ];
        
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Get trigger type label
     */
    public function getTriggerTypeLabel(): string
    {
        $labels = [
            self::TRIGGER_MANUAL => 'Manual',
            self::TRIGGER_SCHEDULED => 'Scheduled',
            self::TRIGGER_API => 'API',
            self::TRIGGER_WEBHOOK => 'Webhook',
            self::TRIGGER_EVENT => 'Event'
        ];
        
        return $labels[$this->trigger_type] ?? ucfirst($this->trigger_type);
    }

    /**
     * Get status color
     */
    public function getStatusColor(): string
    {
        $colors = [
            self::STATUS_PENDING => 'orange',
            self::STATUS_RUNNING => 'blue',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_FAILED => 'red',
            self::STATUS_CANCELLED => 'gray',
            self::STATUS_TIMEOUT => 'red'
        ];
        
        return $colors[$this->status] ?? 'gray';
    }

    /**
     * Check if file exists
     */
    public function fileExists(): bool
    {
        return $this->file_path && file_exists($this->file_path);
    }

    /**
     * Get file download URL
     */
    public function getDownloadUrl(): ?string
    {
        if (!$this->fileExists()) {
            return null;
        }
        
        return route('analytics.download-report', ['execution' => $this->id]);
    }

    /**
     * Clean up execution files
     */
    public function cleanup(): void
    {
        if ($this->file_path && file_exists($this->file_path)) {
            unlink($this->file_path);
            $this->file_path = null;
            $this->file_size = null;
            $this->save();
        }
    }

    /**
     * Format for display
     */
    public function formatForDisplay(): array
    {
        return [
            'id' => $this->id,
            'report_name' => $this->report->name ?? 'Unknown',
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'status_color' => $this->getStatusColor(),
            'trigger_type' => $this->trigger_type,
            'trigger_type_label' => $this->getTriggerTypeLabel(),
            'triggered_by' => $this->triggeredBy?->name ?? 'System',
            'started_at' => $this->started_at?->format('Y-m-d H:i:s'),
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'duration' => $this->getFormattedDuration(),
            'row_count' => $this->row_count,
            'file_size' => $this->getFormattedFileSize(),
            'file_exists' => $this->fileExists(),
            'download_url' => $this->getDownloadUrl(),
            'error_message' => $this->error_message,
            'memory_usage' => $this->memory_usage ? $this->getFormattedFileSize() : null,
            'cpu_usage' => $this->cpu_usage
        ];
    }

    /**
     * Get execution statistics for period
     */
    public static function getStatisticsForPeriod($startDate, $endDate): array
    {
        $executions = self::whereBetween('started_at', [$startDate, $endDate])->get();
        
        return [
            'total_executions' => $executions->count(),
            'completed_executions' => $executions->where('status', self::STATUS_COMPLETED)->count(),
            'failed_executions' => $executions->where('status', self::STATUS_FAILED)->count(),
            'cancelled_executions' => $executions->where('status', self::STATUS_CANCELLED)->count(),
            'average_duration' => $executions->where('status', self::STATUS_COMPLETED)->avg('duration_seconds'),
            'total_rows_generated' => $executions->where('status', self::STATUS_COMPLETED)->sum('row_count'),
            'total_file_size' => $executions->where('status', self::STATUS_COMPLETED)->sum('file_size'),
            'success_rate' => $executions->count() > 0 ? 
                ($executions->where('status', self::STATUS_COMPLETED)->count() / $executions->count()) * 100 : 0,
            'manual_executions' => $executions->where('trigger_type', self::TRIGGER_MANUAL)->count(),
            'scheduled_executions' => $executions->where('trigger_type', self::TRIGGER_SCHEDULED)->count(),
            'api_executions' => $executions->where('trigger_type', self::TRIGGER_API)->count()
        ];
    }
}