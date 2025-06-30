<?php


declare(strict_types=1);

namespace Shopologic\Plugins\EnterpriseSupplyChainManagement;
declare(strict_types=1);
use Shopologic\Database\Migration;
use Shopologic\Database\Schema;

class CreateSupplyChainTables extends Migration
{
    public function up(): void
    {
        // Suppliers and vendor management
        Schema::create('suppliers', function($table) {
            $table->id();
            $table->string('supplier_code')->unique();
            $table->string('company_name');
            $table->string('contact_person');
            $table->string('email');
            $table->string('phone');
            $table->text('address');
            $table->string('country');
            $table->string('tax_id')->nullable();
            $table->string('business_license')->nullable();
            $table->json('certifications'); // ISO, quality certifications
            $table->string('status'); // active, inactive, under_review, blacklisted
            $table->decimal('overall_rating', 3, 2)->default(0);
            $table->json('capabilities'); // what they can supply
            $table->json('geographic_coverage');
            $table->decimal('financial_stability_score', 3, 2)->nullable();
            $table->timestamps();
            
            $table->index(['status', 'overall_rating']);
            $table->index('country');
        });

        // Supplier performance tracking
        Schema::create('supplier_performance', function($table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained();
            $table->date('performance_date');
            $table->decimal('delivery_performance', 3, 2); // on-time delivery rate
            $table->decimal('quality_score', 3, 2);
            $table->decimal('cost_competitiveness', 3, 2);
            $table->decimal('communication_quality', 3, 2);
            $table->decimal('compliance_score', 3, 2);
            $table->decimal('overall_performance', 3, 2);
            $table->integer('orders_delivered')->default(0);
            $table->integer('orders_late')->default(0);
            $table->integer('quality_issues')->default(0);
            $table->decimal('cost_variance_percentage', 5, 2)->default(0);
            $table->json('performance_notes')->nullable();
            $table->timestamps();
            
            $table->unique(['supplier_id', 'performance_date']);
            $table->index('overall_performance');
        });

        // Supply chain shipments and tracking
        Schema::create('shipments', function($table) {
            $table->id();
            $table->string('tracking_number')->unique();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('purchase_order_id')->nullable()->constrained();
            $table->string('origin_location');
            $table->string('destination_location');
            $table->json('route_waypoints')->nullable();
            $table->string('carrier_name');
            $table->string('transport_mode'); // road, rail, air, sea, multimodal
            $table->string('status'); // created, in_transit, delivered, delayed, lost
            $table->json('products_manifest');
            $table->decimal('total_weight', 10, 3);
            $table->decimal('total_volume', 10, 3);
            $table->decimal('total_value', 15, 2);
            $table->timestamp('shipped_at');
            $table->timestamp('estimated_delivery');
            $table->timestamp('actual_delivery')->nullable();
            $table->json('special_requirements')->nullable(); // temperature, fragile, etc.
            $table->timestamps();
            
            $table->index(['status', 'estimated_delivery']);
            $table->index(['supplier_id', 'shipped_at']);
            $table->index('tracking_number');
        });

        // Real-time shipment tracking
        Schema::create('shipment_tracking', function($table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained();
            $table->string('location');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('status_update');
            $table->text('notes')->nullable();
            $table->timestamp('recorded_at');
            $table->string('data_source'); // manual, gps, carrier_api, iot_sensor
            $table->json('sensor_data')->nullable(); // temperature, humidity, shock
            $table->timestamps();
            
            $table->index(['shipment_id', 'recorded_at']);
            $table->index(['latitude', 'longitude']);
        });

        // Blockchain transactions for traceability
        Schema::create('blockchain_transactions', function($table) {
            $table->id();
            $table->string('transaction_hash')->unique();
            $table->string('block_number');
            $table->string('transaction_type'); // product_origin, shipment_created, quality_check, etc.
            $table->string('entity_type'); // product, shipment, supplier, etc.
            $table->string('entity_id');
            $table->json('transaction_data');
            $table->string('verification_status'); // pending, confirmed, failed
            $table->timestamp('blockchain_timestamp');
            $table->string('gas_used')->nullable();
            $table->decimal('transaction_fee', 18, 8)->nullable();
            $table->timestamps();
            
            $table->index(['entity_type', 'entity_id']);
            $table->index(['transaction_type', 'blockchain_timestamp']);
            $table->index('verification_status');
        });

        // Product traceability chain
        Schema::create('product_traceability', function($table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->string('batch_number')->nullable();
            $table->string('serial_number')->nullable();
            $table->json('origin_details'); // farm, factory, region, etc.
            $table->json('supply_chain_events'); // manufacturing, processing, shipping events
            $table->json('quality_certifications');
            $table->json('sustainability_data'); // carbon footprint, fair trade, etc.
            $table->string('current_location');
            $table->string('custody_chain'); // who has handled the product
            $table->boolean('authenticity_verified')->default(false);
            $table->json('blockchain_references'); // related blockchain transactions
            $table->timestamps();
            
            $table->index(['product_id', 'batch_number']);
            $table->index(['serial_number']);
            $table->index('authenticity_verified');
        });

        // Logistics optimization and route planning
        Schema::create('logistics_routes', function($table) {
            $table->id();
            $table->string('route_name');
            $table->string('origin');
            $table->string('destination');
            $table->json('waypoints');
            $table->decimal('total_distance', 10, 2); // kilometers
            $table->integer('estimated_duration'); // minutes
            $table->decimal('estimated_cost', 10, 2);
            $table->decimal('carbon_footprint', 10, 4); // kg CO2
            $table->string('optimization_algorithm'); // genetic, ant_colony, etc.
            $table->json('constraints'); // vehicle capacity, time windows, etc.
            $table->decimal('efficiency_score', 3, 2);
            $table->boolean('is_optimized')->default(false);
            $table->timestamp('last_optimized')->nullable();
            $table->timestamps();
            
            $table->index(['origin', 'destination']);
            $table->index('efficiency_score');
        });

        // Supply chain risk assessment
        Schema::create('supply_chain_risks', function($table) {
            $table->id();
            $table->string('risk_type'); // supplier, logistics, external, compliance
            $table->string('risk_category'); // operational, financial, regulatory, environmental
            $table->string('entity_type'); // supplier, route, product, region
            $table->string('entity_id');
            $table->decimal('risk_score', 3, 2); // 0.00 to 1.00
            $table->string('risk_level'); // low, medium, high, critical
            $table->text('risk_description');
            $table->json('risk_factors');
            $table->json('impact_assessment');
            $table->decimal('probability', 3, 2);
            $table->json('mitigation_strategies');
            $table->string('status'); // active, mitigated, monitoring, closed
            $table->date('identified_date');
            $table->date('review_date')->nullable();
            $table->timestamps();
            
            $table->index(['entity_type', 'entity_id', 'risk_level']);
            $table->index(['risk_type', 'status']);
            $table->index('risk_score');
        });

        // Contingency plans
        Schema::create('contingency_plans', function($table) {
            $table->id();
            $table->string('plan_name');
            $table->string('plan_type'); // supplier_failure, logistics_disruption, etc.
            $table->json('trigger_conditions');
            $table->json('activation_criteria');
            $table->json('action_steps');
            $table->json('resource_requirements');
            $table->json('stakeholder_notifications');
            $table->decimal('estimated_cost', 15, 2);
            $table->integer('estimated_recovery_time'); // hours
            $table->boolean('auto_activate')->default(false);
            $table->string('status'); // draft, active, archived
            $table->timestamp('last_tested')->nullable();
            $table->timestamp('last_activated')->nullable();
            $table->timestamps();
            
            $table->index(['plan_type', 'status']);
            $table->index('auto_activate');
        });

        // IoT sensor data
        Schema::create('iot_sensor_data', function($table) {
            $table->id();
            $table->string('sensor_id');
            $table->string('sensor_type'); // temperature, humidity, location, shock, light
            $table->string('entity_type'); // shipment, warehouse, vehicle
            $table->string('entity_id');
            $table->decimal('sensor_value', 15, 6);
            $table->string('unit_of_measure');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamp('reading_timestamp');
            $table->json('additional_data')->nullable();
            $table->boolean('threshold_violated')->default(false);
            $table->string('data_quality'); // good, questionable, bad
            $table->timestamps();
            
            $table->index(['entity_type', 'entity_id', 'sensor_type']);
            $table->index(['reading_timestamp', 'sensor_type']);
            $table->index('threshold_violated');
        });

        // Sustainability metrics
        Schema::create('sustainability_metrics', function($table) {
            $table->id();
            $table->string('entity_type'); // supplier, product, shipment, route
            $table->string('entity_id');
            $table->date('metrics_date');
            $table->decimal('carbon_footprint', 12, 4); // kg CO2 equivalent
            $table->decimal('water_usage', 12, 4)->nullable(); // liters
            $table->decimal('energy_consumption', 12, 4)->nullable(); // kWh
            $table->decimal('waste_generated', 12, 4)->nullable(); // kg
            $table->decimal('recycling_rate', 5, 2)->nullable(); // percentage
            $table->json('certifications'); // fair trade, organic, etc.
            $table->json('sustainability_initiatives');
            $table->decimal('sustainability_score', 3, 2);
            $table->timestamps();
            
            $table->index(['entity_type', 'entity_id', 'metrics_date']);
            $table->index('sustainability_score');
        });

        // Supply chain analytics
        Schema::create('supply_chain_analytics', function($table) {
            $table->id();
            $table->date('analytics_date');
            $table->string('metric_type'); // efficiency, cost, sustainability, risk
            $table->string('scope'); // global, regional, supplier, product
            $table->string('scope_id')->nullable();
            $table->json('metric_values');
            $table->json('trend_analysis');
            $table->json('benchmark_comparisons');
            $table->json('improvement_recommendations');
            $table->timestamps();
            
            $table->index(['analytics_date', 'metric_type']);
            $table->index(['scope', 'scope_id']);
        });

        // Supplier contracts
        Schema::create('supplier_contracts', function($table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained();
            $table->string('contract_number')->unique();
            $table->string('contract_type'); // purchase_agreement, service_contract, framework
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status'); // draft, active, expired, terminated
            $table->json('terms_and_conditions');
            $table->json('pricing_terms');
            $table->json('delivery_terms');
            $table->json('quality_requirements');
            $table->json('performance_kpis');
            $table->decimal('contract_value', 15, 2);
            $table->boolean('auto_renewal')->default(false);
            $table->integer('renewal_notice_days')->default(30);
            $table->timestamps();
            
            $table->index(['supplier_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_contracts');
        Schema::dropIfExists('supply_chain_analytics');
        Schema::dropIfExists('sustainability_metrics');
        Schema::dropIfExists('iot_sensor_data');
        Schema::dropIfExists('contingency_plans');
        Schema::dropIfExists('supply_chain_risks');
        Schema::dropIfExists('logistics_routes');
        Schema::dropIfExists('product_traceability');
        Schema::dropIfExists('blockchain_transactions');
        Schema::dropIfExists('shipment_tracking');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('supplier_performance');
        Schema::dropIfExists('suppliers');
    }
}