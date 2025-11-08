<?php

use Shopologic\Core\Database\Schema\Blueprint;
use Shopologic\Core\Database\Schema\Schema;

return new class {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('set null');
            
            // Personal information
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            
            // Customer status
            $table->boolean('is_active')->default(true);
            $table->boolean('accepts_marketing')->default(false);
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('active');
            
            // Statistics
            $table->integer('total_orders')->default(0);
            $table->decimal('total_spent', 10, 2)->default(0);
            $table->decimal('average_order_value', 10, 2)->default(0);
            
            // Additional information
            $table->date('date_of_birth')->nullable();
            $table->string('company_name')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            
            // Timestamps
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('email');
            $table->index('user_id');
            $table->index('store_id');
            $table->index('status');
            $table->index(['email', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
