<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\StoreProduct;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreProductRequest extends FormRequest
{

    public function authorize(): bool
    {
        $storeProduct = $this->route('storeProduct');

        return $this->user()->can('update', $storeProduct);
    }


    public function rules(): array
    {
        $storeProduct = $this->route('storeProduct');

        return [
            'cost_price' => ['sometimes', 'numeric', 'min:0'],
            'sale_price' => [
                'sometimes',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) use ($storeProduct) {
                    $costPrice = $this->input('cost_price', $storeProduct->cost_price);
                    if ($value < $costPrice) {
                        $fail('The sale price must be greater than or equal to the cost price.');
                    }
                },
            ],
            'stock_quantity' => ['sometimes', 'integer', 'min:0'],
            'min_stock_level' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
