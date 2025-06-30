<?php


declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedPersonalizationEngine;
declare(strict_types=1);
use Shopologic\Database\Migration;
use Shopologic\Database\Schema;

class CreatePersonalizationTables extends Migration
{
    public function up(): void
    {
        // Customer personalization profiles
        Schema::create('customer_personalization_profiles', function($table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->json('behavioral_attributes'); // browsing patterns, preferences
            $table->json('demographic_attributes'); // age, location, etc.
            $table->json('psychographic_attributes'); // interests, values, lifestyle
            $table->json('transactional_attributes'); // purchase history patterns
            $table->json('engagement_attributes'); // email opens, social interactions
            $table->json('preference_signals'); // explicit and implicit preferences
            $table->json('predictive_attributes'); // ML-generated insights
            $table->decimal('personalization_score', 3, 2)->default(0);
            $table->string('primary_segment');
            $table->json('segment_memberships'); // multiple segment assignments
            $table->timestamp('last_updated');
            $table->timestamps();
            
            $table->unique('customer_id');
            $table->index(['primary_segment', 'personalization_score']);
            $table->index('last_updated');
        });

        // Behavioral event tracking
        Schema::create('behavioral_events', function($table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->string('session_id');
            $table->string('event_type'); // page_view, product_view, add_to_cart, etc.
            $table->string('event_category'); // navigation, product_interaction, purchase
            $table->json('event_data'); // specific event details
            $table->string('page_url')->nullable();
            $table->string('referrer_url')->nullable();
            $table->string('channel'); // web, mobile, email, social
            $table->string('device_type'); // desktop, mobile, tablet
            $table->string('user_agent')->nullable();
            $table->decimal('engagement_duration', 8, 2)->nullable(); // seconds
            $table->timestamp('event_timestamp');
            $table->boolean('processed')->default(false);
            $table->timestamps();
            
            $table->index(['customer_id', 'event_type', 'event_timestamp']);
            $table->index(['session_id', 'event_timestamp']);
            $table->index(['processed', 'event_timestamp']);
        });

        // Personalized recommendations
        Schema::create('personalized_recommendations', function($table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->string('recommendation_type'); // products, content, offers
            $table->string('recommendation_context'); // homepage, product_page, email
            $table->json('recommended_items'); // item IDs and metadata
            $table->string('algorithm_used'); // collaborative_filtering, content_based, etc.
            $table->decimal('confidence_score', 3, 2);
            $table->json('recommendation_reasons'); // why these items were recommended
            $table->boolean('displayed')->default(false);
            $table->boolean('clicked')->default(false);
            $table->boolean('converted')->default(false);
            $table->timestamp('generated_at');
            $table->timestamp('displayed_at')->nullable();
            $table->timestamp('interacted_at')->nullable();
            $table->timestamps();
            
            $table->index(['customer_id', 'recommendation_type', 'generated_at']);
            $table->index(['displayed', 'clicked', 'converted']);
            $table->index('confidence_score');
        });

        // Content personalization variants
        Schema::create('content_personalization_variants', function($table) {
            $table->id();
            $table->string('content_id'); // reference to content being personalized
            $table->string('content_type'); // banner, product_description, email, etc.
            $table->string('variant_name');
            $table->json('variant_data'); // the actual personalized content
            $table->json('targeting_criteria'); // who should see this variant
            $table->string('optimization_objective'); // engagement, conversion, clicks
            $table->decimal('performance_score', 5, 2)->default(0);
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('conversions')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_winning_variant')->default(false);
            $table->timestamps();
            
            $table->index(['content_id', 'content_type', 'is_active']);
            $table->index('performance_score');
            $table->index('is_winning_variant');
        });

        // Real-time personalization context
        Schema::create('real_time_personalization_context', function($table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->string('session_id');
            $table->json('current_context'); // current page, products viewed, etc.
            $table->json('session_behavior'); // actions taken in current session
            $table->json('real_time_signals'); // immediate behavioral indicators
            $table->json('predicted_intent'); // what customer likely wants to do
            $table->decimal('intent_confidence', 3, 2);
            $table->json('personalization_opportunities'); // areas for personalization
            $table->timestamp('context_timestamp');
            $table->integer('ttl_seconds')->default(1800); // time to live
            $table->timestamps();
            
            $table->index(['customer_id', 'session_id']);
            $table->index(['context_timestamp', 'ttl_seconds']);
            $table->index('intent_confidence');
        });

        // Customer journey stages
        Schema::create('customer_journey_stages', function($table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->string('current_stage'); // awareness, consideration, purchase, retention, advocacy
            $table->string('previous_stage')->nullable();
            $table->json('stage_indicators'); // signals that indicate stage
            $table->decimal('stage_confidence', 3, 2);
            $table->json('stage_progression_prediction'); // likely next stages
            $table->json('personalization_strategy'); // how to personalize for this stage
            $table->timestamp('stage_entered_at');
            $table->integer('days_in_stage');
            $table->boolean('is_current')->default(true);
            $table->timestamps();
            
            $table->index(['customer_id', 'is_current']);
            $table->index(['current_stage', 'stage_entered_at']);
        });

        // Machine learning model performance
        Schema::create('ml_model_performance', function($table) {
            $table->id();
            $table->string('model_name'); // recommendation_engine, intent_prediction, etc.
            $table->string('model_version');
            $table->string('model_type'); // collaborative_filtering, neural_network, etc.
            $table->json('model_parameters');
            $table->json('training_data_metrics');
            $table->json('performance_metrics'); // accuracy, precision, recall, etc.
            $table->decimal('overall_score', 5, 4);
            $table->boolean('is_production_model')->default(false);
            $table->timestamp('trained_at');
            $table->timestamp('deployed_at')->nullable();
            $table->json('feature_importance')->nullable();
            $table->timestamps();
            
            $table->index(['model_name', 'model_version']);
            $table->index('is_production_model');
            $table->index('overall_score');
        });

        // A/B test results for personalization
        Schema::create('personalization_ab_tests', function($table) {
            $table->id();
            $table->string('test_name');
            $table->string('test_type'); // content_variant, algorithm_comparison, etc.
            $table->json('test_configuration');
            $table->string('status'); // draft, running, completed, paused
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('sample_size_per_variant');
            $table->json('variants'); // test variants and their configurations
            $table->json('success_metrics'); // what constitutes success
            $table->json('test_results')->nullable();
            $table->string('winning_variant')->nullable();
            $table->decimal('statistical_significance', 3, 2)->nullable();
            $table->timestamps();
            
            $table->index(['test_type', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index('statistical_significance');
        });

        // Customer preference learning
        Schema::create('customer_preference_learning', function($table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->string('preference_category'); // product_type, brand, price_range, etc.
            $table->string('preference_value');
            $table->decimal('preference_strength', 3, 2); // 0.00 to 1.00
            $table->string('learning_source'); // explicit, implicit, inferred
            $table->json('supporting_evidence'); // behaviors that support this preference
            $table->integer('confidence_votes')->default(1);
            $table->timestamp('first_observed');
            $table->timestamp('last_reinforced');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['customer_id', 'preference_category', 'is_active']);
            $table->index('preference_strength');
            $table->index('last_reinforced');
        });

        // Omnichannel personalization sync
        Schema::create('omnichannel_personalization_sync', function($table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->string('channel'); // web, mobile, email, sms, in_store
            $table->json('channel_context'); // channel-specific data
            $table->json('personalization_data'); // what personalizations are active
            $table->json('cross_channel_insights'); // insights from other channels
            $table->timestamp('last_interaction');
            $table->boolean('sync_enabled')->default(true);
            $table->json('channel_preferences'); // how customer prefers to interact
            $table->timestamps();
            
            $table->index(['customer_id', 'channel']);
            $table->index('last_interaction');
            $table->index('sync_enabled');
        });

        // Personalization performance metrics
        Schema::create('personalization_performance_metrics', function($table) {
            $table->id();
            $table->date('metrics_date');
            $table->string('metric_scope'); // global, customer_segment, individual
            $table->string('scope_identifier')->nullable(); // segment ID or customer ID
            $table->decimal('engagement_lift', 5, 2)->default(0); // % improvement
            $table->decimal('conversion_lift', 5, 2)->default(0);
            $table->decimal('revenue_lift', 5, 2)->default(0);
            $table->decimal('click_through_rate', 5, 2)->default(0);
            $table->decimal('personalization_coverage', 5, 2)->default(0); // % of interactions personalized
            $table->integer('total_personalizations')->default(0);
            $table->integer('successful_personalizations')->default(0);
            $table->json('detailed_metrics'); // breakdown by type, channel, etc.
            $table->timestamps();
            
            $table->index(['metrics_date', 'metric_scope']);
            $table->index(['engagement_lift', 'conversion_lift']);
        });

        // Dynamic customer segments
        Schema::create('dynamic_customer_segments', function($table) {
            $table->id();
            $table->string('segment_name');
            $table->string('segment_type'); // behavioral, predictive, value_based
            $table->json('segment_criteria'); // rules that define the segment
            $table->json('ml_model_config')->nullable(); // if ML-generated segment
            $table->integer('customer_count');
            $table->json('segment_characteristics'); // avg values, patterns
            $table->json('personalization_strategy'); // how to personalize for this segment
            $table->decimal('segment_value_score', 8, 2); // business value of segment
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_calculated');
            $table->timestamps();
            
            $table->index(['segment_type', 'is_active']);
            $table->index('segment_value_score');
            $table->index('last_calculated');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_customer_segments');
        Schema::dropIfExists('personalization_performance_metrics');
        Schema::dropIfExists('omnichannel_personalization_sync');
        Schema::dropIfExists('customer_preference_learning');
        Schema::dropIfExists('personalization_ab_tests');
        Schema::dropIfExists('ml_model_performance');
        Schema::dropIfExists('customer_journey_stages');
        Schema::dropIfExists('real_time_personalization_context');
        Schema::dropIfExists('content_personalization_variants');
        Schema::dropIfExists('personalized_recommendations');
        Schema::dropIfExists('behavioral_events');
        Schema::dropIfExists('customer_personalization_profiles');
    }
}