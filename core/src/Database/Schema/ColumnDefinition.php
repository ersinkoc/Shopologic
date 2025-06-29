<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Schema;

class ColumnDefinition
{
    public string $type;
    public string $name;
    public ?int $length = null;
    public ?int $total = null;
    public ?int $places = null;
    public ?int $precision = null;
    public bool $unsigned = false;
    public bool $autoIncrement = false;
    public mixed $default = null;
    public bool $nullable = false;
    public bool $unique = false;
    public bool $primary = false;
    public bool $index = false;
    public ?string $comment = null;
    public ?string $charset = null;
    public ?string $collation = null;
    public array $allowed = [];
    public bool $change = false;
    public ?string $after = null;
    public bool $first = false;
    public ?string $onUpdate = null;
    public bool $useCurrent = false;
    public bool $useCurrentOnUpdate = false;
    public bool $virtualAs = false;
    public bool $storedAs = false;
    public bool $generatedAs = false;
    public bool $always = false;

    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $key => $value) {
            $this->$key = $value;
        }
    }

    public function autoIncrement(): self
    {
        $this->autoIncrement = true;
        return $this;
    }

    public function comment(string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    public function default($value): self
    {
        $this->default = $value;
        return $this;
    }

    public function first(): self
    {
        $this->first = true;
        return $this;
    }

    public function after(string $column): self
    {
        $this->after = $column;
        return $this;
    }

    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;
        return $this;
    }

    public function unsigned(): self
    {
        $this->unsigned = true;
        return $this;
    }

    public function useCurrent(): self
    {
        $this->useCurrent = true;
        return $this;
    }

    public function useCurrentOnUpdate(): self
    {
        $this->useCurrent = true;
        $this->useCurrentOnUpdate = true;
        return $this;
    }

    public function index(?string $name = null): self
    {
        $this->index = true;
        return $this;
    }

    public function primary(): self
    {
        $this->primary = true;
        return $this;
    }

    public function unique(?string $name = null): self
    {
        $this->unique = true;
        return $this;
    }

    public function change(): self
    {
        $this->change = true;
        return $this;
    }

    public function charset(string $charset): self
    {
        $this->charset = $charset;
        return $this;
    }

    public function collation(string $collation): self
    {
        $this->collation = $collation;
        return $this;
    }

    public function virtualAs(string $expression): self
    {
        $this->virtualAs = $expression;
        return $this;
    }

    public function storedAs(string $expression): self
    {
        $this->storedAs = $expression;
        return $this;
    }

    public function generatedAs(string $expression): self
    {
        $this->generatedAs = $expression;
        return $this;
    }

    public function always(): self
    {
        $this->always = true;
        return $this;
    }
    
    public function constrained(?string $table = null, string $column = 'id'): self
    {
        // This would normally create a foreign key constraint
        // For now, just return self for chaining
        return $this;
    }
    
    public function cascadeOnDelete(): self
    {
        // This would normally set cascade on delete
        // For now, just return self for chaining
        return $this;
    }
}