<?php

declare(strict_types=1);
namespace Shopologic\Plugins\AdvancedEmailMarketing\Services;

interface CampaignServiceInterface
{
    /**
     * Create new email campaign
     */
    public function createCampaign(array $data): object;

    /**
     * Get campaign by ID
     */
    public function getCampaign(int $campaignId): ?object;

    /**
     * Update campaign
     */
    public function updateCampaign(int $campaignId, array $data): bool;

    /**
     * Send campaign
     */
    public function sendCampaign(int $campaignId, array $options = []): bool;

    /**
     * Get queued emails
     */
    public function getQueuedEmails(array $criteria): array;

    /**
     * Update email status
     */
    public function updateEmailStatus(int $emailId, string $status): bool;

    /**
     * Get active campaign count
     */
    public function getActiveCampaignCount(): int;

    /**
     * Get recent campaigns
     */
    public function getRecentCampaigns(int $limit): array;

    /**
     * Get campaign performance
     */
    public function getCampaignPerformance(int $campaignId): array;

    /**
     * Schedule campaign
     */
    public function scheduleCampaign(int $campaignId, \DateTime $scheduledAt): bool;

    /**
     * Pause campaign
     */
    public function pauseCampaign(int $campaignId): bool;

    /**
     * Resume campaign
     */
    public function resumeCampaign(int $campaignId): bool;

    /**
     * Delete campaign
     */
    public function deleteCampaign(int $campaignId): bool;

    /**
     * Duplicate campaign
     */
    public function duplicateCampaign(int $campaignId): object;

    /**
     * Get campaign recipients
     */
    public function getCampaignRecipients(int $campaignId): array;

    /**
     * Add recipients to campaign
     */
    public function addRecipients(int $campaignId, array $recipients): int;
}