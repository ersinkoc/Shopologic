<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedInventory\Repositories;

use Shopologic\Core\Database\Repository;
use Shopologic\Core\Database\DB;

class InventoryRepository extends Repository
{
    protected string $table = 'inventory_items';
    protected string $primaryKey = 'id';

    /**
     * Get inventory by product and location
     */
    public function getByProductAndLocation(int $productId, int $locationId): ?array
    {
        return DB::table($this->table)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->first();
    }

    /**
     * Get inventory by SKU
     */
    public function getBySku(string $sku): ?array
    {
        return DB::table($this->table)
            ->where('sku', $sku)
            ->first();
    }

    /**
     * Get all inventory for a product
     */
    public function getByProduct(int $productId): array
    {
        return DB::table($this->table)
            ->where('product_id', $productId)
            ->get();
    }

    /**
     * Get all inventory for a location
     */
    public function getByLocation(int $locationId): array
    {
        return DB::table($this->table)
            ->where('location_id', $locationId)
            ->orderBy('product_id')
            ->get();
    }

    /**
     * Get low stock items
     */
    public function getLowStockItems(int $threshold = null): array
    {
        $query = DB::table($this->table . ' as i')
            ->join('products as p', 'i.product_id', '=', 'p.id')
            ->select('i.*', 'p.name as product_name', 'p.sku as product_sku');

        if ($threshold !== null) {
            $query->where('i.quantity', '<=', $threshold);
        } else {
            $query->whereRaw('i.quantity <= i.min_quantity');
        }

        return $query->where('i.track_quantity', true)
            ->orderBy('i.quantity')
            ->get();
    }

    /**
     * Get out of stock items
     */
    public function getOutOfStockItems(): array
    {
        return DB::table($this->table . ' as i')
            ->join('products as p', 'i.product_id', '=', 'p.id')
            ->select('i.*', 'p.name as product_name', 'p.sku as product_sku')
            ->where('i.quantity', '<=', 0)
            ->where('i.track_quantity', true)
            ->orderBy('p.name')
            ->get();
    }

    /**
     * Update quantity
     */
    public function updateQuantity(int $id, float $quantity): bool
    {
        return DB::table($this->table)
            ->where('id', $id)
            ->update([
                'quantity' => $quantity,
                'last_updated' => now()
            ]) > 0;
    }

    /**
     * Adjust quantity
     */
    public function adjustQuantity(int $id, float $adjustment): bool
    {
        return DB::table($this->table)
            ->where('id', $id)
            ->increment('quantity', $adjustment) > 0;
    }

    /**
     * Reserve quantity
     */
    public function reserveQuantity(int $id, float $quantity): bool
    {
        return DB::table($this->table)
            ->where('id', $id)
            ->where('quantity', '>=', $quantity)
            ->update([
                'quantity' => DB::raw("quantity - {$quantity}"),
                'reserved_quantity' => DB::raw("reserved_quantity + {$quantity}"),
                'last_updated' => now()
            ]) > 0;
    }

    /**
     * Release reserved quantity
     */
    public function releaseReservedQuantity(int $id, float $quantity): bool
    {
        return DB::table($this->table)
            ->where('id', $id)
            ->where('reserved_quantity', '>=', $quantity)
            ->update([
                'quantity' => DB::raw("quantity + {$quantity}"),
                'reserved_quantity' => DB::raw("reserved_quantity - {$quantity}"),
                'last_updated' => now()
            ]) > 0;
    }

    /**
     * Commit reserved quantity
     */
    public function commitReservedQuantity(int $id, float $quantity): bool
    {
        return DB::table($this->table)
            ->where('id', $id)
            ->where('reserved_quantity', '>=', $quantity)
            ->update([
                'reserved_quantity' => DB::raw("reserved_quantity - {$quantity}"),
                'last_updated' => now()
            ]) > 0;
    }

    /**
     * Get inventory value by location
     */
    public function getInventoryValueByLocation(int $locationId = null): float
    {
        $query = DB::table($this->table)
            ->selectRaw('SUM(quantity * unit_cost) as total_value');

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        $result = $query->first();
        return $result ? (float)$result['total_value'] : 0.0;
    }

    /**
     * Get inventory metrics
     */
    public function getInventoryMetrics(): array
    {
        return [
            'total_items' => $this->count(),
            'total_quantity' => DB::table($this->table)->sum('quantity'),
            'total_value' => $this->getInventoryValueByLocation(),
            'low_stock_count' => count($this->getLowStockItems()),
            'out_of_stock_count' => count($this->getOutOfStockItems()),
            'locations_count' => DB::table($this->table)->distinct('location_id')->count('location_id')
        ];
    }

    /**
     * Get inventory turnover
     */
    public function getInventoryTurnover(int $productId, int $days = 365): float
    {
        $soldQuantity = DB::table('inventory_movements')
            ->where('product_id', $productId)
            ->where('type', 'sale')
            ->where('created_at', '>=', date('Y-m-d', strtotime("-{$days} days")))
            ->sum('quantity');

        $avgInventory = DB::table($this->table)
            ->where('product_id', $productId)
            ->avg('quantity');

        return $avgInventory > 0 ? abs($soldQuantity) / $avgInventory : 0;
    }

    /**
     * Get products by availability
     */
    public function getProductsByAvailability(int $locationId = null): array
    {
        $query = DB::table($this->table . ' as i')
            ->join('products as p', 'i.product_id', '=', 'p.id')
            ->select(
                'p.id',
                'p.name',
                'p.sku',
                DB::raw('SUM(i.quantity) as total_quantity'),
                DB::raw('COUNT(DISTINCT i.location_id) as locations_count'),
                DB::raw('MIN(i.quantity) as min_location_quantity'),
                DB::raw('MAX(i.quantity) as max_location_quantity')
            );

        if ($locationId) {
            $query->where('i.location_id', $locationId);
        }

        return $query->groupBy('p.id', 'p.name', 'p.sku')
            ->having('total_quantity', '>', 0)
            ->orderBy('total_quantity', 'desc')
            ->get();
    }

    /**
     * Get expiring items
     */
    public function getExpiringItems(int $days = 30): array
    {
        return DB::table($this->table . ' as i')
            ->join('inventory_batches as b', 'i.id', '=', 'b.inventory_item_id')
            ->join('products as p', 'i.product_id', '=', 'p.id')
            ->select('i.*', 'b.batch_number', 'b.expiry_date', 'b.quantity as batch_quantity', 'p.name as product_name')
            ->where('b.expiry_date', '<=', date('Y-m-d', strtotime("+{$days} days")))
            ->where('b.expiry_date', '>', date('Y-m-d'))
            ->where('b.quantity', '>', 0)
            ->orderBy('b.expiry_date')
            ->get();
    }

    /**
     * Get inventory age report
     */
    public function getInventoryAgeReport(): array
    {
        return DB::table($this->table . ' as i')
            ->join('inventory_movements as m', function($join) {
                $join->on('i.id', '=', 'm.inventory_item_id')
                    ->where('m.type', '=', 'receipt')
                    ->where('m.quantity', '>', 0);
            })
            ->select(
                'i.id',
                'i.product_id',
                'i.quantity',
                DB::raw('DATEDIFF(NOW(), MAX(m.created_at)) as age_days'),
                DB::raw('CASE 
                    WHEN DATEDIFF(NOW(), MAX(m.created_at)) <= 30 THEN "0-30 days"
                    WHEN DATEDIFF(NOW(), MAX(m.created_at)) <= 60 THEN "31-60 days"
                    WHEN DATEDIFF(NOW(), MAX(m.created_at)) <= 90 THEN "61-90 days"
                    WHEN DATEDIFF(NOW(), MAX(m.created_at)) <= 180 THEN "91-180 days"
                    ELSE "Over 180 days"
                END as age_bracket')
            )
            ->where('i.quantity', '>', 0)
            ->groupBy('i.id', 'i.product_id', 'i.quantity')
            ->get();
    }

    /**
     * Get reorder suggestions
     */
    public function getReorderSuggestions(): array
    {
        return DB::table($this->table . ' as i')
            ->join('products as p', 'i.product_id', '=', 'p.id')
            ->leftJoin('suppliers as s', 'p.supplier_id', '=', 's.id')
            ->select(
                'i.*',
                'p.name as product_name',
                'p.sku as product_sku',
                's.name as supplier_name',
                DB::raw('(i.max_quantity - i.quantity) as suggested_order_quantity'),
                DB::raw('(i.max_quantity - i.quantity) * i.unit_cost as estimated_cost')
            )
            ->where('i.quantity', '<=', DB::raw('i.reorder_point'))
            ->where('i.track_quantity', true)
            ->orderBy('i.quantity')
            ->get();
    }

    /**
     * Bulk update inventory
     */
    public function bulkUpdate(array $updates): int
    {
        $updated = 0;
        
        DB::transaction(function() use ($updates, &$updated) {
            foreach ($updates as $update) {
                if (isset($update['id']) && isset($update['quantity'])) {
                    $result = $this->updateQuantity($update['id'], $update['quantity']);
                    if ($result) {
                        $updated++;
                    }
                }
            }
        });

        return $updated;
    }

    /**
     * Get ABC analysis
     */
    public function getAbcAnalysis(): array
    {
        $items = DB::table($this->table . ' as i')
            ->join('products as p', 'i.product_id', '=', 'p.id')
            ->leftJoin('inventory_movements as m', function($join) {
                $join->on('i.id', '=', 'm.inventory_item_id')
                    ->where('m.type', '=', 'sale')
                    ->where('m.created_at', '>=', date('Y-m-d', strtotime('-365 days')));
            })
            ->select(
                'i.id',
                'i.product_id',
                'p.name as product_name',
                'p.sku',
                'i.quantity',
                'i.unit_cost',
                DB::raw('COALESCE(SUM(ABS(m.quantity)), 0) as annual_usage'),
                DB::raw('COALESCE(SUM(ABS(m.quantity)) * i.unit_cost, 0) as annual_value')
            )
            ->groupBy('i.id', 'i.product_id', 'p.name', 'p.sku', 'i.quantity', 'i.unit_cost')
            ->orderBy('annual_value', 'desc')
            ->get();

        $totalValue = array_sum(array_column($items, 'annual_value'));
        $cumulativeValue = 0;
        $results = ['A' => [], 'B' => [], 'C' => []];

        foreach ($items as $item) {
            $cumulativeValue += $item['annual_value'];
            $percentage = $totalValue > 0 ? ($cumulativeValue / $totalValue) * 100 : 0;

            if ($percentage <= 80) {
                $results['A'][] = $item;
            } elseif ($percentage <= 95) {
                $results['B'][] = $item;
            } else {
                $results['C'][] = $item;
            }
        }

        return $results;
    }
}