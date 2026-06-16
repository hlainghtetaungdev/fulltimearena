<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'agent_id', 'promo_code_used', 'full_name', 'profile_image_path',
        'phone_country', 'phone_number', 'phone_e164', 'password_hash',
        'active', 'last_login_at',
    ];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'last_login_at' => 'datetime'];
    }

    public function getRole(): string
    {
        return 'user';
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(StaffAccount::class, 'agent_id');
    }

    public function unitRequests(): HasMany
    {
        return $this->hasMany(UnitRequest::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }
}
