<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Schema;

class ForeignKeyDefinition extends Command
{
    public function references(string $column): self
    {
        $this->references = $column;
        return $this;
    }

    public function on(string $table): self
    {
        $this->on = $table;
        return $this;
    }

    public function onDelete(string $action): self
    {
        $this->onDelete = $action;
        return $this;
    }

    public function onUpdate(string $action): self
    {
        $this->onUpdate = $action;
        return $this;
    }

    public function cascadeOnUpdate(): self
    {
        return $this->onUpdate('cascade');
    }

    public function cascadeOnDelete(): self
    {
        return $this->onDelete('cascade');
    }

    public function restrictOnUpdate(): self
    {
        return $this->onUpdate('restrict');
    }

    public function restrictOnDelete(): self
    {
        return $this->onDelete('restrict');
    }

    public function nullOnDelete(): self
    {
        return $this->onDelete('set null');
    }
}