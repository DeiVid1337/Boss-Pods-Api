<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Sale $sale): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isManager()) {
            return $user->store_id === $sale->store_id;
        }

        return $user->isSeller() && $user->id === $sale->user_id && $user->store_id === $sale->store_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isSeller();
    }

    public function update(User $user, Sale $sale): bool
    {
        return false;
    }

    public function delete(User $user, Sale $sale): bool
    {
        return false;
    }
}
