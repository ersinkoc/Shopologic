<?php

use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_users', function ($table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('role', 50)->default('viewer');
            $table->json('permissions')->nullable();
            $table->timestamps();
            
            $table->unique(['store_id', 'user_id']);
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_users');
    }
};