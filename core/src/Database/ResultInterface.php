<?php

declare(strict_types=1);

namespace Shopologic\Core\Database;

interface ResultInterface extends \Iterator, \Countable
{
    public function fetch(): ?array;
    public function fetchAll(): array;
    public function fetchColumn(int $column = 0): mixed;
    public function fetchObject(?string $class = null): ?object;
    public function rowCount(): int;
    public function columnCount(): int;
    public function getColumnMeta(int $column): array;
}