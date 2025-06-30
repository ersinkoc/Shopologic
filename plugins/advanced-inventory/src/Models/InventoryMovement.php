<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedInventory\Models;

use Shopologic\Core\Database\Model;

class InventoryMovement extends Model
{
    protected string $table = 'inventory_movements';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'inventory_item_id',
        'type',
        'quantity',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'from_location_id',
        'to_location_id',
        'reason',
        'notes',
        'user_id',
        'batch_number',
        'expiry_date',
        'movement_date'
    ];

    protected array $casts = [
        'inventory_item_id' => 'integer',
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'reference_id' => 'integer',
        'from_location_id' => 'integer',
        'to_location_id' => 'integer',
        'user_id' => 'integer',
        'expiry_date' => 'date',
        'movement_date' => 'datetime'
    ];

    /**
     * Movement types
     */
    const TYPE_PURCHASE = 'purchase';
    const TYPE_SALE = 'sale';
    const TYPE_TRANSFER = 'transfer';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_RETURN = 'return';
    const TYPE_DAMAGE = 'damage';
    const TYPE_THEFT = 'theft';
    const TYPE_PRODUCTION = 'production';
    const TYPE_CONSUMPTION = 'consumption';

    /**
     * Get inventory item
     */
    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * Get from location
     */
    public function fromLocation()
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    /**
     * Get to location
     */
    public function toLocation()
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    /**
     * Get user who created movement
     */
    public function user()
    {
        return $this->belongsTo('Shopologic\Core\Models\User', 'user_id');
    }

    /**
     * Get reference model (polymorphic)
     */
    public function reference()
    {
        return $this->morphTo('reference');
    }

    /**
     * Scope by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope incoming movements
     */
    public function scopeIncoming($query)
    {
        return $query->whereIn('type', [
            self::TYPE_PURCHASE,
            self::TYPE_RETURN,
            self::TYPE_PRODUCTION,
            self::TYPE_ADJUSTMENT
        ])->where('quantity', '>', 0);
    }

    /**
     * Scope outgoing movements
     */
    public function scopeOutgoing($query)
    {
        return $query->whereIn('type', [
            self::TYPE_SALE,
            self::TYPE_DAMAGE,
            self::TYPE_THEFT,
            self::TYPE_CONSUMPTION
        ])->orWhere('quantity', '<', 0);
    }

    /**
     * Scope by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('movement_date', [$startDate, $endDate]);
    }

    /**
     * Check if movement is incoming
     */
    public function isIncoming(): bool
    {
        return in_array($this->type, [
            self::TYPE_PURCHASE,
            self::TYPE_RETURN,
            self::TYPE_PRODUCTION
        ]) || ($this->type === self::TYPE_ADJUSTMENT && $this->quantity > 0);
    }

    /**
     * Check if movement is outgoing
     */
    public function isOutgoing(): bool
    {
        return in_array($this->type, [
            self::TYPE_SALE,
            self::TYPE_DAMAGE,
            self::TYPE_THEFT,
            self::TYPE_CONSUMPTION
        ]) || ($this->type === self::TYPE_ADJUSTMENT && $this->quantity < 0);
    }

    /**
     * Check if movement is transfer
     */
    public function isTransfer(): bool
    {
        return $this->type === self::TYPE_TRANSFER;
    }

    /**
     * Get movement direction
     */
    public function getDirection(): string
    {
        if ($this->isIncoming()) {
            return 'in';
        }
        
        if ($this->isOutgoing()) {
            return 'out';
        }
        
        return 'transfer';
    }

    /**
     * Get absolute quantity
     */
    public function getAbsoluteQuantity(): int
    {
        return abs($this->quantity);
    }

    /**
     * Get movement value
     */
    public function getValue(): float
    {
        return $this->total_cost ?? ($this->quantity * $this->unit_cost);
    }

    /**
     * Get type label
     */
    public function getTypeLabel(): string
    {
        $labels = [
            self::TYPE_PURCHASE => 'Purchase',
            self::TYPE_SALE => 'Sale',
            self::TYPE_TRANSFER => 'Transfer',
            self::TYPE_ADJUSTMENT => 'Adjustment',
            self::TYPE_RETURN => 'Return',
            self::TYPE_DAMAGE => 'Damage',
            self::TYPE_THEFT => 'Theft',
            self::TYPE_PRODUCTION => 'Production',
            self::TYPE_CONSUMPTION => 'Consumption'
        ];
        
        return $labels[$this->type] ?? ucfirst($this->type);
    }

    /**
     * Get type color
     */
    public function getTypeColor(): string
    {
        $colors = [
            self::TYPE_PURCHASE => 'green',
            self::TYPE_SALE => 'blue',
            self::TYPE_TRANSFER => 'orange',
            self::TYPE_ADJUSTMENT => 'yellow',
            self::TYPE_RETURN => 'purple',
            self::TYPE_DAMAGE => 'red',
            self::TYPE_THEFT => 'red',
            self::TYPE_PRODUCTION => 'green',
            self::TYPE_CONSUMPTION => 'gray'
        ];
        
        return $colors[$this->type] ?? 'gray';
    }

    /**
     * Format for display
     */
    public function formatForDisplay(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'type_color' => $this->getTypeColor(),
            'direction' => $this->getDirection(),
            'quantity' => $this->quantity,
            'absolute_quantity' => $this->getAbsoluteQuantity(),
            'unit_cost' => $this->unit_cost,
            'total_value' => $this->getValue(),
            'reference' => $this->reference_type . '#' . $this->reference_id,
            'location' => $this->isTransfer() ? 
                $this->fromLocation->name . ' → ' . $this->toLocation->name : 
                ($this->toLocation->name ?? $this->fromLocation->name ?? 'N/A'),
            'reason' => $this->reason,
            'notes' => $this->notes,
            'batch_number' => $this->batch_number,
            'expiry_date' => $this->expiry_date,
            'user' => $this->user->name ?? 'System',
            'date' => $this->movement_date->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Can be reversed
     */
    public function canBeReversed(): bool
    {
        // Check if already reversed
        if ($this->reversed_at) {
            return false;
        }
        
        // Check if movement type allows reversal
        $reversibleTypes = [
            self::TYPE_SALE,
            self::TYPE_PURCHASE,
            self::TYPE_TRANSFER,
            self::TYPE_ADJUSTMENT
        ];
        
        return in_array($this->type, $reversibleTypes);
    }

    /**
     * Create reversal movement
     */
    public function createReversal(string $reason = null): self
    {
        if (!$this->canBeReversed()) {
            throw new \Exception('This movement cannot be reversed');
        }
        
        $reversal = $this->replicate();
        $reversal->quantity = -$this->quantity;
        $reversal->reason = $reason ?? 'Reversal of movement #' . $this->id;
        $reversal->reference_type = 'movement_reversal';
        $reversal->reference_id = $this->id;
        $reversal->movement_date = now();
        
        // Swap locations for transfers
        if ($this->isTransfer()) {
            $reversal->from_location_id = $this->to_location_id;
            $reversal->to_location_id = $this->from_location_id;
        }
        
        $reversal->save();
        
        // Mark original as reversed
        $this->reversed_at = now();
        $this->reversed_by = $reversal->id;
        $this->save();
        
        return $reversal;
    }
}