<?php

declare(strict_types=1);

use Shopologic\Core\Database\Migrations\Migration;
use Shopologic\Core\Database\Schema\Schema;
use Shopologic\Core\Database\Schema\Blueprint;

class CreateTranslationsTable extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
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
}