<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        $store = $request->route('store');
        $storeId = $store ? (is_object($store) ? $store->id : $store) : ($request->route('id'));

        if (!$storeId) {
            return response()->json([
                'message' => 'Store ID is required.',
            ], 400);
        }

        if ($user->store_id !== (int) $storeId) {
            return response()->json([
                'message' => 'You do not have access to this store.',
            ], 403);
        }

        return $next($request);
    }
}
