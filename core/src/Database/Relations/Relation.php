<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Relations;

use Shopologic\Core\Database\Builder;
use Shopologic\Core\Database\Model;
use Shopologic\Core\Database\Collection;

abstract class Relation
{
    protected Builder $query;
    protected Model $parent;
    protected Model $related;
    protected static bool $constraints = true;

    public function __construct(Builder $query, Model $parent)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->related = $query->getModel();
        
        $this->addConstraints();
    }

    abstract public function addConstraints(): void;
    abstract public function addEagerConstraints(array $models): void;
    abstract public function initRelation(array $models, string $relation): array;
    abstract public function match(array $models, Collection $results, string $relation): array;
    abstract public function getResults();

    public static function noConstraints(\Closure $callback)
    {
        $previous = static::$constraints;
        static::$constraints = false;

        try {
            return $callback();
        } finally {
            static::$constraints = $previous;
        }
    }

    public function getQuery(): Builder
    {
        return $this->query;
    }

    public function getBaseQuery(): Builder
    {
        return $this->query->getQuery();
    }

    public function getParent(): Model
    {
        return $this->parent;
    }

    public function getQualifiedParentKeyName(): string
    {
        return $this->parent->getTable() . '.' . $this->parent->getKeyName();
    }

    public function getRelated(): Model
    {
        return $this->related;
    }

    public function createdAt(): string
    {
        return $this->parent->getCreatedAtColumn();
    }

    public function updatedAt(): string
    {
        return $this->parent->getUpdatedAtColumn();
    }

    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*']): Builder
    {
        return $query->select($columns)->whereColumn(
            $this->getQualifiedParentKeyName(),
            '=',
            $this->getExistenceCompareKey()
        );
    }

    abstract public function getExistenceCompareKey(): string;

    public function getKeys(array $models, ?string $key = null): array
    {
        $keys = [];

        foreach ($models as $model) {
            if ($key) {
                $keys[] = $model->getAttribute($key);
            } else {
                $keys[] = $model->getKey();
            }
        }

        return array_unique(array_filter($keys));
    }

    public function __call(string $method, array $parameters)
    {
        $result = $this->query->$method(...$parameters);

        if ($result === $this->query) {
            return $this;
        }

        return $result;
    }

    public function __clone()
    {
        $this->query = clone $this->query;
    }
}