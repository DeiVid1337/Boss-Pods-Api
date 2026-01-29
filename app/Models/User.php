<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'store_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function sellerInventories(): HasMany
    {
        return $this->hasMany(SellerInventory::class);
    }

    public function scopeAdmins($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('role', 'admin');
    }

    public function scopeManagers($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('role', 'manager');
    }

    public function scopeSellers($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('role', 'seller');
    }

    public function scopeForStore($query, int $storeId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function isSeller(): bool
    {
        return $this->role === 'seller';
    }
}
