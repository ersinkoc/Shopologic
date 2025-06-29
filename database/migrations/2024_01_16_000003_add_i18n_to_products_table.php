<?php

use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create product translations table
        Schema::create('product_translations', function ($table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('locale', 10);
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->timestamps();
            
            $table->unique(['product_id', 'locale']);
            $table->index('locale');
        });
        
        // Create category translations table
        Schema::create('category_translations', function ($table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->string('locale', 10);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
            
            $table->unique(['category_id', 'locale']);
            $table->index('locale');
        });
        
        // Add currency_code to orders table
        Schema::table('orders', function ($table) {
            $table->string('currency_code', 3)->after('grand_total')->default('USD');
            $table->decimal('exchange_rate', 10, 6)->after('currency_code')->default(1.000000);
        });
        
        // Add locale to customers table
        Schema::table('customers', function ($table) {
            $table->string('locale', 10)->after('email')->nullable();
            $table->string('currency_code', 3)->after('locale')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function ($table) {
            $table->dropColumn(['locale', 'currency_code']);
        });
        
        Schema::table('orders', function ($table) {
            $table->dropColumn(['currency_code', 'exchange_rate']);
        });
        
        Schema::dropIfExists('category_translations');
        Schema::dropIfExists('product_translations');
    }
};