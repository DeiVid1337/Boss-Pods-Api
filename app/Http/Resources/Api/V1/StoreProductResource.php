<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreProductResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'product_id' => $this->product_id,
            'cost_price' => $this->cost_price,
            'sale_price' => $this->sale_price,
            'stock_quantity' => $this->stock_quantity,
            'min_stock_level' => $this->min_stock_level,
            'is_active' => $this->is_active,
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
