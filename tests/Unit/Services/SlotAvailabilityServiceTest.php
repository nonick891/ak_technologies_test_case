<?php

namespace Tests\Unit\Services;

use App\Models\Slot;
use App\SlotHoldApp\Services\SlotAvailabilityService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class SlotAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;
    use MockeryPHPUnitIntegration;

    public $seeder = DatabaseSeeder::class;

    protected array $configSlotAvailabityCache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configSlotAvailabityCache = include __DIR__ . '/../../../config/slot_availability_cache.php';
    }

    public function test_returns_cached_availability_when_present(): void
    {
        Cache::spy();

        DB::spy();

        $cachedData = [
            ['slot_id' => 1, 'capacity' => 10, 'remaining' => 5],
        ];

        Cache::shouldReceive('get')
            ->once()
            ->with('slots.availability')
            ->andReturn($cachedData);

        $service = new SlotAvailabilityService($this->configSlotAvailabityCache);

        $result = $service->getAvailability();

        $this->assertSame($cachedData, $result);
    }

    public function test_queries_and_caches_when_not_in_cache(): void
    {
        $lock = Mockery::mock(Lock::class);

        Cache::shouldReceive('get')
            ->once()
            ->with('slots.availability')
            ->andReturn(null);

        Cache::shouldReceive('lock')
            ->once()
            ->with('slots.availability.lock', 5)
            ->andReturn($lock);

        $lock
            ->shouldReceive('get')
            ->once()
            ->andReturn(true);

        // After acquiring lock, service re-reads cache
        Cache::shouldReceive('get')
            ->once()
            ->with('slots.availability')
            ->andReturn(null);

        // Query builder chain on Slot
        $query = Mockery::mock();
        $collection = collect([
            (function () {
                $slot = new Slot();
                $slot->id = 1;
                $slot->capacity = 10;
                $slot->remaining = 7;

                return $slot;
            })(),
        ]);

        DB::shouldReceive('table')
            ->once()
            ->andReturn($query);

        $query
            ->shouldReceive('select')
            ->once()
            ->with(['id', 'capacity', 'remaining'])
            ->andReturnSelf();

        $query
            ->shouldReceive('orderBy')
            ->once()
            ->with('id')
            ->andReturnSelf();

        $query
            ->shouldReceive('get')
            ->once()
            ->andReturn($collection);

        $expectedAvailability = [
            [
                'slot_id' => 1,
                'capacity' => 10,
                'remaining' => 7,
            ],
        ];

        Cache::shouldReceive('put')
            ->once()
            ->withArgs(function ($key, $value, $ttl) use ($expectedAvailability) {
                return $key === 'slots.availability'
                    && $value === $expectedAvailability
                    && is_int($ttl);
            });

        $lock
            ->shouldReceive('release')
            ->once();

        $service = new SlotAvailabilityService($this->configSlotAvailabityCache);

        $result = $service->getAvailability();

        $this->assertSame($expectedAvailability, $result);
    }

    public function test_returns_stale_data_when_lock_cannot_be_acquired_but_stale_exists(): void
    {
        $lock = Mockery::mock(Lock::class);

        Cache::shouldReceive('get')
            ->once()
            ->with('slots.availability')
            ->andReturn(null);

        Cache::shouldReceive('lock')
            ->once()
            ->with('slots.availability.lock', 5)
            ->andReturn($lock);

        $lock
            ->shouldReceive('get')
            ->once()
            ->andReturn(false);

        $staleData = [
            ['slot_id' => 2, 'capacity' => 20, 'remaining' => 3],
        ];

        Cache::shouldReceive('get')
            ->once()
            ->with('slots.availability')
            ->andReturn($staleData);

        $lock
            ->shouldReceive('release')
            ->once();

        $service = new SlotAvailabilityService($this->configSlotAvailabityCache);

        $result = $service->getAvailability();

        $this->assertSame($staleData, $result);
    }

    public function test_invalidate_availability_cache_forgets_cache_key(): void
    {
        Cache::shouldReceive('forget')
            ->once()
            ->with('slots.availability');

        $service = new SlotAvailabilityService($this->configSlotAvailabityCache);

        $service->invalidateAvailabilityCache();
    }

    public function test_run_with_cache_invalidation_executes_operation_and_invalidates_cache(): void
    {
        Cache::shouldReceive('forget')
            ->once()
            ->with('slots.availability');

        $service = new SlotAvailabilityService($this->configSlotAvailabityCache);

        $operationExecuted = false;

        $result = $service->runWithCacheInvalidation(function () use (&$operationExecuted): string {
            $operationExecuted = true;

            return 'result';
        });

        $this->assertTrue($operationExecuted);

        $this->assertSame('result', $result);
    }
}
