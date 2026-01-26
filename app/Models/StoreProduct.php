<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreProduct extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'product_id',
        'cost_price',
        'sale_price',
        'stock_quantity',
        'min_stock_level',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }


    public function scopeLowStock($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereColumn('stock_quantity', '<=', 'min_stock_level');
    }


    public function scopeForStore($query, int $storeId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function hasStock(int $quantity): bool
    {
        return $this->stock_quantity >= $quantity;
    }

    public function decrementStock(int $quantity): void
    {
        $this->decrement('stock_quantity', $quantity);
    }
}
