<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Store;
use App\Models\StoreProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductStoreRequest extends FormRequest
{

    public function authorize(): bool
    {
        return $this->user()->can('create', StoreProduct::class);
    }


    public function rules(): array
    {
        $store = $this->route('store');

        return [
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
                Rule::unique('store_products')
                    ->where('store_id', $store->id)
                    ->whereNull('deleted_at'),
            ],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0', 'gte:cost_price'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'min_stock_level' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }


    public function messages(): array
    {
        return [
            'product_id.unique' => 'Product already in store inventory.',
            'sale_price.gte' => 'The sale price must be greater than or equal to the cost price.',
        ];
    }
}
