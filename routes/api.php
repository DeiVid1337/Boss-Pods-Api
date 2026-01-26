<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\StoreProductController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::get('/stores', [StoreController::class, 'index']);
        Route::post('/stores', [StoreController::class, 'store']);
        Route::get('/stores/{store}', [StoreController::class, 'show'])
            ->middleware('store.access');
        Route::put('/stores/{store}', [StoreController::class, 'update'])
            ->middleware('store.access');
        Route::delete('/stores/{store}', [StoreController::class, 'destroy'])
            ->middleware('store.access');

        Route::prefix('stores/{store}')->middleware('store.access')->scopeBindings()->group(function () {
            Route::get('/products', [StoreProductController::class, 'index']);
            Route::post('/products', [StoreProductController::class, 'store']);
            Route::get('/products/{storeProduct}', [StoreProductController::class, 'show']);
            Route::put('/products/{storeProduct}', [StoreProductController::class, 'update']);
            Route::delete('/products/{storeProduct}', [StoreProductController::class, 'destroy']);

            Route::get('/sales', [SaleController::class, 'index']);
            Route::post('/sales', [SaleController::class, 'store']);
            Route::get('/sales/{sale}', [SaleController::class, 'show']);
        });

        Route::apiResource('products', ProductController::class);

        Route::get('/customers', [CustomerController::class, 'index']);
        Route::post('/customers', [CustomerController::class, 'store']);
        Route::get('/customers/{customer}', [CustomerController::class, 'show']);
        Route::put('/customers/{customer}', [CustomerController::class, 'update']);

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
    });
});
