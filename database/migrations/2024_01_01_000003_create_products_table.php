<?php

declare(strict_types=1);

use Shopologic\Core\Database\Migrations\Migration;
use Shopologic\Core\Database\Schema\Schema;
use Shopologic\Core\Database\Schema\Blueprint;
use Shopologic\Core\Database\DatabaseManager;

class CreateProductsTable extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 100)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('cost', 10, 2)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('category_id')->nullable();
            
            // Handle database-specific JSON columns
            $connection = app(DatabaseManager::class);
            $driver = $connection->getDriverName();
            
            if ($driver === 'pgsql') {
                // PostgreSQL supports JSONB for better performance
                $table->jsonb('attributes')->nullable();
                $table->jsonb('meta_data')->nullable();
            } else {
                // MySQL/MariaDB use regular JSON
                $table->json('attributes')->nullable();
                $table->json('meta_data')->nullable();
            }
            
            // Full-text search index (database-specific)
            if ($driver === 'pgsql') {
                // PostgreSQL uses GIN indexes for full-text search
                $table->index(['name', 'description'], 'products_fulltext_idx')->algorithm('gin');
            } elseif ($driver === 'mysql') {
                // MySQL uses FULLTEXT indexes
                $table->fullText(['name', 'description'], 'products_fulltext_idx');
            }
            
            $table->timestamps();
            
            // Regular indexes
            $table->index('slug');
            $table->index('sku');
            $table->index('category_id');
            $table->index('is_active');
            $table->index('created_at');
        });
        
        // Add database-specific features after table creation
        $connection = app(DatabaseManager::class);
        $driver = $connection->getDriverName();
        
        if ($driver === 'pgsql') {
            // PostgreSQL: Add check constraint for price
            $connection->statement('ALTER TABLE products ADD CONSTRAINT price_positive CHECK (price > 0)');
            
            // PostgreSQL: Create trigger for updated_at
            $connection->statement("
                CREATE OR REPLACE FUNCTION update_updated_at_column()
                RETURNS TRIGGER AS $$
                BEGIN
                    NEW.updated_at = CURRENT_TIMESTAMP;
                    RETURN NEW;
                END;
                $$ language 'plpgsql';
            ");
            
            $connection->statement("
                CREATE TRIGGER update_products_updated_at 
                BEFORE UPDATE ON products 
                FOR EACH ROW 
                EXECUTE FUNCTION update_updated_at_column();
            ");
        } elseif ($driver === 'mysql') {
            // MySQL: Add check constraint (MySQL 8.0.16+)
            try {
                $connection->statement('ALTER TABLE products ADD CONSTRAINT price_positive CHECK (price > 0)');
            } catch (\Exception $e) {
                // Older MySQL versions don't support CHECK constraints
            }
        }
    }

    public function down(): void
    {
        $connection = app(DatabaseManager::class);
        $driver = $connection->getDriverName();
        
        if ($driver === 'pgsql') {
            // Drop PostgreSQL-specific objects
            $connection->statement('DROP TRIGGER IF EXISTS update_products_updated_at ON products');
            $connection->statement('DROP FUNCTION IF EXISTS update_updated_at_column()');
        }
        
        Schema::dropIfExists('products');
    }
}