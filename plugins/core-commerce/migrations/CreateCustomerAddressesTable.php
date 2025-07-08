<?php


declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce;

use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema\Schema;
use Shopologic\Core\Database\Schema\Blueprint;

class CreateCustomerAddressesTable extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function(Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->bigInteger('customer_id');
            $table->enum('type', ['billing', 'shipping', 'both'])->default('both');
            $table->string('name', 255)->nullable();
            $table->string('company', 255)->nullable();
            $table->string('line1', 255);
            $table->string('line2', 255)->nullable();
            $table->string('city', 100);
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 2);
            $table->string('phone', 50)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->index(['customer_id', 'type']);
            $table->index(['customer_id', 'is_default']);
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
}