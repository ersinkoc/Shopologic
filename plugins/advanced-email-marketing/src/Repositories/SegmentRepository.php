<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Repositories;

use Shopologic\Core\Database\Repository;
use Shopologic\Core\Database\DB;
use AdvancedEmailMarketing\Models\Segment;

class SegmentRepository extends Repository
{
    protected string $table = 'email_segments';
    protected string $primaryKey = 'id';
    protected string $modelClass = Segment::class;

    /**
     * Get segments with pagination
     */
    public function getWithPagination(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $query = DB::table($this->table);
        
        $this->applyFilters($query, $filters);
        
        $total = $query->count();
        $offset = ($page - 1) * $perPage;
        
        $segments = $query->orderBy('created_at', 'desc')
            ->limit($perPage)
            ->offset($offset)
            ->get();
        
        return [
            'data' => $segments,
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
     * Get segments by campaign ID
     */
    public function getByCampaignId(int $campaignId): array
    {
        return DB::table($this->table . ' as s')
            ->join('campaign_segments as cs', 's.id', '=', 'cs.segment_id')
            ->where('cs.campaign_id', $campaignId)
            ->select('s.*')
            ->get();
    }

    /**
     * Get segments by automation ID
     */
    public function getByAutomationId(int $automationId): array
    {
        return DB::table($this->table . ' as s')
            ->join('automation_segments as as', 's.id', '=', 'as.segment_id')
            ->where('as.automation_id', $automationId)
            ->select('s.*')
            ->get();
    }

    /**
     * Get segments by subscriber ID
     */
    public function getBySubscriberId(int $subscriberId): array
    {
        return DB::table($this->table . ' as s')
            ->join('segment_members as sm', 's.id', '=', 'sm.segment_id')
            ->where('sm.subscriber_id', $subscriberId)
            ->select('s.*', 'sm.criteria_match', 'sm.added_at')
            ->get();
    }

    /**
     * Get member count
     */
    public function getMemberCount(int $segmentId): int
    {
        return DB::table('segment_members')
            ->where('segment_id', $segmentId)
            ->count();
    }

    /**
     * Get members with pagination
     */
    public function getMembers(int $segmentId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $query = DB::table('segment_members as sm')
            ->join('email_subscribers as s', 'sm.subscriber_id', '=', 's.id')
            ->where('sm.segment_id', $segmentId)
            ->select('s.*', 'sm.criteria_match', 'sm.added_at as member_since');
        
        if (isset($filters['status'])) {
            $query->where('s.status', $filters['status']);
        }
        
        if (isset($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('s.email', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhere('s.first_name', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhere('s.last_name', 'LIKE', '%' . $filters['search'] . '%');
            });
        }
        
        $total = $query->count();
        $offset = ($page - 1) * $perPage;
        
        $members = $query->orderBy('sm.added_at', 'desc')
            ->limit($perPage)
            ->offset($offset)
            ->get();
        
        return [
            'data' => $members,
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
     * Add members to segment
     */
    public function addMembers(int $segmentId, array $subscriberIds, array $criteriaMatch = []): int
    {
        $added = 0;
        $now = now();
        
        foreach ($subscriberIds as $subscriberId) {
            $exists = DB::table('segment_members')
                ->where('segment_id', $segmentId)
                ->where('subscriber_id', $subscriberId)
                ->exists();
            
            if (!$exists) {
                DB::table('segment_members')->insert([
                    'segment_id' => $segmentId,
                    'subscriber_id' => $subscriberId,
                    'criteria_match' => json_encode($criteriaMatch[$subscriberId] ?? []),
                    'added_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now
                ]);
                $added++;
            }
        }
        
        // Update member count
        $this->updateMemberCount($segmentId);
        
        return $added;
    }

    /**
     * Remove members from segment
     */
    public function removeMembers(int $segmentId, array $subscriberIds): int
    {
        $removed = DB::table('segment_members')
            ->where('segment_id', $segmentId)
            ->whereIn('subscriber_id', $subscriberIds)
            ->delete();
        
        // Update member count
        $this->updateMemberCount($segmentId);
        
        return $removed;
    }

    /**
     * Clear all members
     */
    public function clearMembers(int $segmentId): int
    {
        $removed = DB::table('segment_members')
            ->where('segment_id', $segmentId)
            ->delete();
        
        // Update member count
        DB::table($this->table)
            ->where('id', $segmentId)
            ->update(['member_count' => 0, 'updated_at' => now()]);
        
        return $removed;
    }

    /**
     * Update member count
     */
    public function updateMemberCount(int $segmentId): void
    {
        $count = DB::table('segment_members')
            ->where('segment_id', $segmentId)
            ->count();
        
        DB::table($this->table)
            ->where('id', $segmentId)
            ->update([
                'member_count' => $count,
                'updated_at' => now()
            ]);
    }

    /**
     * Get segment usage
     */
    public function getSegmentUsage(int $segmentId): array
    {
        $usage = [];
        
        // Campaign usage
        $campaigns = DB::table('campaign_segments as cs')
            ->join('email_campaigns as c', 'cs.campaign_id', '=', 'c.id')
            ->where('cs.segment_id', $segmentId)
            ->whereIn('c.status', ['draft', 'scheduled', 'sending'])
            ->select('c.id', 'c.name', 'c.status')
            ->get();
        
        foreach ($campaigns as $campaign) {
            $usage[] = [
                'type' => 'campaign',
                'id' => $campaign->id,
                'name' => $campaign->name,
                'status' => $campaign->status
            ];
        }
        
        // Automation usage
        $automations = DB::table('automation_segments as as')
            ->join('email_automations as a', 'as.automation_id', '=', 'a.id')
            ->where('as.segment_id', $segmentId)
            ->where('a.status', 'active')
            ->select('a.id', 'a.name')
            ->get();
        
        foreach ($automations as $automation) {
            $usage[] = [
                'type' => 'automation',
                'id' => $automation->id,
                'name' => $automation->name
            ];
        }
        
        return $usage;
    }

    /**
     * Get active segments
     */
    public function getActiveSegments(): array
    {
        return DB::table($this->table)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get dynamic segments needing update
     */
    public function getDynamicSegmentsNeedingUpdate(): array
    {
        $cutoff = now()->subHours(24);
        
        return DB::table($this->table)
            ->where('type', 'dynamic')
            ->where('is_active', true)
            ->where(function($query) use ($cutoff) {
                $query->whereNull('last_calculated_at')
                      ->orWhere('last_calculated_at', '<', $cutoff);
            })
            ->get();
    }

    /**
     * Get growth metrics
     */
    public function getGrowthMetrics(int $segmentId, int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        $dailyGrowth = DB::table('segment_members')
            ->where('segment_id', $segmentId)
            ->where('added_at', '>=', $startDate)
            ->select(
                DB::raw('DATE(added_at) as date'),
                DB::raw('COUNT(*) as new_members')
            )
            ->groupBy(DB::raw('DATE(added_at)'))
            ->orderBy('date')
            ->get();
        
        $totalBefore = DB::table('segment_members')
            ->where('segment_id', $segmentId)
            ->where('added_at', '<', $startDate)
            ->count();
        
        $currentTotal = $this->getMemberCount($segmentId);
        $growth = $currentTotal - $totalBefore;
        $growthRate = $totalBefore > 0 ? ($growth / $totalBefore) * 100 : 0;
        
        return [
            'daily_growth' => $dailyGrowth,
            'total_growth' => $growth,
            'growth_rate' => $growthRate,
            'current_total' => $currentTotal
        ];
    }

    /**
     * Search segments
     */
    public function searchSegments(string $query): array
    {
        return DB::table($this->table)
            ->where(function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('description', 'LIKE', "%{$query}%");
            })
            ->where('is_active', true)
            ->orderBy('member_count', 'desc')
            ->limit(20)
            ->get();
    }

    /**
     * Get segments by tag
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
     * Update calculation status
     */
    public function updateCalculationStatus(int $segmentId, string $status, string $error = null): bool
    {
        $data = [
            'calculation_status' => $status,
            'updated_at' => now()
        ];
        
        if ($status === 'completed') {
            $data['last_calculated_at'] = now();
            $data['calculation_error'] = null;
        } elseif ($status === 'failed' && $error) {
            $data['calculation_error'] = $error;
        }
        
        return DB::table($this->table)
            ->where('id', $segmentId)
            ->update($data) > 0;
    }

    /**
     * Get segment overlap
     */
    public function getSegmentOverlap(array $segmentIds): array
    {
        if (count($segmentIds) < 2) {
            return [];
        }
        
        $result = [];
        
        for ($i = 0; $i < count($segmentIds); $i++) {
            for ($j = $i + 1; $j < count($segmentIds); $j++) {
                $overlap = DB::table('segment_members as sm1')
                    ->join('segment_members as sm2', 'sm1.subscriber_id', '=', 'sm2.subscriber_id')
                    ->where('sm1.segment_id', $segmentIds[$i])
                    ->where('sm2.segment_id', $segmentIds[$j])
                    ->count();
                
                $result[] = [
                    'segment1' => $segmentIds[$i],
                    'segment2' => $segmentIds[$j],
                    'overlap_count' => $overlap
                ];
            }
        }
        
        return $result;
    }

    /**
     * Apply filters to query
     */
    private function applyFilters($query, array $filters): void
    {
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        
        if (isset($filters['status'])) {
            if ($filters['status'] === 'active') {
                $query->where('is_active', true);
            } elseif ($filters['status'] === 'inactive') {
                $query->where('is_active', false);
            }
        }
        
        if (isset($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('name', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'LIKE', '%' . $filters['search'] . '%');
            });
        }
    }
}