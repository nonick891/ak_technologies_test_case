<?php

namespace App\SlotHoldApp\Services;

use App\Models\Slot;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\LockProvider;

class SlotAvailabilityService
{
    public function __construct(
        protected CacheRepository $cache,
        protected LockProvider $lockProvider,
        protected Slot $slot,
        protected array $config
    ) {
    }

    public function getAvailability(): array
    {
        $cacheKey = $this->cacheKey();

        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $lock = $this->lockProvider->lock(
            name: $this->lockKey(),
            seconds: $this->lockSeconds()
        );

        try {
            if (! $lock->get()) {
                $stale = $this->cache->get($cacheKey);

                if ($stale !== null) {
                    return $stale;
                }

                return $this->queryAvailability();
            }

            $cached = $this->cache->get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }

            $availability = $this->queryAvailability();

            $this->cache->put(
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
        $this->cache->forget($this->cacheKey());
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
        return $this->slot
            ->newQuery()
            ->select(['id', 'capacity', 'remaining'])
            ->orderBy('id')
            ->get()
            ->map(static function (Slot $slot): array {
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
