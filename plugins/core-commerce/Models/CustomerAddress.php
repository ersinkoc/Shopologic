<?php

namespace Shopologic\Plugins\CoreCommerce\Models;

use Shopologic\Core\Database\Model;
use Shopologic\Core\Database\Relations\BelongsTo;

class CustomerAddress extends Model
{
    protected string $table = 'customer_addresses';
    
    protected array $fillable = [
        'customer_id', 'type', 'name', 'company',
        'line1', 'line2', 'city', 'state',
        'postal_code', 'country', 'phone',
        'is_default'
    ];
    
    protected array $casts = [
        'customer_id' => 'integer',
        'is_default' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
    
    public function getFormatted(): string
    {
        $parts = [];
        
        if ($this->name) {
            $parts[] = $this->name;
        }
        
        if ($this->company) {
            $parts[] = $this->company;
        }
        
        $parts[] = $this->line1;
        
        if ($this->line2) {
            $parts[] = $this->line2;
        }
        
        $cityLine = [];
        if ($this->city) {
            $cityLine[] = $this->city;
        }
        if ($this->state) {
            $cityLine[] = $this->state;
        }
        if ($this->postal_code) {
            $cityLine[] = $this->postal_code;
        }
        
        if (!empty($cityLine)) {
            $parts[] = implode(', ', $cityLine);
        }
        
        if ($this->country) {
            $parts[] = $this->country;
        }
        
        return implode("\n", $parts);
    }
}