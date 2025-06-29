<?php

declare(strict_types=1);

namespace Shopologic\Core\Database;

interface ConnectionInterface
{
    public function connect(): void;
    public function disconnect(): void;
    public function isConnected(): bool;
    public function query(string $sql, array $bindings = []): ResultInterface;
    public function execute(string $sql, array $bindings = []): int;
    public function prepare(string $sql): StatementInterface;
    public function beginTransaction(): bool;
    public function commit(): bool;
    public function rollback(): bool;
    public function inTransaction(): bool;
    public function lastInsertId(?string $sequence = null): string;
    public function quote(string $value): string;
    public function getConfig(): array;
}