<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\CreateSaleRequest;
use App\Http\Resources\Api\V1\SaleResource;
use App\Models\Sale;
use App\Models\Store;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    public function __construct(
        private SaleService $saleService
    ) {}


    public function index(Request $request, Store $store): JsonResponse
    {
        Gate::authorize('viewAny', Sale::class);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'search' => ['sometimes', 'string', 'max:255'],
            'sort_by' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'string', 'max:255'],
        ]);

        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 15;
        $filters = array_filter([
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'search' => $validated['search'] ?? null,
            'sort_by' => $validated['sort_by'] ?? null,
            'sort_order' => $validated['sort_order'] ?? null,
        ], fn($value) => $value !== null);

        $sales = $this->saleService->list($store, $request->user(), $filters, $perPage);

        return SaleResource::collection($sales)->response();
    }


    public function show(Request $request, Store $store, Sale $sale): JsonResponse
    {
        Gate::authorize('view', $sale);

        $sale->load([
            'saleItems.storeProduct.product',
            'customer',
            'user',
        ]);

        return (new SaleResource($sale))->response();
    }


    public function store(CreateSaleRequest $request, Store $store): JsonResponse
    {
        try {
            $sale = $this->saleService->createSale(
                $store,
                $request->user(),
                $request->validated()
            );

            $sale->load([
                'saleItems.storeProduct.product',
                'customer',
                'user',
            ]);

            return (new SaleResource($sale))
                ->response()
                ->setStatusCode(201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
