<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedAnalyticsReporting\Repositories;

use Shopologic\Core\Database\QueryBuilder;

class ReportRepository\n{
    private string $table = 'analytics_reports';

    /**
     * Create a new report
     */
    public function create(array $data): array
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $id = QueryBuilder::table($this->table)->insert($data);
        return $this->findById($id);
    }

    /**
     * Find report by ID
     */
    public function findById(int $id): ?array
    {
        return QueryBuilder::table($this->table)
            ->where('id', $id)
            ->first();
    }

    /**
     * Find report by slug
     */
    public function findBySlug(string $slug): ?array
    {
        return QueryBuilder::table($this->table)
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Update report
     */
    public function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return QueryBuilder::table($this->table)
            ->where('id', $id)
            ->update($data) > 0;
    }

    /**
     * Delete report
     */
    public function delete(int $id): bool
    {
        return QueryBuilder::table($this->table)
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Get all reports
     */
    public function getAll(): array
    {
        return QueryBuilder::table($this->table)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Get reports by type
     */
    public function getByType(string $type): array
    {
        return QueryBuilder::table($this->table)
            ->where('type', $type)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Get reports by status
     */
    public function getByStatus(string $status): array
    {
        return QueryBuilder::table($this->table)
            ->where('status', $status)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Get reports by user
     */
    public function getByUser(int $userId): array
    {
        return QueryBuilder::table($this->table)
            ->where('created_by', $userId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Get scheduled reports
     */
    public function getScheduledReports(string $frequency): array
    {
        return QueryBuilder::table($this->table)
            ->where('is_scheduled', true)
            ->where('schedule_frequency', $frequency)
            ->where('status', 'active')
            ->where(function($query) {
                $query->whereNull('next_run_at')
                      ->orWhere('next_run_at', '<=', date('Y-m-d H:i:s'));
            })
            ->get();
    }

    /**
     * Get reports with pagination
     */
    public function getPaginated(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        $query = QueryBuilder::table($this->table);

        // Apply filters
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        if (isset($filters['is_scheduled'])) {
            $query->where('is_scheduled', $filters['is_scheduled']);
        }

        if (isset($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $search = $filters['search'];
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        $total = $query->count();
        $reports = $query->orderBy('created_at', 'DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return [
            'data' => $reports,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * Get report statistics
     */
    public function getReportStatistics(): array
    {
        $result = QueryBuilder::table($this->table)
            ->select([
                'COUNT(*) as total_reports',
                'COUNT(CASE WHEN status = "active" THEN 1 END) as active_reports',
                'COUNT(CASE WHEN status = "draft" THEN 1 END) as draft_reports',
                'COUNT(CASE WHEN is_scheduled = 1 THEN 1 END) as scheduled_reports',
                'COUNT(CASE WHEN last_generated_at IS NOT NULL THEN 1 END) as generated_reports'
            ])
            ->first();

        return $result ?: [];
    }

    /**
     * Get most popular reports
     */
    public function getMostPopularReports(int $limit = 10): array
    {
        // This would require a separate tracking table for report views
        // For now, return reports ordered by last generated date
        return QueryBuilder::table($this->table)
            ->where('status', 'active')
            ->whereNotNull('last_generated_at')
            ->orderBy('last_generated_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent reports
     */
    public function getRecentReports(int $limit = 5): array
    {
        return QueryBuilder::table($this->table)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recently generated reports
     */
    public function getRecentlyGeneratedReports(int $limit = 5): array
    {
        return QueryBuilder::table($this->table)
            ->whereNotNull('last_generated_at')
            ->orderBy('last_generated_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Get overdue scheduled reports
     */
    public function getOverdueScheduledReports(): array
    {
        return QueryBuilder::table($this->table)
            ->where('is_scheduled', true)
            ->where('status', 'active')
            ->where('next_run_at', '<', date('Y-m-d H:i:s'))
            ->get();
    }

    /**
     * Get reports by type distribution
     */
    public function getReportTypeDistribution(): array
    {
        return QueryBuilder::table($this->table)
            ->select([
                'type',
                'COUNT(*) as count'
            ])
            ->groupBy('type')
            ->orderBy('count', 'DESC')
            ->get();
    }

    /**
     * Search reports
     */
    public function search(string $query, array $filters = []): array
    {
        $searchQuery = QueryBuilder::table($this->table);

        // Search in name and description
        $searchQuery->where(function($q) use ($query) {
            $q->where('name', 'LIKE', "%{$query}%")
              ->orWhere('description', 'LIKE', "%{$query}%")
              ->orWhere('slug', 'LIKE', "%{$query}%");
        });

        // Apply additional filters
        if (isset($filters['type'])) {
            $searchQuery->where('type', $filters['type']);
        }

        if (isset($filters['status'])) {
            $searchQuery->where('status', $filters['status']);
        }

        if (isset($filters['created_by'])) {
            $searchQuery->where('created_by', $filters['created_by']);
        }

        return $searchQuery->orderBy('created_at', 'DESC')
            ->limit(50)
            ->get();
    }

    /**
     * Duplicate report
     */
    public function duplicate(int $reportId, int $userId): ?array
    {
        $originalReport = $this->findById($reportId);
        if (!$originalReport) {
            return null;
        }

        // Prepare data for duplication
        $duplicateData = $originalReport;
        unset($duplicateData['id']);
        unset($duplicateData['created_at']);
        unset($duplicateData['updated_at']);
        unset($duplicateData['last_generated_at']);
        unset($duplicateData['next_run_at']);

        // Modify for duplicate
        $duplicateData['name'] = $originalReport['name'] . ' (Copy)';
        $duplicateData['slug'] = $originalReport['slug'] . '-copy-' . time();
        $duplicateData['status'] = 'draft';
        $duplicateData['created_by'] = $userId;
        $duplicateData['is_scheduled'] = false;

        return $this->create($duplicateData);
    }

    /**
     * Archive old reports
     */
    public function archiveOldReports(int $daysOld): int
    {
        $cutoffDate = date('Y-m-d', strtotime("-{$daysOld} days"));
        
        return QueryBuilder::table($this->table)
            ->where('status', 'active')
            ->where('created_at', '<', $cutoffDate)
            ->whereNull('last_generated_at')
            ->update([
                'status' => 'archived',
                'updated_at' => date('Y-m-d H:i:s')
            ]);
    }

    /**
     * Get report templates
     */
    public function getReportTemplates(): array
    {
        return QueryBuilder::table($this->table)
            ->where('type', 'template')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    /**
     * Create report from template
     */
    public function createFromTemplate(int $templateId, array $customizations, int $userId): ?array
    {
        $template = $this->findById($templateId);
        if (!$template || $template['type'] !== 'template') {
            return null;
        }

        // Prepare data from template
        $reportData = $template;
        unset($reportData['id']);
        unset($reportData['created_at']);
        unset($reportData['updated_at']);
        unset($reportData['last_generated_at']);
        unset($reportData['next_run_at']);

        // Apply customizations
        $reportData = array_merge($reportData, $customizations);
        $reportData['type'] = $customizations['type'] ?? 'custom';
        $reportData['status'] = 'draft';
        $reportData['created_by'] = $userId;
        $reportData['slug'] = $this->generateUniqueSlug($reportData['name']);

        return $this->create($reportData);
    }

    /**
     * Generate unique slug
     */
    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        $slug = $baseSlug;
        $counter = 1;

        while ($this->findBySlug($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get reports scheduled for today
     */
    public function getReportsScheduledForToday(): array
    {
        $today = date('Y-m-d');
        
        return QueryBuilder::table($this->table)
            ->where('is_scheduled', true)
            ->where('status', 'active')
            ->where('next_run_at', 'LIKE', "{$today}%")
            ->orderBy('next_run_at')
            ->get();
    }

    /**
     * Bulk update reports
     */
    public function bulkUpdate(array $reportIds, array $data): int
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return QueryBuilder::table($this->table)
            ->whereIn('id', $reportIds)
            ->update($data);
    }

    /**
     * Bulk delete reports
     */
    public function bulkDelete(array $reportIds): int
    {
        return QueryBuilder::table($this->table)
            ->whereIn('id', $reportIds)
            ->delete();
    }

    /**
     * Get report execution history
     */
    public function getReportExecutionHistory(int $reportId, int $limit = 10): array
    {
        // This would require a separate report_executions table
        // For now, return empty array
        return [];
    }

    /**
     * Check if user can access report
     */
    public function canUserAccessReport(int $reportId, int $userId): bool
    {
        $report = $this->findById($reportId);
        if (!$report) {
            return false;
        }

        // Owner can always access
        if ($report['created_by'] == $userId) {
            return true;
        }

        // Check if report is public or user has permissions
        // This would depend on your permission system
        return $report['status'] === 'active';
    }
}