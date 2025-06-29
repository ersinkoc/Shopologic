<?php

use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add store_id to orders table
        Schema::table('orders', function ($table) {
            $table->foreignId('store_id')->nullable()->after('id')->constrained();
            $table->index('store_id');
        });
        
        // Add store_id to customers table
        Schema::table('customers', function ($table) {
            $table->foreignId('store_id')->nullable()->after('id')->constrained();
            $table->index('store_id');
        });
        
        // Add store_id to carts table
        Schema::table('carts', function ($table) {
            $table->foreignId('store_id')->nullable()->after('id')->constrained();
            $table->index('store_id');
        });
        
        // Create store_products pivot table for shared products
        Schema::create('store_products', function ($table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('stock')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
            
            $table->unique(['store_id', 'product_id']);
            $table->index('is_active');
        });
        
        // Create store_categories pivot table
        Schema::create('store_categories', function ($table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->unique(['store_id', 'category_id']);
            $table->index('is_active');
            $table->index('sort_order');
        });
        
        // Create store_payment_methods pivot table
        Schema::create('store_payment_methods', function ($table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('payment_method', 50);
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->unique(['store_id', 'payment_method']);
            $table->index('is_active');
        });
        
        // Create store_shipping_methods pivot table
        Schema::create('store_shipping_methods', function ($table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('shipping_method', 50);
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->json('zones')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->unique(['store_id', 'shipping_method']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_shipping_methods');
        Schema::dropIfExists('store_payment_methods');
        Schema::dropIfExists('store_categories');
        Schema::dropIfExists('store_products');
        
        Schema::table('carts', function ($table) {
            $table->dropForeign(['store_id']);
            $table->dropColumn('store_id');
        });
        
        Schema::table('customers', function ($table) {
            $table->dropForeign(['store_id']);
            $table->dropColumn('store_id');
        });
        
        Schema::table('orders', function ($table) {
            $table->dropForeign(['store_id']);
            $table->dropColumn('store_id');
        });
    }
};