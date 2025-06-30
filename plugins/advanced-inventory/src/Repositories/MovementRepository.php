<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedInventory\Repositories;

use Shopologic\Core\Database\Repository;
use Shopologic\Core\Database\DB;

class MovementRepository extends Repository
{
    protected string $table = 'inventory_movements';
    protected string $primaryKey = 'id';

    /**
     * Record inventory movement
     */
    public function recordMovement(array $data): array
    {
        $data['created_at'] = $data['created_at'] ?? now();
        $data['reference_number'] = $data['reference_number'] ?? $this->generateReferenceNumber($data['type']);
        
        return $this->create($data);
    }

    /**
     * Get movements by inventory item
     */
    public function getByInventoryItem(int $inventoryItemId, array $filters = []): array
    {
        $query = DB::table($this->table)
            ->where('inventory_item_id', $inventoryItemId);

        $this->applyFilters($query, $filters);

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get movements by product
     */
    public function getByProduct(int $productId, array $filters = []): array
    {
        $query = DB::table($this->table)
            ->where('product_id', $productId);

        $this->applyFilters($query, $filters);

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get movements by location
     */
    public function getByLocation(int $locationId, array $filters = []): array
    {
        $query = DB::table($this->table)
            ->where('location_id', $locationId);

        $this->applyFilters($query, $filters);

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get movements by type
     */
    public function getByType(string $type, array $filters = []): array
    {
        $query = DB::table($this->table)
            ->where('type', $type);

        $this->applyFilters($query, $filters);

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get movements by reference
     */
    public function getByReference(string $referenceType, int $referenceId): array
    {
        return DB::table($this->table)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get movement summary by type
     */
    public function getMovementSummary(array $filters = []): array
    {
        $query = DB::table($this->table)
            ->select(
                'type',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(ABS(quantity * unit_cost)) as total_value')
            );

        $this->applyFilters($query, $filters);

        return $query->groupBy('type')->get();
    }

    /**
     * Get daily movement report
     */
    public function getDailyMovementReport(\DateTime $date): array
    {
        return DB::table($this->table . ' as m')
            ->join('products as p', 'm.product_id', '=', 'p.id')
            ->select(
                'm.type',
                'p.name as product_name',
                'p.sku',
                'm.quantity',
                'm.unit_cost',
                DB::raw('m.quantity * m.unit_cost as value'),
                'm.location_id',
                'm.created_at'
            )
            ->whereDate('m.created_at', $date->format('Y-m-d'))
            ->orderBy('m.created_at', 'desc')
            ->get();
    }

    /**
     * Get movement trends
     */
    public function getMovementTrends(int $days = 30): array
    {
        return DB::table($this->table)
            ->select(
                DB::raw('DATE(created_at) as date'),
                'type',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(ABS(quantity * unit_cost)) as total_value')
            )
            ->where('created_at', '>=', date('Y-m-d', strtotime("-{$days} days")))
            ->groupBy(DB::raw('DATE(created_at)'), 'type')
            ->orderBy('date', 'desc')
            ->get();
    }

    /**
     * Get top moving products
     */
    public function getTopMovingProducts(string $type = 'sale', int $limit = 10, int $days = 30): array
    {
        return DB::table($this->table . ' as m')
            ->join('products as p', 'm.product_id', '=', 'p.id')
            ->select(
                'p.id',
                'p.name',
                'p.sku',
                DB::raw('COUNT(*) as movement_count'),
                DB::raw('SUM(ABS(m.quantity)) as total_quantity'),
                DB::raw('SUM(ABS(m.quantity * m.unit_cost)) as total_value')
            )
            ->where('m.type', $type)
            ->where('m.created_at', '>=', date('Y-m-d', strtotime("-{$days} days")))
            ->groupBy('p.id', 'p.name', 'p.sku')
            ->orderBy('total_quantity', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get inventory valuation history
     */
    public function getValuationHistory(int $productId = null, int $days = 30): array
    {
        $query = DB::table($this->table)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(CASE WHEN quantity > 0 THEN quantity * unit_cost ELSE 0 END) as inbound_value'),
                DB::raw('SUM(CASE WHEN quantity < 0 THEN ABS(quantity * unit_cost) ELSE 0 END) as outbound_value')
            )
            ->where('created_at', '>=', date('Y-m-d', strtotime("-{$days} days")));

        if ($productId) {
            $query->where('product_id', $productId);
        }

        return $query->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'desc')
            ->get();
    }

    /**
     * Get stock card for product
     */
    public function getStockCard(int $productId, int $locationId = null, \DateTime $from = null, \DateTime $to = null): array
    {
        $query = DB::table($this->table . ' as m')
            ->join('inventory_items as i', 'm.inventory_item_id', '=', 'i.id')
            ->select(
                'm.*',
                'i.location_id',
                DB::raw('(SELECT SUM(quantity) FROM ' . $this->table . ' WHERE inventory_item_id = m.inventory_item_id AND created_at <= m.created_at) as running_balance')
            )
            ->where('m.product_id', $productId);

        if ($locationId) {
            $query->where('i.location_id', $locationId);
        }

        if ($from) {
            $query->where('m.created_at', '>=', $from->format('Y-m-d 00:00:00'));
        }

        if ($to) {
            $query->where('m.created_at', '<=', $to->format('Y-m-d 23:59:59'));
        }

        return $query->orderBy('m.created_at')->get();
    }

    /**
     * Cancel movement
     */
    public function cancelMovement(int $movementId, string $reason): bool
    {
        $movement = $this->findById($movementId);
        if (!$movement || $movement['status'] === 'cancelled') {
            return false;
        }

        // Update movement status
        $this->update($movementId, [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_reason' => $reason
        ]);

        // Create reversal movement
        $reversalData = $movement;
        unset($reversalData['id'], $reversalData['created_at'], $reversalData['updated_at']);
        
        $reversalData['quantity'] = -$movement['quantity'];
        $reversalData['type'] = 'reversal';
        $reversalData['reference_type'] = 'movement';
        $reversalData['reference_id'] = $movementId;
        $reversalData['notes'] = "Reversal of movement #{$movementId}: {$reason}";

        $this->recordMovement($reversalData);

        return true;
    }

    /**
     * Get movement statistics
     */
    public function getMovementStatistics(array $filters = []): array
    {
        $query = DB::table($this->table);
        $this->applyFilters($query, $filters);

        $stats = $query->select(
            DB::raw('COUNT(*) as total_movements'),
            DB::raw('COUNT(DISTINCT product_id) as unique_products'),
            DB::raw('COUNT(DISTINCT location_id) as unique_locations'),
            DB::raw('SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END) as total_inbound'),
            DB::raw('SUM(CASE WHEN quantity < 0 THEN ABS(quantity) ELSE 0 END) as total_outbound'),
            DB::raw('SUM(CASE WHEN quantity > 0 THEN quantity * unit_cost ELSE 0 END) as total_inbound_value'),
            DB::raw('SUM(CASE WHEN quantity < 0 THEN ABS(quantity * unit_cost) ELSE 0 END) as total_outbound_value')
        )->first();

        return (array)$stats;
    }

    /**
     * Bulk record movements
     */
    public function bulkRecord(array $movements): array
    {
        $recorded = [];
        
        DB::transaction(function() use ($movements, &$recorded) {
            foreach ($movements as $movement) {
                $recorded[] = $this->recordMovement($movement);
            }
        });

        return $recorded;
    }

    /**
     * Apply filters to query
     */
    private function applyFilters($query, array $filters): void
    {
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        if (isset($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
    }

    /**
     * Generate reference number
     */
    private function generateReferenceNumber(string $type): string
    {
        $prefix = match($type) {
            'receipt' => 'RCP',
            'sale' => 'SAL',
            'transfer' => 'TRF',
            'adjustment' => 'ADJ',
            'return' => 'RTN',
            'damage' => 'DMG',
            'production' => 'PRD',
            'assembly' => 'ASM',
            default => 'MOV'
        };

        return $prefix . '-' . date('Ymd') . '-' . str_pad((string)rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
}