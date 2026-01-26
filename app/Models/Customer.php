<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'total_purchases',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }


    public function scopeByPhone($query, string $phone): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('phone', $phone);
    }

    public function incrementPurchases(int $quantity): void
    {
        $this->increment('total_purchases', $quantity);
    }
}
