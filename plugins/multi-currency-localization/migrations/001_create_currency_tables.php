<?php


declare(strict_types=1);

namespace Shopologic\Plugins\MultiCurrencyLocalization;
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
        // Create currencies table
        Schema::create('currencies', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('code', 3)->unique(); // ISO 4217 currency code
            $table->string('name', 100);
            $table->string('symbol', 10);
            $table->string('symbol_position', 10)->default('before'); // before, after
            $table->integer('decimal_places')->default(2);
            $table->string('decimal_separator', 5)->default('.');
            $table->string('thousands_separator', 5)->default(',');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_base_currency')->default(false);
            $table->integer('sort_order')->default(0);
            $table->json('formatting_rules')->nullable(); // Additional formatting rules
            $table->timestamps();
            
            $table->index(['is_active', 'sort_order']);
            $table->index('is_base_currency');
        });

        // Create exchange rates table
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->decimal('rate', 20, 8); // High precision for exchange rates
            $table->decimal('inverse_rate', 20, 8); // Cached inverse rate
            $table->string('provider', 50)->nullable(); // Rate provider
            $table->timestamp('rate_date');
            $table->boolean('is_manual')->default(false);
            $table->json('provider_data')->nullable(); // Additional provider data
            $table->timestamps();
            
            $table->unique(['from_currency', 'to_currency', 'rate_date']);
            $table->foreign('from_currency')->references('code')->on('currencies')->onDelete('cascade');
            $table->foreign('to_currency')->references('code')->on('currencies')->onDelete('cascade');
            $table->index(['from_currency', 'to_currency']);
            $table->index('rate_date');
        });

        // Create currency history table
        Schema::create('currency_history', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('currency_code', 3);
            $table->decimal('rate_to_base', 20, 8);
            $table->date('history_date');
            $table->decimal('high', 20, 8)->nullable();
            $table->decimal('low', 20, 8)->nullable();
            $table->decimal('open', 20, 8)->nullable();
            $table->decimal('close', 20, 8)->nullable();
            $table->decimal('volatility', 8, 4)->nullable(); // Volatility percentage
            $table->timestamps();
            
            $table->foreign('currency_code')->references('code')->on('currencies')->onDelete('cascade');
            $table->unique(['currency_code', 'history_date']);
            $table->index('history_date');
        });

        // Create localization settings table
        Schema::create('localization_settings', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('country_code', 2); // ISO 3166-1 alpha-2
            $table->string('language_code', 5); // ISO 639-1 with optional region
            $table->string('currency_code', 3);
            $table->string('timezone', 50);
            $table->string('date_format', 20)->default('Y-m-d');
            $table->string('time_format', 20)->default('H:i:s');
            $table->string('number_format', 50)->default('1,234.56');
            $table->boolean('is_rtl')->default(false);
            $table->json('formatting_preferences')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('currency_code')->references('code')->on('currencies')->onDelete('cascade');
            $table->unique(['country_code', 'language_code']);
            $table->index(['country_code', 'is_active']);
        });

        // Create translations table
        Schema::create('translations', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('key', 255); // Translation key
            $table->string('language_code', 5);
            $table->text('translation');
            $table->string('domain', 50)->default('general'); // Translation domain/namespace
            $table->boolean('is_fuzzy')->default(false); // Needs review
            $table->boolean('is_approved')->default(false);
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->json('metadata')->nullable(); // Additional translation data
            $table->timestamps();
            
            $table->unique(['key', 'language_code', 'domain']);
            $table->index(['language_code', 'domain']);
            $table->index(['is_approved', 'is_fuzzy']);
            $table->fullText(['key', 'translation']);
        });

        // Create regional pricing table
        Schema::create('regional_pricing', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('product_id');
            $table->string('country_code', 2)->nullable();
            $table->string('region', 100)->nullable(); // Geographic region
            $table->string('currency_code', 3);
            $table->decimal('base_price', 12, 4);
            $table->decimal('adjusted_price', 12, 4);
            $table->decimal('adjustment_factor', 8, 4)->default(1.0000); // Multiplier
            $table->enum('adjustment_type', ['percentage', 'fixed', 'factor'])->default('factor');
            $table->decimal('adjustment_value', 12, 4)->default(0);
            $table->string('pricing_strategy', 50)->default('standard');
            $table->date('effective_date');
            $table->date('expiry_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('conditions')->nullable(); // Pricing conditions
            $table->timestamps();
            
            $table->foreign('currency_code')->references('code')->on('currencies')->onDelete('cascade');
            $table->index(['product_id', 'country_code', 'currency_code']);
            $table->index(['effective_date', 'expiry_date']);
            $table->index(['is_active', 'effective_date']);
        });

        // Create tax rules table
        Schema::create('tax_rules', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('name', 255);
            $table->string('country_code', 2);
            $table->string('state_code', 10)->nullable();
            $table->string('tax_type', 50); // VAT, GST, Sales Tax, etc.
            $table->decimal('rate', 8, 4); // Tax rate percentage
            $table->boolean('is_compound')->default(false); // Compound tax
            $table->integer('priority')->default(0);
            $table->json('conditions')->nullable(); // Product categories, customer types, etc.
            $table->date('effective_date');
            $table->date('expiry_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('exemptions')->nullable(); // Tax exemptions
            $table->timestamps();
            
            $table->index(['country_code', 'state_code', 'is_active']);
            $table->index(['effective_date', 'expiry_date']);
            $table->index(['tax_type', 'is_active']);
        });

        // Create tax rates table (for quick lookups)
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('country_code', 2);
            $table->string('state_code', 10)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('tax_jurisdiction', 100);
            $table->decimal('combined_rate', 8, 4); // Combined tax rate
            $table->decimal('state_rate', 8, 4)->default(0);
            $table->decimal('county_rate', 8, 4)->default(0);
            $table->decimal('city_rate', 8, 4)->default(0);
            $table->decimal('special_rate', 8, 4)->default(0);
            $table->boolean('freight_taxable')->default(true);
            $table->date('last_updated');
            $table->timestamps();
            
            $table->index(['country_code', 'state_code', 'city']);
            $table->index(['postal_code', 'country_code']);
            $table->index('last_updated');
        });

        // Create country settings table
        Schema::create('country_settings', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('country_code', 2)->unique();
            $table->string('country_name', 100);
            $table->string('default_currency', 3);
            $table->string('default_language', 5);
            $table->boolean('shipping_enabled')->default(true);
            $table->boolean('billing_enabled')->default(true);
            $table->json('supported_currencies')->nullable();
            $table->json('supported_languages')->nullable();
            $table->json('payment_methods')->nullable();
            $table->json('shipping_methods')->nullable();
            $table->decimal('purchase_power_index', 8, 4)->nullable();
            $table->decimal('cost_of_living_index', 8, 4)->nullable();
            $table->boolean('requires_tax_id')->default(false);
            $table->boolean('requires_vat_id')->default(false);
            $table->json('compliance_requirements')->nullable();
            $table->timestamps();
            
            $table->foreign('default_currency')->references('code')->on('currencies')->onDelete('cascade');
            $table->index(['shipping_enabled', 'billing_enabled']);
        });

        // Create customer locations table
        Schema::create('customer_locations', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('session_id', 100)->nullable();
            $table->string('ip_address', 45);
            $table->string('country_code', 2)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('timezone', 50)->nullable();
            $table->string('isp', 255)->nullable();
            $table->string('detected_currency', 3)->nullable();
            $table->string('detected_language', 5)->nullable();
            $table->string('preferred_currency', 3)->nullable();
            $table->string('preferred_language', 5)->nullable();
            $table->json('location_data')->nullable(); // Full location data from provider
            $table->timestamp('detected_at');
            $table->boolean('is_current')->default(true);
            $table->timestamps();
            
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->index(['customer_id', 'is_current']);
            $table->index(['session_id', 'is_current']);
            $table->index(['ip_address', 'detected_at']);
            $table->index('country_code');
        });

        // Create price conversions table (for caching)
        Schema::create('price_conversions', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->decimal('original_amount', 12, 4);
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->decimal('converted_amount', 12, 4);
            $table->decimal('exchange_rate', 20, 8);
            $table->timestamp('conversion_date');
            $table->timestamp('expires_at');
            $table->string('cache_key', 255)->unique();
            $table->timestamps();
            
            $table->foreign('from_currency')->references('code')->on('currencies')->onDelete('cascade');
            $table->foreign('to_currency')->references('code')->on('currencies')->onDelete('cascade');
            $table->index(['from_currency', 'to_currency']);
            $table->index('expires_at');
            $table->index('conversion_date');
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('price_conversions');
        Schema::dropIfExists('customer_locations');
        Schema::dropIfExists('country_settings');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('regional_pricing');
        Schema::dropIfExists('translations');
        Schema::dropIfExists('localization_settings');
        Schema::dropIfExists('currency_history');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
    }
};