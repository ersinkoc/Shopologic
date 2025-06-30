<?php


declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedAnalyticsReporting;
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
        // Create analytics events table
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('event_type', 100)->index();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('session_id', 100)->index();
            $table->string('user_agent', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('device_type', 50)->nullable();
            $table->string('browser', 100)->nullable();
            $table->string('operating_system', 100)->nullable();
            $table->string('referrer_url', 2000)->nullable();
            $table->string('page_url', 2000)->nullable();
            $table->string('utm_source', 255)->nullable();
            $table->string('utm_medium', 255)->nullable();
            $table->string('utm_campaign', 255)->nullable();
            $table->string('utm_term', 255)->nullable();
            $table->string('utm_content', 255)->nullable();
            $table->json('event_data')->nullable();
            $table->decimal('event_value', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamp('event_timestamp');
            $table->timestamps();
            
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->index(['event_type', 'event_timestamp']);
            $table->index(['customer_id', 'event_timestamp']);
            $table->index(['session_id', 'event_timestamp']);
            $table->index(['event_timestamp', 'event_type']);
        });

        // Create analytics sessions table
        Schema::create('analytics_sessions', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('session_id', 100)->unique();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->timestamp('session_start');
            $table->timestamp('session_end')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->integer('page_views')->default(0);
            $table->integer('events_count')->default(0);
            $table->boolean('is_bounce')->default(false);
            $table->string('landing_page', 2000)->nullable();
            $table->string('exit_page', 2000)->nullable();
            $table->string('traffic_source', 100)->nullable();
            $table->string('campaign', 255)->nullable();
            $table->string('device_type', 50)->nullable();
            $table->string('browser', 100)->nullable();
            $table->string('operating_system', 100)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->decimal('conversion_value', 12, 2)->default(0);
            $table->integer('goals_completed')->default(0);
            $table->json('session_data')->nullable();
            $table->timestamps();
            
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->index(['customer_id', 'session_start']);
            $table->index(['session_start', 'session_end']);
            $table->index(['traffic_source', 'session_start']);
        });

        // Create analytics funnels table
        Schema::create('analytics_funnels', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->json('steps'); // Array of funnel steps with conditions
            $table->enum('status', ['active', 'inactive', 'draft'])->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['status', 'start_date', 'end_date']);
        });

        // Create analytics cohorts table
        Schema::create('analytics_cohorts', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('cohort_type', 100); // registration, first_purchase, etc.
            $table->date('cohort_date');
            $table->string('period_type', 20); // daily, weekly, monthly
            $table->integer('period_number'); // 0, 1, 2, 3... (periods since cohort date)
            $table->integer('total_customers');
            $table->integer('returning_customers');
            $table->decimal('retention_rate', 5, 4);
            $table->decimal('revenue', 12, 2)->default(0);
            $table->decimal('average_order_value', 12, 2)->default(0);
            $table->integer('orders_count')->default(0);
            $table->json('metrics')->nullable(); // Additional metrics
            $table->timestamps();
            
            $table->unique(['cohort_type', 'cohort_date', 'period_type', 'period_number'], 'cohort_unique');
            $table->index(['cohort_type', 'cohort_date']);
            $table->index(['period_type', 'period_number']);
        });

        // Create analytics segments table
        Schema::create('analytics_segments', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['behavioral', 'demographic', 'geographic', 'psychographic', 'custom']);
            $table->json('conditions'); // Segment conditions and filters
            $table->integer('customers_count')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->decimal('average_order_value', 12, 2)->default(0);
            $table->decimal('conversion_rate', 5, 4)->default(0);
            $table->timestamp('last_calculated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_update')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['type', 'is_active']);
            $table->index('last_calculated_at');
        });

        // Create analytics reports table
        Schema::create('analytics_reports', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['sales', 'customers', 'products', 'cohort', 'funnel', 'custom']);
            $table->json('configuration'); // Report configuration (metrics, dimensions, filters)
            $table->json('visualization_config')->nullable(); // Chart and visualization settings
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->boolean('is_scheduled')->default(false);
            $table->string('schedule_frequency', 50)->nullable(); // daily, weekly, monthly
            $table->json('schedule_config')->nullable();
            $table->json('recipients')->nullable(); // Email recipients for scheduled reports
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['type', 'status']);
            $table->index(['is_scheduled', 'next_run_at']);
        });

        // Create analytics dashboards table
        Schema::create('analytics_dashboards', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->json('layout'); // Dashboard layout configuration
            $table->json('widgets'); // Widgets configuration
            $table->json('filters')->nullable(); // Global dashboard filters
            $table->boolean('is_default')->default(false);
            $table->boolean('is_public')->default(false);
            $table->json('permissions')->nullable(); // User/role permissions
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['is_default', 'is_public']);
        });

        // Create analytics widgets table
        Schema::create('analytics_widgets', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('name', 255);
            $table->string('type', 100); // chart, table, metric, etc.
            $table->json('configuration'); // Widget configuration
            $table->json('data_source'); // Data source configuration
            $table->json('visualization_config'); // Chart/display configuration
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['type', 'is_active']);
        });

        // Create analytics metrics table
        Schema::create('analytics_metrics', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('metric_name', 255);
            $table->string('metric_type', 100); // revenue, conversion, retention, etc.
            $table->date('metric_date');
            $table->string('dimension_type', 100)->nullable(); // product, category, campaign, etc.
            $table->string('dimension_value', 255)->nullable();
            $table->decimal('metric_value', 15, 4);
            $table->integer('sample_size')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->unique(['metric_name', 'metric_date', 'dimension_type', 'dimension_value'], 'metrics_unique');
            $table->index(['metric_name', 'metric_date']);
            $table->index(['metric_type', 'metric_date']);
            $table->index(['dimension_type', 'dimension_value']);
        });

        // Create analytics dimensions table
        Schema::create('analytics_dimensions', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('dimension_name', 255);
            $table->string('dimension_type', 100);
            $table->string('dimension_value', 255);
            $table->text('dimension_description')->nullable();
            $table->json('attributes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['dimension_name', 'dimension_value']);
            $table->index(['dimension_type', 'is_active']);
        });

        // Create analytics KPIs table
        Schema::create('analytics_kpis', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->string('metric_source', 255); // Which metric to track
            $table->enum('comparison_type', ['target', 'previous_period', 'year_over_year']);
            $table->decimal('target_value', 15, 4)->nullable();
            $table->decimal('current_value', 15, 4)->nullable();
            $table->decimal('previous_value', 15, 4)->nullable();
            $table->decimal('variance', 15, 4)->nullable();
            $table->decimal('variance_percent', 8, 4)->nullable();
            $table->enum('trend', ['up', 'down', 'stable'])->nullable();
            $table->string('format', 50)->default('number'); // number, currency, percentage
            $table->string('color', 7)->nullable(); // Hex color for display
            $table->timestamp('last_calculated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['is_active', 'sort_order']);
            $table->index('last_calculated_at');
        });

        // Create analytics alerts table
        Schema::create('analytics_alerts', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('metric_source', 255); // Which metric to monitor
            $table->enum('condition_type', ['above', 'below', 'equals', 'percentage_change']);
            $table->decimal('threshold_value', 15, 4);
            $table->enum('comparison_period', ['current', 'previous_hour', 'previous_day', 'previous_week']);
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->json('notification_channels'); // email, sms, slack, webhook
            $table->json('recipients'); // Who to notify
            $table->boolean('is_active')->default(true);
            $table->integer('check_frequency_minutes')->default(60);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_triggered_at')->nullable();
            $table->integer('trigger_count')->default(0);
            $table->boolean('is_muted')->default(false);
            $table->timestamp('muted_until')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['is_active', 'check_frequency_minutes']);
            $table->index(['last_checked_at', 'is_active']);
        });

        // Create analytics exports table
        Schema::create('analytics_exports', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('export_type', 100); // report, dashboard, raw_data
            $table->unsignedBigInteger('source_id')->nullable(); // ID of report/dashboard
            $table->string('format', 20); // csv, excel, pdf, json
            $table->json('export_config'); // Configuration for the export
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('file_path', 500)->nullable();
            $table->string('file_name', 255)->nullable();
            $table->integer('file_size')->nullable(); // File size in bytes
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('requested_by');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['status', 'created_at']);
            $table->index(['expires_at', 'status']);
            $table->index(['requested_by', 'created_at']);
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_exports');
        Schema::dropIfExists('analytics_alerts');
        Schema::dropIfExists('analytics_kpis');
        Schema::dropIfExists('analytics_dimensions');
        Schema::dropIfExists('analytics_metrics');
        Schema::dropIfExists('analytics_widgets');
        Schema::dropIfExists('analytics_dashboards');
        Schema::dropIfExists('analytics_reports');
        Schema::dropIfExists('analytics_segments');
        Schema::dropIfExists('analytics_cohorts');
        Schema::dropIfExists('analytics_funnels');
        Schema::dropIfExists('analytics_sessions');
        Schema::dropIfExists('analytics_events');
    }
};