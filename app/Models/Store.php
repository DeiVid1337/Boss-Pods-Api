<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function storeProducts(): HasMany
    {
        return $this->hasMany(StoreProduct::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }


    public function scopeActive($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }


    public function resolveChildRouteBinding($childType, $value, $field)
    {
        return match ($childType) {
            'storeProduct' => $this->storeProducts()
                ->where($field ?? 'id', $value)
                ->first(),
            'sale' => $this->sales()
                ->where($field ?? 'id', $value)
                ->first(),
            default => null,
        };
    }
}
