<?php

declare(strict_types=1);

namespace Shopologic\Core\Database\Migrations;

abstract class Migration
{
    /**
     * Run the migrations.
     */
    abstract public function up(): void;

    /**
     * Reverse the migrations.
     */
    abstract public function down(): void;

    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return null;
    }
}