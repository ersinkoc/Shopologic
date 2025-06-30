<?php

declare(strict_types=1);

namespace Shopologic\Plugins\Shared\Interfaces;

/**
 * Interface for plugins that provide marketing capabilities
 * Enables email marketing, campaign management, and customer segmentation
 */
interface MarketingProviderInterface
{
    /**
     * Send transactional email
     */
    public function sendTransactionalEmail(string $templateKey, array $recipientData, array $templateData): bool;
    
    /**
     * Add customer to email list/segment
     */
    public function addToEmailList(int $customerId, string $listId): bool;
    
    /**
     * Remove customer from email list/segment
     */
    public function removeFromEmailList(int $customerId, string $listId): bool;
    
    /**
     * Trigger automated campaign
     */
    public function triggerAutomation(string $automationKey, int $customerId, array $triggerData = []): bool;
    
    /**
     * Get customer's email engagement score
     */
    public function getEngagementScore(int $customerId): float;
    
    /**
     * Get available email templates
     */
    public function getEmailTemplates(): array;
    
    /**
     * Get customer segments
     */
    public function getCustomerSegments(): array;
    
    /**
     * Check if customer belongs to segment
     */
    public function isInSegment(int $customerId, string $segmentId): bool;
    
    /**
     * Get campaign performance data
     */
    public function getCampaignMetrics(string $campaignId): array;
    
    /**
     * Subscribe to marketing events (email opened, clicked, etc.)
     */
    public function subscribeToMarketingEvents(string $eventType, callable $callback): void;
}