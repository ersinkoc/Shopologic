<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Schema;

abstract class Grammar
{
    protected array $modifiers = [];
    protected array $serialTypes = [];

    public function wrapTable($table): string
    {
        return $this->wrap($table);
    }

    public function wrap($value): string
    {
        if (strpos(strtolower($value), ' as ') !== false) {
            return $this->wrapAliasedValue($value);
        }

        if ($this->isExpression($value)) {
            return $this->getValue($value);
        }

        if (strpos($value, '.') !== false) {
            return $this->wrapSegments(explode('.', $value));
        }

        return $this->wrapValue($value);
    }

    protected function wrapAliasedValue($value): string
    {
        $segments = preg_split('/\s+as\s+/i', $value);

        return $this->wrap($segments[0]) . ' as ' . $this->wrapValue($segments[1]);
    }

    protected function wrapSegments($segments): string
    {
        return implode('.', array_map([$this, 'wrapValue'], $segments));
    }

    protected function wrapValue($value): string
    {
        if ($value !== '*') {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }

    protected function isExpression($value): bool
    {
        return is_object($value);
    }

    protected function getValue($expression): string
    {
        return $expression->getValue();
    }

    public function columnize(array $columns): string
    {
        return implode(', ', array_map([$this, 'wrap'], $columns));
    }

    public function parameterize(array $values): string
    {
        return implode(', ', array_map(function () {
            return '?';
        }, $values));
    }

    public function parameter($value): string
    {
        return $this->isExpression($value) ? $this->getValue($value) : '?';
    }

    public function quoteString($value): string
    {
        return "'$value'";
    }

    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s';
    }

    abstract public function compileTableExists(): string;
    abstract public function compileColumnListing(string $table): string;
    abstract public function compileColumnType(): string;
    abstract public function compileCreate(Blueprint $blueprint): string;
    abstract public function compileDrop(string $table): string;
    abstract public function compileDropIfExists(string $table): string;
    abstract public function compileRename(string $from, string $to): string;
    abstract public function compileEnableForeignKeyConstraints(): string;
    abstract public function compileDisableForeignKeyConstraints(): string;
    abstract public function compileDropAllTables(): string;
    abstract public function compileDropAllViews(): string;
    abstract public function compileGetAllTables(): string;
    abstract public function compileGetAllViews(): string;
}