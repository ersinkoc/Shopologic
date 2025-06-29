<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Relations;

use Shopologic\Core\Database\Builder;
use Shopologic\Core\Database\Model;
use Shopologic\Core\Database\Collection;

class BelongsTo extends Relation
{
    protected string $foreignKey;
    protected string $ownerKey;
    protected string $relationName;

    public function __construct(Builder $query, Model $child, string $foreignKey, string $ownerKey, string $relationName)
    {
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
        $this->relationName = $relationName;

        parent::__construct($query, $child);
    }

    public function getResults()
    {
        return $this->query->first();
    }

    public function addConstraints(): void
    {
        if (static::$constraints) {
            $table = $this->related->getTable();
            $this->query->where($table . '.' . $this->ownerKey, '=', $this->parent->{$this->foreignKey});
        }
    }

    public function addEagerConstraints(array $models): void
    {
        $key = $this->related->getTable() . '.' . $this->ownerKey;
        $this->query->whereIn($key, $this->getEagerModelKeys($models));
    }

    protected function getEagerModelKeys(array $models): array
    {
        $keys = [];

        foreach ($models as $model) {
            if (!is_null($value = $model->{$this->foreignKey})) {
                $keys[] = $value;
            }
        }

        return array_unique($keys);
    }

    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }

    public function match(array $models, Collection $results, string $relation): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->getAttribute($this->ownerKey)] = $result;
        }

        foreach ($models as $model) {
            if (isset($dictionary[$model->{$this->foreignKey}])) {
                $model->setRelation($relation, $dictionary[$model->{$this->foreignKey}]);
            }
        }

        return $models;
    }

    public function associate($model): Model
    {
        $ownerKey = $model instanceof Model ? $model->getAttribute($this->ownerKey) : $model;

        $this->parent->setAttribute($this->foreignKey, $ownerKey);

        if ($model instanceof Model) {
            $this->parent->setRelation($this->relationName, $model);
        }

        return $this->parent;
    }

    public function dissociate(): Model
    {
        $this->parent->setAttribute($this->foreignKey, null);
        return $this->parent->setRelation($this->relationName, null);
    }

    public function getEager(): Collection
    {
        return $this->get();
    }

    public function getQualifiedForeignKeyName(): string
    {
        return $this->parent->getTable() . '.' . $this->foreignKey;
    }

    public function getOwnerKeyName(): string
    {
        return $this->ownerKey;
    }

    public function getQualifiedOwnerKeyName(): string
    {
        return $this->related->getTable() . '.' . $this->ownerKey;
    }

    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    public function getExistenceCompareKey(): string
    {
        return $this->getQualifiedForeignKeyName();
    }
}