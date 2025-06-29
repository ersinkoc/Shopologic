<?php

use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema;

class CreateAnalyticsTables extends Migration
{
    public function up(): void
    {
        // Analytics events table
        Schema::create('analytics_events', function ($table) {
            $table->id();
            $table->string('event');
            $table->json('properties')->nullable();
            $table->string('user_id')->nullable();
            $table->string('session_id');
            $table->timestamp('timestamp');
            $table->date('date');
            $table->integer('hour');
            $table->timestamps();
            
            $table->index(['event', 'timestamp']);
            $table->index(['user_id', 'timestamp']);
            $table->index(['session_id', 'timestamp']);
            $table->index(['date', 'hour']);
        });
        
        // Analytics sessions table
        Schema::create('analytics_sessions', function ($table) {
            $table->string('id')->primary();
            $table->string('user_id')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration')->default(0);
            $table->integer('page_count')->default(0);
            $table->integer('event_count')->default(0);
            $table->json('pages')->nullable();
            $table->string('source')->nullable();
            $table->string('medium')->nullable();
            $table->string('campaign')->nullable();
            $table->string('device_type')->nullable();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->boolean('is_bounce')->default(false);
            $table->timestamps();
            
            $table->index(['user_id', 'started_at']);
            $table->index(['started_at', 'ended_at']);
            $table->index(['source', 'medium']);
            $table->index('device_type');
        });
        
        // Analytics users table
        Schema::create('analytics_users', function ($table) {
            $table->string('id')->primary();
            $table->timestamp('first_seen');
            $table->timestamp('last_seen');
            $table->integer('session_count')->default(0);
            $table->integer('total_duration')->default(0);
            $table->integer('total_pageviews')->default(0);
            $table->integer('total_events')->default(0);
            $table->string('acquisition_channel')->nullable();
            $table->string('acquisition_source')->nullable();
            $table->json('attributes')->nullable();
            $table->timestamps();
            
            $table->index(['first_seen', 'last_seen']);
            $table->index('acquisition_channel');
        });
        
        // Analytics aggregations table
        Schema::create('analytics_aggregations', function ($table) {
            $table->id();
            $table->string('type'); // hourly, daily, weekly, monthly
            $table->date('date');
            $table->integer('hour')->nullable();
            $table->integer('week')->nullable();
            $table->integer('month')->nullable();
            $table->integer('year')->nullable();
            $table->integer('unique_users')->default(0);
            $table->integer('sessions')->default(0);
            $table->integer('pageviews')->default(0);
            $table->integer('events')->default(0);
            $table->decimal('bounce_rate', 5, 2)->default(0);
            $table->decimal('avg_session_duration', 10, 2)->default(0);
            $table->json('metrics')->nullable();
            $table->timestamps();
            
            $table->unique(['type', 'date', 'hour']);
            $table->index(['type', 'date']);
            $table->index(['year', 'month']);
            $table->index(['year', 'week']);
        });
        
        // Page metrics table
        Schema::create('page_metrics', function ($table) {
            $table->id();
            $table->string('page');
            $table->date('date');
            $table->integer('hour');
            $table->integer('pageviews')->default(0);
            $table->integer('unique_pageviews')->default(0);
            $table->decimal('avg_time_on_page', 10, 2)->default(0);
            $table->decimal('bounce_rate', 5, 2)->default(0);
            $table->decimal('exit_rate', 5, 2)->default(0);
            $table->integer('entrance_count')->default(0);
            $table->timestamps();
            
            $table->unique(['page', 'date', 'hour']);
            $table->index(['page', 'date']);
        });
        
        // E-commerce analytics table
        Schema::create('ecommerce_analytics', function ($table) {
            $table->id();
            $table->string('transaction_id');
            $table->string('user_id')->nullable();
            $table->string('session_id');
            $table->date('date');
            $table->decimal('revenue', 10, 2);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('shipping', 10, 2)->default(0);
            $table->integer('quantity');
            $table->string('currency', 3)->default('USD');
            $table->string('source')->nullable();
            $table->string('medium')->nullable();
            $table->timestamps();
            
            $table->index(['date', 'transaction_id']);
            $table->index('user_id');
            $table->index(['source', 'medium']);
        });
        
        // Product analytics table
        Schema::create('product_analytics', function ($table) {
            $table->id();
            $table->string('product_id');
            $table->string('product_name');
            $table->string('product_sku')->nullable();
            $table->date('date');
            $table->integer('views')->default(0);
            $table->integer('impressions')->default(0);
            $table->integer('add_to_carts')->default(0);
            $table->integer('purchases')->default(0);
            $table->integer('quantity_sold')->default(0);
            $table->decimal('revenue', 10, 2)->default(0);
            $table->timestamps();
            
            $table->unique(['product_id', 'date']);
            $table->index(['date', 'revenue']);
        });
        
        // Analytics reports table
        Schema::create('analytics_reports', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->json('config');
            $table->string('status')->default('pending');
            $table->bigInteger('scheduled_report_id')->nullable();
            $table->string('output_path')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            
            $table->index('status');
            $table->index('scheduled_report_id');
        });
        
        // Scheduled reports table
        Schema::create('scheduled_reports', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->json('config');
            $table->string('frequency'); // daily, weekly, monthly
            $table->json('recipients');
            $table->string('status')->default('active');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'next_run_at']);
        });
        
        // Analytics processing status table
        Schema::create('analytics_processing_status', function ($table) {
            $table->string('type')->primary(); // hourly, daily, weekly, monthly
            $table->timestamp('last_processed');
            $table->timestamps();
        });
        
        // Goal conversions table (link to marketing conversions)
        Schema::create('analytics_goals', function ($table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('type'); // destination, duration, pages_per_session, event
            $table->json('config');
            $table->decimal('value', 10, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index('type');
            $table->index('active');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('analytics_goals');
        Schema::dropIfExists('analytics_processing_status');
        Schema::dropIfExists('scheduled_reports');
        Schema::dropIfExists('analytics_reports');
        Schema::dropIfExists('product_analytics');
        Schema::dropIfExists('ecommerce_analytics');
        Schema::dropIfExists('page_metrics');
        Schema::dropIfExists('analytics_aggregations');
        Schema::dropIfExists('analytics_users');
        Schema::dropIfExists('analytics_sessions');
        Schema::dropIfExists('analytics_events');
    }
}