<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreProductResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        $user = $request->user();
        $targetSellerId = null;
        $sellerQuantity = 0;

        if ($user && $user->isSeller()) {
            $targetSellerId = $user->id;
        } elseif ($request->query('seller_id')) {
            $targetSellerId = (int) $request->query('seller_id');
        }

        if ($targetSellerId) {
            if ($this->relationLoaded('sellerInventories')) {
                $sellerInventory = $this->sellerInventories->first();
                $sellerQuantity = $sellerInventory ? (int) $sellerInventory->quantity : 0;
            } else {
                $sellerInventory = \App\Models\SellerInventory::where('user_id', $targetSellerId)
                    ->where('store_product_id', $this->id)
                    ->first();
                $sellerQuantity = $sellerInventory ? (int) $sellerInventory->quantity : 0;
            }
        }

        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'product_id' => $this->product_id,
            'cost_price' => $this->cost_price,
            'sale_price' => $this->sale_price,
            'stock_quantity' => $this->stock_quantity,
            'min_stock_level' => $this->min_stock_level,
            'is_active' => $this->is_active,
            'available_quantity' => $this->available_quantity,
            'seller_quantity' => $this->when($targetSellerId !== null, $sellerQuantity),
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'brand' => $this->product->brand,
                    'name' => $this->product->name,
                    'flavor' => $this->product->flavor,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
