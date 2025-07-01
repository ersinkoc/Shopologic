<?php

declare(strict_types=1);

use Shopologic\Core\Database\Migrations\Migration;
use Shopologic\Core\Database\Schema\Schema;
use Shopologic\Core\Database\Schema\Blueprint;

class CreateStoreUsersTable extends Migration
{
    public function up(): void
    {
        Schema::create('store_users', function (Blueprint $table) {
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
}