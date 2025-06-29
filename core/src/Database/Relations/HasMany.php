<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Relations;

use Shopologic\Core\Database\Collection;

class HasMany extends HasOneOrMany
{
    public function getResults()
    {
        return $this->query->get();
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
        return $this->matchMany($models, $results, $relation);
    }
}