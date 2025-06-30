<?php


declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce;
declare(strict_types=1);
use Shopologic\Core\Database\Migration;
use Shopologic\Core\Database\Schema\Schema;
use Shopologic\Core\Database\Schema\Blueprint;

class CreateProductCategoriesTable extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function(Blueprint $table) {
            $table->bigInteger('product_id');
            $table->bigInteger('category_id');
            $table->timestamps();
            
            $table->primary(['product_id', 'category_id']);
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
}