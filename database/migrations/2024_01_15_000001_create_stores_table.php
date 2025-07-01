<?php

declare(strict_types=1);

use Shopologic\Core\Database\Migrations\Migration;
use Shopologic\Core\Database\Schema\Schema;

class CreateStoresTable extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function ($table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('domain')->nullable()->unique();
            $table->string('subdomain', 50)->nullable()->unique();
            $table->string('path_prefix', 50)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('config')->nullable();
            $table->string('theme', 50)->nullable();
            $table->string('locale', 10)->default('en');
            $table->string('currency', 3)->default('USD');
            $table->string('timezone', 50)->default('UTC');
            $table->json('meta')->nullable();
            $table->timestamps();
            
            $table->index('is_active');
            $table->index('is_default');
            $table->index(['domain', 'is_active']);
            $table->index(['subdomain', 'is_active']);
            $table->index(['path_prefix', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
}