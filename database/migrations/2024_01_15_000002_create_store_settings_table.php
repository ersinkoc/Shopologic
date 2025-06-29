<?php

use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_settings', function ($table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->timestamps();
            
            $table->unique(['store_id', 'key']);
            $table->index('key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};