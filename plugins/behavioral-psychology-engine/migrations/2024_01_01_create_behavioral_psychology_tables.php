<?php

use Shopologic\Database\Migration;
use Shopologic\Database\Schema;

class CreateBehavioralPsychologyTables extends Migration
{
    public function up(): void
    {
        // Psychology triggers
        Schema::create('psychology_triggers', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('name');
            $table->string('type'); // urgency, scarcity, social_proof, authority, reciprocity
            $table->string('trigger_event'); // product_view, cart_add, checkout_start, etc
            $table->json('conditions')->nullable();
            $table->json('content'); // messages, styles, positions
            $table->boolean('is_active')->default(true);
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->decimal('effectiveness_score', 3, 2)->default(0.5);
            $table->integer('conversions')->default(0);
            $table->integer('impressions')->default(0);
            $table->timestamps();
            
            $table->index(['store_id', 'type', 'is_active']);
            $table->index(['trigger_event', 'is_active']);
        });

        // Psychology campaigns
        Schema::create('psychology_campaigns', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('name');
            $table->string('campaign_type'); // loss_aversion, fomo, social_proof, etc
            $table->json('trigger_ids');
            $table->json('target_audience')->nullable();
            $table->json('goals');
            $table->string('status'); // draft, active, paused, completed
            $table->timestamp('start_date');
            $table->timestamp('end_date')->nullable();
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->decimal('revenue_impact', 15, 2)->default(0);
            $table->timestamps();
            
            $table->index(['store_id', 'status']);
            $table->index(['campaign_type', 'status']);
        });

        // User psychology profiles
        Schema::create('user_psychology_profiles', function($table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('store_id')->constrained();
            $table->json('personality_traits')->nullable();
            $table->json('trigger_responses'); // how user responds to different triggers
            $table->json('preferred_biases'); // which biases work best
            $table->string('buyer_type'); // impulse, research, bargain_hunter, etc
            $table->decimal('price_sensitivity', 3, 2);
            $table->decimal('urgency_responsiveness', 3, 2);
            $table->decimal('social_proof_influence', 3, 2);
            $table->integer('total_conversions')->default(0);
            $table->timestamps();
            
            $table->unique(['user_id', 'store_id']);
            $table->index('buyer_type');
        });

        // Trigger impressions and conversions
        Schema::create('trigger_events', function($table) {
            $table->id();
            $table->foreignId('trigger_id')->constrained('psychology_triggers');
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('session_id');
            $table->string('event_type'); // impression, interaction, conversion
            $table->foreignId('product_id')->nullable()->constrained();
            $table->decimal('conversion_value', 10, 2)->nullable();
            $table->json('context_data')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            
            $table->index(['trigger_id', 'event_type', 'occurred_at']);
            $table->index(['user_id', 'event_type']);
            $table->index('session_id');
        });

        // A/B test variants
        Schema::create('psychology_test_variants', function($table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('psychology_campaigns');
            $table->string('variant_name');
            $table->json('trigger_configuration');
            $table->integer('traffic_percentage');
            $table->integer('impressions')->default(0);
            $table->integer('conversions')->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->decimal('confidence_level', 3, 2)->default(0);
            $table->boolean('is_winner')->default(false);
            $table->timestamps();
            
            $table->index(['campaign_id', 'is_winner']);
        });

        // Social proof data
        Schema::create('social_proof_data', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->integer('recent_purchases_24h')->default(0);
            $table->integer('recent_purchases_7d')->default(0);
            $table->integer('current_viewers')->default(0);
            $table->integer('wishlist_adds_24h')->default(0);
            $table->json('recent_buyers')->nullable(); // anonymized recent buyer info
            $table->json('popularity_metrics')->nullable();
            $table->timestamp('last_updated');
            $table->timestamps();
            
            $table->unique(['store_id', 'product_id']);
            $table->index('recent_purchases_24h');
        });

        // Behavioral events
        Schema::create('behavioral_events', function($table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('session_id');
            $table->string('event_type'); // page_view, scroll_depth, time_on_page, etc
            $table->string('page_type'); // product, category, checkout, etc
            $table->json('event_data');
            $table->json('applied_triggers')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            
            $table->index(['user_id', 'event_type', 'occurred_at']);
            $table->index(['session_id', 'occurred_at']);
        });

        // Cognitive bias applications
        Schema::create('cognitive_bias_applications', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('bias_type'); // anchoring, decoy, bandwagon, authority, etc
            $table->string('application_context'); // pricing, recommendations, checkout, etc
            $table->json('configuration');
            $table->boolean('is_active')->default(true);
            $table->decimal('effectiveness_score', 3, 2)->default(0.5);
            $table->integer('applications')->default(0);
            $table->integer('successful_influences')->default(0);
            $table->timestamps();
            
            $table->index(['store_id', 'bias_type', 'is_active']);
        });

        // Campaign results
        Schema::create('campaign_results', function($table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('psychology_campaigns');
            $table->date('result_date');
            $table->integer('impressions')->default(0);
            $table->integer('unique_users')->default(0);
            $table->integer('conversions')->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->decimal('revenue', 15, 2)->default(0);
            $table->decimal('average_order_value', 10, 2)->default(0);
            $table->json('trigger_performance')->nullable();
            $table->json('segment_performance')->nullable();
            $table->timestamps();
            
            $table->unique(['campaign_id', 'result_date']);
            $table->index('result_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_results');
        Schema::dropIfExists('cognitive_bias_applications');
        Schema::dropIfExists('behavioral_events');
        Schema::dropIfExists('social_proof_data');
        Schema::dropIfExists('psychology_test_variants');
        Schema::dropIfExists('trigger_events');
        Schema::dropIfExists('user_psychology_profiles');
        Schema::dropIfExists('psychology_campaigns');
        Schema::dropIfExists('psychology_triggers');
    }
}