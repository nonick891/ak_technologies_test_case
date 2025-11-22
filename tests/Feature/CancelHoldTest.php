<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CancelHoldTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        $this->idempotencyKey = Str::uuid();
    }

    public function test_cancel_held_hold_does_not_change_remaining(): void
    {
        $now = now();
        $slotId = DB::table('slots')->insertGetId([
            'capacity'   => 5,
            'remaining'  => 5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $holdId = DB::table('holds')->insertGetId([
            'slot_id'     => $slotId,
            'status'      => 'held',
            'idempotency_key' => $this->idempotencyKey,
            'expires_at'  => $now->copy()->addMinutes(5),
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        $response = $this->deleteJson("/holds/{$holdId}");

        $response->assertOk()
            ->assertJsonFragment([
                'id'     => $holdId,
                'status' => 'cancelled',
            ]);

        $this->assertDatabaseHas('holds', [
            'id'     => $holdId,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('slots', [
            'id'        => $slotId,
            'remaining' => 5,
        ]);
    }

    public function test_cancel_confirmed_hold_returns_capacity(): void
    {
        $now = now();
        $slotId = DB::table('slots')->insertGetId([
            'capacity'   => 5,
            'remaining'  => 4,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $holdId = DB::table('holds')->insertGetId([
            'slot_id'     => $slotId,
            'status'      => 'confirmed',
            'idempotency_key' => $this->idempotencyKey,
            'expires_at'  => $now->copy()->addMinutes(5),
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        $response = $this->deleteJson("/holds/{$holdId}");

        $response->assertOk()
            ->assertJsonFragment([
                'id'     => $holdId,
                'status' => 'cancelled',
            ]);

        $this->assertDatabaseHas('slots', [
            'id'        => $slotId,
            'remaining' => 5, // incremented by 1
        ]);
    }

    public function test_cancel_is_idempotent(): void
    {
        $now = now();
        $slotId = DB::table('slots')->insertGetId([
            'capacity'   => 5,
            'remaining'  => 4,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $holdId = DB::table('holds')->insertGetId([
            'slot_id'     => $slotId,
            'status'      => 'confirmed',
            'idempotency_key' => $this->idempotencyKey,
            'expires_at'  => $now->copy()->addMinutes(5),
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        // First cancel
        $this->deleteJson("/holds/{$holdId}")
            ->assertOk()
            ->assertJsonFragment([
                'status' => 'cancelled',
            ]);

        // Remaining should now be 5
        $this->assertDatabaseHas('slots', [
            'id'        => $slotId,
            'remaining' => 5,
        ]);

        // Second cancel should keep everything the same
        $this->deleteJson("/holds/{$holdId}")
            ->assertOk()
            ->assertJsonFragment([
                'status' => 'cancelled',
            ]);

        $this->assertDatabaseHas('slots', [
            'id'        => $slotId,
            'remaining' => 5,
        ]);
    }
}
