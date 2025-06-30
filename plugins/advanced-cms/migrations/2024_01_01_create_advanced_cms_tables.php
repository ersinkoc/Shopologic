<?php


declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedCms;
declare(strict_types=1);
use Shopologic\Database\Migration;
use Shopologic\Database\Schema;

class CreateAdvancedCmsTables extends Migration
{
    public function up(): void
    {
        // Content management
        Schema::create('cms_content', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('title');
            $table->text('slug');
            $table->longText('body');
            $table->string('type'); // page, blog, product_description, landing_page
            $table->string('status'); // draft, published, archived
            $table->foreignId('author_id')->constrained('users');
            $table->string('language', 5)->default('en');
            $table->json('meta_data')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('seo_keywords')->nullable();
            $table->decimal('seo_score', 5, 2)->default(0);
            $table->decimal('quality_score', 5, 2)->default(0);
            $table->decimal('engagement_score', 5, 2)->default(0);
            $table->decimal('overall_score', 5, 2)->default(0);
            $table->boolean('ai_generated')->default(false);
            $table->text('generation_prompt')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            
            $table->index(['store_id', 'status', 'type']);
            $table->index(['slug', 'language']);
            $table->index('published_at');
            $table->fulltext(['title', 'body']);
        });

        // Content revisions
        Schema::create('cms_content_revisions', function($table) {
            $table->id();
            $table->foreignId('content_id')->constrained('cms_content');
            $table->foreignId('author_id')->constrained('users');
            $table->json('changes');
            $table->text('change_summary')->nullable();
            $table->string('revision_type'); // auto, manual, ai_enhanced
            $table->timestamps();
            
            $table->index(['content_id', 'created_at']);
        });

        // AI writing sessions
        Schema::create('ai_writing_sessions', function($table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('session_type'); // generate, enhance, rewrite
            $table->text('prompt');
            $table->json('parameters');
            $table->longText('generated_content');
            $table->decimal('quality_score', 3, 2)->nullable();
            $table->boolean('accepted')->default(false);
            $table->json('feedback')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'session_type']);
            $table->index('created_at');
        });

        // SEO analysis results
        Schema::create('seo_analysis', function($table) {
            $table->id();
            $table->foreignId('content_id')->constrained('cms_content');
            $table->decimal('overall_score', 5, 2);
            $table->json('title_analysis');
            $table->json('meta_description_analysis');
            $table->json('heading_analysis');
            $table->json('keyword_analysis');
            $table->json('readability_analysis');
            $table->json('link_analysis');
            $table->json('image_analysis');
            $table->json('recommendations');
            $table->timestamp('analyzed_at');
            $table->timestamps();
            
            $table->index(['content_id', 'analyzed_at']);
        });

        // Content personalization rules
        Schema::create('content_personalization_rules', function($table) {
            $table->id();
            $table->foreignId('content_id')->constrained('cms_content');
            $table->string('rule_name');
            $table->json('conditions'); // user segments, behavior, location, etc
            $table->json('modifications'); // content changes to apply
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->decimal('effectiveness_score', 3, 2)->default(0);
            $table->timestamps();
            
            $table->index(['content_id', 'is_active', 'priority']);
        });

        // Content translations
        Schema::create('content_translations', function($table) {
            $table->id();
            $table->foreignId('original_content_id')->constrained('cms_content');
            $table->foreignId('translated_content_id')->constrained('cms_content');
            $table->string('source_language', 5);
            $table->string('target_language', 5);
            $table->string('translation_method'); // human, ai, hybrid
            $table->decimal('quality_score', 3, 2)->nullable();
            $table->json('translation_metadata')->nullable();
            $table->timestamps();
            
            $table->unique(['original_content_id', 'target_language']);
            $table->index(['source_language', 'target_language']);
        });

        // Content analytics
        Schema::create('content_analytics', function($table) {
            $table->id();
            $table->foreignId('content_id')->constrained('cms_content');
            $table->date('analytics_date');
            $table->integer('page_views')->default(0);
            $table->integer('unique_visitors')->default(0);
            $table->decimal('avg_time_on_page', 8, 2)->default(0);
            $table->decimal('bounce_rate', 5, 2)->default(0);
            $table->integer('social_shares')->default(0);
            $table->integer('comments')->default(0);
            $table->integer('conversions')->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->json('traffic_sources')->nullable();
            $table->json('engagement_metrics')->nullable();
            $table->timestamps();
            
            $table->unique(['content_id', 'analytics_date']);
            $table->index('analytics_date');
        });

        // Dynamic content blocks
        Schema::create('dynamic_content_blocks', function($table) {
            $table->id();
            $table->string('block_name');
            $table->string('block_type'); // product_recommendations, trending_content, personalized_offers
            $table->json('configuration');
            $table->json('targeting_rules')->nullable();
            $table->longText('template');
            $table->boolean('is_active')->default(true);
            $table->decimal('performance_score', 3, 2)->default(0);
            $table->timestamps();
            
            $table->index(['block_type', 'is_active']);
        });

        // Content A/B tests
        Schema::create('content_ab_tests', function($table) {
            $table->id();
            $table->foreignId('content_id')->constrained('cms_content');
            $table->string('test_name');
            $table->json('variants'); // different versions being tested
            $table->string('metric'); // conversion_rate, engagement, time_on_page
            $table->integer('traffic_split'); // percentage of traffic for test
            $table->string('status'); // running, paused, completed
            $table->json('results')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            
            $table->index(['content_id', 'status']);
            $table->index('started_at');
        });

        // Content templates
        Schema::create('content_templates', function($table) {
            $table->id();
            $table->string('template_name');
            $table->string('template_type'); // blog_post, landing_page, product_page
            $table->longText('template_content');
            $table->json('template_fields'); // configurable fields
            $table->json('seo_settings')->nullable();
            $table->boolean('is_public')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->integer('usage_count')->default(0);
            $table->timestamps();
            
            $table->index(['template_type', 'is_public']);
        });

        // Content suggestions from AI
        Schema::create('content_suggestions', function($table) {
            $table->id();
            $table->foreignId('content_id')->constrained('cms_content');
            $table->string('suggestion_type'); // seo_improvement, readability, engagement
            $table->text('suggestion');
            $table->json('suggestion_data');
            $table->decimal('confidence_score', 3, 2);
            $table->string('status'); // pending, applied, dismissed
            $table->foreignId('applied_by')->nullable()->constrained('users');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            
            $table->index(['content_id', 'status']);
            $table->index(['suggestion_type', 'confidence_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_suggestions');
        Schema::dropIfExists('content_templates');
        Schema::dropIfExists('content_ab_tests');
        Schema::dropIfExists('dynamic_content_blocks');
        Schema::dropIfExists('content_analytics');
        Schema::dropIfExists('content_translations');
        Schema::dropIfExists('content_personalization_rules');
        Schema::dropIfExists('seo_analysis');
        Schema::dropIfExists('ai_writing_sessions');
        Schema::dropIfExists('cms_content_revisions');
        Schema::dropIfExists('cms_content');
    }
}