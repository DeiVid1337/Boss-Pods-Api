<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SellerInventory;
use App\Models\User;

class SellerInventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isSeller();
    }

    public function view(User $user, SellerInventory $sellerInventory): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isManager()) {
            return $user->store_id === $sellerInventory->storeProduct->store_id;
        }

        if ($user->isSeller()) {
            return $user->id === $sellerInventory->user_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isSeller();
    }

    public function update(User $user, SellerInventory $sellerInventory): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isManager()) {
            return $user->store_id === $sellerInventory->storeProduct->store_id;
        }

        if ($user->isSeller()) {
            return $user->id === $sellerInventory->user_id;
        }

        return false;
    }

    public function delete(User $user, SellerInventory $sellerInventory): bool
    {
        return $this->update($user, $sellerInventory);
    }
}
