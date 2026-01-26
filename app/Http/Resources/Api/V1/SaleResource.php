<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'user_id' => $this->user_id,
            'customer_id' => $this->customer_id,
            'total_amount' => $this->total_amount,
            'sale_date' => $this->sale_date?->toIso8601String(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'items' => $this->when(
                $this->relationLoaded('saleItems'),
                function () {
                    return SaleItemResource::collection($this->saleItems);
                }
            ),
            'customer' => $this->when(
                $this->relationLoaded('customer') && $this->customer,
                function () {
                    return [
                        'id' => $this->customer->id,
                        'name' => $this->customer->name,
                        'phone' => $this->customer->phone,
                    ];
                }
            ),
            'user' => $this->when(
                $this->relationLoaded('user') && $this->user,
                function () {
                    return [
                        'id' => $this->user->id,
                        'name' => $this->user->name,
                    ];
                }
            ),
        ];
    }
}
