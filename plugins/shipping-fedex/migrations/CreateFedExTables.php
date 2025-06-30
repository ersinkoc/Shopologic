<?php


declare(strict_types=1);

namespace Shopologic\Plugins\ShippingFedex;
declare(strict_types=1);

use Shopologic\Core\Database\Schema\Schema;
use Shopologic\Core\Database\Schema\Blueprint;
use Shopologic\Core\Database\Migration;

class CreateFedExTables extends Migration
{
    public function up(): void
    {
        // FedEx shipments table
        Schema::create('fedex_shipments', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->bigInteger('order_id')->index();
            $table->string('tracking_number', 100)->unique();
            $table->string('master_tracking_number', 100)->nullable();
            $table->string('service_type', 50);
            $table->string('packaging_type', 50)->default('YOUR_PACKAGING');
            $table->decimal('rate', 10, 2);
            $table->string('currency', 3);
            $table->string('status', 50)->default('created')->index();
            $table->text('label_data')->nullable();
            $table->string('label_format', 10)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            
            $table->foreign('order_id')->references('id')->on('orders');
            $table->index(['status', 'created_at']);
            $table->index(['tracking_number', 'status']);
        });

        // FedEx tracking events table
        Schema::create('fedex_tracking_events', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('tracking_number', 100)->index();
            $table->timestamp('event_timestamp');
            $table->string('event_type', 50);
            $table->text('event_description');
            $table->string('location', 255)->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
            
            $table->index(['tracking_number', 'event_timestamp']);
            $table->unique(['tracking_number', 'event_timestamp', 'event_type']);
        });

        // FedEx service zones table
        Schema::create('fedex_service_zones', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->integer('zone');
            $table->string('from_postal', 10);
            $table->string('to_postal', 10);
            $table->string('service_type', 50);
            $table->integer('min_days');
            $table->integer('max_days');
            $table->timestamps();
            
            $table->index(['from_postal', 'to_postal', 'service_type']);
            $table->index('zone');
        });

        // FedEx rate cache table
        Schema::create('fedex_rate_cache', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('cache_key', 255)->unique();
            $table->json('rates');
            $table->string('from_postal', 10);
            $table->string('to_postal', 10);
            $table->decimal('total_weight', 10, 2);
            $table->timestamps();
            
            $table->index(['from_postal', 'to_postal']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fedex_rate_cache');
        Schema::dropIfExists('fedex_service_zones');
        Schema::dropIfExists('fedex_tracking_events');
        Schema::dropIfExists('fedex_shipments');
    }
}