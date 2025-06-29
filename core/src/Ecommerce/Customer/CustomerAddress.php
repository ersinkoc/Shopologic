<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Customer;

use Shopologic\Core\Database\Model;
use Shopologic\Core\Ecommerce\Shipping\Address;

class CustomerAddress extends Model
{
    protected string $table = 'customer_addresses';
    
    protected array $fillable = [
        'user_id',
        'type',
        'first_name',
        'last_name',
        'company',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'phone',
        'is_default',
    ];
    
    protected array $casts = [
        'user_id' => 'integer',
        'is_default' => 'boolean',
    ];

    /**
     * Get the customer
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'user_id');
    }

    /**
     * Convert to Address object
     */
    public function toAddress(): Address
    {
        return new Address([
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'company' => $this->company,
            'addressLine1' => $this->address_line_1,
            'addressLine2' => $this->address_line_2,
            'city' => $this->city,
            'state' => $this->state,
            'postalCode' => $this->postal_code,
            'country' => $this->country,
            'phone' => $this->phone,
        ]);
    }

    /**
     * Set as default
     */
    public function setAsDefault(): bool
    {
        // Remove default flag from other addresses of same type
        static::where('user_id', $this->user_id)
              ->where('type', $this->type)
              ->where('id', '!=', $this->id)
              ->update(['is_default' => false]);
        
        $this->is_default = true;
        return $this->save();
    }

    /**
     * Get formatted address
     */
    public function format(): string
    {
        return $this->toAddress()->format();
    }
}