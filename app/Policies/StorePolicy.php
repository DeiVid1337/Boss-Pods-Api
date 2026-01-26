<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

class StorePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || ($user->store_id !== null);
    }

    public function view(User $user, Store $store): bool
    {
        return $user->isAdmin() || $user->store_id === $store->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Store $store): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Store $store): bool
    {
        return $user->isAdmin();
    }
}
