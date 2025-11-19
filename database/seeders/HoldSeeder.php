<?php

namespace Database\Seeders;

use App\Models\Hold;
use App\Models\Slot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class HoldSeeder extends Seeder
{
    public function run(): void
    {
        $slots = Slot::query()->take(3)->get();

        foreach ($slots as $slot) {
            Hold::query()->create([
                'slot_id' => $slot->id,
                'status' => 'held',
                'idempotency_key' => (string) Str::uuid(),
                'expires_at' => now()->addMinutes(5),
            ]);

            Hold::query()->create([
                'slot_id' => $slot->id,
                'status' => 'confirmed',
                'idempotency_key' => (string) Str::uuid(),
                'expires_at' => now()->addMinutes(5),
            ]);

            Hold::query()->create([
                'slot_id' => $slot->id,
                'status' => 'cancelled',
                'idempotency_key' => (string) Str::uuid(),
                'expires_at' => now()->subMinutes(5),
            ]);
        }
    }
}
