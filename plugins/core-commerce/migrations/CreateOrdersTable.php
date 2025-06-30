<?php


declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce;
declare(strict_types=1);
use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema\Schema;
use Shopologic\Core\Database\Schema\Blueprint;

class CreateOrdersTable extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function(Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('order_number', 50)->unique();
            $table->bigInteger('customer_id')->nullable();
            $table->string('customer_email', 255);
            $table->string('customer_name', 255);
            $table->enum('status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'])->default('pending')->index();
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending')->index();
            $table->string('payment_method', 100)->nullable();
            $table->string('payment_transaction_id', 255)->nullable();
            $table->string('shipping_method', 100)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('shipping_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->json('promo_codes')->nullable();
            $table->json('shipping_address');
            $table->json('billing_address');
            $table->text('customer_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->index(['status', 'created_at']);
            $table->index(['payment_status', 'created_at']);
            $table->index('customer_email');
            $table->index('created_at');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
}