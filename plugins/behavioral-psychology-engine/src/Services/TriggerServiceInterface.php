<?php

namespace BehavioralPsychologyEngine\Services;

interface TriggerServiceInterface
{
    /**
     * Get applicable triggers for a product
     */
    public function getApplicableTriggers(object $product, string $type): array;

    /**
     * Create a loss aversion campaign
     */
    public function createLossAversionCampaign(array $data): object;

    /**
     * Record trigger conversion
     */
    public function recordTriggerConversion(array $data): void;

    /**
     * Update effectiveness scores for triggers
     */
    public function updateEffectivenessScores(array $triggers): void;

    /**
     * Update social proof data
     */
    public function updateSocialProofData(array $data): void;

    /**
     * Analyze trigger effectiveness
     */
    public function analyzeEffectiveness(array $options = []): array;

    /**
     * Get active trigger count
     */
    public function getActiveTriggerCount(): int;

    /**
     * Get top performing triggers
     */
    public function getTopPerformingTriggers(int $limit = 5): array;

    /**
     * Get performance report
     */
    public function getPerformanceReport(): array;

    /**
     * Create new trigger
     */
    public function createTrigger(array $data): object;

    /**
     * Update existing trigger
     */
    public function updateTrigger(int $triggerId, array $data): bool;

    /**
     * Delete trigger
     */
    public function deleteTrigger(int $triggerId): bool;

    /**
     * Get all active triggers
     */
    public function getActiveTriggers(): array;

    /**
     * Optimize underperforming trigger
     */
    public function optimizeTrigger(object $trigger): void;
}