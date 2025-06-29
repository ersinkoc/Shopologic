<?php

use Shopologic\Database\Migration;
use Shopologic\Database\Schema;

class CreateSmartSearchTables extends Migration
{
    public function up(): void
    {
        // Search queries log
        Schema::create('search_queries', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('session_id');
            $table->text('query');
            $table->string('query_type'); // text, visual, voice, barcode
            $table->json('filters')->nullable();
            $table->string('sort_by')->nullable();
            $table->integer('results_count');
            $table->integer('page_number')->default(1);
            $table->json('clicked_results')->nullable();
            $table->boolean('has_conversion')->default(false);
            $table->decimal('search_score', 5, 2)->nullable();
            $table->timestamp('performed_at');
            $table->timestamps();
            
            $table->index(['store_id', 'performed_at']);
            $table->index(['user_id', 'performed_at']);
            $table->index('query');
            $table->fulltext('query');
        });

        // Search suggestions
        Schema::create('search_suggestions', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('suggestion');
            $table->string('type'); // query, product, category, brand
            $table->integer('frequency')->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->decimal('click_through_rate', 5, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['store_id', 'suggestion', 'type']);
            $table->index(['frequency', 'is_active']);
            $table->fulltext('suggestion');
        });

        // Visual search index
        Schema::create('visual_search_index', function($table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->string('image_hash');
            $table->json('feature_vector'); // AI-extracted features
            $table->json('color_histogram')->nullable();
            $table->json('texture_features')->nullable();
            $table->json('shape_features')->nullable();
            $table->json('object_detection')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('indexed_at');
            $table->timestamps();
            
            $table->index(['product_id', 'is_primary']);
            $table->index('image_hash');
        });

        // User search profiles
        Schema::create('user_search_profiles', function($table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('store_id')->constrained();
            $table->json('preferred_categories')->nullable();
            $table->json('preferred_brands')->nullable();
            $table->json('price_preferences')->nullable();
            $table->json('search_patterns')->nullable();
            $table->json('clicked_positions')->nullable();
            $table->string('typical_intent'); // browse, research, purchase
            $table->decimal('avg_query_length', 5, 2);
            $table->integer('total_searches')->default(0);
            $table->integer('refinement_rate')->default(0);
            $table->timestamps();
            
            $table->unique(['user_id', 'store_id']);
        });

        // Search synonyms
        Schema::create('search_synonyms', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('term');
            $table->json('synonyms');
            $table->string('type'); // manual, learned, domain
            $table->decimal('confidence', 3, 2)->default(1.0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['store_id', 'term', 'is_active']);
        });

        // Search facets configuration
        Schema::create('search_facets', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('facet_name');
            $table->string('facet_type'); // attribute, category, price, brand
            $table->string('display_name');
            $table->integer('display_order');
            $table->json('configuration')->nullable();
            $table->boolean('is_collapsible')->default(false);
            $table->boolean('is_multi_select')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['store_id', 'display_order', 'is_active']);
        });

        // Zero results queries
        Schema::create('zero_results_queries', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->text('query');
            $table->json('filters')->nullable();
            $table->integer('frequency')->default(1);
            $table->json('suggested_alternatives')->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('last_occurred');
            $table->timestamps();
            
            $table->index(['store_id', 'frequency', 'is_resolved']);
            $table->fulltext('query');
        });

        // Search relevance training
        Schema::create('search_relevance_training', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->text('query');
            $table->foreignId('product_id')->constrained();
            $table->decimal('relevance_score', 5, 4);
            $table->string('signal_type'); // click, cart_add, purchase, dwell_time
            $table->json('context')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            
            $table->index(['store_id', 'query', 'product_id']);
            $table->index(['signal_type', 'occurred_at']);
        });

        // Search performance metrics
        Schema::create('search_performance_metrics', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->date('metric_date');
            $table->integer('total_searches');
            $table->integer('unique_searches');
            $table->decimal('avg_results_count', 8, 2);
            $table->decimal('zero_results_rate', 5, 2);
            $table->decimal('avg_click_position', 5, 2);
            $table->decimal('search_exit_rate', 5, 2);
            $table->decimal('search_refinement_rate', 5, 2);
            $table->decimal('search_conversion_rate', 5, 2);
            $table->json('top_queries')->nullable();
            $table->json('top_no_results_queries')->nullable();
            $table->timestamps();
            
            $table->unique(['store_id', 'metric_date']);
            $table->index('metric_date');
        });

        // NLP entities
        Schema::create('search_nlp_entities', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('entity_type'); // brand, color, size, material, etc
            $table->string('entity_value');
            $table->json('variations')->nullable();
            $table->json('related_products')->nullable();
            $table->integer('frequency')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
            
            $table->index(['store_id', 'entity_type', 'entity_value']);
            $table->index('frequency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_nlp_entities');
        Schema::dropIfExists('search_performance_metrics');
        Schema::dropIfExists('search_relevance_training');
        Schema::dropIfExists('zero_results_queries');
        Schema::dropIfExists('search_facets');
        Schema::dropIfExists('search_synonyms');
        Schema::dropIfExists('user_search_profiles');
        Schema::dropIfExists('visual_search_index');
        Schema::dropIfExists('search_suggestions');
        Schema::dropIfExists('search_queries');
    }
}