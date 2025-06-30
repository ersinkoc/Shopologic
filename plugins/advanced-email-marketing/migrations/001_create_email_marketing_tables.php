<?php


declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing;
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
        // Create email subscribers table
        Schema::create('email_subscribers', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('email', 255)->unique();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->enum('status', ['subscribed', 'unsubscribed', 'bounced', 'complained', 'pending'])->default('pending');
            $table->string('source', 100)->default('manual'); // manual, api, widget, checkout, etc.
            $table->json('tags')->nullable(); // Subscriber tags
            $table->json('custom_fields')->nullable(); // Custom subscriber data
            $table->decimal('engagement_score', 5, 2)->default(0); // 0-100 engagement score
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('timezone', 50)->nullable();
            $table->string('language', 10)->default('en');
            $table->boolean('double_opt_in')->default(true);
            $table->string('unsubscribe_reason', 255)->nullable();
            $table->timestamps();
            
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->index(['status', 'engagement_score']);
            $table->index(['source', 'created_at']);
            $table->index('last_activity_at');
        });

        // Create email campaigns table
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('name', 255);
            $table->string('subject', 255);
            $table->text('preview_text')->nullable();
            $table->longText('content'); // HTML content
            $table->longText('plain_text_content')->nullable();
            $table->enum('type', ['one_time', 'automated', 'ab_test', 'drip'])->default('one_time');
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'paused', 'cancelled'])->default('draft');
            $table->string('from_name', 255);
            $table->string('from_email', 255);
            $table->string('reply_to', 255)->nullable();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->unsignedBigInteger('segment_id')->nullable();
            $table->json('recipient_criteria')->nullable(); // Dynamic recipient selection
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->integer('total_recipients')->default(0);
            $table->integer('delivered_count')->default(0);
            $table->integer('opened_count')->default(0);
            $table->integer('clicked_count')->default(0);
            $table->integer('bounced_count')->default(0);
            $table->integer('complained_count')->default(0);
            $table->integer('unsubscribed_count')->default(0);
            $table->decimal('open_rate', 5, 2)->default(0);
            $table->decimal('click_rate', 5, 2)->default(0);
            $table->decimal('unsubscribe_rate', 5, 2)->default(0);
            $table->decimal('revenue_attributed', 12, 2)->default(0);
            $table->json('utm_parameters')->nullable();
            $table->json('settings')->nullable(); // Campaign-specific settings
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            
            $table->foreign('template_id')->references('id')->on('email_templates')->onDelete('set null');
            $table->foreign('segment_id')->references('id')->on('email_segments')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['status', 'scheduled_at']);
            $table->index(['type', 'status']);
            $table->index('sent_at');
        });

        // Create email templates table
        Schema::create('email_templates', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('subject', 255);
            $table->text('preview_text')->nullable();
            $table->longText('content'); // HTML content
            $table->longText('plain_text_content')->nullable();
            $table->enum('type', ['campaign', 'automation', 'transactional'])->default('campaign');
            $table->string('category', 100)->nullable(); // welcome, promotional, transactional, etc.
            $table->json('variables')->nullable(); // Available template variables
            $table->json('design_settings')->nullable(); // Colors, fonts, layout settings
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // System templates cannot be deleted
            $table->string('thumbnail', 500)->nullable(); // Template preview image
            $table->integer('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['type', 'category', 'is_active']);
            $table->index('usage_count');
        });

        // Create email segments table
        Schema::create('email_segments', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('type', ['static', 'dynamic'])->default('dynamic');
            $table->json('conditions'); // Segment criteria/filters
            $table->integer('member_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_calculated_at')->nullable();
            $table->boolean('auto_update')->default(true);
            $table->integer('calculation_frequency')->default(60); // minutes
            $table->json('tags')->nullable(); // Segment tags
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['type', 'is_active']);
            $table->index('last_calculated_at');
        });

        // Create segment members table
        Schema::create('segment_members', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('segment_id');
            $table->unsignedBigInteger('subscriber_id');
            $table->timestamp('added_at');
            $table->json('criteria_match')->nullable(); // Which criteria matched
            $table->timestamps();
            
            $table->foreign('segment_id')->references('id')->on('email_segments')->onDelete('cascade');
            $table->foreign('subscriber_id')->references('id')->on('email_subscribers')->onDelete('cascade');
            $table->unique(['segment_id', 'subscriber_id']);
            $table->index('added_at');
        });

        // Create email automations table
        Schema::create('email_automations', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('type', ['welcome', 'abandoned_cart', 'post_purchase', 'birthday', 'win_back', 'custom']);
            $table->enum('status', ['active', 'inactive', 'draft'])->default('draft');
            $table->json('trigger_conditions'); // When to trigger the automation
            $table->json('workflow_steps'); // Email sequence and actions
            $table->json('settings')->nullable(); // Automation settings
            $table->integer('subscribers_count')->default(0);
            $table->integer('emails_sent')->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->decimal('revenue_generated', 12, 2)->default(0);
            $table->timestamp('last_triggered_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['type', 'status']);
            $table->index('last_triggered_at');
        });

        // Create automation triggers table
        Schema::create('automation_triggers', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('automation_id');
            $table->string('event_type', 100); // customer.registered, cart.abandoned, etc.
            $table->json('conditions')->nullable(); // Additional trigger conditions
            $table->integer('delay_minutes')->default(0); // Delay before triggering
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('automation_id')->references('id')->on('email_automations')->onDelete('cascade');
            $table->index(['event_type', 'is_active']);
        });

        // Create automation actions table
        Schema::create('automation_actions', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('automation_id');
            $table->integer('step_order');
            $table->enum('action_type', ['send_email', 'wait', 'condition', 'tag', 'segment', 'webhook']);
            $table->json('action_data'); // Action-specific configuration
            $table->integer('delay_minutes')->default(0);
            $table->json('conditions')->nullable(); // Conditional actions
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('automation_id')->references('id')->on('email_automations')->onDelete('cascade');
            $table->index(['automation_id', 'step_order']);
        });

        // Create automation flows table (track subscriber progress)
        Schema::create('automation_flows', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('automation_id');
            $table->unsignedBigInteger('subscriber_id');
            $table->integer('current_step')->default(0);
            $table->enum('status', ['active', 'completed', 'stopped', 'failed'])->default('active');
            $table->timestamp('started_at');
            $table->timestamp('last_action_at')->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->json('context_data')->nullable(); // Data from trigger event
            $table->timestamps();
            
            $table->foreign('automation_id')->references('id')->on('email_automations')->onDelete('cascade');
            $table->foreign('subscriber_id')->references('id')->on('email_subscribers')->onDelete('cascade');
            $table->index(['automation_id', 'status']);
            $table->index('next_action_at');
            $table->index(['subscriber_id', 'status']);
        });

        // Create email sends table (track individual email sends)
        Schema::create('email_sends', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->unsignedBigInteger('automation_id')->nullable();
            $table->unsignedBigInteger('subscriber_id');
            $table->string('email_address', 255);
            $table->string('subject', 255);
            $table->enum('status', ['queued', 'sent', 'delivered', 'bounced', 'complained', 'failed']);
            $table->enum('send_type', ['campaign', 'automation', 'transactional', 'test']);
            $table->string('message_id', 255)->nullable(); // Provider message ID
            $table->timestamp('queued_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('provider_response')->nullable();
            $table->string('tracking_id', 100)->unique(); // For tracking opens/clicks
            $table->timestamps();
            
            $table->foreign('campaign_id')->references('id')->on('email_campaigns')->onDelete('set null');
            $table->foreign('automation_id')->references('id')->on('email_automations')->onDelete('set null');
            $table->foreign('subscriber_id')->references('id')->on('email_subscribers')->onDelete('cascade');
            $table->index(['status', 'send_type']);
            $table->index(['email_address', 'sent_at']);
            $table->index('tracking_id');
        });

        // Create email opens table
        Schema::create('email_opens', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('send_id');
            $table->unsignedBigInteger('subscriber_id');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('location', 255)->nullable(); // City, Country
            $table->string('device_type', 50)->nullable(); // mobile, desktop, tablet
            $table->timestamp('opened_at');
            $table->timestamps();
            
            $table->foreign('send_id')->references('id')->on('email_sends')->onDelete('cascade');
            $table->foreign('subscriber_id')->references('id')->on('email_subscribers')->onDelete('cascade');
            $table->index(['send_id', 'opened_at']);
            $table->index(['subscriber_id', 'opened_at']);
        });

        // Create email clicks table
        Schema::create('email_clicks', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('send_id');
            $table->unsignedBigInteger('subscriber_id');
            $table->string('url', 2000);
            $table->string('link_id', 100)->nullable(); // Internal link identifier
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('location', 255)->nullable();
            $table->string('device_type', 50)->nullable();
            $table->timestamp('clicked_at');
            $table->timestamps();
            
            $table->foreign('send_id')->references('id')->on('email_sends')->onDelete('cascade');
            $table->foreign('subscriber_id')->references('id')->on('email_subscribers')->onDelete('cascade');
            $table->index(['send_id', 'clicked_at']);
            $table->index(['subscriber_id', 'clicked_at']);
            $table->index('url');
        });

        // Create email bounces table
        Schema::create('email_bounces', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('send_id');
            $table->unsignedBigInteger('subscriber_id');
            $table->string('email_address', 255);
            $table->enum('bounce_type', ['hard', 'soft', 'technical']);
            $table->string('bounce_reason', 500)->nullable();
            $table->string('diagnostic_code', 500)->nullable();
            $table->json('provider_data')->nullable();
            $table->timestamp('bounced_at');
            $table->timestamps();
            
            $table->foreign('send_id')->references('id')->on('email_sends')->onDelete('cascade');
            $table->foreign('subscriber_id')->references('id')->on('email_subscribers')->onDelete('cascade');
            $table->index(['bounce_type', 'bounced_at']);
            $table->index(['email_address', 'bounce_type']);
        });

        // Create email complaints table
        Schema::create('email_complaints', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('send_id');
            $table->unsignedBigInteger('subscriber_id');
            $table->string('email_address', 255);
            $table->string('feedback_type', 100)->default('abuse'); // abuse, fraud, virus, etc.
            $table->text('complaint_details')->nullable();
            $table->json('provider_data')->nullable();
            $table->timestamp('complained_at');
            $table->timestamps();
            
            $table->foreign('send_id')->references('id')->on('email_sends')->onDelete('cascade');
            $table->foreign('subscriber_id')->references('id')->on('email_subscribers')->onDelete('cascade');
            $table->index(['feedback_type', 'complained_at']);
            $table->index('email_address');
        });

        // Create email unsubscribes table
        Schema::create('email_unsubscribes', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('send_id')->nullable();
            $table->unsignedBigInteger('subscriber_id');
            $table->string('email_address', 255);
            $table->enum('unsubscribe_type', ['link', 'reply', 'manual', 'bounce', 'complaint']);
            $table->string('reason', 255)->nullable();
            $table->text('feedback')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('unsubscribed_at');
            $table->timestamps();
            
            $table->foreign('send_id')->references('id')->on('email_sends')->onDelete('set null');
            $table->foreign('subscriber_id')->references('id')->on('email_subscribers')->onDelete('cascade');
            $table->index(['unsubscribe_type', 'unsubscribed_at']);
            $table->index('email_address');
        });

        // Create A/B tests table
        Schema::create('ab_tests', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('name', 255);
            $table->enum('test_type', ['subject', 'content', 'send_time', 'from_name']);
            $table->unsignedBigInteger('campaign_id');
            $table->json('variants'); // Test variant configurations
            $table->integer('sample_size_percentage')->default(50); // % of audience to test
            $table->enum('status', ['active', 'completed', 'stopped'])->default('active');
            $table->string('winning_variant', 50)->nullable();
            $table->decimal('confidence_level', 5, 2)->default(95.00);
            $table->json('results')->nullable(); // Test results and statistics
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            
            $table->foreign('campaign_id')->references('id')->on('email_campaigns')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['test_type', 'status']);
            $table->index('started_at');
        });

        // Create personalization rules table
        Schema::create('personalization_rules', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('rule_type', ['content', 'subject', 'send_time', 'product_recommendation']);
            $table->json('conditions'); // When to apply the rule
            $table->json('actions'); // What changes to make
            $table->integer('priority')->default(0); // Rule execution order
            $table->boolean('is_active')->default(true);
            $table->integer('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['rule_type', 'is_active', 'priority']);
        });

        // Create deliverability tests table
        Schema::create('deliverability_tests', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('test_name', 255);
            $table->enum('test_type', ['spam_score', 'inbox_placement', 'domain_reputation', 'authentication']);
            $table->text('test_description')->nullable();
            $table->enum('status', ['pending', 'running', 'completed', 'failed']);
            $table->json('test_parameters'); // Test configuration
            $table->json('results')->nullable(); // Test results
            $table->decimal('score', 5, 2)->nullable(); // Overall test score
            $table->text('recommendations')->nullable(); // Improvement recommendations
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['test_type', 'status']);
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('deliverability_tests');
        Schema::dropIfExists('personalization_rules');
        Schema::dropIfExists('ab_tests');
        Schema::dropIfExists('email_unsubscribes');
        Schema::dropIfExists('email_complaints');
        Schema::dropIfExists('email_bounces');
        Schema::dropIfExists('email_clicks');
        Schema::dropIfExists('email_opens');
        Schema::dropIfExists('email_sends');
        Schema::dropIfExists('automation_flows');
        Schema::dropIfExists('automation_actions');
        Schema::dropIfExists('automation_triggers');
        Schema::dropIfExists('email_automations');
        Schema::dropIfExists('segment_members');
        Schema::dropIfExists('email_segments');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('email_campaigns');
        Schema::dropIfExists('email_subscribers');
    }
};