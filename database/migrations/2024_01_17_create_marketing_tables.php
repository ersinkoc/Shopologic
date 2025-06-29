<?php

use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema;

class CreateMarketingTables extends Migration
{
    public function up(): void
    {
        // Email campaigns table
        Schema::create('email_campaigns', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->string('from_name');
            $table->string('from_email');
            $table->string('reply_to')->nullable();
            $table->string('template');
            $table->json('content')->nullable();
            $table->bigInteger('segment_id')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('total_recipients')->default(0);
            $table->integer('sent')->default(0);
            $table->integer('delivered')->default(0);
            $table->integer('bounced')->default(0);
            $table->integer('opens')->default(0);
            $table->integer('unique_opens')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('unique_clicks')->default(0);
            $table->integer('unsubscribes')->default(0);
            $table->integer('complaints')->default(0);
            $table->timestamps();
            
            $table->index('status');
            $table->index('scheduled_at');
        });
        
        // Email tracking table
        Schema::create('email_trackings', function ($table) {
            $table->id();
            $table->bigInteger('campaign_id');
            $table->string('tracking_id')->unique();
            $table->string('recipient');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->integer('open_count')->default(0);
            $table->integer('click_count')->default(0);
            $table->timestamps();
            
            $table->index(['campaign_id', 'recipient']);
            $table->index('tracking_id');
        });
        
        // Email clicks table
        Schema::create('email_clicks', function ($table) {
            $table->id();
            $table->bigInteger('tracking_id');
            $table->string('link_id');
            $table->timestamp('clicked_at');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            
            $table->index(['tracking_id', 'link_id']);
        });
        
        // Email subscribers table
        Schema::create('email_subscribers', function ($table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('subscribed_at');
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->json('tags')->nullable();
            $table->json('custom_fields')->nullable();
            $table->timestamps();
            
            $table->index('status');
            $table->index('email');
        });
        
        // Email segments table
        Schema::create('email_segments', function ($table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('criteria');
            $table->integer('subscriber_count')->default(0);
            $table->timestamps();
        });
        
        // Social posts table
        Schema::create('social_posts', function ($table) {
            $table->id();
            $table->text('content');
            $table->json('providers');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->json('media')->nullable();
            $table->json('hashtags')->nullable();
            $table->json('mentions')->nullable();
            $table->string('status')->default('draft');
            $table->json('results')->nullable();
            $table->timestamps();
            
            $table->index('status');
            $table->index('scheduled_at');
        });
        
        // Automation workflows table
        Schema::create('automation_workflows', function ($table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger_type');
            $table->json('trigger_config')->nullable();
            $table->json('actions');
            $table->json('conditions')->nullable();
            $table->string('status')->default('active');
            $table->integer('priority')->default(0);
            $table->timestamps();
            
            $table->index(['status', 'trigger_type']);
            $table->index('priority');
        });
        
        // Workflow executions table
        Schema::create('workflow_executions', function ($table) {
            $table->id();
            $table->bigInteger('workflow_id');
            $table->bigInteger('contact_id');
            $table->json('context')->nullable();
            $table->string('status')->default('running');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            
            $table->index(['workflow_id', 'status']);
            $table->index('contact_id');
        });
        
        // Workflow steps table
        Schema::create('workflow_steps', function ($table) {
            $table->id();
            $table->bigInteger('workflow_execution_id');
            $table->integer('step_index');
            $table->string('action_type');
            $table->json('action_config')->nullable();
            $table->string('status');
            $table->timestamp('executed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            
            $table->index('workflow_execution_id');
        });
        
        // Contacts table
        Schema::create('contacts', function ($table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->bigInteger('user_id')->nullable();
            $table->json('tags')->nullable();
            $table->json('custom_fields')->nullable();
            $table->integer('score')->default(0);
            $table->timestamps();
            
            $table->index('email');
            $table->index('user_id');
        });
        
        // A/B tests table
        Schema::create('ab_tests', function ($table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('type');
            $table->json('variants');
            $table->json('traffic_allocation')->nullable();
            $table->json('targeting_rules')->nullable();
            $table->string('goal_type');
            $table->json('goal_config')->nullable();
            $table->string('status')->default('draft');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('winner')->nullable();
            $table->json('statistics')->nullable();
            $table->timestamps();
            
            $table->index('status');
            $table->index('name');
        });
        
        // A/B test participants table
        Schema::create('ab_test_participants', function ($table) {
            $table->id();
            $table->bigInteger('test_id');
            $table->string('visitor_id');
            $table->string('variant');
            $table->timestamp('assigned_at');
            $table->timestamps();
            
            $table->unique(['test_id', 'visitor_id']);
            $table->index(['test_id', 'variant']);
        });
        
        // A/B test conversions table
        Schema::create('ab_test_conversions', function ($table) {
            $table->id();
            $table->bigInteger('test_id');
            $table->string('variant');
            $table->string('visitor_id');
            $table->decimal('value', 10, 2)->nullable();
            $table->timestamp('converted_at');
            $table->timestamps();
            
            $table->index(['test_id', 'variant']);
            $table->index('visitor_id');
        });
        
        // A/B test events table
        Schema::create('ab_test_events', function ($table) {
            $table->id();
            $table->bigInteger('test_id');
            $table->string('variant');
            $table->string('visitor_id');
            $table->string('event');
            $table->json('data')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            
            $table->index(['test_id', 'variant', 'event']);
        });
        
        // Conversions table
        Schema::create('conversions', function ($table) {
            $table->id();
            $table->string('goal_name');
            $table->string('visitor_id');
            $table->string('session_id');
            $table->decimal('value', 10, 2)->default(0);
            $table->decimal('revenue', 10, 2)->default(0);
            $table->json('attribution')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('converted_at');
            $table->timestamps();
            
            $table->index(['goal_name', 'converted_at']);
            $table->index('visitor_id');
        });
        
        // Micro conversions table
        Schema::create('micro_conversions', function ($table) {
            $table->id();
            $table->string('action');
            $table->string('visitor_id');
            $table->string('session_id');
            $table->json('data')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            
            $table->index(['action', 'occurred_at']);
            $table->index('visitor_id');
        });
        
        // Funnel steps table
        Schema::create('funnel_steps', function ($table) {
            $table->id();
            $table->string('funnel_name');
            $table->string('step_name');
            $table->string('visitor_id');
            $table->string('session_id');
            $table->json('data')->nullable();
            $table->timestamp('entered_at');
            $table->timestamps();
            
            $table->index(['funnel_name', 'step_name']);
            $table->index('visitor_id');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('funnel_steps');
        Schema::dropIfExists('micro_conversions');
        Schema::dropIfExists('conversions');
        Schema::dropIfExists('ab_test_events');
        Schema::dropIfExists('ab_test_conversions');
        Schema::dropIfExists('ab_test_participants');
        Schema::dropIfExists('ab_tests');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflow_executions');
        Schema::dropIfExists('automation_workflows');
        Schema::dropIfExists('social_posts');
        Schema::dropIfExists('email_segments');
        Schema::dropIfExists('email_subscribers');
        Schema::dropIfExists('email_clicks');
        Schema::dropIfExists('email_trackings');
        Schema::dropIfExists('email_campaigns');
    }
}