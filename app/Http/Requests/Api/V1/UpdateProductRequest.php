<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'brand' => ['sometimes', 'string', 'max:255'],
            'name' => ['sometimes', 'string', 'max:255'],
            'flavor' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('products')->where(function ($query) use ($product) {
                    return $query->where('brand', $this->input('brand', $product->brand))
                        ->where('name', $this->input('name', $product->name));
                })->ignore($product->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'flavor.unique' => 'A product with this brand, name, and flavor already exists.',
        ];
    }
}
