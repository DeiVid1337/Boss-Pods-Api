<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SellerInventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'store_product_id' => $this->store_product_id,
            'quantity' => $this->quantity,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'store_product' => $this->whenLoaded('storeProduct', function () {
                return [
                    'id' => $this->storeProduct->id,
                    'store_id' => $this->storeProduct->store_id,
                    'product_id' => $this->storeProduct->product_id,
                    'cost_price' => $this->storeProduct->cost_price,
                    'sale_price' => $this->storeProduct->sale_price,
                    'stock_quantity' => $this->storeProduct->stock_quantity,
                    'product' => $this->whenLoaded('storeProduct.product', function () {
                        return [
                            'id' => $this->storeProduct->product->id,
                            'brand' => $this->storeProduct->product->brand,
                            'name' => $this->storeProduct->product->name,
                            'flavor' => $this->storeProduct->product->flavor,
                        ];
                    }),
                    'store' => $this->whenLoaded('storeProduct.store', function () {
                        return [
                            'id' => $this->storeProduct->store->id,
                            'name' => $this->storeProduct->store->name,
                        ];
                    }),
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
