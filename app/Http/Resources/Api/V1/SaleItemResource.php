<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'store_product_id' => $this->store_product_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'subtotal' => $this->subtotal,
            'store_product' => $this->when(
                $this->relationLoaded('storeProduct'),
                function () {
                    return $this->when(
                        $this->storeProduct && $this->storeProduct->relationLoaded('product'),
                        function () {
                            return [
                                'id' => $this->storeProduct->id,
                                'product' => [
                                    'id' => $this->storeProduct->product->id,
                                    'brand' => $this->storeProduct->product->brand,
                                    'name' => $this->storeProduct->product->name,
                                    'flavor' => $this->storeProduct->product->flavor,
                                ],
                                'sale_price' => $this->storeProduct->sale_price,
                            ];
                        },
                        function () {
                            return [
                                'id' => $this->storeProduct->id,
                                'sale_price' => $this->storeProduct->sale_price,
                            ];
                        }
                    );
                }
            ),
        ];
    }
}
