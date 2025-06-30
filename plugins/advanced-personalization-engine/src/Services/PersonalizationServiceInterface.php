<?php

namespace AdvancedPersonalizationEngine\Services;

interface PersonalizationServiceInterface
{
    /**
     * Get customer personalization profile
     */
    public function getCustomerProfile(int $customerId): array;

    /**
     * Update personalization models
     */
    public function updatePersonalizationModels(int $customerId, array $profileData): bool;

    /**
     * Predict customer intent
     */
    public function predictCustomerIntent(int $customerId, array $context): array;

    /**
     * Generate personalized experience
     */
    public function generatePersonalizedExperience(int $customerId, array $options): array;

    /**
     * Update dynamic customer segments
     */
    public function updateDynamicSegments(array $options): array;

    /**
     * Get active personalization count
     */
    public function getActivePersonalizationCount(): int;

    /**
     * Calculate personalization effectiveness
     */
    public function calculatePersonalizationEffectiveness(array $criteria = []): array;

    /**
     * Optimize personalization strategy
     */
    public function optimizePersonalizationStrategy(int $customerId, array $objectives): array;

    /**
     * Get real-time personalization data
     */
    public function getRealTimePersonalizationData(int $customerId): array;

    /**
     * Process personalization feedback
     */
    public function processPersonalizationFeedback(int $customerId, array $feedback): bool;

    /**
     * Generate personalization insights
     */
    public function generatePersonalizationInsights(array $parameters = []): array;

    /**
     * Adapt personalization for channel
     */
    public function adaptPersonalizationForChannel(int $customerId, string $channel): array;
}