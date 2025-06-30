<?php


declare(strict_types=1);

namespace Shopologic\Plugins\CustomerLifetimeValueOptimizer;
declare(strict_types=1);
use Shopologic\Database\Migration;
use Shopologic\Database\Schema;

class CreateCLVOptimizerTables extends Migration
{
    public function up(): void
    {
        // Customer CLV predictions
        Schema::create('customer_clv_predictions', function($table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->decimal('current_clv', 15, 2);
            $table->decimal('predicted_clv_30d', 15, 2);
            $table->decimal('predicted_clv_90d', 15, 2);
            $table->decimal('predicted_clv_365d', 15, 2);
            $table->decimal('lifetime_predicted_clv', 15, 2);
            $table->string('prediction_model'); // regression, cohort_based, rfm_enhanced
            $table->decimal('prediction_confidence', 3, 2);
            $table->json('prediction_factors');
            $table->json('growth_indicators');
            $table->timestamp('calculated_at');
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->unique(['customer_id', 'calculated_at']);
            $table->index(['predicted_clv_365d', 'prediction_confidence']);
            $table->index('calculated_at');
        });

        // Customer segments based on various criteria
        Schema::create('customer_segments', function($table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->string('segment_type'); // rfm, clv_based, behavioral, lifecycle
            $table->string('segment_name'); // high_value, medium_value, low_value, etc.
            $table->json('segment_criteria');
            $table->decimal('segment_score', 8, 4);
            $table->integer('segment_rank')->nullable();
            $table->json('segment_characteristics');
            $table->date('segment_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['customer_id', 'segment_type', 'is_active']);
            $table->index(['segment_name', 'segment_score']);
            $table->index('segment_date');
        });

        // RFM Analysis results
        Schema::create('rfm_analysis', function($table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->integer('recency_score'); // 1-5
            $table->integer('frequency_score'); // 1-5
            $table->integer('monetary_score'); // 1-5
            $table->string('rfm_segment'); // Champions, Loyal, Potential, etc.
            $table->integer('recency_days');
            $table->integer('frequency_count');
            $table->decimal('monetary_value', 15, 2);
            $table->decimal('rfm_combined_score', 5, 2);
            $table->json('percentile_ranks');
            $table->date('analysis_date');
            $table->timestamps();
            
            $table->unique(['customer_id', 'analysis_date']);
            $table->index(['rfm_segment', 'rfm_combined_score']);
            $table->index(['recency_score', 'frequency_score', 'monetary_score']);
        });

        // Churn risk predictions
        Schema::create('churn_predictions', function($table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->decimal('churn_risk_score', 3, 2); // 0.00 to 1.00
            $table->string('risk_level'); // low, medium, high, critical
            $table->string('prediction_model'); // logistic_regression, random_forest, etc.
            $table->json('risk_factors'); // factors contributing to churn risk
            $table->json('behavioral_indicators');
            $table->integer('days_to_predicted_churn')->nullable();
            $table->string('prediction_horizon'); // 30d, 60d, 90d
            $table->decimal('model_confidence', 3, 2);
            $table->timestamp('predicted_at');
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->index(['customer_id', 'predicted_at']);
            $table->index(['risk_level', 'churn_risk_score']);
            $table->index('predicted_at');
        });

        // Retention campaigns
        Schema::create('retention_campaigns', function($table) {
            $table->id();
            $table->string('campaign_name');
            $table->string('campaign_type'); // churn_prevention, win_back, engagement
            $table->string('target_segment'); // high_risk, inactive, etc.
            $table->json('campaign_rules'); // targeting criteria
            $table->json('campaign_tactics'); // email, sms, discount, etc.
            $table->string('status'); // draft, active, paused, completed
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('target_customer_count');
            $table->integer('actual_customer_count')->default(0);
            $table->decimal('campaign_budget', 15, 2)->nullable();
            $table->decimal('campaign_cost', 15, 2)->default(0);
            $table->timestamps();
            
            $table->index(['campaign_type', 'status']);
            $table->index(['start_date', 'end_date']);
        });

        // Retention campaign executions
        Schema::create('retention_campaign_executions', function($table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('retention_campaigns');
            $table->foreignId('customer_id')->constrained();
            $table->string('execution_channel'); // email, sms, push, phone
            $table->json('personalization_data');
            $table->string('status'); // sent, delivered, opened, clicked, converted
            $table->timestamp('sent_at');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->decimal('conversion_value', 15, 2)->nullable();
            $table->json('response_data')->nullable();
            $table->timestamps();
            
            $table->index(['campaign_id', 'customer_id']);
            $table->index(['status', 'sent_at']);
            $table->index('converted_at');
        });

        // Customer lifetime metrics
        Schema::create('customer_lifetime_metrics', function($table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->integer('total_orders');
            $table->decimal('total_revenue', 15, 2);
            $table->decimal('average_order_value', 10, 2);
            $table->integer('days_since_first_order');
            $table->integer('days_since_last_order');
            $table->decimal('purchase_frequency', 8, 4); // orders per day
            $table->integer('customer_lifetime_days');
            $table->decimal('customer_lifetime_value', 15, 2);
            $table->decimal('predicted_lifetime_value', 15, 2);
            $table->json('engagement_metrics');
            $table->json('behavioral_metrics');
            $table->timestamp('calculated_at');
            $table->timestamps();
            
            $table->unique(['customer_id', 'calculated_at']);
            $table->index('customer_lifetime_value');
            $table->index('days_since_last_order');
        });

        // Cohort analysis data
        Schema::create('cohort_analysis', function($table) {
            $table->id();
            $table->string('cohort_name'); // 2024-01, Q1-2024, etc.
            $table->date('cohort_period_start');
            $table->date('cohort_period_end');
            $table->string('cohort_type'); // monthly, quarterly, yearly
            $table->integer('cohort_size'); // number of customers
            $table->integer('period_number'); // 0, 1, 2, 3... (months/periods since start)
            $table->integer('active_customers');
            $table->decimal('retention_rate', 5, 2);
            $table->decimal('revenue_per_customer', 10, 2);
            $table->decimal('cumulative_revenue', 15, 2);
            $table->decimal('average_clv', 12, 2);
            $table->json('cohort_metrics');
            $table->timestamps();
            
            $table->unique(['cohort_name', 'period_number']);
            $table->index(['cohort_type', 'cohort_period_start']);
            $table->index('retention_rate');
        });

        // Customer behavior scoring
        Schema::create('customer_behavior_scores', function($table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->decimal('engagement_score', 5, 2); // 0-100
            $table->decimal('loyalty_score', 5, 2); // 0-100
            $table->decimal('value_score', 5, 2); // 0-100
            $table->decimal('activity_score', 5, 2); // 0-100
            $table->decimal('overall_behavior_score', 5, 2); // weighted average
            $table->json('score_components'); // breakdown of how scores were calculated
            $table->json('behavioral_indicators');
            $table->date('score_date');
            $table->timestamps();
            
            $table->unique(['customer_id', 'score_date']);
            $table->index('overall_behavior_score');
            $table->index('score_date');
        });

        // Value enhancement opportunities
        Schema::create('value_enhancement_opportunities', function($table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->string('opportunity_type'); // upsell, cross_sell, frequency_increase
            $table->string('opportunity_category'); // product_recommendation, bundle, subscription
            $table->json('opportunity_details');
            $table->decimal('potential_value_increase', 12, 2);
            $table->decimal('probability_score', 3, 2);
            $table->integer('estimated_timeline_days');
            $table->string('status'); // identified, targeted, converted, expired
            $table->timestamp('identified_at');
            $table->timestamp('targeted_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->decimal('actual_value_increase', 12, 2)->nullable();
            $table->timestamps();
            
            $table->index(['customer_id', 'opportunity_type', 'status']);
            $table->index(['probability_score', 'potential_value_increase']);
        });

        // Customer journey stages
        Schema::create('customer_journey_stages', function($table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->string('current_stage'); // prospect, new, developing, established, loyal, at_risk, churned
            $table->string('previous_stage')->nullable();
            $table->timestamp('stage_entered_at');
            $table->integer('days_in_stage');
            $table->json('stage_characteristics');
            $table->json('recommended_actions');
            $table->decimal('stage_value_potential', 12, 2);
            $table->boolean('is_current')->default(true);
            $table->timestamps();
            
            $table->index(['customer_id', 'is_current']);
            $table->index(['current_stage', 'stage_entered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_journey_stages');
        Schema::dropIfExists('value_enhancement_opportunities');
        Schema::dropIfExists('customer_behavior_scores');
        Schema::dropIfExists('cohort_analysis');
        Schema::dropIfExists('customer_lifetime_metrics');
        Schema::dropIfExists('retention_campaign_executions');
        Schema::dropIfExists('retention_campaigns');
        Schema::dropIfExists('churn_predictions');
        Schema::dropIfExists('rfm_analysis');
        Schema::dropIfExists('customer_segments');
        Schema::dropIfExists('customer_clv_predictions');
    }
}