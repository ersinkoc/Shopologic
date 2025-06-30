<?php


declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce;
declare(strict_types=1);
use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema\Schema;
use Shopologic\Core\Database\Schema\Blueprint;

class CreateCustomersTable extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function(Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->string('email', 255)->unique();
            $table->string('password', 255);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('phone', 50)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->index();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->boolean('accepts_marketing')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->integer('order_count')->default(0);
            $table->decimal('average_order_value', 10, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->json('preferences')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'created_at']);
            $table->index('email_verified_at');
            $table->index('accepts_marketing');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
}