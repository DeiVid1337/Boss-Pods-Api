<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SellerInventory;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{

    public function list(Store $store, User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Sale::query()
            ->forStore($store->id);

        if ($user->isSeller()) {
            $query->forUser($user->id);
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $driver = DB::getDriverName();
            
            if ($driver === 'pgsql') {
                $query->where('notes', 'ILIKE', "%{$search}%");
            } else {
                $query->whereRaw('LOWER(notes) LIKE LOWER(?)', ["%{$search}%"]);
            }
        }

        if (isset($filters['from']) && isset($filters['to'])) {
            $query->byDateRange($filters['from'], $filters['to']);
        } elseif (isset($filters['from'])) {
            $query->where('sale_date', '>=', $filters['from']);
        } elseif (isset($filters['to'])) {
            $query->where('sale_date', '<=', $filters['to']);
        }

        $sortBy = $filters['sort_by'] ?? 'sale_date';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        
        $allowedSortBy = ['sale_date', 'total_amount', 'created_at'];
        if (!in_array($sortBy, $allowedSortBy, true)) {
            $sortBy = 'sale_date';
        }
        
        $sortOrder = strtolower($sortOrder);
        if (!in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'desc';
        }
        
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate(min($perPage, 100));
    }


    public function find(Store $store, int $id): ?Sale
    {
        return Sale::forStore($store->id)->find($id);
    }


    public function createSale(Store $store, User $user, array $data): Sale
    {
        $items = $data['items'] ?? [];
        $customerId = $data['customer_id'] ?? null;
        $notes = $data['notes'] ?? null;

        $this->validateStockAndOwnership($store, $user, $items);

        return DB::transaction(function () use ($store, $user, $items, $customerId, $notes) {
            $totalAmount = 0;
            $totalPods = 0;
            $storeProducts = [];

            foreach ($items as $item) {
                $storeProduct = StoreProduct::findOrFail($item['store_product_id']);
                $quantity = (int) $item['quantity'];
                $unitPrice = $storeProduct->sale_price;
                $subtotal = $unitPrice * $quantity;

                $totalAmount += $subtotal;
                $totalPods += $quantity;
                $storeProducts[] = [
                    'store_product' => $storeProduct,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ];
            }

            $sale = Sale::create([
                'store_id' => $store->id,
                'user_id' => $user->id,
                'customer_id' => $customerId,
                'total_amount' => $totalAmount,
                'sale_date' => now(),
                'notes' => $notes,
            ]);

            foreach ($storeProducts as $itemData) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'store_product_id' => $itemData['store_product']->id,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'subtotal' => $itemData['subtotal'],
                ]);

                $itemData['store_product']->decrementStock($itemData['quantity']);

                if ($user->isSeller()) {
                    $sellerInventory = SellerInventory::where('user_id', $user->id)
                        ->where('store_product_id', $itemData['store_product']->id)
                        ->first();

                    if ($sellerInventory) {
                        $sellerInventory->quantity = max(0, $sellerInventory->quantity - $itemData['quantity']);
                        if ($sellerInventory->quantity === 0) {
                            $sellerInventory->delete();
                        } else {
                            $sellerInventory->save();
                        }
                    }
                }
            }

            if ($customerId) {
                $customer = Customer::findOrFail($customerId);
                $customer->incrementPurchases($totalPods);
            }

            $sale->load('saleItems.storeProduct');

            return $sale;
        });
    }


    private function validateStockAndOwnership(Store $store, User $user, array $items): void
    {
        $errors = [];
        $aggregated = [];
        $firstIndexByProduct = [];

        foreach ($items as $index => $item) {
            $storeProductId = $item['store_product_id'] ?? null;
            $quantity = (int) ($item['quantity'] ?? 0);

            if (!$storeProductId) {
                $errors["items.{$index}.store_product_id"] = ['The store product ID is required.'];
                continue;
            }

            $storeProduct = StoreProduct::find($storeProductId);

            if (!$storeProduct) {
                $errors["items.{$index}.store_product_id"] = ['The selected store product does not exist.'];
                continue;
            }

            if ($storeProduct->store_id !== $store->id) {
                $errors["items.{$index}.store_product_id"] = ['The store product does not belong to this store.'];
                continue;
            }

            $aggregated[$storeProductId] = ($aggregated[$storeProductId] ?? 0) + $quantity;
            if (!isset($firstIndexByProduct[$storeProductId])) {
                $firstIndexByProduct[$storeProductId] = $index;
            }
        }

        foreach ($aggregated as $storeProductId => $totalQuantity) {
            $storeProduct = StoreProduct::find($storeProductId);

            if ($user->isSeller()) {
                $sellerInventory = SellerInventory::where('user_id', $user->id)
                    ->where('store_product_id', $storeProductId)
                    ->first();

                if (!$sellerInventory || $sellerInventory->quantity < $totalQuantity) {
                    $available = $sellerInventory ? $sellerInventory->quantity : 0;
                    $idx = $firstIndexByProduct[$storeProductId];
                    $errors["items.{$idx}.quantity"] = [
                        "Insufficient quantity in seller inventory. Available: {$available}, Requested (total for this product): {$totalQuantity}.",
                    ];
                }
            } else {
                if (!$storeProduct->hasStock($totalQuantity)) {
                    $idx = $firstIndexByProduct[$storeProductId];
                    $errors["items.{$idx}.quantity"] = [
                        "Insufficient stock. Available: {$storeProduct->stock_quantity}, Requested (total for this product): {$totalQuantity}.",
                    ];
                }
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }
}
