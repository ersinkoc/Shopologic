<?php


declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce;

use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema\Schema;
use Shopologic\Core\Database\Schema\Blueprint;

class CreateProductVariantsTable extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function(Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->bigInteger('product_id');
            $table->string('name', 255);
            $table->string('sku', 100)->unique();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->decimal('weight', 8, 3)->nullable();
            $table->json('dimensions')->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->boolean('manage_stock')->default(true);
            $table->boolean('is_active')->default(true);
            $table->json('attributes');
            $table->string('image', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index(['product_id', 'is_active']);
            $table->index(['product_id', 'sort_order']);
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
}