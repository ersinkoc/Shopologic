<?php


declare(strict_types=1);

namespace Shopologic\Plugins\OmnichannelIntegrationHub;
declare(strict_types=1);
use Shopologic\Database\Migration;
use Shopologic\Database\Schema;

class CreateOmnichannelTables extends Migration
{
    public function up(): void
    {
        // Channel configurations
        Schema::create('omnichannel_channels', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('name');
            $table->string('type'); // pos, marketplace, social, mobile, web
            $table->string('platform')->nullable(); // amazon, ebay, facebook, instagram, etc
            $table->json('credentials')->nullable();
            $table->json('configuration');
            $table->string('status'); // connected, disconnected, error, suspended
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->integer('sync_errors')->default(0);
            $table->timestamps();
            
            $table->index(['store_id', 'type', 'is_active']);
            $table->index(['status', 'is_active']);
        });

        // Inventory sync tracking
        Schema::create('omnichannel_inventory', function($table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('channel_id')->constrained('omnichannel_channels');
            $table->foreignId('location_id')->nullable()->constrained();
            $table->integer('available_quantity');
            $table->integer('reserved_quantity')->default(0);
            $table->integer('buffer_quantity')->default(0);
            $table->boolean('sync_enabled')->default(true);
            $table->timestamp('last_synced_at');
            $table->json('sync_metadata')->nullable();
            $table->timestamps();
            
            $table->unique(['product_id', 'channel_id', 'location_id']);
            $table->index(['channel_id', 'sync_enabled']);
            $table->index('last_synced_at');
        });

        // Order routing and fulfillment
        Schema::create('omnichannel_orders', function($table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->foreignId('channel_id')->constrained('omnichannel_channels');
            $table->string('channel_order_id')->nullable();
            $table->string('fulfillment_method'); // ship_from_store, warehouse, dropship, pickup
            $table->json('routing_details');
            $table->json('split_fulfillment')->nullable();
            $table->string('sync_status'); // pending, synced, error
            $table->timestamp('channel_created_at')->nullable();
            $table->timestamps();
            
            $table->index(['order_id', 'channel_id']);
            $table->index(['channel_id', 'channel_order_id']);
            $table->index('sync_status');
        });

        // Unified customer profiles
        Schema::create('unified_customer_profiles', function($table) {
            $table->id();
            $table->foreignId('primary_customer_id')->constrained('customers');
            $table->json('channel_profiles'); // mapping of channel_id to customer_id
            $table->json('merged_data');
            $table->string('preferred_channel')->nullable();
            $table->decimal('total_lifetime_value', 15, 2)->default(0);
            $table->integer('total_orders')->default(0);
            $table->json('channel_activity')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            
            $table->index('primary_customer_id');
            $table->index('last_activity_at');
        });

        // Product channel mapping
        Schema::create('omnichannel_products', function($table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('channel_id')->constrained('omnichannel_channels');
            $table->string('channel_product_id')->nullable();
            $table->boolean('is_listed')->default(true);
            $table->decimal('channel_price', 10, 2)->nullable();
            $table->json('channel_attributes')->nullable();
            $table->json('mapping_rules')->nullable();
            $table->string('sync_status'); // synced, pending, error
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            
            $table->unique(['product_id', 'channel_id']);
            $table->index(['channel_id', 'is_listed']);
            $table->index(['channel_id', 'channel_product_id']);
        });

        // Channel performance metrics
        Schema::create('channel_performance_metrics', function($table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('omnichannel_channels');
            $table->date('metric_date');
            $table->integer('orders_count')->default(0);
            $table->decimal('revenue', 15, 2)->default(0);
            $table->decimal('average_order_value', 10, 2)->default(0);
            $table->integer('new_customers')->default(0);
            $table->integer('returning_customers')->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->json('top_products')->nullable();
            $table->json('hourly_distribution')->nullable();
            $table->timestamps();
            
            $table->unique(['channel_id', 'metric_date']);
            $table->index('metric_date');
        });

        // Inventory reservations
        Schema::create('inventory_reservations', function($table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('channel_id')->constrained('omnichannel_channels');
            $table->foreignId('order_id')->nullable()->constrained();
            $table->foreignId('location_id')->nullable()->constrained();
            $table->integer('quantity');
            $table->string('reservation_type'); // order, cart, transfer
            $table->timestamp('expires_at');
            $table->string('status'); // active, fulfilled, expired, cancelled
            $table->timestamps();
            
            $table->index(['product_id', 'channel_id', 'status']);
            $table->index(['expires_at', 'status']);
        });

        // Channel sync logs
        Schema::create('channel_sync_logs', function($table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('omnichannel_channels');
            $table->string('sync_type'); // inventory, orders, products, customers
            $table->string('direction'); // push, pull, bidirectional
            $table->integer('records_processed')->default(0);
            $table->integer('records_succeeded')->default(0);
            $table->integer('records_failed')->default(0);
            $table->json('error_details')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['channel_id', 'sync_type', 'started_at']);
        });

        // Channel webhooks
        Schema::create('channel_webhooks', function($table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('omnichannel_channels');
            $table->string('event_type');
            $table->string('webhook_url');
            $table->string('secret_key')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('failure_count')->default(0);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
            
            $table->index(['channel_id', 'event_type', 'is_active']);
        });

        // Cross-channel analytics
        Schema::create('cross_channel_analytics', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->date('analytics_date');
            $table->json('channel_attribution');
            $table->json('customer_journey_stats');
            $table->json('product_performance_by_channel');
            $table->decimal('total_omnichannel_revenue', 15, 2);
            $table->integer('cross_channel_customers');
            $table->json('channel_overlap_matrix');
            $table->timestamps();
            
            $table->unique(['store_id', 'analytics_date']);
            $table->index('analytics_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cross_channel_analytics');
        Schema::dropIfExists('channel_webhooks');
        Schema::dropIfExists('channel_sync_logs');
        Schema::dropIfExists('inventory_reservations');
        Schema::dropIfExists('channel_performance_metrics');
        Schema::dropIfExists('omnichannel_products');
        Schema::dropIfExists('unified_customer_profiles');
        Schema::dropIfExists('omnichannel_orders');
        Schema::dropIfExists('omnichannel_inventory');
        Schema::dropIfExists('omnichannel_channels');
    }
}