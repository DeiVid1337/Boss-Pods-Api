<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreStoreRequest;
use App\Http\Requests\Api\V1\UpdateStoreRequest;
use App\Http\Resources\Api\V1\StoreResource;
use App\Models\Store;
use App\Services\CacheService;
use App\Services\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StoreController extends Controller
{
    public function __construct(
        private StoreService $storeService,
        private CacheService $cacheService
    ) {}


    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Store::class);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'string', 'max:255'],
            'sort_by' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'string', 'max:255'],
        ]);

        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 15;
        $filters = array_filter([
            'is_active' => $validated['is_active'] ?? null,
            'search' => $validated['search'] ?? null,
            'sort_by' => $validated['sort_by'] ?? null,
            'sort_order' => $validated['sort_order'] ?? null,
        ], fn($value) => $value !== null);

        $stores = $this->cacheService->getList(
            'stores',
            $request->user(),
            $filters,
            $perPage,
            fn() => $this->storeService->list($request->user(), $filters, $perPage)
        );

        return StoreResource::collection($stores)->response();
    }


    public function show(Request $request, Store $store): JsonResponse
    {
        Gate::authorize('view', $store);

        $store = $this->cacheService->getShow(
            'stores',
            $store->id,
            fn() => $this->storeService->find($store->id)
        );

        if (!$store) {
            return response()->json(['message' => 'Store not found.'], 404);
        }

        return (new StoreResource($store))->response();
    }


    public function store(StoreStoreRequest $request): JsonResponse
    {
        $store = $this->storeService->create($request->validated());

        $this->cacheService->invalidateList('stores');

        return (new StoreResource($store))
            ->response()
            ->setStatusCode(201);
    }


    public function update(UpdateStoreRequest $request, Store $store): JsonResponse
    {
        $store = $this->storeService->update($store, $request->validated());

        $this->cacheService->invalidateShow('stores', $store->id);
        $this->cacheService->invalidateList('stores');

        return (new StoreResource($store))->response();
    }


    public function destroy(Request $request, Store $store): JsonResponse
    {
        Gate::authorize('delete', $store);

        $this->storeService->delete($store);

        $this->cacheService->invalidateShow('stores', $store->id);
        $this->cacheService->invalidateList('stores');

        return response()->json([
            'message' => 'Store deleted successfully.',
        ]);
    }
}
