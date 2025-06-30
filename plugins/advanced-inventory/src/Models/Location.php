<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedInventory\Models;

use Shopologic\Core\Database\Model;

class Location extends Model
{
    protected string $table = 'inventory_locations';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'code',
        'name',
        'type',
        'parent_id',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'phone',
        'email',
        'manager_id',
        'is_active',
        'is_default',
        'can_ship',
        'can_receive',
        'priority',
        'capacity',
        'current_usage',
        'operating_hours',
        'timezone',
        'settings'
    ];

    protected array $casts = [
        'parent_id' => 'integer',
        'manager_id' => 'integer',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'can_ship' => 'boolean',
        'can_receive' => 'boolean',
        'priority' => 'integer',
        'capacity' => 'integer',
        'current_usage' => 'integer',
        'operating_hours' => 'json',
        'settings' => 'json'
    ];

    /**
     * Location types
     */
    const TYPE_WAREHOUSE = 'warehouse';
    const TYPE_STORE = 'store';
    const TYPE_DISTRIBUTION_CENTER = 'distribution_center';
    const TYPE_SUPPLIER = 'supplier';
    const TYPE_CUSTOMER = 'customer';
    const TYPE_TRANSIT = 'transit';
    const TYPE_VIRTUAL = 'virtual';

    /**
     * Get parent location
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get child locations
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Get manager
     */
    public function manager()
    {
        return $this->belongsTo('Shopologic\Core\Models\User', 'manager_id');
    }

    /**
     * Get inventory items
     */
    public function inventoryItems()
    {
        return $this->hasMany(InventoryItem::class, 'location_id');
    }

    /**
     * Get incoming movements
     */
    public function incomingMovements()
    {
        return $this->hasMany(InventoryMovement::class, 'to_location_id');
    }

    /**
     * Get outgoing movements
     */
    public function outgoingMovements()
    {
        return $this->hasMany(InventoryMovement::class, 'from_location_id');
    }

    /**
     * Get location zones
     */
    public function zones()
    {
        return $this->hasMany(LocationZone::class, 'location_id');
    }

    /**
     * Scope active locations
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
     * Scope can ship
     */
    public function scopeCanShip($query)
    {
        return $query->where('can_ship', true);
    }

    /**
     * Scope can receive
     */
    public function scopeCanReceive($query)
    {
        return $query->where('can_receive', true);
    }

    /**
     * Check if location is warehouse
     */
    public function isWarehouse(): bool
    {
        return $this->type === self::TYPE_WAREHOUSE;
    }

    /**
     * Check if location is store
     */
    public function isStore(): bool
    {
        return $this->type === self::TYPE_STORE;
    }

    /**
     * Check if location is distribution center
     */
    public function isDistributionCenter(): bool
    {
        return $this->type === self::TYPE_DISTRIBUTION_CENTER;
    }

    /**
     * Check if location is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Check if location is default
     */
    public function isDefault(): bool
    {
        return $this->is_default;
    }

    /**
     * Get full address
     */
    public function getFullAddress(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country
        ]);
        
        return implode(', ', $parts);
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
     * Get operating hours for day
     */
    public function getOperatingHours(string $day): ?array
    {
        $hours = $this->operating_hours ?? [];
        return $hours[$day] ?? null;
    }

    /**
     * Check if open at time
     */
    public function isOpenAt(\DateTime $dateTime): bool
    {
        $hours = $this->operating_hours ?? [];
        $day = strtolower($dateTime->format('l'));
        
        if (!isset($hours[$day]) || !$hours[$day]['open']) {
            return false;
        }
        
        $currentTime = $dateTime->format('H:i');
        $openTime = $hours[$day]['open_time'] ?? '00:00';
        $closeTime = $hours[$day]['close_time'] ?? '23:59';
        
        return $currentTime >= $openTime && $currentTime <= $closeTime;
    }

    /**
     * Get inventory value
     */
    public function getInventoryValue(): float
    {
        return $this->inventoryItems()
            ->sum(DB::raw('quantity_on_hand * unit_cost'));
    }

    /**
     * Get inventory count
     */
    public function getInventoryCount(): int
    {
        return $this->inventoryItems()
            ->sum('quantity_on_hand');
    }

    /**
     * Get unique SKU count
     */
    public function getSkuCount(): int
    {
        return $this->inventoryItems()
            ->distinct('sku')
            ->count();
    }

    /**
     * Get low stock items
     */
    public function getLowStockItems(): array
    {
        return $this->inventoryItems()
            ->whereRaw('quantity_on_hand <= reorder_point')
            ->get()
            ->toArray();
    }

    /**
     * Get location statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_value' => $this->getInventoryValue(),
            'total_items' => $this->getInventoryCount(),
            'unique_skus' => $this->getSkuCount(),
            'capacity_usage' => $this->getCapacityUsage(),
            'low_stock_count' => count($this->getLowStockItems()),
            'movements_today' => $this->getMovementsCount('today'),
            'movements_week' => $this->getMovementsCount('week'),
            'movements_month' => $this->getMovementsCount('month')
        ];
    }

    /**
     * Get movements count for period
     */
    private function getMovementsCount(string $period): int
    {
        $date = match($period) {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => now()->startOfDay()
        };
        
        $incoming = $this->incomingMovements()
            ->where('movement_date', '>=', $date)
            ->count();
            
        $outgoing = $this->outgoingMovements()
            ->where('movement_date', '>=', $date)
            ->count();
            
        return $incoming + $outgoing;
    }

    /**
     * Activate location
     */
    public function activate(): void
    {
        $this->is_active = true;
        $this->save();
    }

    /**
     * Deactivate location
     */
    public function deactivate(): void
    {
        $this->is_active = false;
        $this->save();
    }

    /**
     * Set as default
     */
    public function setAsDefault(): void
    {
        // Remove default from other locations
        self::where('is_default', true)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);
        
        $this->is_default = true;
        $this->save();
    }

    /**
     * Get hierarchical path
     */
    public function getPath(): array
    {
        $path = [];
        $location = $this;
        
        while ($location) {
            array_unshift($path, $location);
            $location = $location->parent;
        }
        
        return $path;
    }

    /**
     * Get path string
     */
    public function getPathString(string $separator = ' > '): string
    {
        $path = $this->getPath();
        return implode($separator, array_map(fn($loc) => $loc->name, $path));
    }
}