<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CreateHoldTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_hold_happy_path(): void
    {
        $now = now();
        $slotId = DB::table('slots')->insertGetId([
            'capacity'   => 10,
            'remaining'  => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $headers = [
            'Idempotency-Key' => '11111111-1111-1111-1111-111111111111',
        ];

        $response = $this->postJson("/slots/{$slotId}/hold", [], $headers);

        $response->assertCreated()
            ->assertJsonStructure(['id', 'slot_id', 'status', 'expires_at'])
            ->assertJsonFragment([
                'slot_id' => $slotId,
                'status'  => 'held',
            ]);

        $this->assertDatabaseHas('holds', [
            'slot_id'         => $slotId,
            'status'          => 'held',
            'idempotency_key' => $headers['Idempotency-Key'],
        ]);

        $this->assertNotNull($response->json('expires_at'));
    }

    public function test_cannot_create_hold_when_slot_full(): void
    {
        $now = now();
        $slotId = DB::table('slots')->insertGetId([
            'capacity'   => 5,
            'remaining'  => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $headers = [
            'Idempotency-Key' => '22222222-2222-2222-2222-222222222222',
        ];

        $response = $this->postJson("/slots/{$slotId}/hold", [], $headers);

        $response->assertStatus(409);
        $this->assertDatabaseCount('holds', 0);
    }

    public function test_create_hold_is_idempotent_by_header(): void
    {
        $now = now();
        $slotId = DB::table('slots')->insertGetId([
            'capacity'   => 10,
            'remaining'  => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $headers = [
            'Idempotency-Key' => '33333333-3333-3333-3333-333333333333',
        ];

        // First request
        $first = $this->postJson("/slots/{$slotId}/hold", [], $headers)
            ->assertCreated();

        $firstBody = $first->json();

        // Second request with same key
        $second = $this->postJson("/slots/{$slotId}/hold", [], $headers)
            ->assertOk();

        $secondBody = $second->json();

        // Same hold id and body
        $this->assertEquals($firstBody['id'], $secondBody['id']);
        $this->assertEquals($firstBody['status'], $secondBody['status']);

        // Only one record with that idempotency key
        $this->assertEquals(
            1,
            DB::table('holds')->where('idempotency_key', $headers['Idempotency-Key'])->count()
        );
    }
}
