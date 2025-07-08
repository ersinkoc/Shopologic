<?php


declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce;

use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema\Schema;
use Shopologic\Core\Database\Schema\Blueprint;

class CreateProductsTable extends Migration
{
    public function up(): void
    {
        Schema::create('products', function(Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('name', 255)->index();
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('sku', 100)->unique();
            $table->decimal('price', 10, 2)->index();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->enum('status', ['draft', 'active', 'inactive'])->default('draft')->index();
            $table->string('type', 50)->default('simple');
            $table->decimal('weight', 8, 3)->nullable();
            $table->json('dimensions')->nullable();
            $table->bigInteger('category_id')->nullable();
            $table->bigInteger('brand_id')->nullable();
            $table->bigInteger('tax_class_id')->nullable();
            $table->boolean('manage_stock')->default(true);
            $table->integer('stock_quantity')->default(0);
            $table->enum('stock_status', ['in_stock', 'out_of_stock'])->default('in_stock');
            $table->boolean('is_featured')->default(false)->index();
            $table->string('meta_title', 255)->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->json('attributes')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['status', 'created_at']);
            $table->index(['is_featured', 'status']);
            $table->fullText(['name', 'description']);
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
}