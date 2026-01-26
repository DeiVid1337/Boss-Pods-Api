<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Store;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class StoreService
{

    public function list(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Store::query();

        if (!$user->isAdmin()) {
            if ($user->store_id) {
                $query->where('id', $user->store_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $driver = DB::getDriverName();
            
            if ($driver === 'pgsql') {
                $query->where('name', 'ILIKE', "%{$search}%");
            } else {
                $query->whereRaw('LOWER(name) LIKE LOWER(?)', ["%{$search}%"]);
            }
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $sortBy = $filters['sort_by'] ?? 'name';
        $sortOrder = $filters['sort_order'] ?? 'asc';
        
        $allowedSortBy = ['name', 'is_active', 'created_at'];
        if (!in_array($sortBy, $allowedSortBy, true)) {
            $sortBy = 'name';
        }
        
        $sortOrder = strtolower($sortOrder);
        if (!in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'asc';
        }
        
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate(min($perPage, 100));
    }


    public function find(int $id): ?Store
    {
        return Store::find($id);
    }


    public function create(array $data): Store
    {
        return Store::create($data);
    }


    public function update(Store $store, array $data): Store
    {
        $store->update($data);
        $store->refresh();

        return $store;
    }


    public function delete(Store $store): bool
    {
        return $store->delete();
    }
}
