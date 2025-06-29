<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth\Models;

use Shopologic\Core\Database\Model;
use Shopologic\Core\Auth\Contracts\Authenticatable;

class User extends Model implements Authenticatable
{
    protected string $table = 'users';
    
    protected array $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'remember_token',
        'two_factor_secret',
        'two_factor_confirmed_at',
        'oauth_provider',
        'oauth_id',
        'avatar',
    ];
    
    protected array $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];
    
    protected array $casts = [
        'email_verified_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the unique identifier for the user
     */
    public function getAuthIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Get the password for the user
     */
    public function getAuthPassword(): string
    {
        return $this->password;
    }

    /**
     * Get the remember token
     */
    public function getRememberToken(): ?string
    {
        return $this->remember_token;
    }

    /**
     * Set the remember token
     */
    public function setRememberToken(string $token): void
    {
        $this->remember_token = $token;
    }

    /**
     * Get the remember token name
     */
    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    /**
     * Check if the user's email is verified
     */
    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Mark the user's email as verified
     */
    public function markEmailAsVerified(): bool
    {
        $this->email_verified_at = new \DateTime();
        return $this->save();
    }

    /**
     * Get the user's roles
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * Get the user's permissions through roles
     */
    public function permissions()
    {
        return $this->hasManyThrough(Permission::class, Role::class);
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $role): bool
    {
        if (is_string($role)) {
            return $this->roles->contains('name', $role);
        }
        
        return false;
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if user has all of the given roles
     */
    public function hasAllRoles(array $roles): bool
    {
        foreach ($roles as $role) {
            if (!$this->hasRole($role)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        // Check direct permissions first
        if ($this->permissions->contains('name', $permission)) {
            return true;
        }
        
        // Check permissions through roles
        foreach ($this->roles as $role) {
            if ($role->permissions->contains('name', $permission)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Assign a role to the user
     */
    public function assignRole(string|Role $role): void
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->firstOrFail();
        }
        
        $this->roles()->attach($role);
    }

    /**
     * Remove a role from the user
     */
    public function removeRole(string|Role $role): void
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->firstOrFail();
        }
        
        $this->roles()->detach($role);
    }

    /**
     * Sync user roles
     */
    public function syncRoles(array $roles): void
    {
        $roleIds = [];
        
        foreach ($roles as $role) {
            if (is_string($role)) {
                $roleModel = Role::where('name', $role)->first();
                if ($roleModel) {
                    $roleIds[] = $roleModel->id;
                }
            } elseif (is_numeric($role)) {
                $roleIds[] = $role;
            } elseif ($role instanceof Role) {
                $roleIds[] = $role->id;
            }
        }
        
        $this->roles()->sync($roleIds);
    }

    /**
     * Create API token for the user
     */
    public function createToken(string $name, array $abilities = ['*']): array
    {
        $token = hash('sha256', random_bytes(40));
        
        $this->tokens()->create([
            'name' => $name,
            'token' => hash('sha256', $token),
            'abilities' => $abilities,
        ]);
        
        return [
            'token' => $token,
            'plain_text_token' => $token,
        ];
    }

    /**
     * Get user's API tokens
     */
    public function tokens()
    {
        return $this->hasMany(PersonalAccessToken::class);
    }
}