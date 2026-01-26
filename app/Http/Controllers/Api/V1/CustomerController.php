<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\CreateCustomerRequest;
use App\Http\Requests\Api\V1\UpdateCustomerRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Models\Customer;
use App\Services\CacheService;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $customerService,
        private CacheService $cacheService
    ) {}


    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Customer::class);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'sort_by' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'string', 'max:255'],
        ]);

        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 15;
        $filters = array_filter([
            'search' => $validated['search'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'sort_by' => $validated['sort_by'] ?? null,
            'sort_order' => $validated['sort_order'] ?? null,
        ], fn($value) => $value !== null);

        $customers = $this->cacheService->getList(
            'customers',
            $request->user(),
            $filters,
            $perPage,
            fn() => $this->customerService->list($filters, $perPage)
        );

        return CustomerResource::collection($customers)->response();
    }


    public function show(Request $request, Customer $customer): JsonResponse
    {
        Gate::authorize('view', $customer);

        $request->validate([
            'include' => ['sometimes', 'string', 'in:sales'],
        ]);

        $customer = $this->cacheService->getShow(
            'customers',
            $customer->id,
            fn() => $this->customerService->find($customer->id)
        );

        if (!$customer) {
            return response()->json(['message' => 'Customer not found.'], 404);
        }

        if ($request->get('include') === 'sales') {
            $customer->load('sales');
        }

        return (new CustomerResource($customer))->response();
    }


    public function store(CreateCustomerRequest $request): JsonResponse
    {
        try {
            $customer = $this->customerService->create($request->validated());

            $this->cacheService->invalidateList('customers');

            return (new CustomerResource($customer))
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


    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        try {
            $customer = $this->customerService->update($customer, $request->validated());

            $this->cacheService->invalidateShow('customers', $customer->id);
            $this->cacheService->invalidateList('customers');

            return (new CustomerResource($customer))->response();
        } catch (RuntimeException $e) {
            if ($e->getCode() === 422) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }
            throw $e;
        }
    }
}
