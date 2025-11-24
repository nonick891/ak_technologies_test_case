<?php

namespace App\SlotHoldApp\Services;

use App\Models\Hold;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SlotService
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
                return [
                    'message' => 'Lock timeout. Please try again in ' . $this->lockSeconds() . ' seconds.',
                ];
            }

            $slots = $this->getSlots();

            Cache::put(
                key: $cacheKey,
                value: $slots,
                ttl: $this->cacheTtlSeconds()
            );

            return $slots;
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
    protected function getSlots(): array
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

    public function createHold($slot, $idempotencyKey = null)
    {
        /** @var Hold $hold */
        try
        {
            $result = DB::transaction(function () use ($slot, $idempotencyKey) {
                // Lock a slot row to prevent overselling
                $slot->refresh();

                $slot->lockForUpdate();

                if ($slot->remaining <= 0) {
                    abort(
                        Response::HTTP_CONFLICT,
                        'No remaining capacity for this slot.'
                    );
                }

                $expiresAt = CarbonImmutable::now()->addMinutes(5);

                return Hold::create([
                    'slot_id'         => $slot->id,
                    'status'          => Hold::STATUS_HELD,
                    'idempotency_key' => $idempotencyKey,
                    'expires_at'      => $expiresAt,
                ]);
            });
        }
        catch (\Throwable $th)
        {
            $result = ['message' => 'Slot is full.', 'details' => []];
        }

        return $result;
    }

    public function confirmHold($hold = null): void
    {
        DB::transaction(function () use ($hold) {
            $slot = $hold->slot()
                ->getQuery()
                ->lockForUpdate()
                ->first();

            if ($slot->remaining <= 0) {
                abort(
                    Response::HTTP_CONFLICT,
                    'No remaining capacity to confirm this hold.'
                );
            }

            $slot->decrement('remaining');

            $hold->status = Hold::STATUS_CONFIRMED;

            $hold->save();
        });
    }

    public function cancelHold(Hold $hold): void
    {
        DB::transaction(function () use ($hold) {
            $slot = $hold->slot()
                ->getQuery()
                ->lockForUpdate()
                ->first();

            // If already confirmed, put capacity back
            if ($hold->isConfirmed()) {
                $slot->increment('remaining');
            }

            $hold->status = Hold::STATUS_CANCELLED;

            $hold->save();
        });
    }
}
