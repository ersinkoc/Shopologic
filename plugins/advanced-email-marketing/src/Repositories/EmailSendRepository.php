<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Repositories;

use Shopologic\Core\Database\Repository;
use Shopologic\Core\Database\DB;
use AdvancedEmailMarketing\Models\EmailSend;

class EmailSendRepository extends Repository
{
    protected string $table = 'email_sends';
    protected string $primaryKey = 'id';
    protected string $modelClass = EmailSend::class;

    /**
     * Create email send record
     */
    public function createSend(array $data): int
    {
        $data['sent_at'] = $data['sent_at'] ?? now();
        $data['created_at'] = now();
        $data['updated_at'] = now();
        
        return DB::table($this->table)->insertGetId($data);
    }

    /**
     * Update delivery status
     */
    public function updateDeliveryStatus(string $messageId, string $status, array $details = []): bool
    {
        $data = [
            'status' => $status,
            'updated_at' => now()
        ];
        
        if ($status === 'delivered') {
            $data['delivered_at'] = now();
        } elseif ($status === 'bounced') {
            $data['bounced_at'] = now();
            $data['bounce_type'] = $details['bounce_type'] ?? 'hard';
            $data['bounce_reason'] = $details['bounce_reason'] ?? 'Unknown';
        }
        
        if (!empty($details)) {
            $data['metadata'] = json_encode($details);
        }
        
        return DB::table($this->table)
            ->where('message_id', $messageId)
            ->update($data) > 0;
    }

    /**
     * Track email open
     */
    public function trackOpen(array $data): bool
    {
        $send = null;
        
        if (isset($data['message_id'])) {
            $send = DB::table($this->table)
                ->where('message_id', $data['message_id'])
                ->first();
        } elseif (isset($data['send_id'])) {
            $send = $this->findById($data['send_id']);
        }
        
        if (!$send) {
            return false;
        }
        
        // Update first open time
        if (!$send->opened_at) {
            DB::table($this->table)
                ->where('id', $send->id)
                ->update([
                    'opened_at' => now(),
                    'updated_at' => now()
                ]);
        }
        
        // Record open event
        DB::table('email_opens')->insert([
            'send_id' => $send->id,
            'subscriber_id' => $send->subscriber_id,
            'opened_at' => now(),
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'device_type' => $data['device_type'] ?? null,
            'country' => $data['country'] ?? null,
            'city' => $data['city'] ?? null,
            'created_at' => now()
        ]);
        
        return true;
    }

    /**
     * Track email click
     */
    public function trackClick(array $data): bool
    {
        $send = null;
        
        if (isset($data['message_id'])) {
            $send = DB::table($this->table)
                ->where('message_id', $data['message_id'])
                ->first();
        } elseif (isset($data['send_id'])) {
            $send = $this->findById($data['send_id']);
        }
        
        if (!$send) {
            return false;
        }
        
        // Update first click time
        if (!$send->clicked_at) {
            DB::table($this->table)
                ->where('id', $send->id)
                ->update([
                    'clicked_at' => now(),
                    'updated_at' => now()
                ]);
        }
        
        // Record click event
        DB::table('email_clicks')->insert([
            'send_id' => $send->id,
            'subscriber_id' => $send->subscriber_id,
            'url' => $data['url'] ?? '',
            'clicked_at' => now(),
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'device_type' => $data['device_type'] ?? null,
            'country' => $data['country'] ?? null,
            'city' => $data['city'] ?? null,
            'created_at' => now()
        ]);
        
        return true;
    }

    /**
     * Get sends by campaign
     */
    public function getByCampaignId(int $campaignId): array
    {
        return DB::table($this->table)
            ->where('campaign_id', $campaignId)
            ->orderBy('sent_at', 'desc')
            ->get();
    }

    /**
     * Get sends by subscriber
     */
    public function getBySubscriberId(int $subscriberId, int $limit = null): array
    {
        $query = DB::table($this->table)
            ->where('subscriber_id', $subscriberId)
            ->orderBy('sent_at', 'desc');
        
        if ($limit) {
            $query->limit($limit);
        }
        
        return $query->get();
    }

    /**
     * Get campaign performance
     */
    public function getCampaignPerformance(int $campaignId): array
    {
        $performance = DB::table($this->table)
            ->where('campaign_id', $campaignId)
            ->select(
                DB::raw('COUNT(*) as total_sent'),
                DB::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered'),
                DB::raw('SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened'),
                DB::raw('COUNT(DISTINCT CASE WHEN opened_at IS NOT NULL THEN subscriber_id END) as unique_opens'),
                DB::raw('SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked'),
                DB::raw('COUNT(DISTINCT CASE WHEN clicked_at IS NOT NULL THEN subscriber_id END) as unique_clicks'),
                DB::raw('SUM(CASE WHEN status = "bounced" THEN 1 ELSE 0 END) as bounced'),
                DB::raw('SUM(CASE WHEN bounce_type = "hard" THEN 1 ELSE 0 END) as hard_bounces'),
                DB::raw('SUM(CASE WHEN bounce_type = "soft" THEN 1 ELSE 0 END) as soft_bounces'),
                DB::raw('SUM(CASE WHEN status = "complained" THEN 1 ELSE 0 END) as complaints'),
                DB::raw('SUM(CASE WHEN unsubscribed_at IS NOT NULL THEN 1 ELSE 0 END) as unsubscribes')
            )
            ->first();
        
        return (array)$performance;
    }

    /**
     * Get click details for campaign
     */
    public function getClickDetails(int $campaignId): array
    {
        return DB::table('email_clicks as c')
            ->join($this->table . ' as s', 'c.send_id', '=', 's.id')
            ->where('s.campaign_id', $campaignId)
            ->select(
                'c.url',
                DB::raw('COUNT(*) as total_clicks'),
                DB::raw('COUNT(DISTINCT c.subscriber_id) as unique_clicks'),
                DB::raw('MIN(c.clicked_at) as first_click'),
                DB::raw('MAX(c.clicked_at) as last_click')
            )
            ->groupBy('c.url')
            ->orderBy('total_clicks', 'desc')
            ->get();
    }

    /**
     * Get device statistics
     */
    public function getDeviceStatistics(int $campaignId = null, int $days = 30): array
    {
        $query = DB::table('email_opens as o')
            ->join($this->table . ' as s', 'o.send_id', '=', 's.id')
            ->where('o.opened_at', '>=', now()->subDays($days));
        
        if ($campaignId) {
            $query->where('s.campaign_id', $campaignId);
        }
        
        return $query->select(
                'o.device_type',
                DB::raw('COUNT(*) as opens'),
                DB::raw('COUNT(DISTINCT o.subscriber_id) as unique_users')
            )
            ->groupBy('o.device_type')
            ->orderBy('opens', 'desc')
            ->get();
    }

    /**
     * Get email client statistics
     */
    public function getEmailClientStatistics(int $campaignId = null): array
    {
        $query = DB::table('email_opens as o')
            ->join($this->table . ' as s', 'o.send_id', '=', 's.id');
        
        if ($campaignId) {
            $query->where('s.campaign_id', $campaignId);
        }
        
        return $query->select(
                DB::raw('CASE 
                    WHEN o.user_agent LIKE "%Gmail%" THEN "Gmail"
                    WHEN o.user_agent LIKE "%Outlook%" THEN "Outlook"
                    WHEN o.user_agent LIKE "%Yahoo%" THEN "Yahoo"
                    WHEN o.user_agent LIKE "%Apple Mail%" THEN "Apple Mail"
                    WHEN o.user_agent LIKE "%Thunderbird%" THEN "Thunderbird"
                    ELSE "Other"
                END as email_client'),
                DB::raw('COUNT(*) as opens')
            )
            ->groupBy('email_client')
            ->orderBy('opens', 'desc')
            ->get();
    }

    /**
     * Get geographic statistics
     */
    public function getGeographicStatistics(int $campaignId = null): array
    {
        $query = DB::table('email_opens as o')
            ->join($this->table . ' as s', 'o.send_id', '=', 's.id')
            ->whereNotNull('o.country');
        
        if ($campaignId) {
            $query->where('s.campaign_id', $campaignId);
        }
        
        return $query->select(
                'o.country',
                'o.city',
                DB::raw('COUNT(*) as opens'),
                DB::raw('COUNT(DISTINCT o.subscriber_id) as unique_users')
            )
            ->groupBy('o.country', 'o.city')
            ->orderBy('opens', 'desc')
            ->limit(50)
            ->get();
    }

    /**
     * Get time-based engagement
     */
    public function getTimeBasedEngagement(int $campaignId): array
    {
        return DB::table('email_opens as o')
            ->join($this->table . ' as s', 'o.send_id', '=', 's.id')
            ->where('s.campaign_id', $campaignId)
            ->select(
                DB::raw('HOUR(o.opened_at) as hour'),
                DB::raw('COUNT(*) as opens')
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();
    }

    /**
     * Get failed sends
     */
    public function getFailedSends(int $days = 7): array
    {
        return DB::table($this->table)
            ->whereIn('status', ['bounced', 'failed'])
            ->where('sent_at', '>=', now()->subDays($days))
            ->orderBy('sent_at', 'desc')
            ->get();
    }

    /**
     * Clean up old send data
     */
    public function cleanupOldData(int $days = 365): int
    {
        return DB::table($this->table)
            ->where('created_at', '<', now()->subDays($days))
            ->where('campaign_id', null) // Only clean transactional emails
            ->delete();
    }

    /**
     * Get engagement timeline
     */
    public function getEngagementTimeline(int $sendId): array
    {
        $timeline = [];
        
        $send = $this->findById($sendId);
        if (!$send) {
            return $timeline;
        }
        
        // Sent event
        $timeline[] = [
            'event' => 'sent',
            'timestamp' => $send['sent_at'],
            'details' => []
        ];
        
        // Delivered event
        if ($send['delivered_at']) {
            $timeline[] = [
                'event' => 'delivered',
                'timestamp' => $send['delivered_at'],
                'details' => []
            ];
        }
        
        // Open events
        $opens = DB::table('email_opens')
            ->where('send_id', $sendId)
            ->orderBy('opened_at')
            ->get();
        
        foreach ($opens as $open) {
            $timeline[] = [
                'event' => 'opened',
                'timestamp' => $open->opened_at,
                'details' => [
                    'device' => $open->device_type,
                    'location' => $open->city . ', ' . $open->country
                ]
            ];
        }
        
        // Click events
        $clicks = DB::table('email_clicks')
            ->where('send_id', $sendId)
            ->orderBy('clicked_at')
            ->get();
        
        foreach ($clicks as $click) {
            $timeline[] = [
                'event' => 'clicked',
                'timestamp' => $click->clicked_at,
                'details' => [
                    'url' => $click->url,
                    'device' => $click->device_type
                ]
            ];
        }
        
        // Sort by timestamp
        usort($timeline, function($a, $b) {
            return strtotime($a['timestamp']) - strtotime($b['timestamp']);
        });
        
        return $timeline;
    }
}