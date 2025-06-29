<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth\Models;

use Shopologic\Core\Database\Model;

class Permission extends Model
{
    protected string $table = 'permissions';
    
    protected array $fillable = [
        'name',
        'display_name',
        'description',
        'category',
    ];

    /**
     * Get roles that have this permission
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }

    /**
     * Get users that have this permission directly (not through roles)
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_permissions');
    }

    /**
     * Check if this permission belongs to a category
     */
    public function inCategory(string $category): bool
    {
        return $this->category === $category;
    }

    /**
     * Get all permissions in a specific category
     */
    public static function byCategory(string $category): array
    {
        return static::where('category', $category)->get()->all();
    }

    /**
     * Create multiple permissions at once
     */
    public static function createMany(array $permissions): array
    {
        $created = [];
        
        foreach ($permissions as $permission) {
            if (is_string($permission)) {
                $permission = ['name' => $permission];
            }
            
            $created[] = static::create($permission);
        }
        
        return $created;
    }
}