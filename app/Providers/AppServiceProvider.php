<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SellerInventory;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use App\Policies\CustomerPolicy;
use App\Policies\ProductPolicy;
use App\Policies\SalePolicy;
use App\Policies\SellerInventoryPolicy;
use App\Policies\StorePolicy;
use App\Policies\StoreProductPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Gate::policy(Store::class, StorePolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(StoreProduct::class, StoreProductPolicy::class);
        Gate::policy(Sale::class, SalePolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(SellerInventory::class, SellerInventoryPolicy::class);
    }
}
