<?php


declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce;
declare(strict_types=1);
use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema\Schema;
use Shopologic\Core\Database\Schema\Blueprint;

class CreateCartsTable extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function(Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('session_id', 100)->nullable()->index();
            $table->bigInteger('customer_id')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('shipping_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->json('promo_codes')->nullable();
            $table->string('shipping_method', 100)->nullable();
            $table->json('shipping_address')->nullable();
            $table->json('billing_address')->nullable();
            $table->text('customer_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->index(['customer_id', 'updated_at']);
            $table->index('updated_at');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
}