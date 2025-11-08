<?php

use Shopologic\Core\Database\Schema\Blueprint;
use Shopologic\Core\Database\Schema\Schema;

return new class {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('set null');
            
            // Order details
            $table->string('order_number', 50)->unique();
            $table->enum('status', [
                'pending',
                'processing',
                'confirmed',
                'shipped',
                'delivered',
                'cancelled',
                'refunded'
            ])->default('pending');
            
            // Payment
            $table->enum('payment_status', [
                'pending',
                'authorized',
                'paid',
                'partially_refunded',
                'refunded',
                'failed'
            ])->default('pending');
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_transaction_id')->nullable();
            
            // Amounts
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('shipping_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('currency_code', 3)->default('USD');
            
            // Shipping information
            $table->string('shipping_method')->nullable();
            $table->string('shipping_address_line1')->nullable();
            $table->string('shipping_address_line2')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state')->nullable();
            $table->string('shipping_postal_code', 20)->nullable();
            $table->string('shipping_country', 2)->nullable();
            
            // Billing information
            $table->string('billing_address_line1')->nullable();
            $table->string('billing_address_line2')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_state')->nullable();
            $table->string('billing_postal_code', 20)->nullable();
            $table->string('billing_country', 2)->nullable();
            
            // Customer information
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->string('customer_name')->nullable();
            
            // Additional information
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            
            // Timestamps
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('order_number');
            $table->index('customer_id');
            $table->index('status');
            $table->index('payment_status');
            $table->index(['created_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
