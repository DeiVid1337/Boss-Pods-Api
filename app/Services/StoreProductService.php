<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Store;
use App\Models\StoreProduct;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StoreProductService
{

    public function list(Store $store, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = StoreProduct::query()
            ->where('store_products.store_id', $store->id)
            ->with('product');

        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $driver = DB::getDriverName();
            
            $query->whereHas('product', function ($q) use ($search, $driver) {
                if ($driver === 'pgsql') {
                    $q->where('brand', 'ILIKE', "%{$search}%")
                        ->orWhere('name', 'ILIKE', "%{$search}%")
                        ->orWhere('flavor', 'ILIKE', "%{$search}%");
                } else {
                    $q->whereRaw('LOWER(brand) LIKE LOWER(?)', ["%{$search}%"])
                        ->orWhereRaw('LOWER(name) LIKE LOWER(?)', ["%{$search}%"])
                        ->orWhereRaw('LOWER(flavor) LIKE LOWER(?)', ["%{$search}%"]);
                }
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('store_products.is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['low_stock']) && filter_var($filters['low_stock'], FILTER_VALIDATE_BOOLEAN)) {
            $query->lowStock();
        }

        $sortBy = $filters['sort_by'] ?? 'product_name';
        $sortOrder = $filters['sort_order'] ?? 'asc';
        
        $allowedSortBy = ['stock_quantity', 'sale_price', 'created_at', 'product_name'];
        if (!in_array($sortBy, $allowedSortBy, true)) {
            $sortBy = 'product_name';
        }
        
        $sortOrder = strtolower($sortOrder);
        if (!in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'asc';
        }
        
        if ($sortBy === 'product_name') {
            $query->join('products', 'store_products.product_id', '=', 'products.id')
                ->orderBy('products.name', $sortOrder)
                ->select('store_products.*');
        } else {
            $query->orderBy("store_products.{$sortBy}", $sortOrder);
        }

        return $query->paginate(min($perPage, 100));
    }


    public function find(Store $store, int $id): ?StoreProduct
    {
        return StoreProduct::where('store_id', $store->id)
            ->with('product')
            ->find($id);
    }


    public function create(Store $store, array $data): StoreProduct
    {
        $data['store_id'] = $store->id;
        $data['min_stock_level'] = $data['min_stock_level'] ?? 0;
        $data['is_active'] = $data['is_active'] ?? true;

        try {
            $storeProduct = StoreProduct::create($data);
            $storeProduct->load('product');

            return $storeProduct;
        } catch (QueryException $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();

            if ($errorCode === '23000' || $errorCode === '23505' ||
                str_contains($errorMessage, 'unique constraint') ||
                str_contains($errorMessage, 'duplicate key value')) {
                throw new RuntimeException('Product already in store inventory.', 422);
            }
            throw $e;
        }
    }


    public function update(StoreProduct $storeProduct, array $data): StoreProduct
    {
        $storeProduct->update($data);
        $storeProduct->refresh();
        $storeProduct->load('product');

        return $storeProduct;
    }


    public function delete(StoreProduct $storeProduct): void
    {
        if ($storeProduct->saleItems()->exists()) {
            throw new RuntimeException('Cannot delete: product has sales history.', 409);
        }

        try {
            $storeProduct->delete();
        } catch (QueryException $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();

            if ($errorCode === '23000' || $errorCode === '23503' ||
                str_contains($errorMessage, 'foreign key constraint') ||
                str_contains($errorMessage, 'violates foreign key constraint')) {
                throw new RuntimeException('Cannot delete: product has sales history.', 409);
            }
            throw $e;
        }
    }
}
