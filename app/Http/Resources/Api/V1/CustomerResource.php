<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'total_purchases' => $this->total_purchases,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'sales' => $this->when(
                $this->relationLoaded('sales'),
                function () {
                    return $this->sales->map(function ($sale) {
                        return [
                            'id' => $sale->id,
                            'store_id' => $sale->store_id,
                            'total_amount' => $sale->total_amount,
                            'sale_date' => $sale->sale_date?->toIso8601String(),
                            'created_at' => $sale->created_at?->toIso8601String(),
                        ];
                    });
                }
            ),
        ];
    }
}
