<?php

namespace App\SlotHoldApp\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SlotAvailabilityService
{
    /**
     * Service configuration. If null, will load from config file.
     *
     * @var array<string, mixed>
     */
    protected array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? config('slot_availability_cache');
    }

    public function getAvailability(): array
    {
        $cacheKey = $this->cacheKey();

        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $lock = Cache::lock(
            $this->lockKey(),
            $this->lockSeconds()
        );

        try {
            if (! $lock->get()) {
                $stale = Cache::get($cacheKey);

                if ($stale !== null) {
                    return $stale;
                }

                return $this->queryAvailability();
            }

            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }

            $availability = $this->queryAvailability();

            Cache::put(
                key: $cacheKey,
                value: $availability,
                ttl: $this->cacheTtlSeconds()
            );

            return $availability;
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Invalidate the cached slot availability.
     */
    public function invalidateAvailabilityCache(): void
    {
        Cache::forget($this->cacheKey());
    }

    /**
     * Run a state-changing operation and ensure availability cache is invalidated.
     *
     * @template TReturn
     *
     * @param Closure():TReturn $operation
     * @return TReturn
     */
    public function runWithCacheInvalidation(\Closure $operation): mixed
    {
        $result = $operation();

        $this->invalidateAvailabilityCache();

        return $result;
    }

    /**
     * @return array<int, array{slot_id:int, capacity:int, remaining:int}>
     */
    protected function queryAvailability(): array
    {
        return DB::table('slots')
            ->select(['id', 'capacity', 'remaining'])
            ->orderBy('id')
            ->get()
            ->map(static function ($slot): array {
                return [
                    'slot_id' => $slot->id,
                    'capacity' => $slot->capacity,
                    'remaining' => $slot->remaining,
                ];
            })
            ->all();
    }
    protected function cacheKey(): string
    {
        return $this->config['cache_key'] ?? 'slots.availability';
    }

    protected function cacheTtlSeconds(): int
    {
        return (int) $this->config['ttl_seconds'] ?? 10;
    }

    protected function lockKey(): string
    {
        return $this->config['lock_key'] ?? 'slots.availability.lock';
    }

    protected function lockSeconds(): int
    {
        return (int) $this->config['lock_seconds'] ?? 5;
    }
}
