<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Schema;

class PostgreSQLSchemaBuilder extends SchemaBuilder
{
    protected function createGrammar(): Grammar
    {
        return new PostgreSQLGrammar();
    }
}