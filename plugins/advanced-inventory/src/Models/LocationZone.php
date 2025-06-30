<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedInventory\Models;

use Shopologic\Core\Database\Model;

class LocationZone extends Model
{
    protected string $table = 'location_zones';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'location_id',
        'name',
        'code',
        'type',
        'description',
        'capacity',
        'current_usage',
        'temperature_min',
        'temperature_max',
        'humidity_min',
        'humidity_max',
        'is_active',
        'priority',
        'picking_sequence',
        'storage_conditions',
        'restrictions'
    ];

    protected array $casts = [
        'location_id' => 'integer',
        'capacity' => 'integer',
        'current_usage' => 'integer',
        'temperature_min' => 'decimal:2',
        'temperature_max' => 'decimal:2',
        'humidity_min' => 'decimal:2',
        'humidity_max' => 'decimal:2',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'picking_sequence' => 'integer',
        'storage_conditions' => 'json',
        'restrictions' => 'json'
    ];

    /**
     * Zone types
     */
    const TYPE_RECEIVING = 'receiving';
    const TYPE_STORAGE = 'storage';
    const TYPE_PICKING = 'picking';
    const TYPE_SHIPPING = 'shipping';
    const TYPE_QUARANTINE = 'quarantine';
    const TYPE_RETURNS = 'returns';
    const TYPE_COLD_STORAGE = 'cold_storage';
    const TYPE_HAZMAT = 'hazmat';

    /**
     * Get location
     */
    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * Get inventory items in this zone
     */
    public function inventoryItems()
    {
        return $this->hasMany(InventoryItem::class, 'zone_id');
    }

    /**
     * Get zone bins
     */
    public function bins()
    {
        return $this->hasMany(ZoneBin::class, 'zone_id');
    }

    /**
     * Scope active zones
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check if zone is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Get capacity usage percentage
     */
    public function getCapacityUsage(): float
    {
        if ($this->capacity <= 0) {
            return 0;
        }
        
        return ($this->current_usage / $this->capacity) * 100;
    }

    /**
     * Check if has capacity
     */
    public function hasCapacity(int $quantity = 1): bool
    {
        if ($this->capacity <= 0) {
            return true; // Unlimited capacity
        }
        
        return ($this->current_usage + $quantity) <= $this->capacity;
    }

    /**
     * Update usage
     */
    public function updateUsage(int $change): void
    {
        $this->current_usage = max(0, $this->current_usage + $change);
        $this->save();
    }

    /**
     * Check temperature compatibility
     */
    public function isTemperatureCompatible(float $temperature): bool
    {
        if ($this->temperature_min === null && $this->temperature_max === null) {
            return true;
        }
        
        $minOk = $this->temperature_min === null || $temperature >= $this->temperature_min;
        $maxOk = $this->temperature_max === null || $temperature <= $this->temperature_max;
        
        return $minOk && $maxOk;
    }

    /**
     * Check humidity compatibility
     */
    public function isHumidityCompatible(float $humidity): bool
    {
        if ($this->humidity_min === null && $this->humidity_max === null) {
            return true;
        }
        
        $minOk = $this->humidity_min === null || $humidity >= $this->humidity_min;
        $maxOk = $this->humidity_max === null || $humidity <= $this->humidity_max;
        
        return $minOk && $maxOk;
    }

    /**
     * Check if product can be stored
     */
    public function canStoreProduct(array $productConditions): bool
    {
        // Check temperature requirements
        if (isset($productConditions['temperature'])) {
            if (!$this->isTemperatureCompatible($productConditions['temperature'])) {
                return false;
            }
        }
        
        // Check humidity requirements
        if (isset($productConditions['humidity'])) {
            if (!$this->isHumidityCompatible($productConditions['humidity'])) {
                return false;
            }
        }
        
        // Check restrictions
        $restrictions = $this->restrictions ?? [];
        if (!empty($restrictions)) {
            foreach ($restrictions as $restriction) {
                if (!$this->meetsRestriction($restriction, $productConditions)) {
                    return false;
                }
            }
        }
        
        return true;
    }

    /**
     * Check if meets restriction
     */
    private function meetsRestriction(array $restriction, array $productConditions): bool
    {
        $type = $restriction['type'] ?? '';
        $value = $restriction['value'] ?? '';
        
        switch ($type) {
            case 'category':
                return in_array($productConditions['category'] ?? '', (array)$value);
            case 'hazmat':
                return ($productConditions['is_hazmat'] ?? false) === (bool)$value;
            case 'weight_limit':
                return ($productConditions['weight'] ?? 0) <= (float)$value;
            case 'size_limit':
                $dimensions = $productConditions['dimensions'] ?? [];
                $limits = $value;
                return ($dimensions['length'] ?? 0) <= ($limits['length'] ?? PHP_FLOAT_MAX) &&
                       ($dimensions['width'] ?? 0) <= ($limits['width'] ?? PHP_FLOAT_MAX) &&
                       ($dimensions['height'] ?? 0) <= ($limits['height'] ?? PHP_FLOAT_MAX);
            default:
                return true;
        }
    }

    /**
     * Get type label
     */
    public function getTypeLabel(): string
    {
        $labels = [
            self::TYPE_RECEIVING => 'Receiving',
            self::TYPE_STORAGE => 'Storage',
            self::TYPE_PICKING => 'Picking',
            self::TYPE_SHIPPING => 'Shipping',
            self::TYPE_QUARANTINE => 'Quarantine',
            self::TYPE_RETURNS => 'Returns',
            self::TYPE_COLD_STORAGE => 'Cold Storage',
            self::TYPE_HAZMAT => 'Hazmat'
        ];
        
        return $labels[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    /**
     * Get zone statistics
     */
    public function getStatistics(): array
    {
        return [
            'capacity_usage' => $this->getCapacityUsage(),
            'total_items' => $this->inventoryItems()->sum('quantity_on_hand'),
            'unique_skus' => $this->inventoryItems()->distinct('sku')->count(),
            'bin_count' => $this->bins()->count(),
            'active_bins' => $this->bins()->where('is_active', true)->count(),
            'temperature_range' => $this->temperature_min !== null || $this->temperature_max !== null ?
                ($this->temperature_min ?? 'N/A') . '°C to ' . ($this->temperature_max ?? 'N/A') . '°C' : 'N/A',
            'humidity_range' => $this->humidity_min !== null || $this->humidity_max !== null ?
                ($this->humidity_min ?? 'N/A') . '% to ' . ($this->humidity_max ?? 'N/A') . '%' : 'N/A'
        ];
    }

    /**
     * Get available bins
     */
    public function getAvailableBins(): array
    {
        return $this->bins()
            ->where('is_active', true)
            ->where('is_occupied', false)
            ->orderBy('bin_code')
            ->get()
            ->toArray();
    }

    /**
     * Get next picking sequence
     */
    public function getNextPickingSequence(): int
    {
        $maxSequence = $this->bins()->max('picking_sequence') ?? 0;
        return $maxSequence + 1;
    }

    /**
     * Activate zone
     */
    public function activate(): void
    {
        $this->is_active = true;
        $this->save();
    }

    /**
     * Deactivate zone
     */
    public function deactivate(): void
    {
        $this->is_active = false;
        $this->save();
    }

    /**
     * Get full path
     */
    public function getFullPath(): string
    {
        return $this->location->getPathString() . ' > ' . $this->name;
    }
}