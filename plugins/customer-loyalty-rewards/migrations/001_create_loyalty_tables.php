<?php


declare(strict_types=1);

namespace Shopologic\Plugins\CustomerLoyaltyRewards;
declare(strict_types=1);
use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema;
use Shopologic\Core\Database\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations
     */
    public function up(): void
    {
        // Create loyalty tiers table
        Schema::create('loyalty_tiers', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->decimal('minimum_spending', 12, 2)->default(0);
            $table->integer('minimum_orders')->default(0);
            $table->integer('minimum_points')->default(0);
            $table->decimal('points_multiplier', 4, 2)->default(1.00);
            $table->decimal('product_discount_percent', 5, 2)->default(0);
            $table->decimal('shipping_discount_percent', 5, 2)->default(0);
            $table->json('benefits')->nullable();
            $table->string('color', 7)->nullable(); // Hex color
            $table->string('icon', 50)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['is_active', 'sort_order']);
            $table->index('minimum_spending');
        });

        // Create loyalty members table
        Schema::create('loyalty_members', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('customer_id')->unique();
            $table->unsignedBigInteger('tier_id')->nullable();
            $table->string('member_number', 50)->unique();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->integer('points_balance')->default(0);
            $table->integer('lifetime_points_earned')->default(0);
            $table->integer('lifetime_points_redeemed')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->decimal('annual_spent', 12, 2)->default(0);
            $table->integer('total_orders')->default(0);
            $table->integer('total_referrals')->default(0);
            $table->date('tier_expiry_date')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->date('anniversary_date')->nullable();
            $table->json('preferences')->nullable();
            $table->timestamps();
            
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('tier_id')->references('id')->on('loyalty_tiers')->onDelete('set null');
            $table->index(['status', 'tier_id']);
            $table->index('points_balance');
            $table->index('total_spent');
            $table->index('last_activity_at');
        });

        // Create loyalty points transactions table
        Schema::create('loyalty_points_transactions', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('member_id');
            $table->enum('type', ['earned', 'redeemed', 'expired', 'adjusted']);
            $table->integer('points');
            $table->integer('balance_after');
            $table->string('reason', 100);
            $table->text('description')->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->decimal('order_value', 12, 2)->nullable();
            $table->date('expires_at')->nullable();
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('completed');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            
            $table->foreign('member_id')->references('id')->on('loyalty_members')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['member_id', 'type', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['expires_at', 'status']);
        });

        // Create loyalty rewards table
        Schema::create('loyalty_rewards', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['discount', 'free_shipping', 'product', 'cashback', 'custom']);
            $table->integer('points_cost');
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->enum('discount_type', ['fixed', 'percentage'])->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('minimum_order_value', 12, 2)->nullable();
            $table->integer('usage_limit_per_member')->nullable();
            $table->integer('total_usage_limit')->nullable();
            $table->integer('total_redeemed')->default(0);
            $table->json('eligible_tiers')->nullable(); // Array of tier IDs
            $table->json('eligible_categories')->nullable();
            $table->json('excluded_products')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->integer('expiry_days')->nullable(); // Days after redemption
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            $table->index(['is_active', 'is_featured', 'sort_order']);
            $table->index(['type', 'points_cost']);
            $table->index(['valid_from', 'valid_until']);
        });

        // Create loyalty reward redemptions table
        Schema::create('loyalty_reward_redemptions', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('reward_id');
            $table->string('redemption_code', 50)->unique();
            $table->integer('points_redeemed');
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->enum('status', ['pending', 'used', 'expired', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->foreign('member_id')->references('id')->on('loyalty_members')->onDelete('cascade');
            $table->foreign('reward_id')->references('id')->on('loyalty_rewards')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
            $table->index(['member_id', 'status']);
            $table->index(['redemption_code', 'status']);
            $table->index('expires_at');
        });

        // Create loyalty referrals table
        Schema::create('loyalty_referrals', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('referrer_member_id');
            $table->string('referral_code', 50)->unique();
            $table->string('referee_email', 255);
            $table->unsignedBigInteger('referee_member_id')->nullable();
            $table->enum('status', ['pending', 'clicked', 'registered', 'completed', 'expired'])->default('pending');
            $table->integer('clicks')->default(0);
            $table->timestamp('first_click_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('referee_order_value', 12, 2)->nullable();
            $table->integer('referrer_points_awarded')->default(0);
            $table->string('referee_reward_type', 50)->nullable();
            $table->decimal('referee_reward_value', 12, 2)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamps();
            
            $table->foreign('referrer_member_id')->references('id')->on('loyalty_members')->onDelete('cascade');
            $table->foreign('referee_member_id')->references('id')->on('loyalty_members')->onDelete('set null');
            $table->index(['referrer_member_id', 'status']);
            $table->index(['referral_code', 'status']);
            $table->index('referee_email');
            $table->index('expires_at');
        });

        // Create loyalty campaigns table
        Schema::create('loyalty_campaigns', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['points_multiplier', 'bonus_points', 'tier_upgrade', 'custom_reward']);
            $table->json('rules'); // Campaign rules and conditions
            $table->json('rewards'); // Rewards configuration
            $table->json('target_audience'); // Member segments/criteria
            $table->enum('status', ['draft', 'scheduled', 'active', 'paused', 'completed', 'cancelled'])->default('draft');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_count')->default(0);
            $table->integer('member_limit')->nullable();
            $table->integer('member_count')->default(0);
            $table->decimal('budget_limit', 12, 2)->nullable();
            $table->decimal('budget_used', 12, 2)->default(0);
            $table->integer('priority')->default(0);
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['status', 'start_date', 'end_date']);
            $table->index(['type', 'priority']);
        });

        // Create loyalty challenges table
        Schema::create('loyalty_challenges', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['purchase_amount', 'purchase_count', 'product_category', 'referrals', 'reviews', 'social_share']);
            $table->json('criteria'); // Challenge completion criteria
            $table->json('rewards'); // Rewards for completion
            $table->enum('difficulty', ['easy', 'medium', 'hard', 'epic'])->default('medium');
            $table->enum('frequency', ['one_time', 'daily', 'weekly', 'monthly'])->default('one_time');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('max_participants')->nullable();
            $table->integer('current_participants')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('icon', 50)->nullable();
            $table->string('badge_image_url', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['is_active', 'type', 'frequency']);
            $table->index(['start_date', 'end_date']);
        });

        // Create loyalty badges table
        Schema::create('loyalty_badges', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->json('criteria'); // Earning criteria
            $table->enum('type', ['achievement', 'milestone', 'special', 'seasonal']);
            $table->enum('rarity', ['common', 'uncommon', 'rare', 'epic', 'legendary'])->default('common');
            $table->string('icon', 50);
            $table->string('color', 7)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->integer('points_reward')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['is_active', 'type', 'rarity']);
        });

        // Create loyalty member badges table (many-to-many)
        Schema::create('loyalty_member_badges', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('badge_id');
            $table->timestamp('earned_at');
            $table->json('metadata')->nullable(); // Additional context about earning
            $table->timestamps();
            
            $table->foreign('member_id')->references('id')->on('loyalty_members')->onDelete('cascade');
            $table->foreign('badge_id')->references('id')->on('loyalty_badges')->onDelete('cascade');
            $table->unique(['member_id', 'badge_id']);
            $table->index('earned_at');
        });

        // Create loyalty activities table (activity feed)
        Schema::create('loyalty_activities', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('member_id');
            $table->enum('type', ['points_earned', 'points_redeemed', 'tier_upgraded', 'badge_earned', 'referral_made', 'challenge_completed', 'reward_redeemed']);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->integer('points')->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_public')->default(false); // For leaderboards/social features
            $table->timestamps();
            
            $table->foreign('member_id')->references('id')->on('loyalty_members')->onDelete('cascade');
            $table->index(['member_id', 'type', 'created_at']);
            $table->index(['is_public', 'created_at']);
        });

        // Create loyalty notifications table
        Schema::create('loyalty_notifications', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('member_id');
            $table->enum('type', ['welcome', 'points_earned', 'tier_upgrade', 'reward_available', 'points_expiring', 'birthday', 'referral_success', 'challenge_completed']);
            $table->string('title', 255);
            $table->text('message');
            $table->enum('channel', ['email', 'sms', 'push', 'in_app'])->default('email');
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed'])->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->foreign('member_id')->references('id')->on('loyalty_members')->onDelete('cascade');
            $table->index(['member_id', 'type', 'status']);
            $table->index(['status', 'scheduled_at']);
        });

        // Create loyalty settings table
        Schema::create('loyalty_settings', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('key', 255)->unique();
            $table->text('value');
            $table->enum('type', ['string', 'integer', 'float', 'boolean', 'json'])->default('string');
            $table->text('description')->nullable();
            $table->string('group', 100)->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
            
            $table->index(['group', 'key']);
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_settings');
        Schema::dropIfExists('loyalty_notifications');
        Schema::dropIfExists('loyalty_activities');
        Schema::dropIfExists('loyalty_member_badges');
        Schema::dropIfExists('loyalty_badges');
        Schema::dropIfExists('loyalty_challenges');
        Schema::dropIfExists('loyalty_campaigns');
        Schema::dropIfExists('loyalty_referrals');
        Schema::dropIfExists('loyalty_reward_redemptions');
        Schema::dropIfExists('loyalty_rewards');
        Schema::dropIfExists('loyalty_points_transactions');
        Schema::dropIfExists('loyalty_members');
        Schema::dropIfExists('loyalty_tiers');
    }
};