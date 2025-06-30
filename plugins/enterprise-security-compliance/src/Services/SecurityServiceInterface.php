<?php

declare(strict_types=1);
namespace Shopologic\Plugins\EnterpriseSecurityCompliance\Services;

interface SecurityServiceInterface
{
    /**
     * Calculate overall security score
     */
    public function calculateSecurityScore(): float;

    /**
     * Increment failed login attempts
     */
    public function incrementFailedLogins(string $ipAddress, string $email): int;

    /**
     * Create security incident
     */
    public function createSecurityIncident(array $data): object;

    /**
     * Block IP address
     */
    public function blockIpAddress(string $ipAddress, array $options): void;

    /**
     * Get security metrics
     */
    public function getSecurityMetrics(string $period = '24h'): array;

    /**
     * Perform security scan
     */
    public function performSecurityScan(array $options = []): array;

    /**
     * Get active security incidents
     */
    public function getActiveIncidents(): array;

    /**
     * Resolve security incident
     */
    public function resolveIncident(int $incidentId, array $resolution): bool;

    /**
     * Get security configuration status
     */
    public function getSecurityConfigStatus(): array;

    /**
     * Update security configuration
     */
    public function updateSecurityConfig(array $config): bool;

    /**
     * Get blocked IP addresses
     */
    public function getBlockedIps(): array;

    /**
     * Unblock IP address
     */
    public function unblockIpAddress(string $ipAddress): bool;

    /**
     * Get security logs
     */
    public function getSecurityLogs(array $filters = []): array;

    /**
     * Generate security report
     */
    public function generateSecurityReport(string $period): array;
}