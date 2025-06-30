<?php


declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedInventory;
declare(strict_types=1);
use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema;
use Shopologic\Core\Database\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations
     */
    public function up(): void
    {
        // Create warehouses table
        Schema::create('warehouses', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('name', 255)->index();
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->json('address')->nullable();
            $table->string('contact_person', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            
            $table->index(['is_active', 'is_default']);
        });

        // Create warehouse locations table (for bin/shelf tracking)
        Schema::create('warehouse_locations', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('warehouse_id');
            $table->string('zone', 50)->nullable();
            $table->string('aisle', 50)->nullable();
            $table->string('shelf', 50)->nullable();
            $table->string('bin', 50)->nullable();
            $table->string('barcode', 100)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->index(['warehouse_id', 'zone', 'aisle']);
            $table->unique(['warehouse_id', 'zone', 'aisle', 'shelf', 'bin']);
        });

        // Create inventory items table
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->integer('quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->decimal('average_cost', 12, 4)->default(0);
            $table->integer('reorder_point')->nullable();
            $table->integer('reorder_quantity')->nullable();
            $table->integer('max_stock_level')->nullable();
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();
            
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->unique(['product_id', 'warehouse_id']);
            $table->index(['quantity', 'reorder_point']);
            $table->index('last_movement_at');
        });

        // Create inventory movements table
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('inventory_item_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->enum('movement_type', ['in', 'out']);
            $table->integer('quantity');
            $table->integer('previous_quantity');
            $table->integer('new_quantity');
            $table->string('reason', 100);
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('cost_per_unit', 12, 4)->nullable();
            $table->string('batch_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['product_id', 'warehouse_id', 'created_at']);
            $table->index(['movement_type', 'reason']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('batch_number');
        });

        // Create inventory adjustments table
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('adjustment_number', 100)->unique();
            $table->enum('type', ['increase', 'decrease', 'set']);
            $table->enum('reason', ['damage', 'theft', 'count_variance', 'expiry', 'other']);
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'approved', 'completed', 'cancelled'])->default('draft');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['status', 'created_at']);
        });

        // Create inventory transfers table
        Schema::create('inventory_transfers', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('transfer_number', 100)->unique();
            $table->unsignedBigInteger('from_warehouse_id');
            $table->unsignedBigInteger('to_warehouse_id');
            $table->enum('status', ['pending', 'in_transit', 'completed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            
            $table->foreign('from_warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('to_warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('received_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['status', 'created_at']);
        });

        // Create suppliers table
        Schema::create('suppliers', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('name', 255);
            $table->string('code', 50)->unique();
            $table->string('contact_person', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->json('address')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive', 'blacklisted'])->default('active');
            $table->decimal('credit_limit', 12, 2)->nullable();
            $table->integer('payment_terms_days')->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            
            $table->index('status');
            $table->index('name');
        });

        // Create supplier products table
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('product_id');
            $table->string('supplier_sku', 100)->nullable();
            $table->decimal('cost_price', 12, 4);
            $table->integer('minimum_order_quantity')->default(1);
            $table->integer('lead_time_days')->default(7);
            $table->boolean('is_preferred')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->unique(['supplier_id', 'product_id']);
            $table->index(['product_id', 'is_preferred', 'is_active']);
        });

        // Create purchase orders table
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('po_number', 100)->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->enum('status', ['draft', 'sent', 'confirmed', 'partially_received', 'received', 'cancelled'])->default('draft');
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['status', 'order_date']);
            $table->index('expected_delivery_date');
        });

        // Create purchase order items table
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity_ordered');
            $table->integer('quantity_received')->default(0);
            $table->decimal('unit_cost', 12, 4);
            $table->decimal('total_cost', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index(['purchase_order_id', 'product_id']);
        });

        // Create reorder rules table
        Schema::create('reorder_rules', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->integer('reorder_point');
            $table->integer('reorder_quantity');
            $table->integer('maximum_stock_level')->nullable();
            $table->integer('lead_time_days')->default(7);
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_create_po')->default(true);
            $table->timestamps();
            
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
            $table->unique(['product_id', 'warehouse_id']);
            $table->index(['is_active', 'auto_create_po']);
        });

        // Create inventory alerts table
        Schema::create('inventory_alerts', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->enum('type', ['low_stock', 'out_of_stock', 'overstock', 'expiry_warning']);
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->string('message', 500);
            $table->integer('current_quantity')->nullable();
            $table->integer('threshold_quantity')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['active', 'acknowledged', 'resolved'])->default('active');
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('acknowledged_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['type', 'status', 'severity']);
            $table->index(['product_id', 'warehouse_id']);
        });

        // Create stock reservations table
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->integer('quantity');
            $table->string('reference_type', 50);
            $table->unsignedBigInteger('reference_id');
            $table->enum('status', ['active', 'fulfilled', 'expired', 'cancelled'])->default('active');
            $table->timestamp('expires_at');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['product_id', 'warehouse_id', 'status']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('inventory_alerts');
        Schema::dropIfExists('reorder_rules');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('inventory_transfers');
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('warehouse_locations');
        Schema::dropIfExists('warehouses');
    }
};