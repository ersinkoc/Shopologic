<?php

use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function ($table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->string('symbol', 10);
            $table->enum('symbol_position', ['before', 'after'])->default('before');
            $table->integer('decimal_places')->default(2);
            $table->string('decimal_separator', 1)->default('.');
            $table->string('thousands_separator', 1)->default(',');
            $table->decimal('exchange_rate', 10, 6)->default(1.000000);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            
            $table->index('code');
            $table->index('is_active');
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};