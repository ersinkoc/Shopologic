<?php

declare(strict_types=1);
namespace Shopologic\Plugins\OmnichannelIntegrationHub\Services;

interface ChannelServiceInterface
{
    /**
     * Initialize a new channel
     */
    public function initializeChannel(object $channel): void;

    /**
     * Get channel analytics
     */
    public function getChannelAnalytics(int $channelId, string $period): array;

    /**
     * Fetch orders from channel
     */
    public function fetchChannelOrders(object $channel, array $options = []): array;

    /**
     * Reconcile inventory with channel
     */
    public function reconcileInventory(object $channel): array;

    /**
     * Reconcile orders with channel
     */
    public function reconcileOrders(object $channel): array;

    /**
     * Reconcile customers with channel
     */
    public function reconcileCustomers(object $channel): array;

    /**
     * Get channel health status
     */
    public function getChannelHealthStatus(): array;

    /**
     * Register webhook for channel
     */
    public function registerWebhook(object $channel, string $event, string $url): bool;

    /**
     * Connect to channel
     */
    public function connectChannel(array $config): object;

    /**
     * Disconnect from channel
     */
    public function disconnectChannel(int $channelId): bool;

    /**
     * Update channel configuration
     */
    public function updateChannelConfig(int $channelId, array $config): bool;

    /**
     * Get channel sync status
     */
    public function getChannelSyncStatus(int $channelId): array;

    /**
     * Sync data to channel
     */
    public function syncToChannel(object $channel, string $dataType, array $data): bool;

    /**
     * Get channel performance metrics
     */
    public function getChannelPerformance(int $channelId, string $period): array;
}