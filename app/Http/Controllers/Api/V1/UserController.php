<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\CreateUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\CacheService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService,
        private CacheService $cacheService
    ) {}


    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'role' => ['sometimes', 'string', 'in:admin,manager,seller'],
            'store_id' => ['sometimes', 'integer', 'exists:stores,id'],
            'is_active' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'string', 'max:255'],
            'sort_by' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'string', 'max:255'],
        ]);

        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 15;
        $filters = array_filter([
            'role' => $validated['role'] ?? null,
            'store_id' => $validated['store_id'] ?? null,
            'is_active' => $validated['is_active'] ?? null,
            'search' => $validated['search'] ?? null,
            'sort_by' => $validated['sort_by'] ?? null,
            'sort_order' => $validated['sort_order'] ?? null,
        ], fn($value) => $value !== null);

        $users = $this->cacheService->getList(
            'users',
            $request->user(),
            $filters,
            $perPage,
            fn() => $this->userService->list($request->user(), $filters, $perPage)
        );

        return UserResource::collection($users)->response();
    }


    public function show(Request $request, User $user): JsonResponse
    {
        Gate::authorize('view', $user);

        $user = $this->cacheService->getShow(
            'users',
            $user->id,
            fn() => $this->userService->find($user->id)
        );

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $user->load('store');

        return (new UserResource($user))->response();
    }


    public function store(CreateUserRequest $request): JsonResponse
    {
        try {
            $user = $this->userService->create($request->validated());

            $this->cacheService->invalidateList('users');

            $user->load('store');

            return (new UserResource($user))
                ->response()
                ->setStatusCode(201);
        } catch (RuntimeException $e) {
            if ($e->getCode() === 422) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }
            throw $e;
        }
    }


    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        try {
            $user = $this->userService->update($user, $request->validated());

            $this->cacheService->invalidateShow('users', $user->id);
            $this->cacheService->invalidateList('users');

            $user->load('store');

            return (new UserResource($user))->response();
        } catch (RuntimeException $e) {
            if ($e->getCode() === 422) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }
            throw $e;
        }
    }


    public function destroy(Request $request, User $user): JsonResponse
    {
        Gate::authorize('delete', $user);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot delete your own account.',
            ], 403);
        }

        $this->userService->delete($user);

        $this->cacheService->invalidateShow('users', $user->id);
        $this->cacheService->invalidateList('users');

        return response()->json([
            'message' => 'User deleted successfully.',
        ]);
    }
}
