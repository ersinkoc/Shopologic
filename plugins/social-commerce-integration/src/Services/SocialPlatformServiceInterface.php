<?php

declare(strict_types=1);
namespace Shopologic\Plugins\SocialCommerceIntegration\Services;

interface SocialPlatformServiceInterface
{
    /**
     * Get connected social platforms
     */
    public function getConnectedPlatforms(): array;

    /**
     * Sync product to social platform
     */
    public function syncProductToPlatform(int $platformId, object $product): bool;

    /**
     * Add product tag to post
     */
    public function addProductTag(string $postId, int $productId, array $options = []): bool;

    /**
     * Update post shoppable elements
     */
    public function updatePostShoppableElements(string $postId, array $elements): bool;

    /**
     * Get platform optimization rules
     */
    public function getPlatformOptimizationRules(string $platform, string $contentType): array;

    /**
     * Get optimal post time for platform
     */
    public function getOptimalPostTime(string $platform): string;

    /**
     * Fetch engagement data from platform
     */
    public function fetchEngagementData(int $platformId, array $options = []): array;

    /**
     * Create shoppable post
     */
    public function createShoppablePost(int $platformId, array $postData): string;

    /**
     * Get platform analytics
     */
    public function getPlatformAnalytics(int $platformId, string $period): array;

    /**
     * Connect new platform
     */
    public function connectPlatform(string $platform, array $credentials): bool;

    /**
     * Disconnect platform
     */
    public function disconnectPlatform(int $platformId): bool;

    /**
     * Get platform status
     */
    public function getPlatformStatus(int $platformId): array;

    /**
     * Bulk sync products to platform
     */
    public function bulkSyncProducts(int $platformId, array $productIds): array;

    /**
     * Schedule content publication
     */
    public function scheduleContentPublication(int $platformId, array $content, \DateTime $publishAt): bool;

    /**
     * Get content performance
     */
    public function getContentPerformance(string $contentId, string $platform): array;
}