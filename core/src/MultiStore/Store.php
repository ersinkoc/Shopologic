<?php

declare(strict_types=1);

namespace Shopologic\Core\MultiStore;

use Shopologic\Core\Database\Model;

/**
 * Store model for multi-store support
 */
class Store extends Model
{
    protected string $table = 'stores';
    
    protected array $fillable = [
        'code',
        'name',
        'domain',
        'subdomain',
        'path_prefix',
        'is_active',
        'is_default',
        'config',
        'theme',
        'locale',
        'currency',
        'timezone',
        'meta'
    ];
    
    protected array $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'config' => 'json',
        'meta' => 'json'
    ];
    
    protected array $dates = [
        'created_at',
        'updated_at'
    ];
    
    /**
     * Get store by domain
     */
    public static function findByDomain(string $domain): ?self
    {
        return static::query()
            ->where('domain', $domain)
            ->where('is_active', true)
            ->first();
    }
    
    /**
     * Get store by subdomain
     */
    public static function findBySubdomain(string $subdomain): ?self
    {
        return static::query()
            ->where('subdomain', $subdomain)
            ->where('is_active', true)
            ->first();
    }
    
    /**
     * Get store by path prefix
     */
    public static function findByPathPrefix(string $prefix): ?self
    {
        return static::query()
            ->where('path_prefix', $prefix)
            ->where('is_active', true)
            ->first();
    }
    
    /**
     * Get default store
     */
    public static function getDefault(): ?self
    {
        return static::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }
    
    /**
     * Get store configuration value
     */
    public function getConfig(string $key, $default = null)
    {
        $config = $this->config ?? [];
        return $config[$key] ?? $default;
    }
    
    /**
     * Set store configuration value
     */
    public function setConfig(string $key, $value): self
    {
        $config = $this->config ?? [];
        $config[$key] = $value;
        $this->config = $config;
        return $this;
    }
    
    /**
     * Get store URL
     */
    public function getUrl(string $path = ''): string
    {
        $url = '';
        
        if ($this->domain) {
            $url = 'https://' . $this->domain;
        } elseif ($this->subdomain) {
            $baseHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $parts = explode('.', $baseHost);
            if (count($parts) > 2) {
                array_shift($parts);
            }
            $url = 'https://' . $this->subdomain . '.' . implode('.', $parts);
        } else {
            $url = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            if ($this->path_prefix) {
                $url .= '/' . trim($this->path_prefix, '/');
            }
        }
        
        if ($path) {
            $url .= '/' . ltrim($path, '/');
        }
        
        return $url;
    }
    
    /**
     * Check if store matches request
     */
    public function matchesRequest(string $host, string $path): bool
    {
        // Check domain match
        if ($this->domain && $this->domain === $host) {
            return true;
        }
        
        // Check subdomain match
        if ($this->subdomain) {
            $subdomain = explode('.', $host)[0];
            if ($subdomain === $this->subdomain) {
                return true;
            }
        }
        
        // Check path prefix match
        if ($this->path_prefix) {
            $prefix = '/' . trim($this->path_prefix, '/');
            if (strpos($path, $prefix) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get store settings
     */
    public function settings()
    {
        return $this->hasMany(StoreSettings::class);
    }
    
    /**
     * Get store users
     */
    public function users()
    {
        return $this->belongsToMany('Shopologic\Core\Auth\User', 'store_users')
            ->withPivot('role', 'permissions')
            ->withTimestamps();
    }
    
    /**
     * Get store products
     */
    public function products()
    {
        return $this->belongsToMany('Shopologic\Core\Ecommerce\Product', 'store_products')
            ->withPivot('price', 'stock', 'is_active')
            ->withTimestamps();
    }
    
    /**
     * Get store categories
     */
    public function categories()
    {
        return $this->belongsToMany('Shopologic\Core\Ecommerce\Category', 'store_categories')
            ->withPivot('is_active', 'sort_order')
            ->withTimestamps();
    }
    
    /**
     * Get store orders
     */
    public function orders()
    {
        return $this->hasMany('Shopologic\Core\Ecommerce\Order');
    }
}