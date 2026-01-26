<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CreateSaleRequest extends FormRequest
{

    public function authorize(): bool
    {
        return Gate::allows('create', Sale::class);
    }


    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.store_product_id' => ['required', 'integer', 'exists:store_products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }


    public function messages(): array
    {
        return [
            'customer_id.integer' => 'The customer ID must be an integer.',
            'customer_id.exists' => 'The selected customer does not exist.',
            'items.required' => 'At least one item is required.',
            'items.array' => 'Items must be an array.',
            'items.min' => 'At least one item is required.',
            'items.*.store_product_id.required' => 'Store product ID is required for each item.',
            'items.*.store_product_id.integer' => 'Store product ID must be an integer.',
            'items.*.store_product_id.exists' => 'The selected store product does not exist.',
            'items.*.quantity.required' => 'Quantity is required for each item.',
            'items.*.quantity.integer' => 'Quantity must be an integer.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
            'notes.string' => 'Notes must be a string.',
            'notes.max' => 'Notes may not be greater than 1000 characters.',
        ];
    }
}
