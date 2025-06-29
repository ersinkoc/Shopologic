<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Schema;

class Command
{
    public string $name;
    public ?string $index = null;
    public array $columns = [];
    public ?string $algorithm = null;
    public ?string $on = null;
    public ?string $onDelete = null;
    public ?string $onUpdate = null;
    public ?string $references = null;
    public ?string $from = null;
    public ?string $to = null;

    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $key => $value) {
            $this->$key = $value;
        }
    }
}