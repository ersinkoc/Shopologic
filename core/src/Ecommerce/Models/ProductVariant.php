<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Models;

use Shopologic\Core\Database\Model;

class ProductVariant extends Model
{
    protected string $table = 'product_variants';
    
    protected array $fillable = [
        'product_id',
        'sku',
        'name',
        'price',
        'compare_price',
        'cost',
        'weight',
        'quantity',
        'position',
        'is_active',
    ];
    
    protected array $casts = [
        'product_id' => 'integer',
        'price' => 'decimal:2',
        'compare_price' => 'decimal:2',
        'cost' => 'decimal:2',
        'weight' => 'decimal:3',
        'quantity' => 'integer',
        'position' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get variant options
     */
    public function options()
    {
        return $this->hasMany(ProductVariantOption::class, 'variant_id');
    }

    /**
     * Get formatted name with options
     */
    public function getFullName(): string
    {
        $optionValues = [];
        
        foreach ($this->options as $option) {
            $optionValues[] = $option->value;
        }
        
        if (empty($optionValues)) {
            return $this->name ?? $this->product->name;
        }
        
        return $this->product->name . ' - ' . implode(' / ', $optionValues);
    }

    /**
     * Check if variant is in stock
     */
    public function inStock(): bool
    {
        $product = $this->product;
        
        if (!$product->track_quantity) {
            return true;
        }
        
        return $this->quantity > 0 || $product->allow_backorder;
    }

    /**
     * Get available quantity (accounting for reserved stock)
     * BUG-FUNC-002 FIX: Now properly subtracts reserved_quantity
     */
    public function getAvailableQuantity(): int
    {
        if (!$this->product->track_quantity) {
            return PHP_INT_MAX;
        }

        $reserved = $this->reserved_quantity ?? 0;
        return max(0, $this->quantity - $reserved);
    }

    /**
     * Decrease stock (with transaction and row-level locking to prevent race conditions)
     * BUG-FUNC-001 FIX: Added database transaction with SELECT FOR UPDATE to prevent overselling
     */
    public function decreaseStock(int $quantity): bool
    {
        $product = $this->product;

        if (!$product->track_quantity) {
            return true;
        }

        // Use database transaction with row-level locking to prevent race conditions
        $connection = $this->getConnection();

        try {
            // Start transaction
            $connection->beginTransaction();

            // Lock the row for update (SELECT FOR UPDATE)
            $query = "SELECT quantity, reserved_quantity FROM {$this->table} WHERE id = ? FOR UPDATE";
            $result = $connection->query($query, [$this->id]);
            $row = $result->fetch();

            if (!$row) {
                $connection->rollback();
                return false;
            }

            $currentQuantity = (int) $row['quantity'];
            $reservedQuantity = (int) ($row['reserved_quantity'] ?? 0);
            $availableQuantity = $currentQuantity - $reservedQuantity;

            // Check if we have enough available stock
            if ($availableQuantity < $quantity && !$product->allow_backorder) {
                $connection->rollback();
                return false;
            }

            // Prevent selling when available quantity is 0 or negative
            if ($availableQuantity <= 0 && !$product->allow_backorder) {
                $connection->rollback();
                return false;
            }

            // Update quantity
            $newQuantity = $currentQuantity - $quantity;
            $updateQuery = "UPDATE {$this->table} SET quantity = ? WHERE id = ?";
            $updateResult = $connection->query($updateQuery, [$newQuantity, $this->id]);

            if ($updateResult) {
                $connection->commit();
                // Update model instance
                $this->quantity = $newQuantity;
                return true;
            } else {
                $connection->rollback();
                return false;
            }
        } catch (\Exception $e) {
            $connection->rollback();
            error_log("ProductVariant stock decrease failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Increase stock
     */
    public function increaseStock(int $quantity): bool
    {
        if (!$this->product->track_quantity) {
            return true;
        }
        
        $this->quantity += $quantity;
        return $this->save();
    }
}