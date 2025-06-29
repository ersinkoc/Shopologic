<?php

declare(strict_types=1);

namespace Shopologic\Core\MultiStore;

use Shopologic\Core\Database\Model;

/**
 * Store-specific settings model
 */
class StoreSettings extends Model
{
    protected string $table = 'store_settings';
    
    protected array $fillable = [
        'store_id',
        'key',
        'value',
        'type'
    ];
    
    protected array $casts = [
        'value' => 'string'
    ];
    
    /**
     * Get typed value
     */
    public function getTypedValue()
    {
        switch ($this->type) {
            case 'boolean':
                return filter_var($this->value, FILTER_VALIDATE_BOOLEAN);
                
            case 'integer':
                return (int) $this->value;
                
            case 'float':
                return (float) $this->value;
                
            case 'array':
            case 'json':
                return json_decode($this->value, true) ?? [];
                
            default:
                return $this->value;
        }
    }
    
    /**
     * Set typed value
     */
    public function setTypedValue($value): self
    {
        if (is_array($value) || is_object($value)) {
            $this->value = json_encode($value);
            $this->type = 'json';
        } elseif (is_bool($value)) {
            $this->value = $value ? 'true' : 'false';
            $this->type = 'boolean';
        } elseif (is_int($value)) {
            $this->value = (string) $value;
            $this->type = 'integer';
        } elseif (is_float($value)) {
            $this->value = (string) $value;
            $this->type = 'float';
        } else {
            $this->value = (string) $value;
            $this->type = 'string';
        }
        
        return $this;
    }
    
    /**
     * Get store relationship
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }
    
    /**
     * Get setting by key for a store
     */
    public static function getValue(int $storeId, string $key, $default = null)
    {
        $setting = static::where('store_id', $storeId)
            ->where('key', $key)
            ->first();
        
        return $setting ? $setting->getTypedValue() : $default;
    }
    
    /**
     * Set setting value for a store
     */
    public static function setValue(int $storeId, string $key, $value): self
    {
        $setting = static::firstOrNew([
            'store_id' => $storeId,
            'key' => $key
        ]);
        
        $setting->setTypedValue($value);
        $setting->save();
        
        return $setting;
    }
    
    /**
     * Get all settings for a store
     */
    public static function getStoreSettings(int $storeId): array
    {
        $settings = static::where('store_id', $storeId)->get();
        
        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->key] = $setting->getTypedValue();
        }
        
        return $result;
    }
}