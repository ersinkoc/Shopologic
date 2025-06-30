<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedInventory\Models;

use Shopologic\Core\Database\Model;

class StockLevel extends Model
{
    protected string $table = 'stock_levels';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'inventory_item_id',
        'minimum_stock',
        'maximum_stock',
        'reorder_point',
        'reorder_quantity',
        'safety_stock',
        'lead_time_days',
        'supplier_id',
        'cost_per_unit',
        'last_ordered_at',
        'last_received_at',
        'auto_reorder',
        'seasonal_adjustments',
        'forecasting_model',
        'abc_classification',
        'xyz_classification',
        'velocity_score',
        'notes'
    ];

    protected array $casts = [
        'inventory_item_id' => 'integer',
        'minimum_stock' => 'integer',
        'maximum_stock' => 'integer',
        'reorder_point' => 'integer',
        'reorder_quantity' => 'integer',
        'safety_stock' => 'integer',
        'lead_time_days' => 'integer',
        'supplier_id' => 'integer',
        'cost_per_unit' => 'decimal:2',
        'last_ordered_at' => 'datetime',
        'last_received_at' => 'datetime',
        'auto_reorder' => 'boolean',
        'seasonal_adjustments' => 'json',
        'velocity_score' => 'decimal:2'
    ];

    /**
     * ABC Classifications
     */
    const ABC_A = 'A'; // High value items
    const ABC_B = 'B'; // Medium value items  
    const ABC_C = 'C'; // Low value items

    /**
     * XYZ Classifications
     */
    const XYZ_X = 'X'; // Steady demand
    const XYZ_Y = 'Y'; // Variable demand
    const XYZ_Z = 'Z'; // Sporadic demand

    /**
     * Get inventory item
     */
    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * Get supplier
     */
    public function supplier()
    {
        return $this->belongsTo('Shopologic\Core\Models\Supplier', 'supplier_id');
    }

    /**
     * Get reorder suggestions
     */
    public function reorderSuggestions()
    {
        return $this->hasMany(ReorderSuggestion::class, 'stock_level_id');
    }

    /**
     * Scope items needing reorder
     */
    public function scopeNeedsReorder($query)
    {
        return $query->whereHas('inventoryItem', function($q) {
            $q->whereRaw('quantity_on_hand <= reorder_point');
        });
    }

    /**
     * Scope by ABC classification
     */
    public function scopeByAbcClass($query, string $class)
    {
        return $query->where('abc_classification', $class);
    }

    /**
     * Scope by XYZ classification
     */
    public function scopeByXyzClass($query, string $class)
    {
        return $query->where('xyz_classification', $class);
    }

    /**
     * Scope auto reorder enabled
     */
    public function scopeAutoReorder($query)
    {
        return $query->where('auto_reorder', true);
    }

    /**
     * Check if item needs reorder
     */
    public function needsReorder(): bool
    {
        $currentStock = $this->inventoryItem->quantity_on_hand ?? 0;
        return $currentStock <= $this->reorder_point;
    }

    /**
     * Check if overstocked
     */
    public function isOverstocked(): bool
    {
        if ($this->maximum_stock <= 0) {
            return false;
        }
        
        $currentStock = $this->inventoryItem->quantity_on_hand ?? 0;
        return $currentStock > $this->maximum_stock;
    }

    /**
     * Check if understocked
     */
    public function isUnderstocked(): bool
    {
        $currentStock = $this->inventoryItem->quantity_on_hand ?? 0;
        return $currentStock < $this->minimum_stock;
    }

    /**
     * Get stock status
     */
    public function getStockStatus(): string
    {
        if ($this->needsReorder()) {
            return 'reorder_needed';
        }
        
        if ($this->isOverstocked()) {
            return 'overstocked';
        }
        
        if ($this->isUnderstocked()) {
            return 'understocked';
        }
        
        return 'optimal';
    }

    /**
     * Get recommended order quantity
     */
    public function getRecommendedOrderQuantity(): int
    {
        if (!$this->needsReorder()) {
            return 0;
        }
        
        $currentStock = $this->inventoryItem->quantity_on_hand ?? 0;
        $shortage = $this->reorder_point - $currentStock;
        
        // Use predefined reorder quantity or calculate based on shortage
        return max($this->reorder_quantity, $shortage + $this->safety_stock);
    }

    /**
     * Calculate average daily demand
     */
    public function getAverageDailyDemand(int $days = 30): float
    {
        $movements = $this->inventoryItem
            ->movements()
            ->where('type', 'sale')
            ->where('movement_date', '>=', now()->subDays($days))
            ->sum('quantity');
        
        return abs($movements) / $days;
    }

    /**
     * Calculate optimal reorder point
     */
    public function calculateOptimalReorderPoint(int $days = 30): int
    {
        $avgDailyDemand = $this->getAverageDailyDemand($days);
        $leadTimeDemand = $avgDailyDemand * $this->lead_time_days;
        
        return (int)ceil($leadTimeDemand + $this->safety_stock);
    }

    /**
     * Calculate optimal reorder quantity (EOQ)
     */
    public function calculateEOQ(float $annualDemand, float $orderingCost = 50): int
    {
        if ($this->cost_per_unit <= 0 || $annualDemand <= 0) {
            return $this->reorder_quantity;
        }
        
        $holdingCostRate = 0.25; // 25% annual holding cost
        $holdingCost = $this->cost_per_unit * $holdingCostRate;
        
        $eoq = sqrt((2 * $annualDemand * $orderingCost) / $holdingCost);
        
        return (int)ceil($eoq);
    }

    /**
     * Update ABC classification
     */
    public function updateAbcClassification(): void
    {
        $annualValue = $this->getAnnualValue();
        
        // This would typically be calculated relative to other items
        // For now, use simple thresholds
        if ($annualValue >= 10000) {
            $this->abc_classification = self::ABC_A;
        } elseif ($annualValue >= 1000) {
            $this->abc_classification = self::ABC_B;
        } else {
            $this->abc_classification = self::ABC_C;
        }
        
        $this->save();
    }

    /**
     * Update XYZ classification
     */
    public function updateXyzClassification(): void
    {
        $demandVariability = $this->getDemandVariability();
        
        if ($demandVariability <= 0.5) {
            $this->xyz_classification = self::XYZ_X;
        } elseif ($demandVariability <= 1.0) {
            $this->xyz_classification = self::XYZ_Y;
        } else {
            $this->xyz_classification = self::XYZ_Z;
        }
        
        $this->save();
    }

    /**
     * Get annual value
     */
    public function getAnnualValue(): float
    {
        $annualDemand = $this->getAverageDailyDemand() * 365;
        return $annualDemand * $this->cost_per_unit;
    }

    /**
     * Get demand variability (coefficient of variation)
     */
    public function getDemandVariability(int $days = 90): float
    {
        $movements = $this->inventoryItem
            ->movements()
            ->where('type', 'sale')
            ->where('movement_date', '>=', now()->subDays($days))
            ->pluck('quantity')
            ->map(fn($q) => abs($q))
            ->toArray();
        
        if (count($movements) < 2) {
            return 0;
        }
        
        $mean = array_sum($movements) / count($movements);
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $movements)) / count($movements);
        $stdDev = sqrt($variance);
        
        return $mean > 0 ? $stdDev / $mean : 0;
    }

    /**
     * Get velocity score
     */
    public function calculateVelocityScore(): float
    {
        $avgDailyDemand = $this->getAverageDailyDemand();
        $currentStock = $this->inventoryItem->quantity_on_hand ?? 0;
        
        if ($currentStock <= 0) {
            return 0;
        }
        
        return $avgDailyDemand / $currentStock;
    }

    /**
     * Update velocity score
     */
    public function updateVelocityScore(): void
    {
        $this->velocity_score = $this->calculateVelocityScore();
        $this->save();
    }

    /**
     * Get seasonal adjustment for month
     */
    public function getSeasonalAdjustment(int $month): float
    {
        $adjustments = $this->seasonal_adjustments ?? [];
        return $adjustments[$month] ?? 1.0;
    }

    /**
     * Apply seasonal adjustment to reorder point
     */
    public function getSeasonalReorderPoint(): int
    {
        $currentMonth = (int)now()->format('n');
        $adjustment = $this->getSeasonalAdjustment($currentMonth);
        
        return (int)ceil($this->reorder_point * $adjustment);
    }

    /**
     * Get stock level summary
     */
    public function getSummary(): array
    {
        return [
            'current_stock' => $this->inventoryItem->quantity_on_hand ?? 0,
            'minimum_stock' => $this->minimum_stock,
            'maximum_stock' => $this->maximum_stock,
            'reorder_point' => $this->reorder_point,
            'seasonal_reorder_point' => $this->getSeasonalReorderPoint(),
            'safety_stock' => $this->safety_stock,
            'reorder_quantity' => $this->reorder_quantity,
            'recommended_quantity' => $this->getRecommendedOrderQuantity(),
            'status' => $this->getStockStatus(),
            'abc_class' => $this->abc_classification,
            'xyz_class' => $this->xyz_classification,
            'velocity_score' => $this->velocity_score,
            'avg_daily_demand' => $this->getAverageDailyDemand(),
            'lead_time_days' => $this->lead_time_days,
            'last_ordered' => $this->last_ordered_at?->format('Y-m-d'),
            'last_received' => $this->last_received_at?->format('Y-m-d')
        ];
    }
}