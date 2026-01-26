<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductService
{

    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Product::query();

        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $driver = DB::getDriverName();
            
            $query->where(function ($q) use ($search, $driver) {
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

        if (isset($filters['brand'])) {
            $query->where('brand', $filters['brand']);
        }

        $sortBy = $filters['sort_by'] ?? 'brand';
        $sortOrder = $filters['sort_order'] ?? 'asc';
        
        $allowedSortBy = ['brand', 'name', 'flavor', 'created_at'];
        if (!in_array($sortBy, $allowedSortBy, true)) {
            $sortBy = 'brand';
        }
        
        $sortOrder = strtolower($sortOrder);
        if (!in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'asc';
        }
        
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate(min($perPage, 100));
    }


    public function find(int $id): ?Product
    {
        return Product::find($id);
    }


    public function create(array $data): Product
    {
        return Product::create($data);
    }


    public function update(Product $product, array $data): Product
    {
        try {
            $product->update($data);
            $product->refresh();

            return $product;
        } catch (QueryException $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();

            if ($errorCode === '23000' || $errorCode === '23505' ||
                str_contains($errorMessage, 'unique constraint') ||
                str_contains($errorMessage, 'duplicate key value')) {
                throw new RuntimeException('A product with this brand, name, and flavor already exists.', 409);
            }
            throw $e;
        }
    }


    public function delete(Product $product): bool
    {
        try {
            return $product->delete();
        } catch (QueryException $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            
            if ($errorCode === '23000' || $errorCode === '23503' || 
                str_contains($errorMessage, 'foreign key constraint') ||
                str_contains($errorMessage, 'violates foreign key constraint')) {
                throw new RuntimeException('Cannot delete product: it is referenced by store products.', 409);
            }
            throw $e;
        }
    }
}
