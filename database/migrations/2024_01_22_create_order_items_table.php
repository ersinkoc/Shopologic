<?php

use Shopologic\Core\Database\Schema\Blueprint;
use Shopologic\Core\Database\Schema\Schema;

return new class {
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('set null');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->onDelete('set null');
            
            // Item details
            $table->string('product_name');
            $table->string('product_sku')->nullable();
            $table->integer('quantity')->default(1);
            
            // Pricing
            $table->decimal('unit_price', 10, 2);
            $table->decimal('original_price', 10, 2)->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('tax_rate', 5, 4)->default(0);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('total', 10, 2);
            
            // Product attributes at time of purchase
            $table->json('options')->nullable();
            $table->json('metadata')->nullable();
            $table->text('product_snapshot')->nullable(); // JSON snapshot of product at purchase time
            
            // Fulfillment
            $table->enum('fulfillment_status', [
                'pending',
                'processing',
                'shipped',
                'delivered',
                'cancelled',
                'returned'
            ])->default('pending');
            $table->integer('quantity_fulfilled')->default(0);
            $table->integer('quantity_returned')->default(0);
            
            $table->timestamps();
            
            // Indexes
            $table->index('order_id');
            $table->index('product_id');
            $table->index('fulfillment_status');
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50);
            $table->text('comment')->nullable();
            $table->boolean('notify_customer')->default(false);
            $table->timestamp('notified_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('order_id');
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('order_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            
            // Transaction details
            $table->enum('type', [
                'authorization',
                'capture',
                'sale',
                'refund',
                'void'
            ]);
            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed',
                'cancelled'
            ])->default('pending');
            
            $table->decimal('amount', 10, 2);
            $table->string('currency_code', 3)->default('USD');
            
            // Gateway information
            $table->string('gateway')->nullable();
            $table->string('gateway_transaction_id')->nullable();
            $table->string('gateway_response_code')->nullable();
            $table->text('gateway_response_message')->nullable();
            $table->json('gateway_response_data')->nullable();
            
            // Additional information
            $table->string('payment_method')->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->string('card_brand')->nullable();
            $table->text('notes')->nullable();
            
            // Timestamps
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('order_id');
            $table->index('gateway_transaction_id');
            $table->index('status');
            $table->index(['order_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_transactions');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_items');
    }
};
