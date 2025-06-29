<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Relations;

use Shopologic\Core\Database\Collection;
use Shopologic\Core\Database\Model;

class HasOne extends HasOneOrMany
{
    public function getResults()
    {
        return $this->query->first();
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
        return $this->matchOne($models, $results, $relation);
    }

    public function newRelatedInstanceFor(Model $parent): Model
    {
        return $this->related->newInstance()->setAttribute(
            $this->getForeignKeyName(),
            $parent->{$this->localKey}
        );
    }
}