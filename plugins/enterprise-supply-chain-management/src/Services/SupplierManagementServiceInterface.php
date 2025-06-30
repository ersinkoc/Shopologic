<?php

declare(strict_types=1);
namespace Shopologic\Plugins\EnterpriseSupplyChainManagement\Services;

interface SupplierManagementServiceInterface
{
    /**
     * Evaluate supplier performance
     */
    public function evaluateSupplierPerformance(int $supplierId, array $criteria): array;

    /**
     * Update supplier performance metrics
     */
    public function updatePerformanceMetrics(int $supplierId, array $metrics): array;

    /**
     * Get supplier performance trends
     */
    public function getPerformanceTrends(int $supplierId, string $period = '12m'): array;

    /**
     * Assess supplier health
     */
    public function assessSupplierHealth(array $filters = []): array;

    /**
     * Get average supplier performance
     */
    public function getAverageSupplierPerformance(array $filters = []): float;

    /**
     * Select optimal suppliers
     */
    public function selectOptimalSuppliers(array $requirements): array;

    /**
     * Manage supplier contracts
     */
    public function manageSupplierContract(int $supplierId, array $contractData): array;

    /**
     * Monitor supplier compliance
     */
    public function monitorSupplierCompliance(int $supplierId): array;

    /**
     * Generate supplier scorecard
     */
    public function generateSupplierScorecard(int $supplierId, string $period): array;

    /**
     * Identify supplier risks
     */
    public function identifySupplierRisks(int $supplierId): array;

    /**
     * Benchmark supplier performance
     */
    public function benchmarkSupplierPerformance(int $supplierId, array $benchmarkCriteria): array;

    /**
     * Manage supplier relationships
     */
    public function manageSupplierRelationship(int $supplierId, array $relationshipData): bool;
}