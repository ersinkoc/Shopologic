<?php


declare(strict_types=1);

namespace Shopologic\Plugins\PaymentStripe;
declare(strict_types=1);

use Shopologic\Core\Database\Schema\Schema;
use Shopologic\Core\Database\Schema\Blueprint;
use Shopologic\Core\Database\Migration;

class CreateStripePaymentsTables extends Migration
{
    public function up(): void
    {
        // Stripe customers table
        Schema::create('stripe_customers', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->bigInteger('customer_id')->index();
            $table->string('stripe_id', 255)->unique();
            $table->string('email', 255);
            $table->string('name', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->json('metadata')->nullable();
            $table->string('default_payment_method', 255)->nullable();
            $table->boolean('livemode')->default(false);
            $table->timestamps();
            
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->index(['customer_id', 'stripe_id']);
        });

        // Stripe payments table
        Schema::create('stripe_payments', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->bigInteger('order_id')->index();
            $table->bigInteger('customer_id')->index();
            $table->bigInteger('stripe_customer_id')->nullable();
            $table->string('payment_intent_id', 255)->unique();
            $table->string('charge_id', 255)->nullable()->index();
            $table->decimal('amount', 10, 2);
            $table->decimal('captured_amount', 10, 2)->nullable();
            $table->string('currency', 3);
            $table->string('status', 50)->index();
            $table->string('capture_method', 20);
            $table->string('payment_method_id', 255)->nullable();
            $table->json('payment_method_details')->nullable();
            $table->json('metadata')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->boolean('livemode')->default(false);
            $table->timestamps();
            
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('stripe_customer_id')->references('id')->on('stripe_customers')->nullable();
            $table->index(['status', 'created_at']);
        });

        // Stripe payment methods table
        Schema::create('stripe_payment_methods', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->bigInteger('stripe_customer_id');
            $table->string('payment_method_id', 255)->unique();
            $table->string('type', 50);
            $table->json('card')->nullable();
            $table->json('billing_details')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('livemode')->default(false);
            $table->timestamps();
            
            $table->foreign('stripe_customer_id')->references('id')->on('stripe_customers')->onDelete('cascade');
            $table->index(['stripe_customer_id', 'type']);
        });

        // Stripe refunds table
        Schema::create('stripe_refunds', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->bigInteger('stripe_payment_id');
            $table->string('refund_id', 255)->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->string('status', 50);
            $table->string('reason', 100)->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->foreign('stripe_payment_id')->references('id')->on('stripe_payments')->onDelete('cascade');
            $table->index(['status', 'created_at']);
        });

        // Stripe webhooks table
        Schema::create('stripe_webhooks', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('event_id', 255)->unique();
            $table->string('event_type', 100)->index();
            $table->json('payload');
            $table->boolean('processed')->default(false)->index();
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->boolean('livemode')->default(false);
            $table->timestamps();
            
            $table->index(['event_type', 'processed', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhooks');
        Schema::dropIfExists('stripe_refunds');
        Schema::dropIfExists('stripe_payment_methods');
        Schema::dropIfExists('stripe_payments');
        Schema::dropIfExists('stripe_customers');
    }
}