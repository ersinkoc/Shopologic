<?php

declare(strict_types=1);

use Shopologic\Core\Database\Migrations\Migration;
use Shopologic\Core\Database\Schema\Schema;
use Shopologic\Core\Database\Schema\Blueprint;

class CreatePerformanceTables extends Migration
{
    public function up(): void
    {
        // Queue jobs table
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->text('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
            
            $table->index(['queue', 'reserved_at']);
        });
        
        // Failed jobs table
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('connection');
            $table->string('queue');
            $table->text('payload');
            $table->text('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
        
        // Search documents table
        Schema::create('search_documents', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->string('document_id');
            $table->json('content');
            $table->json('tokens');
            $table->decimal('boost', 3, 2)->default(1.0);
            $table->timestamp('indexed_at');
            $table->timestamps();
            
            $table->unique(['type', 'document_id']);
            $table->index('indexed_at');
        });
        
        // Search index table
        Schema::create('search_index', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('document_id');
            $table->string('term');
            $table->string('field');
            $table->integer('frequency');
            $table->decimal('weight', 3, 2);
            $table->decimal('score', 10, 4);
            
            $table->index(['type', 'document_id']);
            $table->index(['term', 'type']);
            $table->index('score');
        });
        
        // Search terms table
        Schema::create('search_terms', function (Blueprint $table) {
            $table->id();
            $table->string('term')->unique();
            $table->unsignedBigInteger('frequency')->default(0);
            $table->timestamp('updated_at');
            
            $table->index('frequency');
        });
        
        // Search queries table
        Schema::create('search_queries', function (Blueprint $table) {
            $table->id();
            $table->string('query');
            $table->integer('results_count');
            $table->integer('clicked_position')->nullable();
            $table->decimal('search_time', 10, 4);
            $table->timestamp('created_at');
            
            $table->index('query');
            $table->index('created_at');
        });
        
        // Cache tags table (for tagged cache)
        Schema::create('cache_tags', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('tag');
            $table->timestamps();
            
            $table->index(['tag', 'key']);
            $table->index('key');
        });
        
        // Performance metrics table
        Schema::create('performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // page_load, api_call, query, etc.
            $table->string('name');
            $table->decimal('duration', 10, 4); // milliseconds
            $table->integer('memory_usage')->nullable();
            $table->integer('cpu_usage')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at');
            
            $table->index(['type', 'recorded_at']);
            $table->index(['name', 'recorded_at']);
        });
        
        // Slow query log
        Schema::create('slow_queries', function (Blueprint $table) {
            $table->id();
            $table->text('query');
            $table->json('bindings')->nullable();
            $table->decimal('duration', 10, 4); // milliseconds
            $table->string('connection');
            $table->string('file')->nullable();
            $table->integer('line')->nullable();
            $table->timestamp('executed_at');
            
            $table->index('duration');
            $table->index('executed_at');
        });
        
        // HTTP cache table
        Schema::create('http_cache', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('url');
            $table->string('method');
            $table->json('headers');
            $table->longText('content');
            $table->string('etag')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->index('expires_at');
            $table->index(['url', 'method']);
        });
        
        // Object cache table (for database cache driver)
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
            
            $table->index('expiration');
        });
        
        // Cache locks table
        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
            
            $table->index('expiration');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('http_cache');
        Schema::dropIfExists('slow_queries');
        Schema::dropIfExists('performance_metrics');
        Schema::dropIfExists('cache_tags');
        Schema::dropIfExists('search_queries');
        Schema::dropIfExists('search_terms');
        Schema::dropIfExists('search_index');
        Schema::dropIfExists('search_documents');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
    }
}