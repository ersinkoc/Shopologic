<?php


declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce;
declare(strict_types=1);
use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema\Schema;
use Shopologic\Core\Database\Schema\Blueprint;

class CreateOrderStatusHistoryTable extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_history', function(Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->primary();
            $table->bigInteger('order_id');
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50);
            $table->text('comment')->nullable();
            $table->bigInteger('user_id')->nullable();
            $table->timestamp('created_at');
            
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->index(['order_id', 'created_at']);
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
    }
}