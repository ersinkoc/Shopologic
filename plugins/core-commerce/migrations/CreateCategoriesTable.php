<?php


declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce;

use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema\Schema;
use Shopologic\Core\Database\Schema\Blueprint;

class CreateCategoriesTable extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function(Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('name', 255)->index();
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->bigInteger('parent_id')->nullable()->index();
            $table->string('image', 500)->nullable();
            $table->string('icon', 100)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->string('meta_title', 255)->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->timestamps();
            
            $table->foreign('parent_id')->references('id')->on('categories')->onDelete('cascade');
            $table->index(['parent_id', 'sort_order']);
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
}