<?php


declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce;
declare(strict_types=1);
use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema\Schema;
use Shopologic\Core\Database\Schema\Blueprint;

class CreateCartItemsTable extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function(Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->bigInteger('cart_id');
            $table->bigInteger('product_id');
            $table->bigInteger('variant_id')->nullable();
            $table->integer('quantity');
            $table->decimal('price', 10, 2);
            $table->json('options')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->foreign('cart_id')->references('id')->on('carts')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('cascade');
            $table->unique(['cart_id', 'product_id', 'variant_id']);
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
}