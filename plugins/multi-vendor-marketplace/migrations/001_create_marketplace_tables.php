<?php


declare(strict_types=1);

namespace Shopologic\Plugins\MultiVendorMarketplace;
declare(strict_types=1);
use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema;
use Shopologic\Core\Database\Blueprint;

class CreateMarketplaceTables extends Migration
{
    /**
     * Run the migration
     */
    public function up(): void
    {
        // Create vendors table
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('store_name', 255);
            $table->string('business_name', 255)->nullable();
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->string('logo', 500)->nullable();
            $table->string('banner', 500)->nullable();
            $table->string('email', 255);
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('tax_id', 100)->nullable();
            $table->text('bank_details')->nullable();
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->string('payout_schedule', 50)->nullable();
            $table->string('status', 20)->default('pending');
            $table->decimal('rating', 3, 2)->default(0);
            $table->decimal('total_sales', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->json('capabilities')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('status');
            $table->index('slug');
            $table->foreign('user_id')->references('id')->on('users');
        });

        // Create vendor_products table
        Schema::create('vendor_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('product_id');
            $table->string('vendor_sku', 100)->nullable();
            $table->decimal('commission_override', 5, 2)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->integer('lead_time_days')->default(1);
            $table->string('shipping_from', 255)->nullable();
            $table->timestamps();
            
            $table->unique(['vendor_id', 'product_id']);
            $table->index('vendor_id');
            $table->index('product_id');
            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });

        // Create vendor_orders table
        Schema::create('vendor_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_order_id');
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('order_number', 50)->unique();
            $table->string('status', 50)->default('pending');
            $table->json('items');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('commission_amount', 10, 2)->nullable();
            $table->decimal('vendor_earnings', 10, 2)->nullable();
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            
            $table->index('vendor_id');
            $table->index('parent_order_id');
            $table->index('status');
            $table->index('order_number');
            $table->foreign('vendor_id')->references('id')->on('vendors');
            $table->foreign('parent_order_id')->references('id')->on('orders');
            $table->foreign('customer_id')->references('id')->on('customers');
        });

        // Create vendor_commissions table
        Schema::create('vendor_commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('order_id');
            $table->decimal('order_total', 10, 2);
            $table->decimal('commission_rate', 5, 2);
            $table->decimal('commission_amount', 10, 2);
            $table->decimal('vendor_earnings', 10, 2);
            $table->string('status', 20)->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('payout_id')->nullable();
            $table->timestamps();
            
            $table->index('vendor_id');
            $table->index('order_id');
            $table->index('status');
            $table->index(['vendor_id', 'status']);
            $table->foreign('vendor_id')->references('id')->on('vendors');
            $table->foreign('order_id')->references('id')->on('vendor_orders');
        });

        // Create vendor_payouts table
        Schema::create('vendor_payouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('method', 50)->nullable();
            $table->string('transaction_id', 100)->nullable();
            $table->string('status', 20)->default('pending');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->json('commission_ids')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            
            $table->index('vendor_id');
            $table->index('status');
            $table->index(['vendor_id', 'status']);
            $table->foreign('vendor_id')->references('id')->on('vendors');
        });

        // Create vendor_reviews table
        Schema::create('vendor_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('order_id');
            $table->integer('rating')->checkBetween(1, 5);
            $table->string('title', 255)->nullable();
            $table->text('comment')->nullable();
            $table->string('status', 20)->default('pending');
            $table->integer('helpful_count')->default(0);
            $table->boolean('verified_purchase')->default(true);
            $table->timestamps();
            
            $table->index('vendor_id');
            $table->index('customer_id');
            $table->index('status');
            $table->index(['vendor_id', 'status']);
            $table->foreign('vendor_id')->references('id')->on('vendors');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('order_id')->references('id')->on('vendor_orders');
        });

        // Create commission_rules table
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('type', 50);
            $table->string('category', 100)->nullable();
            $table->string('vendor_tier', 50)->nullable();
            $table->decimal('volume_threshold', 10, 2)->nullable();
            $table->decimal('rate', 5, 2);
            $table->integer('priority')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index('type');
            $table->index('priority');
            $table->index('active');
        });

        // Create vendor_analytics table
        Schema::create('vendor_analytics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            $table->date('date');
            $table->integer('views')->default(0);
            $table->integer('visits')->default(0);
            $table->integer('orders')->default(0);
            $table->decimal('revenue', 10, 2)->default(0);
            $table->decimal('commission', 10, 2)->default(0);
            $table->integer('products_sold')->default(0);
            $table->integer('new_customers')->default(0);
            $table->decimal('return_rate', 5, 2)->default(0);
            $table->decimal('average_order_value', 10, 2)->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->timestamps();
            
            $table->unique(['vendor_id', 'date']);
            $table->index('vendor_id');
            $table->index('date');
            $table->foreign('vendor_id')->references('id')->on('vendors');
        });

        // Create vendor_notifications table
        Schema::create('vendor_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            $table->string('type', 50);
            $table->string('title', 255);
            $table->text('message');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            $table->index('vendor_id');
            $table->index(['vendor_id', 'read_at']);
            $table->foreign('vendor_id')->references('id')->on('vendors');
        });

        // Create vendor_documents table
        Schema::create('vendor_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            $table->string('type', 50);
            $table->string('name', 255);
            $table->string('file_path', 500);
            $table->string('status', 20)->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->index('vendor_id');
            $table->index('type');
            $table->foreign('vendor_id')->references('id')->on('vendors');
        });

        // Create vendor_product_views table for detailed analytics
        Schema::create('vendor_product_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('viewer_id')->nullable();
            $table->string('session_id', 100);
            $table->string('referrer', 500)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            
            $table->index('vendor_id');
            $table->index('product_id');
            $table->index('created_at');
        });

        // Create vendor_payout_requests table
        Schema::create('vendor_payout_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            $table->decimal('amount', 10, 2);
            $table->string('status', 20)->default('requested');
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index('vendor_id');
            $table->index('status');
            $table->foreign('vendor_id')->references('id')->on('vendors');
        });
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_payout_requests');
        Schema::dropIfExists('vendor_product_views');
        Schema::dropIfExists('vendor_documents');
        Schema::dropIfExists('vendor_notifications');
        Schema::dropIfExists('vendor_analytics');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('vendor_reviews');
        Schema::dropIfExists('vendor_payouts');
        Schema::dropIfExists('vendor_commissions');
        Schema::dropIfExists('vendor_orders');
        Schema::dropIfExists('vendor_products');
        Schema::dropIfExists('vendors');
    }
}