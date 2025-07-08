<?php


declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce;

use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema\Schema;
use Shopologic\Core\Database\Schema\Blueprint;

class CreateOrderItemsTable extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function(Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->bigInteger('order_id');
            $table->bigInteger('product_id');
            $table->bigInteger('variant_id')->nullable();
            $table->string('product_name', 255);
            $table->string('product_sku', 100);
            $table->integer('quantity');
            $table->decimal('price', 10, 2);
            $table->decimal('total', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->json('options')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('restrict');
            $table->index('order_id');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
}