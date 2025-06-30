<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Services;

use AdvancedEmailMarketing\Repositories\{
    CampaignRepository,;
    TemplateRepository,;
    SegmentRepository;
};
use Shopologic\Core\Events\EventDispatcher;

class CampaignManager\n{
    private CampaignRepository $campaignRepository;
    private TemplateRepository $templateRepository;
    private SegmentRepository $segmentRepository;
    private array $config;

    public function __construct(
        CampaignRepository $campaignRepository,
        TemplateRepository $templateRepository,
        SegmentRepository $segmentRepository,
        array $config = []
    ) {
        $this->campaignRepository = $campaignRepository;
        $this->templateRepository = $templateRepository;
        $this->segmentRepository = $segmentRepository;
        $this->config = $config;
    }

    /**
     * Create new campaign
     */
    public function createCampaign(array $data): array
    {
        // Validate campaign data
        $this->validateCampaignData($data);

        // Set defaults
        $data['status'] = $data['status'] ?? 'draft';
        $data['type'] = $data['type'] ?? 'one_time';
        $data['created_by'] = $data['created_by'] ?? get_current_user_id();

        // Calculate recipient count if segment specified
        if (isset($data['segment_id'])) {
            $segment = $this->segmentRepository->findById($data['segment_id']);
            $data['total_recipients'] = $segment ? $segment['member_count'] : 0;
        }

        $campaign = $this->campaignRepository->create($data);

        EventDispatcher::dispatch('campaign.created', $campaign);

        return $campaign;
    }

    /**
     * Update campaign
     */
    public function updateCampaign(int $campaignId, array $data): bool
    {
        $campaign = $this->campaignRepository->findById($campaignId);
        if (!$campaign) {
            return false;
        }

        // Prevent editing sent campaigns
        if (in_array($campaign['status'], ['sent', 'sending'])) {
            throw new \InvalidArgumentException('Cannot edit campaigns that have been sent or are sending');
        }

        $this->validateCampaignData($data, true);

        $result = $this->campaignRepository->update($campaignId, $data);

        if ($result) {
            EventDispatcher::dispatch('campaign.updated', array_merge($campaign, $data));
        }

        return $result;
    }

    /**
     * Send campaign
     */
    public function sendCampaign(int $campaignId, array $recipients = []): array
    {
        $campaign = $this->campaignRepository->findById($campaignId);
        if (!$campaign) {
            throw new \InvalidArgumentException('Campaign not found');
        }

        if ($campaign['status'] !== 'draft' && $campaign['status'] !== 'scheduled') {
            throw new \InvalidArgumentException('Campaign cannot be sent in current status');
        }

        // Get recipients if not provided
        if (empty($recipients)) {
            $recipients = $this->getCampaignRecipients($campaign);
        }

        // Update campaign status
        $this->campaignRepository->update($campaignId, [
            'status' => 'sending',
            'total_recipients' => count($recipients),
            'sent_at' => date('Y-m-d H:i:s')
        ]);

        // Send emails
        $emailSender = app(EmailSender::class);
        $sendResult = $emailSender->sendCampaignEmail($campaign, $recipients);

        // Update campaign status
        $this->campaignRepository->update($campaignId, [
            'status' => 'sent'
        ]);

        EventDispatcher::dispatch('campaign.sent', [
            'campaign' => $campaign,
            'recipients_count' => count($recipients)
        ]);

        return [
            'campaign_id' => $campaignId,
            'recipients_sent' => count($recipients),
            'send_ids' => $sendResult
        ];
    }

    /**
     * Schedule campaign
     */
    public function scheduleCampaign(int $campaignId, string $scheduledAt): bool
    {
        $campaign = $this->campaignRepository->findById($campaignId);
        if (!$campaign || $campaign['status'] !== 'draft') {
            return false;
        }

        // Validate scheduled time is in future
        if (strtotime($scheduledAt) <= time()) {
            throw new \InvalidArgumentException('Scheduled time must be in the future');
        }

        $result = $this->campaignRepository->update($campaignId, [
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt
        ]);

        if ($result) {
            EventDispatcher::dispatch('campaign.scheduled', [
                'campaign' => $campaign,
                'scheduled_at' => $scheduledAt
            ]);
        }

        return $result;
    }

    /**
     * Pause campaign
     */
    public function pauseCampaign(int $campaignId): bool
    {
        $campaign = $this->campaignRepository->findById($campaignId);
        if (!$campaign || $campaign['status'] !== 'sending') {
            return false;
        }

        return $this->campaignRepository->update($campaignId, [
            'status' => 'paused'
        ]);
    }

    /**
     * Resume campaign
     */
    public function resumeCampaign(int $campaignId): bool
    {
        $campaign = $this->campaignRepository->findById($campaignId);
        if (!$campaign || $campaign['status'] !== 'paused') {
            return false;
        }

        return $this->campaignRepository->update($campaignId, [
            'status' => 'sending'
        ]);
    }

    /**
     * Cancel campaign
     */
    public function cancelCampaign(int $campaignId): bool
    {
        $campaign = $this->campaignRepository->findById($campaignId);
        if (!$campaign || in_array($campaign['status'], ['sent', 'cancelled'])) {
            return false;
        }

        return $this->campaignRepository->update($campaignId, [
            'status' => 'cancelled'
        ]);
    }

    /**
     * Duplicate campaign
     */
    public function duplicateCampaign(int $campaignId, string $newName = null): array
    {
        $campaign = $this->campaignRepository->findById($campaignId);
        if (!$campaign) {
            throw new \InvalidArgumentException('Campaign not found');
        }

        $duplicateData = $campaign;
        unset($duplicateData['id'], $duplicateData['created_at'], $duplicateData['updated_at']);

        $duplicateData['name'] = $newName ?? $campaign['name'] . ' (Copy)';
        $duplicateData['status'] = 'draft';
        $duplicateData['sent_at'] = null;
        $duplicateData['scheduled_at'] = null;
        $duplicateData['total_recipients'] = 0;
        $duplicateData['delivered_count'] = 0;
        $duplicateData['opened_count'] = 0;
        $duplicateData['clicked_count'] = 0;
        $duplicateData['bounced_count'] = 0;
        $duplicateData['complained_count'] = 0;
        $duplicateData['unsubscribed_count'] = 0;

        return $this->campaignRepository->create($duplicateData);
    }

    /**
     * Get campaign analytics
     */
    public function getCampaignAnalytics(int $campaignId): array
    {
        $campaign = $this->campaignRepository->findById($campaignId);
        if (!$campaign) {
            return [];
        }

        $analytics = $this->campaignRepository->getCampaignAnalytics($campaignId);

        return [
            'campaign' => $campaign,
            'summary' => [
                'total_sent' => $campaign['total_recipients'],
                'delivered' => $campaign['delivered_count'],
                'opened' => $campaign['opened_count'],
                'clicked' => $campaign['clicked_count'],
                'bounced' => $campaign['bounced_count'],
                'complained' => $campaign['complained_count'],
                'unsubscribed' => $campaign['unsubscribed_count']
            ],
            'rates' => [
                'delivery_rate' => $this->calculateDeliveryRate($campaign),
                'open_rate' => $this->calculateOpenRate($campaign),
                'click_rate' => $this->calculateClickRate($campaign),
                'click_to_open_rate' => $this->calculateClickToOpenRate($campaign),
                'bounce_rate' => $this->calculateBounceRate($campaign),
                'unsubscribe_rate' => $this->calculateUnsubscribeRate($campaign)
            ],
            'timeline' => $analytics['timeline'] ?? [],
            'clicks_by_link' => $analytics['clicks_by_link'] ?? [],
            'opens_by_hour' => $analytics['opens_by_hour'] ?? [],
            'device_breakdown' => $analytics['device_breakdown'] ?? [],
            'location_breakdown' => $analytics['location_breakdown'] ?? [],
            'revenue_attribution' => $campaign['revenue_attributed']
        ];
    }

    /**
     * Get active campaigns
     */
    public function getActiveCampaigns(): array
    {
        return $this->campaignRepository->getActiveCampaigns();
    }

    /**
     * Process scheduled campaigns
     */
    public function processScheduledCampaigns(): void
    {
        $scheduledCampaigns = $this->campaignRepository->getScheduledCampaigns();

        foreach ($scheduledCampaigns as $campaign) {
            if (strtotime($campaign['scheduled_at']) <= time()) {
                try {
                    $this->sendCampaign($campaign['id']);
                } catch (\RuntimeException $e) {
                    error_log("Failed to send scheduled campaign {$campaign['id']}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Get emails sent today
     */
    public function getEmailsSentToday(): int
    {
        return $this->campaignRepository->getEmailsSentToday();
    }

    /**
     * Get open rate for period
     */
    public function getOpenRate(int $days): float
    {
        return $this->campaignRepository->getOpenRateForPeriod($days);
    }

    /**
     * Get click rate for period
     */
    public function getClickRate(int $days): float
    {
        return $this->campaignRepository->getClickRateForPeriod($days);
    }

    /**
     * Get revenue for period
     */
    public function getRevenue(int $days): float
    {
        return $this->campaignRepository->getRevenueForPeriod($days);
    }

    /**
     * Create A/B test campaign
     */
    public function createAbTestCampaign(array $campaignData, array $variants): array
    {
        $campaignData['type'] = 'ab_test';
        $campaign = $this->createCampaign($campaignData);

        // Create A/B test record
        $abTest = $this->campaignRepository->createAbTest([
            'name' => $campaignData['name'] . ' A/B Test',
            'test_type' => $variants['test_type'],
            'campaign_id' => $campaign['id'],
            'variants' => json_encode($variants['variants']),
            'sample_size_percentage' => $variants['sample_size_percentage'] ?? 50,
            'status' => 'active',
            'started_at' => date('Y-m-d H:i:s'),
            'created_by' => $campaignData['created_by'] ?? get_current_user_id()
        ]);

        return [
            'campaign' => $campaign,
            'ab_test' => $abTest
        ];
    }

    /**
     * Get campaign recipients
     */
    private function getCampaignRecipients(array $campaign): array
    {
        if ($campaign['segment_id']) {
            $segmentationService = app(SegmentationService::class);
            return $segmentationService->getSegmentMembers($campaign['segment_id']);
        }

        if ($campaign['recipient_criteria']) {
            $criteria = json_decode($campaign['recipient_criteria'], true);
            $segmentationService = app(SegmentationService::class);
            return $segmentationService->getSubscribersByCriteria($criteria);
        }

        // Default to all active subscribers
        $subscriberManager = app(SubscriberManager::class);
        return $subscriberManager->getAllActiveSubscribers();
    }

    /**
     * Validate campaign data
     */
    private function validateCampaignData(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            if (empty($data['name'])) {
                throw new \InvalidArgumentException('Campaign name is required');
            }

            if (empty($data['subject'])) {
                throw new \InvalidArgumentException('Campaign subject is required');
            }

            if (empty($data['content']) && empty($data['template_id'])) {
                throw new \InvalidArgumentException('Campaign content or template is required');
            }
        }

        if (isset($data['template_id']) && !$this->templateRepository->findById($data['template_id'])) {
            throw new \InvalidArgumentException('Invalid template ID');
        }

        if (isset($data['segment_id']) && !$this->segmentRepository->findById($data['segment_id'])) {
            throw new \InvalidArgumentException('Invalid segment ID');
        }

        if (isset($data['scheduled_at']) && strtotime($data['scheduled_at']) <= time()) {
            throw new \InvalidArgumentException('Scheduled time must be in the future');
        }
    }

    /**
     * Calculate delivery rate
     */
    private function calculateDeliveryRate(array $campaign): float
    {
        if ($campaign['total_recipients'] === 0) {
            return 0;
        }

        return ($campaign['delivered_count'] / $campaign['total_recipients']) * 100;
    }

    /**
     * Calculate open rate
     */
    private function calculateOpenRate(array $campaign): float
    {
        if ($campaign['delivered_count'] === 0) {
            return 0;
        }

        return ($campaign['opened_count'] / $campaign['delivered_count']) * 100;
    }

    /**
     * Calculate click rate
     */
    private function calculateClickRate(array $campaign): float
    {
        if ($campaign['delivered_count'] === 0) {
            return 0;
        }

        return ($campaign['clicked_count'] / $campaign['delivered_count']) * 100;
    }

    /**
     * Calculate click-to-open rate
     */
    private function calculateClickToOpenRate(array $campaign): float
    {
        if ($campaign['opened_count'] === 0) {
            return 0;
        }

        return ($campaign['clicked_count'] / $campaign['opened_count']) * 100;
    }

    /**
     * Calculate bounce rate
     */
    private function calculateBounceRate(array $campaign): float
    {
        if ($campaign['total_recipients'] === 0) {
            return 0;
        }

        return ($campaign['bounced_count'] / $campaign['total_recipients']) * 100;
    }

    /**
     * Calculate unsubscribe rate
     */
    private function calculateUnsubscribeRate(array $campaign): float
    {
        if ($campaign['delivered_count'] === 0) {
            return 0;
        }

        return ($campaign['unsubscribed_count'] / $campaign['delivered_count']) * 100;
    }
}