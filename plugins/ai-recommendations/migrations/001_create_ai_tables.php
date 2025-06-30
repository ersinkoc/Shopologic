<?php


declare(strict_types=1);

namespace Shopologic\Plugins\AiRecommendations;
declare(strict_types=1);
/**
 * Create AI Recommendations Database Tables
 * 
 * This migration creates all necessary tables for the AI recommendation system
 */

use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema;

return new class extends Migration
{
    /**
     * Run the migration
     */
    public function up(): void
    {
        // User behavior tracking table
        Schema::create('ai_user_behavior', function($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('session_id', 255)->nullable();
            $table->unsignedBigInteger('product_id');
            $table->enum('action', ['view', 'add_to_cart', 'purchase', 'wishlist', 'share', 'search']);
            $table->decimal('duration', 8, 2)->nullable(); // Time spent on product
            $table->json('context')->nullable(); // Additional context data
            $table->timestamp('created_at');
            
            $table->index(['user_id', 'created_at']);
            $table->index(['product_id', 'action']);
            $table->index(['session_id', 'created_at']);
            $table->index('created_at');
        });

        // Product similarity scores
        Schema::create('ai_product_similarities', function($table) {
            $table->id();
            $table->unsignedBigInteger('product_a_id');
            $table->unsignedBigInteger('product_b_id');
            $table->decimal('similarity_score', 5, 4); // 0.0000 to 1.0000
            $table->string('algorithm', 50); // collaborative, content_based, hybrid
            $table->json('features')->nullable(); // Features that contributed to similarity
            $table->timestamp('calculated_at');
            $table->timestamp('updated_at');
            
            $table->unique(['product_a_id', 'product_b_id', 'algorithm']);
            $table->index(['product_a_id', 'similarity_score']);
            $table->index(['similarity_score']);
            $table->index('calculated_at');
        });

        // User preferences and characteristics
        Schema::create('ai_user_preferences', function($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->json('category_preferences')->nullable(); // Category weights
            $table->json('brand_preferences')->nullable(); // Brand preferences
            $table->json('price_range')->nullable(); // Preferred price ranges
            $table->json('feature_preferences')->nullable(); // Product feature preferences
            $table->decimal('avg_order_value', 10, 2)->nullable();
            $table->integer('purchase_frequency')->nullable(); // Days between purchases
            $table->json('seasonal_patterns')->nullable(); // Seasonal buying patterns
            $table->timestamp('last_updated');
            $table->timestamp('created_at');
            
            $table->index('user_id');
            $table->index('last_updated');
        });

        // Recommendation history and results
        Schema::create('ai_recommendation_history', function($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('session_id', 255)->nullable();
            $table->unsignedBigInteger('source_product_id')->nullable(); // Product that triggered recommendation
            $table->json('recommended_products'); // Array of recommended product IDs
            $table->string('algorithm', 50);
            $table->string('context', 100); // product_page, cart, home, etc.
            $table->json('options')->nullable(); // Options used for recommendation
            $table->decimal('avg_confidence', 5, 4)->nullable();
            $table->timestamp('created_at');
            
            $table->index(['user_id', 'created_at']);
            $table->index(['source_product_id', 'created_at']);
            $table->index(['context', 'created_at']);
            $table->index('created_at');
        });

        // User feedback on recommendations
        Schema::create('ai_recommendation_feedback', function($table) {
            $table->id();
            $table->unsignedBigInteger('recommendation_history_id');
            $table->unsignedBigInteger('product_id'); // Which recommended product
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('session_id', 255)->nullable();
            $table->enum('action', ['clicked', 'purchased', 'added_to_cart', 'viewed', 'ignored', 'dismissed']);
            $table->json('context')->nullable(); // Additional context
            $table->timestamp('created_at');
            
            $table->index(['recommendation_history_id', 'action']);
            $table->index(['product_id', 'action']);
            $table->index(['user_id', 'action', 'created_at']);
            $table->index('created_at');
            
            $table->foreign('recommendation_history_id')->references('id')->on('ai_recommendation_history');
        });

        // Model versions and metadata
        Schema::create('ai_model_versions', function($table) {
            $table->id();
            $table->string('algorithm', 50);
            $table->string('version', 20);
            $table->json('parameters'); // Model hyperparameters
            $table->json('training_metrics')->nullable(); // Accuracy, precision, recall, etc.
            $table->integer('training_data_size')->nullable();
            $table->timestamp('training_started_at')->nullable();
            $table->timestamp('training_completed_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('created_at');
            
            $table->index(['algorithm', 'is_active']);
            $table->index('created_at');
        });

        // Training data snapshots
        Schema::create('ai_training_data', function($table) {
            $table->id();
            $table->unsignedBigInteger('model_version_id');
            $table->string('data_type', 50); // user_behavior, product_features, interactions
            $table->json('data_sample')->nullable(); // Sample of training data
            $table->integer('total_records');
            $table->json('statistics')->nullable(); // Data statistics
            $table->timestamp('created_at');
            
            $table->index(['model_version_id', 'data_type']);
            $table->foreign('model_version_id')->references('id')->on('ai_model_versions');
        });

        // Performance metrics and monitoring
        Schema::create('ai_performance_metrics', function($table) {
            $table->id();
            $table->string('metric_type', 50); // ctr, conversion_rate, precision, recall
            $table->string('algorithm', 50);
            $table->string('context', 50); // product_page, cart, home
            $table->decimal('value', 10, 6);
            $table->integer('sample_size');
            $table->date('date');
            $table->json('dimensions')->nullable(); // Additional grouping dimensions
            $table->timestamp('created_at');
            
            $table->unique(['metric_type', 'algorithm', 'context', 'date']);
            $table->index(['metric_type', 'date']);
            $table->index(['algorithm', 'date']);
            $table->index('date');
        });

        // A/B testing experiments
        Schema::create('ai_ab_experiments', function($table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft'); // draft, running, completed, paused
            $table->json('variants'); // Different algorithm/parameter combinations
            $table->integer('traffic_percentage'); // Percentage of users in experiment
            $table->json('success_metrics'); // What metrics to track
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('results')->nullable(); // Experiment results
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            
            $table->index(['status', 'start_date']);
            $table->index('created_at');
        });

        // Product feature vectors for content-based filtering
        Schema::create('ai_product_features', function($table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->unique();
            $table->json('category_vector')->nullable(); // Category embeddings
            $table->json('text_features')->nullable(); // Text-based features from description
            $table->json('numerical_features')->nullable(); // Price, ratings, etc.
            $table->json('tag_vector')->nullable(); // Tag-based features
            $table->decimal('popularity_score', 8, 6)->nullable();
            $table->decimal('quality_score', 5, 4)->nullable();
            $table->timestamp('calculated_at');
            $table->timestamp('updated_at');
            
            $table->index('product_id');
            $table->index('popularity_score');
            $table->index('calculated_at');
        });
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_product_features');
        Schema::dropIfExists('ai_ab_experiments');
        Schema::dropIfExists('ai_performance_metrics');
        Schema::dropIfExists('ai_training_data');
        Schema::dropIfExists('ai_model_versions');
        Schema::dropIfExists('ai_recommendation_feedback');
        Schema::dropIfExists('ai_recommendation_history');
        Schema::dropIfExists('ai_user_preferences');
        Schema::dropIfExists('ai_product_similarities');
        Schema::dropIfExists('ai_user_behavior');
    }
};