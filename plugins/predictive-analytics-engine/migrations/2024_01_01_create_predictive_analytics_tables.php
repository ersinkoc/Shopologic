<?php

use Shopologic\Database\Migration;
use Shopologic\Database\Schema;

class CreatePredictiveAnalyticsTables extends Migration
{
    public function up(): void
    {
        // Prediction models storage
        Schema::create('prediction_models', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('model_type'); // sales, customer_behavior, market_trends, inventory_demand
            $table->string('model_name');
            $table->string('algorithm'); // arima, lstm, random_forest, prophet
            $table->json('parameters')->nullable();
            $table->json('hyperparameters')->nullable();
            $table->decimal('accuracy_score', 5, 4)->nullable();
            $table->decimal('precision_score', 5, 4)->nullable();
            $table->decimal('recall_score', 5, 4)->nullable();
            $table->integer('training_samples');
            $table->timestamp('trained_at');
            $table->boolean('is_active')->default(false);
            $table->json('feature_importance')->nullable();
            $table->timestamps();
            
            $table->index(['store_id', 'model_type', 'is_active']);
        });

        // Sales predictions
        Schema::create('sales_predictions', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('product_id')->nullable()->constrained();
            $table->foreignId('category_id')->nullable()->constrained();
            $table->date('prediction_date');
            $table->decimal('predicted_value', 15, 2);
            $table->decimal('confidence_level', 3, 2);
            $table->decimal('lower_bound', 15, 2);
            $table->decimal('upper_bound', 15, 2);
            $table->string('prediction_type'); // daily, weekly, monthly
            $table->json('factors')->nullable(); // factors affecting prediction
            $table->decimal('actual_value', 15, 2)->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
            
            $table->index(['store_id', 'prediction_date']);
            $table->index(['product_id', 'prediction_date']);
        });

        // Customer behavior predictions
        Schema::create('customer_behavior_predictions', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->string('behavior_type'); // purchase_probability, churn_risk, lifetime_value
            $table->decimal('predicted_value', 10, 4);
            $table->decimal('confidence_level', 3, 2);
            $table->json('behavior_indicators')->nullable();
            $table->date('prediction_date');
            $table->date('valid_until');
            $table->boolean('is_current')->default(true);
            $table->timestamps();
            
            $table->index(['customer_id', 'behavior_type', 'is_current']);
            $table->index(['store_id', 'prediction_date']);
        });

        // Market trend analysis
        Schema::create('market_trends', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('trend_type'); // product, category, seasonal, market
            $table->string('entity_type')->nullable(); // product, category, brand
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('trend_direction'); // up, down, stable
            $table->decimal('trend_strength', 5, 4); // 0-1 scale
            $table->decimal('significance_score', 5, 4);
            $table->json('trend_data');
            $table->date('period_start');
            $table->date('period_end');
            $table->json('influencing_factors')->nullable();
            $table->timestamp('detected_at');
            $table->timestamps();
            
            $table->index(['store_id', 'trend_type', 'period_end']);
            $table->index(['entity_type', 'entity_id']);
        });

        // Anomaly detection
        Schema::create('prediction_anomalies', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('anomaly_type'); // sales_drop, traffic_spike, behavior_change
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->decimal('deviation_score', 8, 4);
            $table->json('anomaly_data');
            $table->string('severity'); // low, medium, high, critical
            $table->boolean('is_resolved')->default(false);
            $table->json('resolution_data')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            
            $table->index(['store_id', 'anomaly_type', 'is_resolved']);
            $table->index(['detected_at', 'severity']);
        });

        // Feature extraction data
        Schema::create('prediction_features', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('feature_type'); // sales, customer, product, temporal
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->json('feature_vector');
            $table->json('categorical_features')->nullable();
            $table->json('numerical_features')->nullable();
            $table->json('temporal_features')->nullable();
            $table->timestamp('extracted_at');
            $table->timestamps();
            
            $table->index(['store_id', 'feature_type']);
            $table->index(['entity_type', 'entity_id']);
        });

        // Seasonal patterns
        Schema::create('seasonal_patterns', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('pattern_type'); // daily, weekly, monthly, yearly
            $table->foreignId('product_id')->nullable()->constrained();
            $table->foreignId('category_id')->nullable()->constrained();
            $table->json('pattern_data');
            $table->decimal('seasonality_strength', 5, 4);
            $table->json('peak_periods')->nullable();
            $table->json('low_periods')->nullable();
            $table->timestamp('analyzed_at');
            $table->timestamps();
            
            $table->index(['store_id', 'pattern_type']);
        });

        // Prediction accuracy tracking
        Schema::create('prediction_accuracy', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('model_id')->constrained('prediction_models');
            $table->date('evaluation_date');
            $table->integer('predictions_count');
            $table->decimal('mean_absolute_error', 10, 4);
            $table->decimal('mean_squared_error', 10, 4);
            $table->decimal('root_mean_squared_error', 10, 4);
            $table->decimal('mean_absolute_percentage_error', 5, 2);
            $table->json('accuracy_by_category')->nullable();
            $table->timestamps();
            
            $table->index(['model_id', 'evaluation_date']);
        });

        // Business insights
        Schema::create('predictive_insights', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('insight_type'); // recommendation, warning, opportunity
            $table->string('category'); // sales, inventory, customer, market
            $table->string('priority'); // low, medium, high, critical
            $table->text('insight_text');
            $table->json('supporting_data');
            $table->decimal('confidence_score', 3, 2);
            $table->decimal('potential_impact', 15, 2)->nullable();
            $table->boolean('is_actionable')->default(true);
            $table->json('recommended_actions')->nullable();
            $table->boolean('is_acted_upon')->default(false);
            $table->timestamp('generated_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->index(['store_id', 'insight_type', 'priority']);
            $table->index(['generated_at', 'is_acted_upon']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predictive_insights');
        Schema::dropIfExists('prediction_accuracy');
        Schema::dropIfExists('seasonal_patterns');
        Schema::dropIfExists('prediction_features');
        Schema::dropIfExists('prediction_anomalies');
        Schema::dropIfExists('market_trends');
        Schema::dropIfExists('customer_behavior_predictions');
        Schema::dropIfExists('sales_predictions');
        Schema::dropIfExists('prediction_models');
    }
}