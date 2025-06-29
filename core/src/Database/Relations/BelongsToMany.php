<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Relations;

use Shopologic\Core\Database\Builder;
use Shopologic\Core\Database\Model;
use Shopologic\Core\Database\Collection;

class BelongsToMany extends Relation
{
    protected string $table;
    protected string $foreignPivotKey;
    protected string $relatedPivotKey;
    protected string $parentKey;
    protected string $relatedKey;
    protected string $relationName;
    protected array $pivotColumns = [];
    protected array $pivotWheres = [];
    protected array $pivotValues = [];
    protected bool $withTimestamps = false;
    protected string $createdAt = 'created_at';
    protected string $updatedAt = 'updated_at';

    public function __construct(
        Builder $query,
        Model $parent,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey,
        string $relationName
    ) {
        $this->table = $table;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
        $this->parentKey = $parentKey;
        $this->relatedKey = $relatedKey;
        $this->relationName = $relationName;

        parent::__construct($query, $parent);
    }

    public function getResults()
    {
        return $this->get();
    }

    public function addConstraints(): void
    {
        $this->performJoin();

        if (static::$constraints) {
            $this->addWhereConstraints();
        }
    }

    protected function performJoin(): Builder
    {
        $this->query->join(
            $this->table,
            $this->getQualifiedRelatedKeyName(),
            '=',
            $this->getQualifiedRelatedPivotKeyName()
        );

        return $this;
    }

    protected function addWhereConstraints(): void
    {
        $this->query->where(
            $this->getQualifiedForeignPivotKeyName(),
            '=',
            $this->parent->{$this->parentKey}
        );
    }

    public function addEagerConstraints(array $models): void
    {
        $this->query->whereIn(
            $this->getQualifiedForeignPivotKeyName(),
            $this->getKeys($models, $this->parentKey)
        );
    }

    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    public function match(array $models, Collection $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->{$this->parentKey};

            if (isset($dictionary[$key])) {
                $model->setRelation(
                    $relation,
                    $this->related->newCollection($dictionary[$key])
                );
            }
        }

        return $models;
    }

    protected function buildDictionary(Collection $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->pivot->{$this->foreignPivotKey}][] = $result;
        }

        return $dictionary;
    }

    public function withPivot($columns): self
    {
        $this->pivotColumns = array_merge(
            $this->pivotColumns,
            is_array($columns) ? $columns : func_get_args()
        );

        return $this;
    }

    public function wherePivot(string $column, $operator = null, $value = null): self
    {
        $this->pivotWheres[] = func_get_args();
        return $this->where($this->table . '.' . $column, $operator, $value);
    }

    public function withTimestamps(string $createdAt = null, string $updatedAt = null): self
    {
        $this->withTimestamps = true;

        if ($createdAt) {
            $this->createdAt = $createdAt;
        }

        if ($updatedAt) {
            $this->updatedAt = $updatedAt;
        }

        return $this->withPivot($this->createdAt, $this->updatedAt);
    }

    public function attach($id, array $attributes = []): void
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        if (is_array($id)) {
            foreach ($id as $key => $value) {
                $this->attach($key, $value);
            }
            return;
        }

        $this->newPivotStatement()->insert(
            $this->formatAttachRecords([$id], $attributes)
        );
    }

    public function detach($ids = null): int
    {
        $query = $this->newPivotStatementForId($ids);

        return $query->delete();
    }

    public function sync($ids, bool $detaching = true): array
    {
        $changes = [
            'attached' => [],
            'detached' => [],
            'updated' => [],
        ];

        $current = $this->newPivotQuery()
            ->pluck($this->relatedPivotKey)
            ->all();

        $records = $this->formatRecordsList($ids);

        $detach = array_diff($current, array_keys($records));

        if ($detaching && count($detach) > 0) {
            $this->detach($detach);
            $changes['detached'] = $detach;
        }

        $changes = array_merge(
            $changes,
            $this->attachNew($records, $current)
        );

        return $changes;
    }

    protected function attachNew(array $records, array $current): array
    {
        $changes = ['attached' => [], 'updated' => []];

        foreach ($records as $id => $attributes) {
            if (!in_array($id, $current)) {
                $this->attach($id, $attributes);
                $changes['attached'][] = $id;
            } elseif (count($attributes) > 0) {
                if ($this->updateExistingPivot($id, $attributes)) {
                    $changes['updated'][] = $id;
                }
            }
        }

        return $changes;
    }

    public function updateExistingPivot($id, array $attributes): int
    {
        if ($this->withTimestamps) {
            $attributes[$this->updatedAt] = new \DateTime();
        }

        return $this->newPivotStatementForId($id)->update($attributes);
    }

    protected function newPivotStatement(): Builder
    {
        return $this->query->getQuery()->from($this->table);
    }

    protected function newPivotStatementForId($ids): Builder
    {
        $query = $this->newPivotStatement();

        $query->where($this->foreignPivotKey, $this->parent->{$this->parentKey});

        if (!is_null($ids)) {
            $query->whereIn($this->relatedPivotKey, (array) $ids);
        }

        return $query;
    }

    public function newPivot(array $attributes = [], bool $exists = false): Pivot
    {
        $pivot = new Pivot($this->parent, $attributes, $this->table, $exists);

        return $pivot->setPivotKeys($this->foreignPivotKey, $this->relatedPivotKey);
    }

    protected function formatAttachRecords(array $ids, array $attributes): array
    {
        $records = [];

        $hasTimestamps = $this->withTimestamps;

        foreach ($ids as $id) {
            $records[] = $this->formatAttachRecord($id, $attributes, $hasTimestamps);
        }

        return $records;
    }

    protected function formatAttachRecord($id, array $attributes, bool $hasTimestamps): array
    {
        $record = $attributes;

        $record[$this->foreignPivotKey] = $this->parent->{$this->parentKey};
        $record[$this->relatedPivotKey] = $id;

        if ($hasTimestamps) {
            $record[$this->createdAt] = new \DateTime();
            $record[$this->updatedAt] = new \DateTime();
        }

        return $record;
    }

    protected function formatRecordsList($records): array
    {
        if (!is_array($records)) {
            return [$records => []];
        }

        $formatted = [];

        foreach ($records as $key => $value) {
            if (is_array($value)) {
                $formatted[$key] = $value;
            } else {
                $formatted[$value] = [];
            }
        }

        return $formatted;
    }

    public function newPivotQuery(): Builder
    {
        $query = $this->newPivotStatement();

        $query->where($this->foreignPivotKey, $this->parent->{$this->parentKey});

        return $query;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getForeignPivotKeyName(): string
    {
        return $this->foreignPivotKey;
    }

    public function getQualifiedForeignPivotKeyName(): string
    {
        return $this->table . '.' . $this->foreignPivotKey;
    }

    public function getRelatedPivotKeyName(): string
    {
        return $this->relatedPivotKey;
    }

    public function getQualifiedRelatedPivotKeyName(): string
    {
        return $this->table . '.' . $this->relatedPivotKey;
    }

    public function getParentKeyName(): string
    {
        return $this->parentKey;
    }

    public function getQualifiedParentKeyName(): string
    {
        return $this->parent->getTable() . '.' . $this->parentKey;
    }

    public function getRelatedKeyName(): string
    {
        return $this->relatedKey;
    }

    public function getQualifiedRelatedKeyName(): string
    {
        return $this->related->getTable() . '.' . $this->relatedKey;
    }

    public function getExistenceCompareKey(): string
    {
        return $this->getQualifiedForeignPivotKeyName();
    }
}