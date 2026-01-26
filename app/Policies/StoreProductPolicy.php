<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StoreProduct;
use App\Models\User;

class StoreProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || ($user->store_id !== null);
    }

    public function view(User $user, StoreProduct $storeProduct): bool
    {
        return $user->isAdmin() || $user->store_id === $storeProduct->store_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    public function update(User $user, StoreProduct $storeProduct): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isManager() && $user->store_id === $storeProduct->store_id;
    }

    public function delete(User $user, StoreProduct $storeProduct): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isManager() && $user->store_id === $storeProduct->store_id;
    }
}
