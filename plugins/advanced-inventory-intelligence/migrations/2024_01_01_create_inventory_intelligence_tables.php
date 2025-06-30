<?php


declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedInventoryIntelligence;
declare(strict_types=1);
use Shopologic\Database\Migration;
use Shopologic\Database\Schema;

class CreateInventoryIntelligenceTables extends Migration
{
    public function up(): void
    {
        // Demand forecasting models
        Schema::create('demand_forecasts', function($table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->string('forecast_model'); // arima, neural_network, seasonal, ensemble
            $table->string('timeframe'); // 7d, 30d, 90d, 365d
            $table->json('forecast_data'); // predicted values by period
            $table->decimal('confidence_score', 3, 2);
            $table->json('model_parameters')->nullable();
            $table->decimal('accuracy_score', 3, 2)->nullable();
            $table->timestamp('generated_at');
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->index(['product_id', 'forecast_model', 'timeframe']);
            $table->index(['generated_at', 'expires_at']);
        });

        // Demand history and patterns
        Schema::create('demand_history', function($table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('location_id')->nullable()->constrained();
            $table->date('demand_date');
            $table->integer('quantity_demanded');
            $table->integer('quantity_sold');
            $table->decimal('demand_rate', 8, 4); // demand per day
            $table->json('demand_factors')->nullable(); // seasonality, promotions, etc.
            $table->boolean('is_outlier')->default(false);
            $table->timestamps();
            
            $table->unique(['product_id', 'location_id', 'demand_date']);
            $table->index(['product_id', 'demand_date']);
        });

        // Inventory optimization recommendations
        Schema::create('inventory_optimizations', function($table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('location_id')->nullable()->constrained();
            $table->string('optimization_type'); // reorder_point, safety_stock, max_stock
            $table->decimal('current_value', 10, 2);
            $table->decimal('recommended_value', 10, 2);
            $table->decimal('potential_savings', 10, 2);
            $table->decimal('confidence_score', 3, 2);
            $table->json('optimization_factors');
            $table->string('status'); // pending, applied, rejected
            $table->timestamp('recommended_at');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            
            $table->index(['product_id', 'optimization_type', 'status']);
            $table->index('recommended_at');
        });

        // Reorder recommendations and automation
        Schema::create('reorder_recommendations', function($table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('location_id')->nullable()->constrained();
            $table->foreignId('supplier_id')->nullable()->constrained();
            $table->integer('recommended_quantity');
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('total_cost', 15, 2);
            $table->string('urgency_level'); // low, medium, high, critical
            $table->decimal('stockout_risk', 3, 2);
            $table->integer('days_of_stock_remaining');
            $table->json('recommendation_factors');
            $table->string('status'); // pending, approved, ordered, rejected
            $table->boolean('is_automated')->default(false);
            $table->timestamp('recommended_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index(['product_id', 'urgency_level', 'status']);
            $table->index(['recommended_at', 'is_automated']);
        });

        // Supplier performance analytics
        Schema::create('supplier_performance', function($table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained();
            $table->date('performance_date');
            $table->decimal('delivery_performance', 3, 2); // on-time delivery rate
            $table->decimal('quality_score', 3, 2);
            $table->decimal('cost_competitiveness', 3, 2);
            $table->decimal('reliability_score', 3, 2);
            $table->decimal('overall_score', 3, 2);
            $table->integer('orders_fulfilled')->default(0);
            $table->integer('orders_late')->default(0);
            $table->integer('orders_defective')->default(0);
            $table->decimal('average_lead_time', 5, 2); // days
            $table->json('performance_metrics')->nullable();
            $table->timestamps();
            
            $table->unique(['supplier_id', 'performance_date']);
            $table->index('overall_score');
        });

        // Purchase order tracking
        Schema::create('purchase_orders', function($table) {
            $table->id();
            $table->string('po_number');
            $table->foreignId('supplier_id')->constrained();
            $table->string('status'); // pending, confirmed, shipped, received, cancelled
            $table->decimal('total_amount', 15, 2);
            $table->timestamp('ordered_at');
            $table->timestamp('expected_delivery')->nullable();
            $table->timestamp('actual_delivery')->nullable();
            $table->json('order_items'); // products and quantities
            $table->boolean('is_automated')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->json('delivery_notes')->nullable();
            $table->timestamps();
            
            $table->unique('po_number');
            $table->index(['supplier_id', 'status']);
            $table->index('ordered_at');
        });

        // Inventory KPIs and metrics
        Schema::create('inventory_kpis', function($table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained();
            $table->foreignId('location_id')->nullable()->constrained();
            $table->date('kpi_date');
            $table->decimal('inventory_turnover', 8, 2)->default(0);
            $table->decimal('days_sales_outstanding', 8, 2)->default(0);
            $table->decimal('stockout_rate', 5, 2)->default(0);
            $table->decimal('carrying_cost_percentage', 5, 2)->default(0);
            $table->decimal('fill_rate', 5, 2)->default(0);
            $table->decimal('gross_margin_roi', 8, 2)->default(0);
            $table->integer('dead_stock_items')->default(0);
            $table->decimal('forecast_accuracy', 5, 2)->default(0);
            $table->json('additional_metrics')->nullable();
            $table->timestamps();
            
            $table->index(['product_id', 'kpi_date']);
            $table->index(['location_id', 'kpi_date']);
            $table->index('kpi_date');
        });

        // Seasonal patterns and trends
        Schema::create('seasonal_patterns', function($table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->string('pattern_type'); // weekly, monthly, yearly, holiday
            $table->json('pattern_data'); // seasonal coefficients
            $table->decimal('seasonality_strength', 3, 2);
            $table->json('peak_periods');
            $table->json('low_periods');
            $table->decimal('trend_slope', 8, 4)->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('last_updated');
            $table->timestamps();
            
            $table->index(['product_id', 'pattern_type']);
        });

        // ABC analysis and classification
        Schema::create('abc_analysis', function($table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->string('abc_category'); // A, B, C
            $table->string('xyz_category')->nullable(); // X, Y, Z (demand variability)
            $table->decimal('revenue_contribution', 5, 2);
            $table->decimal('demand_variability', 5, 2);
            $table->integer('rank_by_revenue');
            $table->integer('rank_by_quantity');
            $table->decimal('cumulative_revenue_percentage', 5, 2);
            $table->json('classification_factors');
            $table->date('classification_date');
            $table->timestamps();
            
            $table->unique(['product_id', 'classification_date']);
            $table->index(['abc_category', 'xyz_category']);
        });

        // Safety stock calculations
        Schema::create('safety_stock_calculations', function($table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('location_id')->nullable()->constrained();
            $table->decimal('current_safety_stock', 10, 2);
            $table->decimal('recommended_safety_stock', 10, 2);
            $table->decimal('service_level_target', 3, 2);
            $table->decimal('demand_variability', 8, 4);
            $table->decimal('lead_time_variability', 8, 4);
            $table->decimal('average_lead_time', 5, 2);
            $table->string('calculation_method'); // statistical, fixed_percentage, manual
            $table->json('calculation_parameters');
            $table->timestamp('calculated_at');
            $table->timestamps();
            
            $table->index(['product_id', 'location_id']);
            $table->index('calculated_at');
        });

        // Inventory transfer suggestions
        Schema::create('inventory_transfers', function($table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('from_location_id')->constrained('locations');
            $table->foreignId('to_location_id')->constrained('locations');
            $table->integer('suggested_quantity');
            $table->decimal('transfer_cost', 10, 2);
            $table->decimal('potential_benefit', 10, 2);
            $table->string('transfer_reason'); // stockout_prevention, overstock_reduction, demand_balancing
            $table->string('status'); // suggested, approved, in_transit, completed, rejected
            $table->timestamp('suggested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['product_id', 'status']);
            $table->index(['from_location_id', 'to_location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transfers');
        Schema::dropIfExists('safety_stock_calculations');
        Schema::dropIfExists('abc_analysis');
        Schema::dropIfExists('seasonal_patterns');
        Schema::dropIfExists('inventory_kpis');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('supplier_performance');
        Schema::dropIfExists('reorder_recommendations');
        Schema::dropIfExists('inventory_optimizations');
        Schema::dropIfExists('demand_history');
        Schema::dropIfExists('demand_forecasts');
    }
}