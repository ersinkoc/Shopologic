<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Repositories;

use Shopologic\Core\Database\Repository;
use Shopologic\Core\Database\DB;
use AdvancedEmailMarketing\Models\Template;

class TemplateRepository extends Repository
{
    protected string $table = 'email_templates';
    protected string $primaryKey = 'id';
    protected string $modelClass = Template::class;

    /**
     * Get templates with pagination
     */
    public function getWithPagination(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $query = DB::table($this->table);
        
        $this->applyFilters($query, $filters);
        
        $total = $query->count();
        $offset = ($page - 1) * $perPage;
        
        $templates = $query->orderBy('created_at', 'desc')
            ->limit($perPage)
            ->offset($offset)
            ->get();
        
        return [
            'data' => $templates,
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
     * Get active templates
     */
    public function getActiveTemplates(string $type = null): array
    {
        $query = DB::table($this->table)
            ->where('is_active', true);
        
        if ($type) {
            $query->where('type', $type);
        }
        
        return $query->orderBy('name')->get();
    }

    /**
     * Get templates by type
     */
    public function getByType(string $type): array
    {
        return DB::table($this->table)
            ->where('type', $type)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get templates by category
     */
    public function getByCategory(string $category): array
    {
        return DB::table($this->table)
            ->where('category', $category)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get template usage count
     */
    public function getUsageCount(int $templateId): int
    {
        $campaignCount = DB::table('email_campaigns')
            ->where('template_id', $templateId)
            ->count();
        
        $automationCount = DB::table('automation_steps')
            ->where('template_id', $templateId)
            ->count();
        
        return $campaignCount + $automationCount;
    }

    /**
     * Get last used date
     */
    public function getLastUsed(int $templateId): ?string
    {
        $lastCampaign = DB::table('email_campaigns')
            ->where('template_id', $templateId)
            ->orderBy('created_at', 'desc')
            ->first();
        
        $lastAutomation = DB::table('automation_steps as s')
            ->join('subscriber_automations as sa', 's.id', '=', 'sa.current_step_id')
            ->where('s.template_id', $templateId)
            ->orderBy('sa.last_activity_at', 'desc')
            ->first();
        
        $dates = [];
        if ($lastCampaign) {
            $dates[] = $lastCampaign->created_at;
        }
        if ($lastAutomation) {
            $dates[] = $lastAutomation->last_activity_at;
        }
        
        if (empty($dates)) {
            return null;
        }
        
        return max($dates);
    }

    /**
     * Get recent campaigns using template
     */
    public function getRecentCampaigns(int $templateId, int $limit = 5): array
    {
        return DB::table('email_campaigns')
            ->where('template_id', $templateId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get active usage (campaigns/automations currently using template)
     */
    public function getActiveUsage(int $templateId): array
    {
        $usage = [];
        
        // Active campaigns
        $campaigns = DB::table('email_campaigns')
            ->where('template_id', $templateId)
            ->whereIn('status', ['draft', 'scheduled', 'sending'])
            ->get();
        
        foreach ($campaigns as $campaign) {
            $usage[] = [
                'type' => 'campaign',
                'id' => $campaign->id,
                'name' => $campaign->name,
                'status' => $campaign->status
            ];
        }
        
        // Active automations
        $automations = DB::table('automation_steps as s')
            ->join('email_automations as a', 's.automation_id', '=', 'a.id')
            ->where('s.template_id', $templateId)
            ->where('a.status', 'active')
            ->select('a.id', 'a.name', 's.name as step_name')
            ->get();
        
        foreach ($automations as $automation) {
            $usage[] = [
                'type' => 'automation',
                'id' => $automation->id,
                'name' => $automation->name,
                'step' => $automation->step_name
            ];
        }
        
        return $usage;
    }

    /**
     * Get template categories
     */
    public function getCategories(): array
    {
        return DB::table($this->table)
            ->whereNotNull('category')
            ->distinct('category')
            ->pluck('category')
            ->toArray();
    }

    /**
     * Search templates
     */
    public function searchTemplates(string $query): array
    {
        return DB::table($this->table)
            ->where(function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('subject', 'LIKE', "%{$query}%")
                  ->orWhere('category', 'LIKE', "%{$query}%");
            })
            ->where('is_active', true)
            ->orderBy('usage_count', 'desc')
            ->limit(20)
            ->get();
    }

    /**
     * Get popular templates
     */
    public function getPopularTemplates(int $limit = 10): array
    {
        return DB::table($this->table)
            ->where('is_active', true)
            ->orderBy('usage_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get template performance statistics
     */
    public function getTemplatePerformance(int $templateId): array
    {
        $stats = DB::table('email_campaigns as c')
            ->join('email_sends as s', 'c.id', '=', 's.campaign_id')
            ->where('c.template_id', $templateId)
            ->select(
                DB::raw('COUNT(DISTINCT c.id) as campaigns_used'),
                DB::raw('COUNT(s.id) as total_sends'),
                DB::raw('AVG(CASE WHEN s.opened_at IS NOT NULL THEN 1 ELSE 0 END) * 100 as avg_open_rate'),
                DB::raw('AVG(CASE WHEN s.clicked_at IS NOT NULL THEN 1 ELSE 0 END) * 100 as avg_click_rate'),
                DB::raw('AVG(CASE WHEN s.status = "bounced" THEN 1 ELSE 0 END) * 100 as avg_bounce_rate')
            )
            ->first();
        
        return (array)$stats;
    }

    /**
     * Get templates by tag
     */
    public function getByTag(string $tag): array
    {
        return DB::table($this->table)
            ->whereJsonContains('tags', $tag)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Update usage count
     */
    public function incrementUsageCount(int $templateId): bool
    {
        return DB::table($this->table)
            ->where('id', $templateId)
            ->update([
                'usage_count' => DB::raw('usage_count + 1'),
                'last_used_at' => now(),
                'updated_at' => now()
            ]) > 0;
    }

    /**
     * Get gallery templates
     */
    public function getGalleryTemplates(string $category = null): array
    {
        $query = DB::table($this->table)
            ->where('is_gallery_template', true)
            ->where('is_active', true);
        
        if ($category) {
            $query->where('category', $category);
        }
        
        return $query->orderBy('popularity_score', 'desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * Clone template
     */
    public function cloneTemplate(int $templateId, string $newName): ?int
    {
        $template = $this->findById($templateId);
        if (!$template) {
            return null;
        }
        
        unset($template['id']);
        $template['name'] = $newName;
        $template['usage_count'] = 0;
        $template['last_used_at'] = null;
        $template['created_at'] = now();
        $template['updated_at'] = now();
        
        return DB::table($this->table)->insertGetId($template);
    }

    /**
     * Get transactional templates
     */
    public function getTransactionalTemplates(): array
    {
        return DB::table($this->table)
            ->where('type', 'transactional')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Find by slug
     */
    public function findBySlug(string $slug): ?array
    {
        return DB::table($this->table)
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Apply filters to query
     */
    private function applyFilters($query, array $filters): void
    {
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        
        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        
        if (isset($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('name', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhere('subject', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhere('content', 'LIKE', '%' . $filters['search'] . '%');
            });
        }
        
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }
    }
}