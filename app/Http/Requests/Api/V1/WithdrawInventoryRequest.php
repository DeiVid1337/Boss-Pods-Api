<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class WithdrawInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $store = $this->route('store');
        $sellerId = $this->input('seller_id');
        $user = $this->user();

        if (!$store instanceof Store) {
            return false;
        }

        if ($sellerId && $user->id !== (int) $sellerId) {
            if ($user->isSeller()) {
                return false;
            }

            if ($user->isManager() && $user->store_id !== $store->id) {
                return false;
            }
        }

        return Gate::allows('create', \App\Models\SellerInventory::class);
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.store_product_id' => ['required', 'integer', 'exists:store_products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'seller_id' => ['sometimes', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'The items field is required.',
            'items.array' => 'The items must be an array.',
            'items.min' => 'At least one item is required.',
            'items.*.store_product_id.required' => 'The store product ID is required.',
            'items.*.store_product_id.integer' => 'The store product ID must be an integer.',
            'items.*.store_product_id.exists' => 'The selected store product does not exist.',
            'items.*.quantity.required' => 'The quantity is required.',
            'items.*.quantity.integer' => 'The quantity must be an integer.',
            'items.*.quantity.min' => 'The quantity must be at least 1.',
            'seller_id.integer' => 'The seller ID must be an integer.',
            'seller_id.exists' => 'The selected seller does not exist.',
        ];
    }
}
