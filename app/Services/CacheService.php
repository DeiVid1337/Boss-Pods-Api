<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CacheService
{

    public function getList(
        string $resource,
        User $user,
        array $filters,
        int $perPage,
        callable $callback,
        ?int $ttl = null
    ): LengthAwarePaginator {
        $ttl = $ttl ?? config('boss_pods.cache.ttl.list', 120);
        
        $key = $this->buildListKey($resource, $user, $filters, $perPage);

        $result = Cache::remember($key, $ttl, $callback);

        if (config('cache.default') === 'array') {
            $this->registerListKey($resource, $key);
        }

        return $result;
    }


    public function getShow(
        string $resource,
        int $id,
        callable $callback,
        ?int $ttl = null
    ): ?Model {
        $ttl = $ttl ?? config('boss_pods.cache.ttl.show', 300);
        
        $key = $this->buildShowKey($resource, $id);

        return Cache::remember($key, $ttl, $callback);
    }


    public function invalidateList(string $resource, ?int $storeId = null): void
    {
        if (config('cache.default') === 'array') {
            $this->invalidateListArray($resource);
            return;
        }

        if (config('cache.default') === 'redis') {
            $pattern = $this->buildListKeyPattern($resource, $storeId);
            try {
                $keys = Cache::getRedis()->keys($pattern);
                if (!empty($keys)) {
                    Cache::getRedis()->del($keys);
                }
            } catch (\Exception $e) {
            }
        }
    }


    private function registerListKey(string $resource, string $key): void
    {
        $setKey = "bp:invalidate:list:{$resource}";
        $keys = Cache::get($setKey, []);
        if (!is_array($keys)) {
            $keys = [];
        }
        if (!in_array($key, $keys, true)) {
            $keys[] = $key;
            Cache::put($setKey, $keys, 86400);
        }
    }


    private function invalidateListArray(string $resource): void
    {
        $setKey = "bp:invalidate:list:{$resource}";
        $keys = Cache::get($setKey, []);
        if (!is_array($keys)) {
            $keys = [];
        }
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        Cache::forget($setKey);
    }


    public function invalidateShow(string $resource, int $id): void
    {
        $key = $this->buildShowKey($resource, $id);
        Cache::forget($key);
    }


    private function buildListKey(string $resource, User $user, array $filters, int $perPage): string
    {
        $normalizedFilters = $this->normalizeFilters($filters);
        
        $context = [
            'user_id' => $user->id,
            'role' => $user->role,
            'store_id' => $user->store_id,
        ];
        
        $hash = md5(json_encode([
            'resource' => $resource,
            'filters' => $normalizedFilters,
            'per_page' => $perPage,
            'context' => $context,
        ]));
        
        return "bp:list:{$resource}:{$hash}";
    }


    private function buildListKeyPattern(string $resource, ?int $storeId = null): string
    {
        $resourcePart = $storeId ? "{$resource}.{$storeId}" : $resource;
        return "bp:list:{$resourcePart}:*";
    }


    private function buildShowKey(string $resource, int $id): string
    {
        return "bp:show:{$resource}:{$id}";
    }


    private function normalizeFilters(array $filters): array
    {
        ksort($filters);
        return array_filter($filters, fn($value) => $value !== null);
    }
}
