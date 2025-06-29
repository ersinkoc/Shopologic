<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Relations;

use Shopologic\Core\Database\Builder;
use Shopologic\Core\Database\Model;
use Shopologic\Core\Database\Collection;

abstract class HasOneOrMany extends Relation
{
    protected string $foreignKey;
    protected string $localKey;

    public function __construct(Builder $query, Model $parent, string $foreignKey, string $localKey)
    {
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;

        parent::__construct($query, $parent);
    }

    public function addConstraints(): void
    {
        if (static::$constraints) {
            $this->query->where($this->foreignKey, '=', $this->getParentKey());
            $this->query->whereNotNull($this->foreignKey);
        }
    }

    public function addEagerConstraints(array $models): void
    {
        $this->query->whereIn(
            $this->foreignKey,
            $this->getKeys($models, $this->localKey)
        );
    }

    public function matchOne(array $models, Collection $results, string $relation): array
    {
        return $this->matchOneOrMany($models, $results, $relation, 'one');
    }

    public function matchMany(array $models, Collection $results, string $relation): array
    {
        return $this->matchOneOrMany($models, $results, $relation, 'many');
    }

    protected function matchOneOrMany(array $models, Collection $results, string $relation, string $type): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);

            if (isset($dictionary[$key])) {
                $model->setRelation(
                    $relation,
                    $type === 'one' ? reset($dictionary[$key]) : $this->related->newCollection($dictionary[$key])
                );
            }
        }

        return $models;
    }

    protected function buildDictionary(Collection $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->{$this->getForeignKeyName()}][] = $result;
        }

        return $dictionary;
    }

    public function save(Model $model): Model
    {
        $model->setAttribute($this->getForeignKeyName(), $this->getParentKey());

        return $model->save() ? $model : false;
    }

    public function saveMany($models): Collection
    {
        foreach ($models as $model) {
            $this->save($model);
        }

        return $models;
    }

    public function create(array $attributes = []): Model
    {
        return tap($this->related->newInstance($attributes), function ($instance) {
            $instance->setAttribute($this->getForeignKeyName(), $this->getParentKey());
            $instance->save();
        });
    }

    public function createMany(array $records): Collection
    {
        $instances = $this->related->newCollection();

        foreach ($records as $record) {
            $instances->push($this->create($record));
        }

        return $instances;
    }

    public function update(array $attributes): int
    {
        return $this->query->update($attributes);
    }

    public function getParentKey()
    {
        return $this->parent->getAttribute($this->localKey);
    }

    public function getForeignKeyName(): string
    {
        $segments = explode('.', $this->foreignKey);
        return end($segments);
    }

    public function getQualifiedForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    public function getExistenceCompareKey(): string
    {
        return $this->getQualifiedForeignKeyName();
    }

    public function getLocalKeyName(): string
    {
        return $this->localKey;
    }
}