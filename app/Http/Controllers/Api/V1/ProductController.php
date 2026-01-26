<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\CreateProductRequest;
use App\Http\Requests\Api\V1\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use App\Services\CacheService;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class ProductController extends Controller
{
    public function __construct(
        private ProductService $productService,
        private CacheService $cacheService
    ) {}


    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Product::class);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'brand' => ['sometimes', 'string', 'max:255'],
            'search' => ['sometimes', 'string', 'max:255'],
            'sort_by' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'string', 'max:255'],
        ]);

        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 15;
        $filters = array_filter([
            'brand' => $validated['brand'] ?? null,
            'search' => $validated['search'] ?? null,
            'sort_by' => $validated['sort_by'] ?? null,
            'sort_order' => $validated['sort_order'] ?? null,
        ], fn($value) => $value !== null);

        $products = $this->cacheService->getList(
            'products',
            $request->user(),
            $filters,
            $perPage,
            fn() => $this->productService->list($filters, $perPage)
        );

        return ProductResource::collection($products)->response();
    }


    public function show(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('view', $product);

        $product = $this->cacheService->getShow(
            'products',
            $product->id,
            fn() => $this->productService->find($product->id)
        );

        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        return (new ProductResource($product))->response();
    }


    public function store(CreateProductRequest $request): JsonResponse
    {
        $product = $this->productService->create($request->validated());

        $this->cacheService->invalidateList('products');

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(201);
    }


    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        try {
            $product = $this->productService->update($product, $request->validated());

            $this->cacheService->invalidateShow('products', $product->id);
            $this->cacheService->invalidateList('products');

            return (new ProductResource($product))->response();
        } catch (RuntimeException $e) {
            if ($e->getCode() === 409) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 409);
            }
            throw $e;
        }
    }


    public function destroy(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('delete', $product);

        try {
            $this->productService->delete($product);

            $this->cacheService->invalidateShow('products', $product->id);
            $this->cacheService->invalidateList('products');

            return response()->json([
                'message' => 'Product deleted successfully.',
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
