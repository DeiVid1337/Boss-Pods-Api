<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreProductStoreRequest;
use App\Http\Requests\Api\V1\UpdateStoreProductRequest;
use App\Http\Resources\Api\V1\StoreProductResource;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use App\Services\CacheService;
use App\Services\StoreProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class StoreProductController extends Controller
{
    public function __construct(
        private StoreProductService $storeProductService,
        private CacheService $cacheService
    ) {}


    public function index(Request $request, Store $store): JsonResponse
    {
        Gate::authorize('viewAny', StoreProduct::class);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'low_stock' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'string', 'max:255'],
            'sort_by' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'string', 'max:255'],
            'seller_id' => ['sometimes', 'integer', 'exists:users,id'],
        ]);

        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 15;
        $filters = array_filter([
            'is_active' => $validated['is_active'] ?? null,
            'low_stock' => $validated['low_stock'] ?? null,
            'search' => $validated['search'] ?? null,
            'sort_by' => $validated['sort_by'] ?? null,
            'sort_order' => $validated['sort_order'] ?? null,
            'seller_id' => $validated['seller_id'] ?? null,
        ], fn($value) => $value !== null);

        $sellerContext = null;
        if (isset($validated['seller_id'])) {
            $authUser = $request->user();
            if ($authUser->isSeller()) {
                return response()->json([
                    'message' => 'Sellers cannot query inventory for other sellers.',
                ], 403);
            }

            $seller = User::findOrFail((int) $validated['seller_id']);
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

            if ($authUser->isManager() && $authUser->store_id !== $store->id) {
                return response()->json([
                    'message' => 'Forbidden.',
                ], 403);
            }

            $sellerContext = $seller;
        } elseif ($request->user()->isSeller()) {
            $sellerContext = $request->user();
        }

        $resourceKey = "stores.{$store->id}.products";
        $storeProducts = $this->cacheService->getList(
            $resourceKey,
            $request->user(),
            $filters,
            $perPage,
            fn() => $this->storeProductService->list($store, $filters, $perPage, $sellerContext)
        );

        return StoreProductResource::collection($storeProducts)->response();
    }


    public function show(Request $request, Store $store, StoreProduct $storeProduct): JsonResponse
    {
        Gate::authorize('view', $storeProduct);

        $resourceKey = "stores.{$store->id}.products";
        $sellerContext = null;
        if ($request->query('seller_id')) {
            $authUser = $request->user();
            if ($authUser->isSeller()) {
                return response()->json([
                    'message' => 'Sellers cannot query inventory for other sellers.',
                ], 403);
            }

            $seller = User::findOrFail((int) $request->query('seller_id'));
            if (!$seller->isSeller() || $seller->store_id !== $store->id) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => ['seller_id' => ['Invalid seller_id for this store.']],
                ], 422);
            }
            $sellerContext = $seller;
        } elseif ($request->user()->isSeller()) {
            $sellerContext = $request->user();
        }

        if ($sellerContext) {
            $storeProduct = $this->storeProductService->find($store, $storeProduct->id, $sellerContext);
        } else {
            $storeProduct = $this->cacheService->getShow(
                $resourceKey,
                $storeProduct->id,
                fn() => $this->storeProductService->find($store, $storeProduct->id, null)
            );
        }

        if (!$storeProduct) {
            return response()->json(['message' => 'Store product not found.'], 404);
        }

        $storeProduct->load('product');

        return (new StoreProductResource($storeProduct))->response();
    }


    public function store(StoreProductStoreRequest $request, Store $store): JsonResponse
    {
        try {
            $storeProduct = $this->storeProductService->create($store, $request->validated());

            $resourceKey = "stores.{$store->id}.products";
            $this->cacheService->invalidateList($resourceKey, $store->id);

            return (new StoreProductResource($storeProduct))
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


    public function update(UpdateStoreProductRequest $request, Store $store, StoreProduct $storeProduct): JsonResponse
    {
        $storeProduct = $this->storeProductService->update($storeProduct, $request->validated());

        $resourceKey = "stores.{$store->id}.products";
        $this->cacheService->invalidateShow($resourceKey, $storeProduct->id);
        $this->cacheService->invalidateList($resourceKey, $store->id);

        return (new StoreProductResource($storeProduct))->response();
    }


    public function destroy(Request $request, Store $store, StoreProduct $storeProduct): JsonResponse
    {
        Gate::authorize('delete', $storeProduct);

        try {
            $this->storeProductService->delete($storeProduct);

            $resourceKey = "stores.{$store->id}.products";
            $this->cacheService->invalidateShow($resourceKey, $storeProduct->id);
            $this->cacheService->invalidateList($resourceKey, $store->id);

            return response()->json([
                'message' => 'Store product removed successfully.',
            ]);
        } catch (RuntimeException $e) {
            if ($e->getCode() === 409) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 409);
            }
            throw $e;
        }
    }
}
