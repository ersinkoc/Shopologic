<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce\Services;

use Shopologic\Core\Database\DatabaseManager;

class InventoryService
{
    private DatabaseManager $db;
    
    public function __construct(DatabaseManager $db)
    {
        $this->db = $db;
    }
    
    public function checkStock(int $productId, int $quantity): bool
    {
        $product = $this->db->table('products')
            ->where('id', $productId)
            ->first();
            
        if (!$product || !$product['manage_stock']) {
            return true;
        }
        
        return $product['stock_quantity'] >= $quantity;
    }
    
    public function updateStock(int $productId, int $quantity, string $operation = 'subtract'): bool
    {
        if ($operation === 'subtract') {
            return $this->db->table('products')
                ->where('id', $productId)
                ->decrement('stock_quantity', $quantity) > 0;
        } else {
            return $this->db->table('products')
                ->where('id', $productId)
                ->increment('stock_quantity', $quantity) > 0;
        }
    }
}