<?php

declare(strict_types=1);

namespace Shopologic\Core\Database;

interface StatementInterface
{
    public function bindValue(string|int $parameter, mixed $value, int $type = null): bool;
    public function bindParam(string|int $parameter, mixed &$variable, int $type = null): bool;
    public function execute(array $params = []): bool;
    public function fetch(): ?array;
    public function fetchAll(): array;
    public function fetchColumn(int $column = 0): mixed;
    public function rowCount(): int;
    public function columnCount(): int;
    public function closeCursor(): void;
}