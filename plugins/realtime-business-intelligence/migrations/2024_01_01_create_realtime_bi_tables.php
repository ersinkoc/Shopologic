<?php

use Shopologic\Database\Migration;
use Shopologic\Database\Schema;

class CreateRealtimeBiTables extends Migration
{
    public function up(): void
    {
        // Real-time metrics storage
        Schema::create('realtime_metrics', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('metric_name');
            $table->decimal('value', 20, 4);
            $table->string('period_type'); // minute, hour, day, week, month
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['store_id', 'metric_name', 'period_type']);
            $table->index(['metric_name', 'period_start', 'period_end']);
            $table->index('period_start');
        });

        // KPI definitions and values
        Schema::create('kpi_definitions', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('kpi_name');
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->string('calculation_method'); // formula, query, function
            $table->text('calculation_config');
            $table->string('unit'); // percentage, currency, count, ratio
            $table->decimal('target_value', 20, 4)->nullable();
            $table->json('thresholds')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('refresh_interval')->default(300); // seconds
            $table->timestamps();
            
            $table->unique(['store_id', 'kpi_name']);
            $table->index(['is_active', 'refresh_interval']);
        });

        // KPI historical values
        Schema::create('kpi_values', function($table) {
            $table->id();
            $table->foreignId('kpi_id')->constrained('kpi_definitions');
            $table->decimal('value', 20, 4);
            $table->decimal('previous_value', 20, 4)->nullable();
            $table->decimal('target_value', 20, 4)->nullable();
            $table->decimal('variance_percentage', 8, 4)->nullable();
            $table->string('trend'); // up, down, stable
            $table->timestamp('calculated_at');
            $table->json('calculation_details')->nullable();
            $table->timestamps();
            
            $table->index(['kpi_id', 'calculated_at']);
            $table->index('calculated_at');
        });

        // Alert definitions
        Schema::create('alert_definitions', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('alert_name');
            $table->string('metric_name');
            $table->string('condition_type'); // threshold, percentage_change, anomaly
            $table->json('condition_config');
            $table->string('severity'); // low, medium, high, critical
            $table->json('recipients');
            $table->json('notification_channels'); // email, slack, sms, webhook
            $table->boolean('is_active')->default(true);
            $table->integer('cooldown_period')->default(3600); // seconds
            $table->timestamps();
            
            $table->index(['store_id', 'is_active']);
            $table->index('metric_name');
        });

        // Alert instances
        Schema::create('alert_instances', function($table) {
            $table->id();
            $table->foreignId('alert_definition_id')->constrained('alert_definitions');
            $table->decimal('trigger_value', 20, 4);
            $table->decimal('threshold_value', 20, 4);
            $table->string('status'); // triggered, acknowledged, resolved, escalated
            $table->json('notification_status')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users');
            $table->timestamp('triggered_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            
            $table->index(['alert_definition_id', 'status']);
            $table->index(['triggered_at', 'status']);
        });

        // Executive reports
        Schema::create('executive_reports', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('report_type'); // daily, weekly, monthly, quarterly
            $table->date('report_date');
            $table->json('kpi_summary');
            $table->json('performance_highlights');
            $table->json('areas_of_concern');
            $table->json('recommendations');
            $table->json('forecasts')->nullable();
            $table->string('status'); // generating, ready, distributed
            $table->json('distribution_log')->nullable();
            $table->timestamps();
            
            $table->unique(['store_id', 'report_type', 'report_date']);
            $table->index('report_date');
        });

        // Dashboard configurations
        Schema::create('dashboard_configurations', function($table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('store_id')->constrained();
            $table->string('dashboard_name');
            $table->json('widget_configuration');
            $table->json('layout_settings');
            $table->json('filter_settings')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            
            $table->index(['user_id', 'store_id']);
            $table->index('is_default');
        });

        // Predictive models
        Schema::create('predictive_models', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('model_name');
            $table->string('target_metric');
            $table->string('algorithm'); // arima, prophet, linear_regression, neural_network
            $table->json('parameters');
            $table->json('training_data_config');
            $table->decimal('accuracy_score', 5, 4)->nullable();
            $table->decimal('mae', 20, 4)->nullable(); // Mean Absolute Error
            $table->decimal('rmse', 20, 4)->nullable(); // Root Mean Square Error
            $table->timestamp('trained_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            
            $table->index(['store_id', 'target_metric', 'is_active']);
        });

        // Predictions
        Schema::create('predictions', function($table) {
            $table->id();
            $table->foreignId('model_id')->constrained('predictive_models');
            $table->string('metric_name');
            $table->date('prediction_date');
            $table->decimal('predicted_value', 20, 4);
            $table->decimal('confidence_lower', 20, 4);
            $table->decimal('confidence_upper', 20, 4);
            $table->decimal('actual_value', 20, 4)->nullable();
            $table->decimal('accuracy', 5, 4)->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
            
            $table->index(['model_id', 'prediction_date']);
            $table->index(['metric_name', 'prediction_date']);
        });

        // Business insights
        Schema::create('business_insights', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('insight_type'); // trend, anomaly, opportunity, risk
            $table->string('category'); // sales, marketing, operations, finance
            $table->string('priority'); // low, medium, high, critical
            $table->string('title');
            $table->text('description');
            $table->json('supporting_data');
            $table->json('recommended_actions')->nullable();
            $table->decimal('confidence_score', 3, 2);
            $table->decimal('potential_impact', 15, 2)->nullable();
            $table->boolean('is_actionable')->default(true);
            $table->string('status'); // new, acknowledged, in_progress, completed, dismissed
            $table->timestamp('discovered_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->index(['store_id', 'insight_type', 'priority']);
            $table->index(['status', 'discovered_at']);
        });

        // Metric snapshots for historical analysis
        Schema::create('metric_snapshots', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('snapshot_type'); // hourly, daily, weekly, monthly
            $table->timestamp('snapshot_time');
            $table->json('metrics_data');
            $table->json('kpis_data');
            $table->json('comparison_data')->nullable();
            $table->timestamps();
            
            $table->index(['store_id', 'snapshot_type', 'snapshot_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_snapshots');
        Schema::dropIfExists('business_insights');
        Schema::dropIfExists('predictions');
        Schema::dropIfExists('predictive_models');
        Schema::dropIfExists('dashboard_configurations');
        Schema::dropIfExists('executive_reports');
        Schema::dropIfExists('alert_instances');
        Schema::dropIfExists('alert_definitions');
        Schema::dropIfExists('kpi_values');
        Schema::dropIfExists('kpi_definitions');
        Schema::dropIfExists('realtime_metrics');
    }
}