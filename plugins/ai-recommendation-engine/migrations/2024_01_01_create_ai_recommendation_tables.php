<?php

use Shopologic\Database\Migration;
use Shopologic\Database\Schema;

class CreateAiRecommendationTables extends Migration
{
    public function up(): void
    {
        // Customer interactions tracking
        Schema::create('ai_customer_interactions', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->string('interaction_type'); // view, click, add_to_cart, purchase, like, share
            $table->json('metadata')->nullable();
            $table->string('session_id')->nullable();
            $table->timestamp('interaction_at');
            $table->timestamps();
            
            $table->index(['customer_id', 'interaction_at']);
            $table->index(['product_id', 'interaction_type']);
            $table->index(['store_id', 'interaction_at']);
        });

        // Product similarity matrix
        Schema::create('ai_product_similarities', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('product_a_id')->constrained('products');
            $table->foreignId('product_b_id')->constrained('products');
            $table->decimal('similarity_score', 5, 4); // 0.0000 to 1.0000
            $table->string('algorithm_type'); // collaborative, content_based, hybrid
            $table->timestamp('calculated_at');
            $table->timestamps();
            
            $table->unique(['product_a_id', 'product_b_id', 'algorithm_type']);
            $table->index(['product_a_id', 'similarity_score']);
        });

        // Customer preference profiles
        Schema::create('ai_customer_profiles', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->json('category_preferences')->nullable(); // weighted category preferences
            $table->json('brand_preferences')->nullable();
            $table->json('price_sensitivity')->nullable();
            $table->json('feature_preferences')->nullable(); // color, size, style preferences
            $table->decimal('exploration_factor', 3, 2)->default(0.2); // 0.0 = only similar, 1.0 = highly diverse
            $table->timestamp('last_updated');
            $table->timestamps();
            
            $table->unique(['store_id', 'customer_id']);
        });

        // ML model performance tracking
        Schema::create('ai_model_performance', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('model_type'); // collaborative, content_based, hybrid, neural
            $table->string('model_version');
            $table->decimal('accuracy_score', 5, 4)->nullable();
            $table->decimal('precision_score', 5, 4)->nullable();
            $table->decimal('recall_score', 5, 4)->nullable();
            $table->decimal('f1_score', 5, 4)->nullable();
            $table->integer('training_samples');
            $table->integer('training_duration_seconds');
            $table->timestamp('trained_at');
            $table->boolean('is_active')->default(false);
            $table->json('hyperparameters')->nullable();
            $table->timestamps();
            
            $table->index(['store_id', 'is_active']);
        });

        // Recommendation feedback
        Schema::create('ai_recommendation_feedback', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('recommendation_id'); // UUID for tracking
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('recommended_product_id')->constrained('products');
            $table->string('feedback_type'); // helpful, not_helpful, irrelevant, purchased
            $table->integer('rating')->nullable(); // 1-5 scale
            $table->text('comment')->nullable();
            $table->json('context')->nullable(); // page, algorithm used, etc.
            $table->timestamps();
            
            $table->index(['recommendation_id']);
            $table->index(['customer_id', 'feedback_type']);
        });

        // Product feature vectors for content-based filtering
        Schema::create('ai_product_features', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->json('feature_vector'); // numerical features for ML
            $table->json('categorical_features'); // category, brand, tags
            $table->json('textual_features'); // processed description, title embeddings
            $table->decimal('popularity_score', 5, 4)->default(0.0);
            $table->decimal('quality_score', 5, 4)->default(0.0);
            $table->timestamp('extracted_at');
            $table->timestamps();
            
            $table->unique(['store_id', 'product_id']);
        });

        // Trending products cache
        Schema::create('ai_trending_products', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('category_id')->nullable()->constrained();
            $table->decimal('trend_score', 8, 4); // trending score
            $table->integer('view_count_24h');
            $table->integer('purchase_count_24h');
            $table->decimal('velocity_score', 5, 4); // rate of change
            $table->string('time_period'); // 1h, 24h, 7d
            $table->timestamp('calculated_at');
            $table->timestamps();
            
            $table->index(['store_id', 'trend_score', 'time_period']);
            $table->index(['category_id', 'trend_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_trending_products');
        Schema::dropIfExists('ai_product_features');
        Schema::dropIfExists('ai_recommendation_feedback');
        Schema::dropIfExists('ai_model_performance');
        Schema::dropIfExists('ai_customer_profiles');
        Schema::dropIfExists('ai_product_similarities');
        Schema::dropIfExists('ai_customer_interactions');
    }
}