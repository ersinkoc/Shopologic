<?php

use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function ($table) {
            $table->id();
            $table->string('locale', 10);
            $table->string('group');
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
            
            $table->unique(['locale', 'group', 'key']);
            $table->index('locale');
            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};