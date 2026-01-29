<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ReturnInventoryRequest;
use App\Http\Requests\Api\V1\WithdrawInventoryRequest;
use App\Http\Resources\Api\V1\SellerInventoryResource;
use App\Models\Store;
use App\Models\User;
use App\Services\SellerInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SellerInventoryController extends Controller
{
    public function __construct(
        private SellerInventoryService $sellerInventoryService
    ) {}

    public function withdraw(WithdrawInventoryRequest $request, Store $store): JsonResponse
    {
        $sellerId = $request->input('seller_id');
        $user = $request->user();

        $seller = $sellerId && ($user->isAdmin() || ($user->isManager() && $user->store_id === $store->id))
            ? User::findOrFail($sellerId)
            : $user;

        if ($sellerId) {
            if (!$seller->isSeller()) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => ['seller_id' => ['The selected seller must be a seller.']],
                ], 422);
            }
            if ($seller->store_id !== $store->id) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => ['seller_id' => ['The selected seller must belong to this store.']],
                ], 422);
            }
        }

        if ($user->isSeller() && $seller->id !== $user->id) {
            return response()->json([
                'message' => 'You can only withdraw products for yourself.',
            ], 403);
        }

        try {
            $inventories = $this->sellerInventoryService->withdraw(
                $store,
                $seller,
                $request->input('items', [])
            );

            return response()->json([
                'message' => 'Products withdrawn successfully.',
                'data' => SellerInventoryResource::collection($inventories),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function return(ReturnInventoryRequest $request, Store $store): JsonResponse
    {
        $sellerId = $request->input('seller_id');
        $user = $request->user();

        $seller = $sellerId && ($user->isAdmin() || ($user->isManager() && $user->store_id === $store->id))
            ? User::findOrFail($sellerId)
            : $user;

        if ($sellerId) {
            if (!$seller->isSeller()) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => ['seller_id' => ['The selected seller must be a seller.']],
                ], 422);
            }
            if ($seller->store_id !== $store->id) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => ['seller_id' => ['The selected seller must belong to this store.']],
                ], 422);
            }
        }

        if ($user->isSeller() && $seller->id !== $user->id) {
            return response()->json([
                'message' => 'You can only return products for yourself.',
            ], 403);
        }

        try {
            $inventories = $this->sellerInventoryService->return(
                $store,
                $seller,
                $request->input('items', [])
            );

            return response()->json([
                'message' => 'Products returned successfully.',
                'data' => SellerInventoryResource::collection($inventories),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function index(Request $request, User $user): JsonResponse
    {
        $authUser = $request->user();

        if ($authUser->isSeller() && $authUser->id !== $user->id) {
            return response()->json([
                'message' => 'You can only view your own inventory.',
            ], 403);
        }

        if ($authUser->isManager() && $authUser->store_id !== $user->store_id) {
            return response()->json([
                'message' => 'You can only view inventory of sellers in your store.',
            ], 403);
        }

        Gate::authorize('viewAny', \App\Models\SellerInventory::class);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 15;

        $inventories = $this->sellerInventoryService->listForUser($user, $perPage);

        return SellerInventoryResource::collection($inventories)->response();
    }

    public function indexForStore(Request $request, Store $store): JsonResponse
    {
        if ($request->user()->isSeller()) {
            return response()->json([
                'message' => 'Sellers cannot access this endpoint.',
            ], 403);
        }

        Gate::authorize('viewAny', \App\Models\SellerInventory::class);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 15;

        $inventories = $this->sellerInventoryService->listForStore($store, $perPage);

        return SellerInventoryResource::collection($inventories)->response();
    }
}
