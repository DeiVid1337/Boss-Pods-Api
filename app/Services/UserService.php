<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UserService
{

    public function list(User $authUser, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::query();

        if (!$authUser->isAdmin()) {
            if ($authUser->isManager() && $authUser->store_id) {
                $query->forStore($authUser->store_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $driver = DB::getDriverName();
            
            $query->where(function ($q) use ($search, $driver) {
                if ($driver === 'pgsql') {
                    $q->where('name', 'ILIKE', "%{$search}%")
                        ->orWhere('email', 'ILIKE', "%{$search}%");
                } else {
                    $q->whereRaw('LOWER(name) LIKE LOWER(?)', ["%{$search}%"])
                        ->orWhereRaw('LOWER(email) LIKE LOWER(?)', ["%{$search}%"]);
                }
            });
        }

        if (isset($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (isset($filters['store_id']) && $authUser->isAdmin()) {
            $query->where('store_id', $filters['store_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $sortBy = $filters['sort_by'] ?? 'name';
        $sortOrder = $filters['sort_order'] ?? 'asc';
        
        $allowedSortBy = ['name', 'email', 'role', 'created_at'];
        if (!in_array($sortBy, $allowedSortBy, true)) {
            $sortBy = 'name';
        }
        
        $sortOrder = strtolower($sortOrder);
        if (!in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'asc';
        }
        
        $query->orderBy($sortBy, $sortOrder);

        return $query->with('store')->paginate(min($perPage, 100));
    }


    public function find(int $id): ?User
    {
        return User::find($id);
    }


    public function create(array $data): User
    {
        $data['is_active'] = $data['is_active'] ?? true;

        try {
            return User::create($data);
        } catch (QueryException $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();

            if ($errorCode === '23000' || $errorCode === '23505' ||
                str_contains($errorMessage, 'unique constraint') ||
                str_contains($errorMessage, 'duplicate key value')) {
                throw new RuntimeException('Email already exists.', 422);
            }
            throw $e;
        }
    }


    public function update(User $user, array $data): User
    {
        if (!isset($data['password']) || $data['password'] === '' || $data['password'] === null) {
            unset($data['password']);
        }

        $role = $data['role'] ?? $user->role;
        if ($role === 'admin') {
            $data['store_id'] = null;
        }

        try {
            $user->update($data);
            $user->refresh();

            return $user;
        } catch (QueryException $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();

            if ($errorCode === '23000' || $errorCode === '23505' ||
                str_contains($errorMessage, 'unique constraint') ||
                str_contains($errorMessage, 'duplicate key value')) {
                throw new RuntimeException('Email already exists.', 422);
            }
            throw $e;
        }
    }


    public function delete(User $user): void
    {
        $user->delete();
    }
}
