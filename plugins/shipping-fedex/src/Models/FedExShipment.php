<?php

declare(strict_types=1);

namespace Shopologic\Plugins\ShippingFedEx\Models;

use Shopologic\Core\Database\Model;

class FedExShipment extends Model
{
    protected string $table = 'fedex_shipments';
    
    protected array $fillable = [
        'order_id',
        'tracking_number',
        'master_tracking_number',
        'service_type',
        'packaging_type',
        'rate',
        'currency',
        'status',
        'label_data',
        'label_format',
        'metadata',
        'shipped_at',
        'delivered_at',
        'cancelled_at'
    ];
    
    protected array $casts = [
        'rate' => 'decimal:2',
        'metadata' => 'json',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime'
    ];
    
    /**
     * Get the order
     */
    public function order()
    {
        return $this->belongsTo(\Shopologic\Core\Ecommerce\Models\Order::class);
    }
    
    /**
     * Check if shipment is active
     */
    public function isActive(): bool
    {
        return !in_array($this->status, ['delivered', 'cancelled']);
    }
    
    /**
     * Check if shipment is delivered
     */
    public function isDelivered(): bool
    {
        return $this->status === 'delivered' || $this->delivered_at !== null;
    }
    
    /**
     * Check if shipment can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['created', 'shipped']) && 
               !$this->isDelivered() && 
               $this->cancelled_at === null;
    }
    
    /**
     * Get days in transit
     */
    public function getDaysInTransit(): ?int
    {
        if (!$this->shipped_at) {
            return null;
        }
        
        $endDate = $this->delivered_at ?? now();
        
        return $this->shipped_at->diffInDays($endDate);
    }
    
    /**
     * Get service display name
     */
    public function getServiceDisplayName(): string
    {
        $names = [
            'FEDEX_GROUND' => 'FedEx Ground',
            'FEDEX_EXPRESS_SAVER' => 'FedEx Express Saver',
            'FEDEX_2_DAY' => 'FedEx 2Day',
            'FEDEX_2_DAY_AM' => 'FedEx 2Day A.M.',
            'STANDARD_OVERNIGHT' => 'FedEx Standard Overnight',
            'PRIORITY_OVERNIGHT' => 'FedEx Priority Overnight',
            'FIRST_OVERNIGHT' => 'FedEx First Overnight',
            'INTERNATIONAL_ECONOMY' => 'FedEx International Economy',
            'INTERNATIONAL_PRIORITY' => 'FedEx International Priority'
        ];
        
        return $names[$this->service_type] ?? $this->service_type;
    }
    
    /**
     * Get status display name
     */
    public function getStatusDisplayName(): string
    {
        $statuses = [
            'created' => 'Label Created',
            'shipped' => 'Shipped',
            'picked_up' => 'Picked Up',
            'in_transit' => 'In Transit',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivered',
            'exception' => 'Exception',
            'cancelled' => 'Cancelled',
            'return_to_sender' => 'Return to Sender'
        ];
        
        return $statuses[$this->status] ?? ucfirst($this->status);
    }
}