<?php

declare(strict_types=1);
namespace Shopologic\Plugins\AdvancedCms\Services;

interface ContentServiceInterface
{
    /**
     * Create new content
     */
    public function createContent(array $data): object;

    /**
     * Update existing content
     */
    public function updateContent(int $contentId, array $data): bool;

    /**
     * Get content by ID
     */
    public function getContent(int $contentId): ?object;

    /**
     * Get content for SEO optimization
     */
    public function getContentForSeoOptimization(array $criteria): array;

    /**
     * Generate daily analytics
     */
    public function generateDailyAnalytics(array $options): array;

    /**
     * Get published content
     */
    public function getPublishedContent(): array;

    /**
     * Update content scores
     */
    public function updateContentScores(int $contentId, array $scores): bool;

    /**
     * Get total content count
     */
    public function getTotalContentCount(): int;

    /**
     * Get content published today
     */
    public function getPublishedToday(): int;

    /**
     * Get average SEO score
     */
    public function getAverageSeoScore(): float;

    /**
     * Get top performing content
     */
    public function getTopPerformingContent(int $limit): array;

    /**
     * Get AI generated content count
     */
    public function getAiGeneratedContentCount(string $period): int;

    /**
     * Create draft content
     */
    public function createDraft(array $data): int;

    /**
     * Publish content
     */
    public function publishContent(int $contentId): bool;

    /**
     * Get content revisions
     */
    public function getContentRevisions(int $contentId): array;

    /**
     * Create content revision
     */
    public function createRevision(int $contentId, array $changes): int;
}