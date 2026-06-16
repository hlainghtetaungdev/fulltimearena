<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class StaffAccount extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'role', 'username', 'display_name', 'password_hash', 'promo_code',
        'active', 'expires_at', 'created_by', 'last_login_at',
    ];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'expires_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'agent_id');
    }

    public function unitRequests(): HasMany
    {
        return $this->hasMany(UnitRequest::class, 'agent_id');
    }
}
