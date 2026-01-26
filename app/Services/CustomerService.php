<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CustomerService
{

    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Customer::query();

        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $driver = DB::getDriverName();
            
            $query->where(function ($q) use ($search, $driver) {
                if ($driver === 'pgsql') {
                    $q->where('name', 'ILIKE', "%{$search}%")
                        ->orWhere('phone', 'ILIKE', "%{$search}%");
                } else {
                    $q->whereRaw('LOWER(name) LIKE LOWER(?)', ["%{$search}%"])
                        ->orWhereRaw('LOWER(phone) LIKE LOWER(?)', ["%{$search}%"]);
                }
            });
        }

        if (isset($filters['phone']) && !empty($filters['phone'])) {
            $query->where('phone', $filters['phone']);
        }

        $sortBy = $filters['sort_by'] ?? 'name';
        $sortOrder = $filters['sort_order'] ?? 'asc';
        
        $allowedSortBy = ['name', 'phone', 'total_purchases', 'created_at'];
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


    public function find(int $id): ?Customer
    {
        return Customer::find($id);
    }


    public function create(array $data): Customer
    {
        $data['total_purchases'] = 0;

        try {
            return Customer::create($data);
        } catch (QueryException $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();

            if ($errorCode === '23000' || $errorCode === '23505' ||
                str_contains($errorMessage, 'unique constraint') ||
                str_contains($errorMessage, 'duplicate key value')) {
                throw new RuntimeException('Phone number already exists.', 422);
            }
            throw $e;
        }
    }


    public function update(Customer $customer, array $data): Customer
    {
        unset($data['total_purchases']);

        try {
            $customer->update($data);
            $customer->refresh();

            return $customer;
        } catch (QueryException $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();

            if ($errorCode === '23000' || $errorCode === '23505' ||
                str_contains($errorMessage, 'unique constraint') ||
                str_contains($errorMessage, 'duplicate key value')) {
                throw new RuntimeException('Phone number already exists.', 422);
            }
            throw $e;
        }
    }
}
