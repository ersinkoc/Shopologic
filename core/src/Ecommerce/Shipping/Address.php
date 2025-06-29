<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Shipping;

class Address
{
    public string $firstName;
    public string $lastName;
    public string $company = '';
    public string $addressLine1;
    public string $addressLine2 = '';
    public string $city;
    public string $state;
    public string $postalCode;
    public string $country;
    public string $phone = '';
    public string $email = '';

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Get full name
     */
    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    /**
     * Get formatted address
     */
    public function format(): string
    {
        $lines = [];
        
        if ($this->company) {
            $lines[] = $this->company;
        }
        
        $lines[] = $this->getFullName();
        $lines[] = $this->addressLine1;
        
        if ($this->addressLine2) {
            $lines[] = $this->addressLine2;
        }
        
        $lines[] = "{$this->city}, {$this->state} {$this->postalCode}";
        $lines[] = $this->country;
        
        return implode("\n", $lines);
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'company' => $this->company,
            'address_line_1' => $this->addressLine1,
            'address_line_2' => $this->addressLine2,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postalCode,
            'country' => $this->country,
            'phone' => $this->phone,
            'email' => $this->email,
        ];
    }

    /**
     * Check if address is complete
     */
    public function isComplete(): bool
    {
        return !empty($this->firstName) &&
               !empty($this->lastName) &&
               !empty($this->addressLine1) &&
               !empty($this->city) &&
               !empty($this->state) &&
               !empty($this->postalCode) &&
               !empty($this->country);
    }

    /**
     * Check if addresses are equal
     */
    public function equals(Address $other): bool
    {
        return $this->toArray() === $other->toArray();
    }
}