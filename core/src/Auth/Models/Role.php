<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth\Models;

use Shopologic\Core\Database\Model;

class Role extends Model
{
    protected string $table = 'roles';
    
    protected array $fillable = [
        'name',
        'display_name',
        'description',
    ];

    /**
     * Get users that have this role
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }

    /**
     * Get permissions assigned to this role
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /**
     * Check if role has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        return $this->permissions->contains('name', $permission);
    }

    /**
     * Assign a permission to this role
     */
    public function givePermission(string|Permission $permission): void
    {
        if (is_string($permission)) {
            $permission = Permission::where('name', $permission)->firstOrFail();
        }
        
        $this->permissions()->attach($permission);
    }

    /**
     * Remove a permission from this role
     */
    public function revokePermission(string|Permission $permission): void
    {
        if (is_string($permission)) {
            $permission = Permission::where('name', $permission)->firstOrFail();
        }
        
        $this->permissions()->detach($permission);
    }

    /**
     * Sync permissions for this role
     */
    public function syncPermissions(array $permissions): void
    {
        $permissionIds = [];
        
        foreach ($permissions as $permission) {
            if (is_string($permission)) {
                $permissionModel = Permission::where('name', $permission)->first();
                if ($permissionModel) {
                    $permissionIds[] = $permissionModel->id;
                }
            } elseif (is_numeric($permission)) {
                $permissionIds[] = $permission;
            } elseif ($permission instanceof Permission) {
                $permissionIds[] = $permission->id;
            }
        }
        
        $this->permissions()->sync($permissionIds);
    }
}