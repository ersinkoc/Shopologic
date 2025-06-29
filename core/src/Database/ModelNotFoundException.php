<?php

declare(strict_types=1);

namespace Shopologic\Core\Database;

class ModelNotFoundException extends \RuntimeException
{
    protected ?string $model = null;
    protected array $ids = [];

    public function setModel(string $model, array $ids = []): self
    {
        $this->model = $model;
        $this->ids = $ids;

        $this->message = "No query results for model [{$model}]";

        if (count($this->ids) > 0) {
            $this->message .= ' ' . implode(', ', $this->ids);
        }

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function getIds(): array
    {
        return $this->ids;
    }
}