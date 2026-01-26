<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }


    public function view(User $user, User $model): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isManager()) {
            return $user->store_id === $model->store_id;
        }

        return false;
    }


    public function create(User $user): bool
    {
        return $user->isAdmin();
    }


    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }


    public function delete(User $user, User $model): bool
    {
        return $user->isAdmin();
    }
}
