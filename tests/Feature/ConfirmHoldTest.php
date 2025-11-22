<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConfirmHoldTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        $this->idempotencyKey = Str::uuid();
    }

    public function test_confirm_valid_hold_decrements_remaining(): void
    {
        $now = now();
        $slotId = DB::table('slots')->insertGetId([
            'capacity'   => 1,
            'remaining'  => 1,
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

        $response = $this->postJson("/holds/{$holdId}/confirm");

        $response->assertOk()
            ->assertJsonFragment([
                'id'      => $holdId,
                'status'  => 'confirmed',
                'slot_id' => $slotId,
            ]);

        $this->assertDatabaseHas('holds', [
            'id'     => $holdId,
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('slots', [
            'id'        => $slotId,
            'remaining' => 0,
        ]);
    }

    public function test_confirm_hold_oversell_protection(): void
    {
        $now = now();
        $slotId = DB::table('slots')->insertGetId([
            'capacity'   => 1,
            'remaining'  => 0,
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

        $response = $this->postJson("/holds/{$holdId}/confirm");

        $response->assertStatus(409);

        $this->assertDatabaseHas('holds', [
            'id'     => $holdId,
            'status' => 'held',
        ]);
    }

    public function test_cannot_confirm_expired_hold(): void
    {
        $now = now();
        $slotId = DB::table('slots')->insertGetId([
            'capacity'   => 1,
            'remaining'  => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $holdId = DB::table('holds')->insertGetId([
            'slot_id'     => $slotId,
            'status'      => 'held',
            'idempotency_key' => $this->idempotencyKey,
            'expires_at'  => $now->copy()->subMinute(),
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        $response = $this->postJson("/holds/{$holdId}/confirm");

        $response->assertStatus(409);

        $this->assertDatabaseHas('holds', [
            'id'     => $holdId,
            'status' => 'held',
        ]);

        $this->assertDatabaseHas('slots', [
            'id'        => $slotId,
            'remaining' => 1,
        ]);
    }

    public function test_confirm_is_idempotent_for_already_confirmed_hold(): void
    {
        $now = now();
        $slotId = DB::table('slots')->insertGetId([
            'capacity'   => 1,
            'remaining'  => 0,
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

        $response = $this->postJson("/holds/{$holdId}/confirm");

        $response->assertOk()
            ->assertJsonFragment([
                'id'     => $holdId,
                'status' => 'confirmed',
            ]);

        // Remaining should not change
        $this->assertDatabaseHas('slots', [
            'id'        => $slotId,
            'remaining' => 0,
        ]);
    }
}
