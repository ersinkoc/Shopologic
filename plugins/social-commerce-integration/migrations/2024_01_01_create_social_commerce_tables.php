<?php


declare(strict_types=1);

namespace Shopologic\Plugins\SocialCommerceIntegration;
declare(strict_types=1);
use Shopologic\Database\Migration;
use Shopologic\Database\Schema;

class CreateSocialCommerceTables extends Migration
{
    public function up(): void
    {
        // Social platform connections
        Schema::create('social_platforms', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('platform_name'); // instagram, facebook, tiktok, youtube, pinterest
            $table->string('platform_type'); // organic, business, creator
            $table->string('account_id');
            $table->string('account_handle');
            $table->json('credentials');
            $table->json('permissions');
            $table->string('status'); // connected, disconnected, error, pending
            $table->json('platform_settings')->nullable();
            $table->timestamp('connected_at');
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
            
            $table->unique(['store_id', 'platform_name', 'account_id']);
            $table->index(['status', 'last_sync_at']);
        });

        // Shoppable content posts
        Schema::create('shoppable_posts', function($table) {
            $table->id();
            $table->foreignId('platform_id')->constrained('social_platforms');
            $table->string('platform_post_id');
            $table->string('content_type'); // post, story, reel, video, pin
            $table->text('caption')->nullable();
            $table->json('media_urls');
            $table->json('tagged_products');
            $table->string('status'); // draft, published, archived, failed
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->json('engagement_metrics')->nullable();
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('purchases')->default(0);
            $table->decimal('revenue_generated', 15, 2)->default(0);
            $table->timestamps();
            
            $table->unique(['platform_id', 'platform_post_id']);
            $table->index(['status', 'published_at']);
        });

        // Influencers
        Schema::create('influencers', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->json('social_handles'); // platform => handle mapping
            $table->string('category'); // fashion, beauty, lifestyle, tech, etc.
            $table->string('tier'); // nano, micro, macro, mega
            $table->integer('total_followers');
            $table->decimal('engagement_rate', 5, 2);
            $table->decimal('avg_likes', 10, 2);
            $table->decimal('avg_comments', 10, 2);
            $table->json('demographics')->nullable();
            $table->json('performance_metrics')->nullable();
            $table->string('status'); // prospective, active, inactive, blacklisted
            $table->timestamps();
            
            $table->index(['store_id', 'tier', 'status']);
            $table->index('total_followers');
            $table->index('engagement_rate');
        });

        // Influencer campaigns
        Schema::create('influencer_campaigns', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('influencer_id')->constrained('influencers');
            $table->string('campaign_name');
            $table->text('campaign_description')->nullable();
            $table->string('campaign_type'); // sponsored_post, product_seeding, affiliate, brand_ambassador
            $table->json('campaign_objectives');
            $table->json('deliverables'); // number of posts, stories, etc.
            $table->decimal('budget', 15, 2);
            $table->json('commission_structure');
            $table->string('status'); // draft, active, completed, cancelled
            $table->date('start_date');
            $table->date('end_date');
            $table->json('tracking_parameters');
            $table->json('performance_goals')->nullable();
            $table->json('actual_performance')->nullable();
            $table->timestamps();
            
            $table->index(['store_id', 'status']);
            $table->index(['influencer_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });

        // User-generated content
        Schema::create('ugc_content', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('platform'); // instagram, tiktok, youtube, etc.
            $table->string('platform_content_id');
            $table->string('content_type'); // image, video, story, reel
            $table->string('author_handle');
            $table->integer('author_followers')->nullable();
            $table->text('content_text')->nullable();
            $table->json('media_urls');
            $table->json('hashtags')->nullable();
            $table->json('mentioned_products')->nullable();
            $table->decimal('quality_score', 3, 2);
            $table->string('status'); // discovered, reviewing, approved, rejected, used
            $table->boolean('rights_cleared')->default(false);
            $table->timestamp('discovered_at');
            $table->timestamp('published_originally_at');
            $table->json('engagement_metrics')->nullable();
            $table->timestamps();
            
            $table->unique(['platform', 'platform_content_id']);
            $table->index(['store_id', 'status', 'quality_score']);
            $table->index('discovered_at');
        });

        // Social proof content
        Schema::create('social_proof_content', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('proof_type'); // recent_purchase, review, user_count, trending
            $table->string('display_format'); // popup, banner, widget, inline
            $table->json('content_data');
            $table->string('trigger_event'); // page_load, scroll, time_based, exit_intent
            $table->json('targeting_rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('display_count')->default(0);
            $table->integer('click_count')->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->index(['store_id', 'proof_type', 'is_active']);
            $table->index('expires_at');
        });

        // Social media analytics
        Schema::create('social_analytics', function($table) {
            $table->id();
            $table->foreignId('platform_id')->constrained('social_platforms');
            $table->date('analytics_date');
            $table->integer('followers_count');
            $table->integer('posts_published');
            $table->integer('total_likes');
            $table->integer('total_comments');
            $table->integer('total_shares');
            $table->integer('total_saves');
            $table->integer('profile_visits');
            $table->integer('website_clicks');
            $table->decimal('engagement_rate', 5, 2);
            $table->decimal('reach', 12, 0);
            $table->decimal('impressions', 12, 0);
            $table->json('demographic_data')->nullable();
            $table->json('top_content')->nullable();
            $table->timestamps();
            
            $table->unique(['platform_id', 'analytics_date']);
            $table->index('analytics_date');
        });

        // Social attribution tracking
        Schema::create('social_attribution', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('user_session_id');
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->string('source_platform');
            $table->string('source_type'); // organic, influencer, ugc, paid
            $table->string('source_content_id')->nullable();
            $table->foreignId('influencer_id')->nullable()->constrained('influencers');
            $table->foreignId('campaign_id')->nullable()->constrained('influencer_campaigns');
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->timestamp('first_click_at');
            $table->timestamp('last_click_at');
            $table->integer('total_clicks')->default(1);
            $table->json('click_path')->nullable();
            $table->boolean('converted')->default(false);
            $table->foreignId('order_id')->nullable()->constrained();
            $table->decimal('order_value', 15, 2)->nullable();
            $table->timestamps();
            
            $table->index(['store_id', 'source_platform', 'converted']);
            $table->index(['user_session_id', 'first_click_at']);
            $table->index(['campaign_id', 'converted']);
        });

        // Social engagement tracking
        Schema::create('social_engagements', function($table) {
            $table->id();
            $table->foreignId('post_id')->nullable()->constrained('shoppable_posts');
            $table->foreignId('ugc_id')->nullable()->constrained('ugc_content');
            $table->string('engagement_type'); // like, comment, share, save, click, view
            $table->string('platform');
            $table->string('user_id')->nullable(); // platform user id
            $table->text('engagement_data')->nullable(); // comment text, etc.
            $table->timestamp('occurred_at');
            $table->timestamps();
            
            $table->index(['post_id', 'engagement_type']);
            $table->index(['ugc_id', 'engagement_type']);
            $table->index('occurred_at');
        });

        // Hashtag performance tracking
        Schema::create('hashtag_performance', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('hashtag');
            $table->string('platform');
            $table->integer('usage_count')->default(0);
            $table->decimal('avg_engagement_rate', 5, 2)->default(0);
            $table->integer('total_reach')->default(0);
            $table->integer('total_impressions')->default(0);
            $table->string('category')->nullable(); // brand, product, campaign, trending
            $table->json('performance_metrics')->nullable();
            $table->date('last_used_date')->nullable();
            $table->timestamps();
            
            $table->unique(['store_id', 'hashtag', 'platform']);
            $table->index('avg_engagement_rate');
        });

        // Social commerce orders
        Schema::create('social_commerce_orders', function($table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->string('source_platform');
            $table->string('source_type'); // organic, influencer, ugc, advertisement
            $table->foreignId('influencer_id')->nullable()->constrained('influencers');
            $table->foreignId('campaign_id')->nullable()->constrained('influencer_campaigns');
            $table->string('source_content_id')->nullable();
            $table->decimal('influencer_commission', 10, 2)->default(0);
            $table->string('attribution_model'); // last_click, first_click, linear, time_decay
            $table->json('attribution_touchpoints')->nullable();
            $table->timestamps();
            
            $table->index(['order_id', 'source_platform']);
            $table->index(['influencer_id', 'campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_commerce_orders');
        Schema::dropIfExists('hashtag_performance');
        Schema::dropIfExists('social_engagements');
        Schema::dropIfExists('social_attribution');
        Schema::dropIfExists('social_analytics');
        Schema::dropIfExists('social_proof_content');
        Schema::dropIfExists('ugc_content');
        Schema::dropIfExists('influencer_campaigns');
        Schema::dropIfExists('influencers');
        Schema::dropIfExists('shoppable_posts');
        Schema::dropIfExists('social_platforms');
    }
}