<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Repositories;

use Shopologic\Core\Database\Repository;
use Shopologic\Core\Database\DB;
use AdvancedEmailMarketing\Models\Automation;

class AutomationRepository extends Repository
{
    protected string $table = 'email_automations';
    protected string $primaryKey = 'id';
    protected string $modelClass = Automation::class;

    /**
     * Get automations with pagination
     */
    public function getWithPagination(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $query = DB::table($this->table);
        
        $this->applyFilters($query, $filters);
        
        $total = $query->count();
        $offset = ($page - 1) * $perPage;
        
        $automations = $query->orderBy('created_at', 'desc')
            ->limit($perPage)
            ->offset($offset)
            ->get();
        
        return [
            'data' => $automations,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total)
            ]
        ];
    }

    /**
     * Get active automations
     */
    public function getActiveAutomations(): array
    {
        return DB::table($this->table)
            ->where('status', 'active')
            ->where(function($q) {
                $q->whereNull('start_date')
                  ->orWhere('start_date', '<=', now());
            })
            ->where(function($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>', now());
            })
            ->get();
    }

    /**
     * Get workflow steps
     */
    public function getWorkflowSteps(int $automationId): array
    {
        return DB::table('automation_steps')
            ->where('automation_id', $automationId)
            ->orderBy('order')
            ->get();
    }

    /**
     * Get active subscribers count
     */
    public function getActiveSubscribersCount(int $automationId): int
    {
        return DB::table('subscriber_automations')
            ->where('automation_id', $automationId)
            ->where('status', 'active')
            ->count();
    }

    /**
     * Get subscribers
     */
    public function getSubscribers(int $automationId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $query = DB::table('subscriber_automations as sa')
            ->join('email_subscribers as s', 'sa.subscriber_id', '=', 's.id')
            ->where('sa.automation_id', $automationId)
            ->select('s.*', 'sa.status as automation_status', 'sa.current_step_id', 
                    'sa.started_at', 'sa.completed_at', 'sa.steps_completed');
        
        if (isset($filters['status'])) {
            $query->where('sa.status', $filters['status']);
        }
        
        if (isset($filters['current_step'])) {
            $query->where('sa.current_step_id', $filters['current_step']);
        }
        
        $total = $query->count();
        $offset = ($page - 1) * $perPage;
        
        $subscribers = $query->orderBy('sa.started_at', 'desc')
            ->limit($perPage)
            ->offset($offset)
            ->get();
        
        return [
            'data' => $subscribers,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total)
            ]
        ];
    }

    /**
     * Get activity log
     */
    public function getActivityLog(int $automationId, array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $query = DB::table('automation_activity_log')
            ->where('automation_id', $automationId);
        
        if (isset($filters['action_type'])) {
            $query->where('action_type', $filters['action_type']);
        }
        
        if (isset($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        
        if (isset($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }
        
        $total = $query->count();
        $offset = ($page - 1) * $perPage;
        
        $logs = $query->orderBy('created_at', 'desc')
            ->limit($perPage)
            ->offset($offset)
            ->get();
        
        return [
            'data' => $logs,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total)
            ]
        ];
    }

    /**
     * Get automation statistics
     */
    public function getAutomationStatistics(int $automationId): array
    {
        $stats = DB::table('subscriber_automations')
            ->where('automation_id', $automationId)
            ->select(
                DB::raw('COUNT(*) as total_subscribers'),
                DB::raw('SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_subscribers'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_subscribers'),
                DB::raw('SUM(CASE WHEN status = "paused" THEN 1 ELSE 0 END) as paused_subscribers'),
                DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_subscribers'),
                DB::raw('AVG(steps_completed) as avg_steps_completed'),
                DB::raw('SUM(emails_sent) as total_emails_sent')
            )
            ->first();
        
        return (array)$stats;
    }

    /**
     * Get automations by trigger type
     */
    public function getByTriggerType(string $triggerType): array
    {
        return DB::table($this->table)
            ->where('trigger_type', $triggerType)
            ->where('status', 'active')
            ->get();
    }

    /**
     * Get subscribers ready for next step
     */
    public function getSubscribersReadyForNextStep(int $automationId): array
    {
        return DB::table('subscriber_automations as sa')
            ->join('automation_steps as s', 'sa.current_step_id', '=', 's.id')
            ->where('sa.automation_id', $automationId)
            ->where('sa.status', 'active')
            ->whereNotNull('sa.current_step_id')
            ->where(function($query) {
                $query->where('s.delay_minutes', 0)
                      ->orWhereRaw('DATE_ADD(sa.last_activity_at, INTERVAL s.delay_minutes MINUTE) <= NOW()');
            })
            ->select('sa.*')
            ->get();
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(int $automationId, \DateTime $startDate, \DateTime $endDate): array
    {
        $completionRate = DB::table('subscriber_automations')
            ->where('automation_id', $automationId)
            ->whereBetween('started_at', [$startDate, $endDate])
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed')
            )
            ->first();
        
        $emailMetrics = DB::table('email_sends as s')
            ->where('automation_id', $automationId)
            ->whereBetween('sent_at', [$startDate, $endDate])
            ->select(
                DB::raw('COUNT(*) as total_sent'),
                DB::raw('AVG(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) * 100 as open_rate'),
                DB::raw('AVG(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) * 100 as click_rate')
            )
            ->first();
        
        return [
            'completion_rate' => $completionRate->total > 0 ? ($completionRate->completed / $completionRate->total) * 100 : 0,
            'total_subscribers' => $completionRate->total,
            'completed_subscribers' => $completionRate->completed,
            'emails_sent' => $emailMetrics->total_sent,
            'open_rate' => $emailMetrics->open_rate,
            'click_rate' => $emailMetrics->click_rate
        ];
    }

    /**
     * Update automation status
     */
    public function updateStatus(int $automationId, string $status): bool
    {
        return DB::table($this->table)
            ->where('id', $automationId)
            ->update([
                'status' => $status,
                'updated_at' => now()
            ]) > 0;
    }

    /**
     * Create workflow step
     */
    public function createWorkflowStep(int $automationId, array $stepData): int
    {
        $stepData['automation_id'] = $automationId;
        $stepData['created_at'] = now();
        $stepData['updated_at'] = now();
        
        return DB::table('automation_steps')->insertGetId($stepData);
    }

    /**
     * Update workflow step
     */
    public function updateWorkflowStep(int $stepId, array $stepData): bool
    {
        $stepData['updated_at'] = now();
        
        return DB::table('automation_steps')
            ->where('id', $stepId)
            ->update($stepData) > 0;
    }

    /**
     * Delete workflow step
     */
    public function deleteWorkflowStep(int $stepId): bool
    {
        return DB::table('automation_steps')
            ->where('id', $stepId)
            ->delete() > 0;
    }

    /**
     * Reorder workflow steps
     */
    public function reorderWorkflowSteps(int $automationId, array $stepOrder): bool
    {
        DB::beginTransaction();
        
        try {
            foreach ($stepOrder as $order => $stepId) {
                DB::table('automation_steps')
                    ->where('id', $stepId)
                    ->where('automation_id', $automationId)
                    ->update(['order' => $order]);
            }
            
            DB::commit();
            return true;
        } catch (\RuntimeException $e) {
            DB::rollBack();
            return false;
        }
    }

    /**
     * Get funnel visualization data
     */
    public function getFunnelData(int $automationId): array
    {
        $steps = $this->getWorkflowSteps($automationId);
        $funnel = [];
        
        foreach ($steps as $step) {
            $stats = DB::table('subscriber_automation_history')
                ->where('step_id', $step->id)
                ->select(
                    DB::raw('COUNT(DISTINCT subscriber_automation_id) as reached'),
                    DB::raw('SUM(CASE WHEN result = "completed" THEN 1 ELSE 0 END) as completed'),
                    DB::raw('SUM(CASE WHEN result = "skipped" THEN 1 ELSE 0 END) as skipped')
                )
                ->first();
            
            $funnel[] = [
                'step' => $step,
                'reached' => $stats->reached,
                'completed' => $stats->completed,
                'skipped' => $stats->skipped
            ];
        }
        
        return $funnel;
    }

    /**
     * Clone automation
     */
    public function cloneAutomation(int $automationId): ?int
    {
        DB::beginTransaction();
        
        try {
            // Clone automation
            $automation = $this->findById($automationId);
            if (!$automation) {
                DB::rollBack();
                return null;
            }
            
            unset($automation['id']);
            $automation['name'] = $automation['name'] . ' (Copy)';
            $automation['status'] = 'draft';
            $automation['statistics'] = null;
            $automation['created_at'] = now();
            $automation['updated_at'] = now();
            
            $newAutomationId = DB::table($this->table)->insertGetId($automation);
            
            // Clone steps
            $steps = $this->getWorkflowSteps($automationId);
            foreach ($steps as $step) {
                $stepData = (array)$step;
                unset($stepData['id']);
                $stepData['automation_id'] = $newAutomationId;
                $stepData['emails_sent'] = 0;
                $stepData['last_executed_at'] = null;
                $stepData['created_at'] = now();
                $stepData['updated_at'] = now();
                
                DB::table('automation_steps')->insert($stepData);
            }
            
            // Clone segment associations
            $segments = DB::table('automation_segments')
                ->where('automation_id', $automationId)
                ->pluck('segment_id');
            
            foreach ($segments as $segmentId) {
                DB::table('automation_segments')->insert([
                    'automation_id' => $newAutomationId,
                    'segment_id' => $segmentId,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            DB::commit();
            return $newAutomationId;
        } catch (\RuntimeException $e) {
            DB::rollBack();
            return null;
        }
    }

    /**
     * Apply filters to query
     */
    private function applyFilters($query, array $filters): void
    {
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['trigger_type'])) {
            $query->where('trigger_type', $filters['trigger_type']);
        }
        
        if (isset($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('name', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'LIKE', '%' . $filters['search'] . '%');
            });
        }
    }
}