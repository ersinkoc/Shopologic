<?php

declare(strict_types=1);

use Shopologic\Core\Database\Migrations\Migration;
use Shopologic\Core\Database\Schema\Schema;
use Shopologic\Core\Database\Schema\Blueprint;

class CreateAdminTables extends Migration
{
    public function up(): void
    {
        // Admin activity log
        Schema::create('admin_activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('event');
            $table->text('description');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('created_at');
            
            $table->index(['user_id', 'created_at']);
            $table->index('event');
            $table->index('created_at');
        });
        
        // Admin notifications
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type')->default('info'); // info, success, warning, danger
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->string('action_url')->nullable();
            $table->string('action_label')->nullable();
            $table->boolean('read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'read', 'created_at']);
        });
        
        // Admin dashboard widgets preferences
        Schema::create('admin_widget_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('widget');
            $table->string('position');
            $table->integer('order')->default(0);
            $table->json('settings')->nullable();
            $table->boolean('collapsed')->default(false);
            $table->timestamps();
            
            $table->unique(['user_id', 'widget']);
            $table->index(['user_id', 'position', 'order']);
        });
        
        // Admin saved filters
        Schema::create('admin_saved_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('resource'); // products, orders, customers, etc.
            $table->json('filters');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            
            $table->index(['user_id', 'resource']);
        });
        
        // Admin custom reports
        Schema::create('admin_custom_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // sales, products, customers, etc.
            $table->json('configuration');
            $table->json('schedule')->nullable();
            $table->timestamp('last_run')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('type');
        });
        
        // Admin scheduled tasks
        Schema::create('admin_scheduled_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('command');
            $table->string('frequency'); // daily, weekly, monthly, etc.
            $table->json('parameters')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_run')->nullable();
            $table->timestamp('next_run')->nullable();
            $table->integer('run_count')->default(0);
            $table->integer('failure_count')->default(0);
            $table->text('last_output')->nullable();
            $table->timestamps();
            
            $table->index(['enabled', 'next_run']);
            $table->index('command');
        });
        
        // Admin IP whitelist
        Schema::create('admin_ip_whitelist', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45);
            $table->string('description')->nullable();
            $table->foreignId('added_by')->constrained('users');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            
            $table->unique('ip_address');
            $table->index('enabled');
        });
        
        // Admin login attempts
        Schema::create('admin_login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->boolean('successful')->default(false);
            $table->string('failure_reason')->nullable();
            $table->timestamp('attempted_at');
            
            $table->index(['email', 'attempted_at']);
            $table->index(['ip_address', 'attempted_at']);
        });
        
        // Admin two-factor auth
        Schema::create('admin_two_factor_auth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('secret')->nullable();
            $table->text('recovery_codes')->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            
            $table->unique('user_id');
        });
        
        // Admin menu customizations
        Schema::create('admin_menu_customizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->json('menu_state'); // collapsed items, custom order, etc.
            $table->json('favorites')->nullable();
            $table->timestamps();
            
            $table->unique('user_id');
        });
        
        // Admin quick actions
        Schema::create('admin_quick_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('icon')->nullable();
            $table->string('action'); // URL or command
            $table->json('parameters')->nullable();
            $table->integer('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'usage_count']);
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('admin_quick_actions');
        Schema::dropIfExists('admin_menu_customizations');
        Schema::dropIfExists('admin_two_factor_auth');
        Schema::dropIfExists('admin_login_attempts');
        Schema::dropIfExists('admin_ip_whitelist');
        Schema::dropIfExists('admin_scheduled_tasks');
        Schema::dropIfExists('admin_custom_reports');
        Schema::dropIfExists('admin_saved_filters');
        Schema::dropIfExists('admin_widget_preferences');
        Schema::dropIfExists('admin_notifications');
        Schema::dropIfExists('admin_activity_log');
    }
}