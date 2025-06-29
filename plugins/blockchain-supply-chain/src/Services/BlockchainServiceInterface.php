<?php

namespace BlockchainSupplyChain\Services;

interface BlockchainServiceInterface
{
    /**
     * Record an event on the blockchain
     */
    public function recordEvent(array $eventData): string;

    /**
     * Get product history from blockchain
     */
    public function getProductHistory(int $productId): array;

    /**
     * Verify a transaction on blockchain
     */
    public function verifyTransaction(string $transactionHash): bool;

    /**
     * Get network status
     */
    public function getNetworkStatus(): array;

    /**
     * Get transaction details
     */
    public function getTransaction(string $transactionHash): array;

    /**
     * Verify blockchain integrity
     */
    public function verifyIntegrity(array $events): bool;

    /**
     * Create smart contract
     */
    public function createContract(array $contractData): string;

    /**
     * Execute smart contract function
     */
    public function executeContract(string $contractAddress, string $function, array $params): string;

    /**
     * Get contract events
     */
    public function getContractEvents(string $contractAddress, array $filters = []): array;

    /**
     * Calculate transaction fee
     */
    public function calculateFee(array $transaction): float;

    /**
     * Get block information
     */
    public function getBlock(int $blockNumber): array;

    /**
     * Check if transaction is confirmed
     */
    public function isTransactionConfirmed(string $transactionHash): bool;
}