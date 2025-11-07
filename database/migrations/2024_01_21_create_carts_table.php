<?php

use Shopologic\Core\Database\Schema\Blueprint;
use Shopologic\Core\Database\Schema\Schema;

return new class {
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('cascade');
            $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('cascade');
            
            // Cart identification
            $table->string('session_id')->nullable()->index();
            $table->string('token', 64)->unique()->nullable();
            
            // Cart status
            $table->enum('status', ['active', 'abandoned', 'converted', 'expired'])->default('active');
            
            // Amounts
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('shipping_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('currency_code', 3)->default('USD');
            
            // Coupon/discount
            $table->string('coupon_code')->nullable();
            
            // Shipping information
            $table->string('shipping_method')->nullable();
            $table->text('shipping_address')->nullable();
            
            // Additional metadata
            $table->json('metadata')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            
            // Timestamps
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('abandoned_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('customer_id');
            $table->index('session_id');
            $table->index('status');
            $table->index('token');
            $table->index(['status', 'updated_at']);
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
            
            // Item details
            $table->integer('quantity')->default(1);
            $table->decimal('price', 10, 2);
            $table->decimal('original_price', 10, 2)->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            
            // Additional data
            $table->json('options')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('cart_id');
            $table->index('product_id');
            $table->index('variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
