<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Services;

use AdvancedEmailMarketing\Services\{
    CampaignManager,
    AutomationEngine,
    SegmentationService,;
    SubscriberManager,;
    EmailSender;
};

class EmailMarketingManager\n{
    private CampaignManager $campaignManager;
    private AutomationEngine $automationEngine;
    private SegmentationService $segmentationService;
    private SubscriberManager $subscriberManager;
    private EmailSender $emailSender;

    public function __construct(
        CampaignManager $campaignManager,
        AutomationEngine $automationEngine,
        SegmentationService $segmentationService,
        SubscriberManager $subscriberManager,
        EmailSender $emailSender
    ) {
        $this->campaignManager = $campaignManager;
        $this->automationEngine = $automationEngine;
        $this->segmentationService = $segmentationService;
        $this->subscriberManager = $subscriberManager;
        $this->emailSender = $emailSender;
    }

    /**
     * Create and send campaign
     */
    public function createAndSendCampaign(array $campaignData): array
    {
        // Create campaign
        $campaign = $this->campaignManager->createCampaign($campaignData);
        
        // Get recipients
        $recipients = $this->getRecipients($campaignData);
        
        // Send campaign
        $result = $this->campaignManager->sendCampaign($campaign->getId(), $recipients);
        
        return [
            'campaign_id' => $campaign->getId(),
            'recipients_count' => count($recipients),
            'send_result' => $result
        ];
    }

    /**
     * Get campaign performance overview
     */
    public function getCampaignPerformance(int $campaignId): array
    {
        return $this->campaignManager->getCampaignAnalytics($campaignId);
    }

    /**
     * Get automation performance
     */
    public function getAutomationPerformance(int $automationId): array
    {
        return $this->automationEngine->getAutomationAnalytics($automationId);
    }

    /**
     * Get subscriber engagement summary
     */
    public function getSubscriberEngagement(int $subscriberId): array
    {
        return $this->subscriberManager->getEngagementSummary($subscriberId);
    }

    /**
     * Get email marketing dashboard data
     */
    public function getDashboardData(): array
    {
        return [
            'total_subscribers' => $this->subscriberManager->getTotalSubscribers(),
            'active_campaigns' => $this->campaignManager->getActiveCampaigns(),
            'running_automations' => $this->automationEngine->getRunningAutomations(),
            'recent_performance' => $this->getRecentPerformance(),
            'top_segments' => $this->segmentationService->getTopSegments(),
            'deliverability_score' => $this->getDeliverabilityScore()
        ];
    }

    /**
     * Bulk import subscribers
     */
    public function bulkImportSubscribers(array $subscribersData, array $options = []): array
    {
        return $this->subscriberManager->bulkImport($subscribersData, $options);
    }

    /**
     * Create automated sequence
     */
    public function createAutomatedSequence(array $sequenceData): array
    {
        return $this->automationEngine->createAutomation($sequenceData);
    }

    /**
     * Get recipients for campaign
     */
    private function getRecipients(array $campaignData): array
    {
        if (isset($campaignData['segment_id'])) {
            return $this->segmentationService->getSegmentMembers($campaignData['segment_id']);
        }

        if (isset($campaignData['recipient_criteria'])) {
            return $this->segmentationService->getSubscribersByCriteria($campaignData['recipient_criteria']);
        }

        return $this->subscriberManager->getAllActiveSubscribers();
    }

    /**
     * Get recent performance metrics
     */
    private function getRecentPerformance(): array
    {
        return [
            'emails_sent_today' => $this->campaignManager->getEmailsSentToday(),
            'open_rate_7days' => $this->campaignManager->getOpenRate(7),
            'click_rate_7days' => $this->campaignManager->getClickRate(7),
            'revenue_30days' => $this->campaignManager->getRevenue(30)
        ];
    }

    /**
     * Get overall deliverability score
     */
    private function getDeliverabilityScore(): float
    {
        // This would be calculated based on various factors
        return 95.5;
    }
}