<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SellerInventory;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerInventoryService
{
    public function withdraw(Store $store, User $seller, array $items): Collection
    {
        $this->validateWithdrawItems($store, $items);

        return DB::transaction(function () use ($seller, $items) {
            $results = new Collection();

            foreach ($items as $item) {
                $storeProductId = (int) $item['store_product_id'];
                $quantity = (int) $item['quantity'];

                $sellerInventory = SellerInventory::withTrashed()
                    ->where('user_id', $seller->id)
                    ->where('store_product_id', $storeProductId)
                    ->first();

                if (!$sellerInventory) {
                    $sellerInventory = new SellerInventory([
                        'user_id' => $seller->id,
                        'store_product_id' => $storeProductId,
                        'quantity' => 0,
                    ]);
                } elseif ($sellerInventory->trashed()) {
                    $sellerInventory->restore();
                }

                $sellerInventory->quantity = ((int) $sellerInventory->quantity) + $quantity;
                $sellerInventory->save();

                $results->push($sellerInventory->fresh());
            }

            return $results;
        });
    }

    public function return(Store $store, User $seller, array $items): Collection
    {
        $this->validateReturnItems($store, $seller, $items);

        return DB::transaction(function () use ($seller, $items) {
            $results = new Collection();

            foreach ($items as $index => $item) {
                $storeProductId = (int) $item['store_product_id'];
                $quantity = (int) $item['quantity'];

                $sellerInventory = SellerInventory::where('user_id', $seller->id)
                    ->where('store_product_id', $storeProductId)
                    ->first();

                if (!$sellerInventory) {
                    throw ValidationException::withMessages([
                        "items.{$index}.store_product_id" => [
                            'Seller inventory not found for this product.',
                        ],
                    ]);
                }

                $newQuantity = max(0, ((int) $sellerInventory->quantity) - $quantity);
                $sellerInventory->quantity = $newQuantity;
                $sellerInventory->save();

                if ($newQuantity === 0) {
                    $sellerInventory->delete();
                }

                $results->push($sellerInventory);
            }

            return $results;
        });
    }

    public function listForUser(User $seller, int $perPage = 15): LengthAwarePaginator
    {
        return SellerInventory::query()
            ->where('user_id', $seller->id)
            ->with(['storeProduct.product', 'storeProduct.store'])
            ->orderBy('created_at', 'desc')
            ->paginate(min($perPage, 100));
    }

    public function listForStore(Store $store, int $perPage = 15): LengthAwarePaginator
    {
        return SellerInventory::query()
            ->whereHas('storeProduct', function ($query) use ($store) {
                $query->where('store_id', $store->id);
            })
            ->with(['user', 'storeProduct.product'])
            ->orderBy('created_at', 'desc')
            ->paginate(min($perPage, 100));
    }

    public function getAvailableQuantity(StoreProduct $storeProduct): int
    {
        if ($storeProduct->relationLoaded('sellerInventories')) {
            $sellerQuantity = $storeProduct->sellerInventories->sum('quantity');
        } else {
            $sellerQuantity = $storeProduct->sellerInventories()->sum('quantity');
        }
        return max(0, $storeProduct->stock_quantity - (int) $sellerQuantity);
    }

    private function validateWithdrawItems(Store $store, array $items): void
    {
        $errors = [];
        $storeProductIds = array_filter(array_column($items, 'store_product_id'));

        $storeProducts = StoreProduct::whereIn('id', $storeProductIds)
            ->with('sellerInventories')
            ->get()
            ->keyBy('id');

        foreach ($items as $index => $item) {
            $storeProductId = $item['store_product_id'] ?? null;
            $quantity = (int) ($item['quantity'] ?? 0);

            if (!$storeProductId) {
                $errors["items.{$index}.store_product_id"] = ['The store product ID is required.'];
                continue;
            }

            $storeProduct = $storeProducts->get($storeProductId);

            if (!$storeProduct) {
                $errors["items.{$index}.store_product_id"] = ['The selected store product does not exist.'];
                continue;
            }

            if ($storeProduct->store_id !== $store->id) {
                $errors["items.{$index}.store_product_id"] = ['The store product does not belong to this store.'];
                continue;
            }

            $availableQuantity = $this->getAvailableQuantity($storeProduct);

            if ($availableQuantity < $quantity) {
                $errors["items.{$index}.quantity"] = [
                    "Insufficient available quantity. Available: {$availableQuantity}, Requested: {$quantity}.",
                ];
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function validateReturnItems(Store $store, User $seller, array $items): void
    {
        $errors = [];
        $storeProductIds = array_filter(array_column($items, 'store_product_id'));

        $storeProducts = StoreProduct::whereIn('id', $storeProductIds)
            ->get()
            ->keyBy('id');

        $sellerInventories = SellerInventory::where('user_id', $seller->id)
            ->whereIn('store_product_id', $storeProductIds)
            ->get()
            ->keyBy('store_product_id');

        foreach ($items as $index => $item) {
            $storeProductId = $item['store_product_id'] ?? null;
            $quantity = (int) ($item['quantity'] ?? 0);

            if (!$storeProductId) {
                $errors["items.{$index}.store_product_id"] = ['The store product ID is required.'];
                continue;
            }

            $storeProduct = $storeProducts->get($storeProductId);

            if (!$storeProduct) {
                $errors["items.{$index}.store_product_id"] = ['The selected store product does not exist.'];
                continue;
            }

            if ($storeProduct->store_id !== $store->id) {
                $errors["items.{$index}.store_product_id"] = ['The store product does not belong to this store.'];
                continue;
            }

            $sellerInventory = $sellerInventories->get($storeProductId);

            if (!$sellerInventory || $sellerInventory->quantity < $quantity) {
                $currentQuantity = $sellerInventory ? $sellerInventory->quantity : 0;
                $errors["items.{$index}.quantity"] = [
                    "Insufficient quantity in seller inventory. Available: {$currentQuantity}, Requested: {$quantity}.",
                ];
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }
}
