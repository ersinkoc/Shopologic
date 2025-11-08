<?php

declare(strict_types=1);

namespace Shopologic\Core\Database;

class Builder extends QueryBuilder
{
    protected ?Model $model = null;
    protected array $eagerLoad = [];
    protected array $scopes = [];
    protected array $removedScopes = [];

    public function setModel(Model $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function getModel(): ?Model
    {
        return $this->model;
    }

    public function find(mixed $id, string $column = 'id'): ?array
    {
        return parent::find($id, $this->model->getKeyName());
    }
    
    public function findModel(mixed $id, array $columns = ['*']): ?Model
    {
        return $this->where($this->model->getKeyName(), $id)->firstModel($columns);
    }

    public function findOrFail(mixed $id, array $columns = ['*']): Model
    {
        $model = $this->findModel($id, $columns);
        
        if (!$model) {
            throw new ModelNotFoundException("Model not found with ID: {$id}");
        }
        
        return $model;
    }

    public function findMany(array $ids, array $columns = ['*']): Collection
    {
        return $this->whereIn($this->model->getKeyName(), $ids)->getModels($columns);
    }

    public function first(): ?array
    {
        $result = parent::first();
        
        if ($result) {
            $models = $this->hydrate([$result]);
            return $models->isEmpty() ? null : ['model' => $models->first()];
        }
        
        return null;
    }
    
    public function firstModel(array $columns = ['*']): ?Model
    {
        $this->select($columns);
        $result = parent::first();
        
        if ($result) {
            return $this->hydrate([$result])->first();
        }
        
        return null;
    }

    public function firstOrFail(array $columns = ['*']): Model
    {
        $model = $this->firstModel($columns);
        
        if (!$model) {
            throw new ModelNotFoundException("No model found matching query");
        }
        
        return $model;
    }

    public function get(): ResultInterface
    {
        return parent::get();
    }
    
    public function getModels(array $columns = ['*']): Collection
    {
        $this->select($columns);
        $results = parent::get();
        $models = $this->hydrate($results->fetchAll());
        
        if (count($this->eagerLoad) > 0) {
            $this->eagerLoadRelations($models);
        }
        
        return new Collection($models);
    }

    public function paginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): Paginator
    {
        // SECURITY FIX: Validate page number to prevent negative values and ensure it's positive
        $page = $page ?: (int) ($_GET[$pageName] ?? 1);

        // Ensure page is at least 1 (prevent negative page numbers and zero)
        if ($page < 1) {
            $page = 1;
        }

        $total = $this->count();
        
        $results = $this->limit($perPage)
                       ->offset(($page - 1) * $perPage)
                       ->getModels($columns);
        
        return new Paginator($results, $total, $perPage, $page);
    }

    public function create(array $attributes): Model
    {
        $model = $this->newModelInstance($attributes);
        $model->save();
        
        return $model;
    }

    public function forceCreate(array $attributes): Model
    {
        $model = $this->newModelInstance();
        
        return $model->unguarded(function () use ($model, $attributes) {
            return $model->fill($attributes)->save();
        });
    }

    public function update(array $values): int
    {
        return parent::update($values);
    }

    public function increment(string $column, int $amount = 1, array $extra = []): int
    {
        $values = array_merge([$column => $this->raw("{$column} + {$amount}")], $extra);
        
        if ($this->model && $this->model->timestamps) {
            $values[$this->model->getUpdatedAtColumn()] = new \DateTime();
        }
        
        return $this->update($values);
    }

    public function decrement(string $column, int $amount = 1, array $extra = []): int
    {
        return $this->increment($column, -$amount, $extra);
    }

    public function delete(): int
    {
        if (isset($this->onDelete)) {
            return (int) call_user_func($this->onDelete, $this);
        }
        
        return parent::delete();
    }

    public function forceDelete(): int
    {
        return parent::delete();
    }

    public function with($relations): self
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }
        
        $this->eagerLoad = array_merge($this->eagerLoad, $relations);
        
        return $this;
    }

    public function without($relations): self
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }
        
        $this->eagerLoad = array_diff($this->eagerLoad, $relations);
        
        return $this;
    }

    public function withCount($relations): self
    {
        // Implementation for counting related models
        return $this;
    }

    public function scope(string $name, ...$parameters): self
    {
        if (method_exists($this->model, 'scope' . ucfirst($name))) {
            $this->model->{'scope' . ucfirst($name)}($this, ...$parameters);
        }
        
        return $this;
    }

    public function withGlobalScope(string $identifier, $scope): self
    {
        $this->scopes[$identifier] = $scope;
        
        if (method_exists($scope, 'apply')) {
            $scope->apply($this, $this->getModel());
        }
        
        return $this;
    }

    public function withoutGlobalScope($scope): self
    {
        if (is_string($scope)) {
            unset($this->scopes[$scope]);
        } else {
            $this->scopes = array_filter($this->scopes, function ($s) use ($scope) {
                return $s !== $scope;
            });
        }
        
        return $this;
    }

    public function withoutGlobalScopes(array $scopes = []): self
    {
        if (empty($scopes)) {
            $this->scopes = [];
        } else {
            foreach ($scopes as $scope) {
                $this->withoutGlobalScope($scope);
            }
        }
        
        return $this;
    }

    protected function hydrate(array $items): Collection
    {
        $models = [];
        
        foreach ($items as $item) {
            $models[] = $this->newModelInstance()->newFromBuilder($item);
        }
        
        return new Collection($models);
    }

    protected function eagerLoadRelations(Collection $models): void
    {
        foreach ($this->eagerLoad as $name => $constraints) {
            if (is_numeric($name)) {
                $name = $constraints;
                $constraints = function () {};
            }
            
            $this->eagerLoadRelation($models, $name, $constraints);
        }
    }

    protected function eagerLoadRelation(Collection $models, string $name, \Closure $constraints): void
    {
        $relation = $this->getRelation($name);
        
        $relation->addEagerConstraints($models);
        
        $constraints($relation);
        
        $results = $relation->getEager();
        
        $relation->match($models, $results, $name);
    }

    protected function getRelation(string $name): Relation
    {
        $relation = $this->model->$name();
        
        if (!$relation instanceof Relation) {
            throw new \RuntimeException("Method {$name} must return a Relation instance.");
        }
        
        return $relation;
    }

    public function newModelInstance(array $attributes = []): Model
    {
        return $this->model->newInstance($attributes)->setConnection($this->connection);
    }

    public function raw(string $value): Expression
    {
        return new Expression($value);
    }

    public function getQuery(): QueryBuilder
    {
        return $this;
    }

    public function toBase(): QueryBuilder
    {
        return $this;
    }
}