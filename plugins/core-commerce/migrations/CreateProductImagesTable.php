<?php

use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema\Schema;
use Shopologic\Core\Database\Schema\Blueprint;

class CreateProductImagesTable extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function(Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->bigInteger('product_id');
            $table->string('url', 500);
            $table->string('alt_text', 255)->nullable();
            $table->string('title', 255)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index(['product_id', 'sort_order']);
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
}