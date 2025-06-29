<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth\Models;

use Shopologic\Core\Database\Model;

class PersonalAccessToken extends Model
{
    protected string $table = 'personal_access_tokens';
    
    protected array $fillable = [
        'name',
        'token',
        'abilities',
        'last_used_at',
        'expires_at',
    ];
    
    protected array $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns the token
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if token can perform given ability
     */
    public function can(string $ability): bool
    {
        return in_array('*', $this->abilities ?? []) || 
               in_array($ability, $this->abilities ?? []);
    }

    /**
     * Check if token cannot perform given ability
     */
    public function cant(string $ability): bool
    {
        return !$this->can($ability);
    }

    /**
     * Touch the token to update last used timestamp
     */
    public function touch(): bool
    {
        $this->last_used_at = new \DateTime();
        return $this->save();
    }

    /**
     * Check if the token has expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at < new \DateTime();
    }

    /**
     * Find token by plaintext token
     */
    public static function findToken(string $token): ?self
    {
        return static::where('token', hash('sha256', $token))->first();
    }
}