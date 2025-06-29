<?php

use Shopologic\Database\Migration;
use Shopologic\Database\Schema;

class CreateAdvancedEmailMarketingTables extends Migration
{
    public function up(): void
    {
        // Email campaigns
        Schema::create('email_campaigns', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('name');
            $table->string('type'); // newsletter, promotional, transactional, automated
            $table->string('status'); // draft, scheduled, sending, sent, paused, cancelled
            $table->text('subject_line');
            $table->string('from_name');
            $table->string('from_email');
            $table->string('reply_to')->nullable();
            $table->longText('content_html');
            $table->text('content_text')->nullable();
            $table->json('target_audience')->nullable();
            $table->json('ab_test_config')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->integer('total_recipients')->default(0);
            $table->timestamps();
            
            $table->index(['store_id', 'status']);
            $table->index(['type', 'status']);
            $table->index('scheduled_at');
        });

        // Email templates
        Schema::create('email_templates', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('name');
            $table->string('category'); // welcome, abandonment, promotional, transactional
            $table->text('description')->nullable();
            $table->text('subject_line');
            $table->longText('content_html');
            $table->text('content_text')->nullable();
            $table->json('variables')->nullable(); // dynamic content placeholders
            $table->boolean('is_active')->default(true);
            $table->integer('usage_count')->default(0);
            $table->timestamps();
            
            $table->index(['store_id', 'category']);
            $table->index('is_active');
        });

        // Email subscribers
        Schema::create('email_subscribers', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->string('email');
            $table->string('status'); // subscribed, unsubscribed, bounced, complained
            $table->json('preferences')->nullable();
            $table->json('tags')->nullable();
            $table->decimal('engagement_score', 5, 2)->default(0);
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('subscription_source')->nullable();
            $table->timestamps();
            
            $table->unique(['store_id', 'email']);
            $table->index(['status', 'engagement_score']);
        });

        // Email lists
        Schema::create('email_lists', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('criteria')->nullable(); // dynamic list criteria
            $table->boolean('is_dynamic')->default(false);
            $table->integer('subscriber_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['store_id', 'is_active']);
        });

        // Email list subscribers mapping
        Schema::create('email_list_subscribers', function($table) {
            $table->id();
            $table->foreignId('list_id')->constrained('email_lists');
            $table->foreignId('subscriber_id')->constrained('email_subscribers');
            $table->timestamp('added_at');
            $table->timestamps();
            
            $table->unique(['list_id', 'subscriber_id']);
        });

        // Email sends (individual email records)
        Schema::create('email_sends', function($table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('email_campaigns');
            $table->foreignId('subscriber_id')->constrained('email_subscribers');
            $table->string('message_id')->nullable(); // Provider message ID
            $table->string('status'); // queued, sending, sent, delivered, bounced, failed
            $table->string('ab_variant')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->json('delivery_data')->nullable();
            $table->timestamps();
            
            $table->index(['campaign_id', 'status']);
            $table->index(['subscriber_id', 'sent_at']);
            $table->index('message_id');
        });

        // Email engagement tracking
        Schema::create('email_engagements', function($table) {
            $table->id();
            $table->foreignId('send_id')->constrained('email_sends');
            $table->string('event_type'); // opened, clicked, unsubscribed, complained
            $table->string('event_data')->nullable(); // clicked link, etc.
            $table->string('user_agent')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            
            $table->index(['send_id', 'event_type']);
            $table->index('occurred_at');
        });

        // Automation workflows
        Schema::create('email_workflows', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger_type'); // event, time, behavior
            $table->json('trigger_config');
            $table->json('workflow_steps');
            $table->string('status'); // active, paused, draft
            $table->integer('enrolled_count')->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->timestamps();
            
            $table->index(['store_id', 'status']);
            $table->index('trigger_type');
        });

        // Workflow enrollments
        Schema::create('workflow_enrollments', function($table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('email_workflows');
            $table->foreignId('subscriber_id')->constrained('email_subscribers');
            $table->string('status'); // active, completed, exited
            $table->integer('current_step')->default(0);
            $table->timestamp('enrolled_at');
            $table->timestamp('completed_at')->nullable();
            $table->json('enrollment_data')->nullable();
            $table->timestamps();
            
            $table->index(['workflow_id', 'status']);
            $table->index(['subscriber_id', 'enrolled_at']);
        });

        // A/B test results
        Schema::create('email_ab_tests', function($table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('email_campaigns');
            $table->string('test_element'); // subject, content, send_time, from_name
            $table->json('variants');
            $table->integer('test_size_percentage');
            $table->string('winner_criteria'); // open_rate, click_rate, conversion_rate
            $table->string('status'); // running, completed, cancelled
            $table->string('winning_variant')->nullable();
            $table->json('results')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            
            $table->index(['campaign_id', 'status']);
        });

        // Email deliverability tracking
        Schema::create('email_deliverability', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('domain');
            $table->date('tracking_date');
            $table->integer('emails_sent')->default(0);
            $table->integer('emails_delivered')->default(0);
            $table->integer('emails_bounced')->default(0);
            $table->integer('emails_complained')->default(0);
            $table->decimal('delivery_rate', 5, 2)->default(0);
            $table->decimal('bounce_rate', 5, 2)->default(0);
            $table->decimal('complaint_rate', 5, 2)->default(0);
            $table->decimal('reputation_score', 5, 2)->default(0);
            $table->timestamps();
            
            $table->unique(['store_id', 'domain', 'tracking_date']);
            $table->index('tracking_date');
        });

        // Email segments
        Schema::create('email_segments', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('conditions'); // segmentation rules
            $table->integer('subscriber_count')->default(0);
            $table->timestamp('last_calculated')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['store_id', 'is_active']);
        });

        // Unsubscribe tracking
        Schema::create('email_unsubscribes', function($table) {
            $table->id();
            $table->foreignId('subscriber_id')->constrained('email_subscribers');
            $table->foreignId('campaign_id')->nullable()->constrained('email_campaigns');
            $table->string('reason')->nullable();
            $table->text('feedback')->nullable();
            $table->string('unsubscribe_type'); // manual, automatic, complaint
            $table->timestamp('unsubscribed_at');
            $table->timestamps();
            
            $table->index(['subscriber_id', 'unsubscribed_at']);
            $table->index('unsubscribe_type');
        });

        // Email performance analytics
        Schema::create('email_analytics', function($table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('email_campaigns');
            $table->date('analytics_date');
            $table->integer('emails_sent')->default(0);
            $table->integer('emails_delivered')->default(0);
            $table->integer('emails_opened')->default(0);
            $table->integer('emails_clicked')->default(0);
            $table->integer('emails_converted')->default(0);
            $table->integer('emails_unsubscribed')->default(0);
            $table->decimal('open_rate', 5, 2)->default(0);
            $table->decimal('click_rate', 5, 2)->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->decimal('unsubscribe_rate', 5, 2)->default(0);
            $table->decimal('revenue_generated', 15, 2)->default(0);
            $table->timestamps();
            
            $table->unique(['campaign_id', 'analytics_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_analytics');
        Schema::dropIfExists('email_unsubscribes');
        Schema::dropIfExists('email_segments');
        Schema::dropIfExists('email_deliverability');
        Schema::dropIfExists('email_ab_tests');
        Schema::dropIfExists('workflow_enrollments');
        Schema::dropIfExists('email_workflows');
        Schema::dropIfExists('email_engagements');
        Schema::dropIfExists('email_sends');
        Schema::dropIfExists('email_list_subscribers');
        Schema::dropIfExists('email_lists');
        Schema::dropIfExists('email_subscribers');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('email_campaigns');
    }
}