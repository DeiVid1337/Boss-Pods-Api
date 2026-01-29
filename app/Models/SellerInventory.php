<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @method static \Illuminate\Database\Eloquent\Builder|SellerInventory forUser(int $userId)
 * @method static \Illuminate\Database\Eloquent\Builder|SellerInventory forStoreProduct(int $storeProductId)
 */
class SellerInventory extends Model
{
    /** @use HasFactory<\Database\Factories\SellerInventoryFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'seller_inventory';

    protected $fillable = [
        'user_id',
        'store_product_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function storeProduct(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class);
    }

    public function scopeForUser($query, int $userId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForStoreProduct($query, int $storeProductId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('store_product_id', $storeProductId);
    }
}
