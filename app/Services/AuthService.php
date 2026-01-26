<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        if (!$user->is_active) {
            throw new AuthenticationException('Account is disabled.');
        }

        $user->tokens()->delete();

        $token = $user->createToken('api', ['api'])->plainTextToken;

        return [
            'user' => $user->load('store'),
            'token' => $token,
        ];
    }

    public function logout(User $user, PersonalAccessToken $token): void
    {
        $token->delete();
    }

    public function getAuthenticatedUser(User $user): User
    {
        return $user->load('store');
    }
}
